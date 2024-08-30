<?php

class SG {
    public function autoriza ($user, $permisos) {
        $msg = '{"status":"update and upgrade error"}';
        $sgids = $this->obtenerSGdesdePermisos($permisos);
        foreach($sgids as $sgid) {
            $reglasActuales = $this->obtenerReglasFromSG($sgid, $user);
            $permisosDelSG = $this->obtenerPermisosFiltadoSG($permisos, $sgid);
            $arregloReglas = $this->diferenciaDeReglas($permisosDelSG, $reglasActuales);
            $msg .= $this->actualizaReglas($arregloReglas['comunes']);
            $msg .= $this->agregaReglas($arregloReglas['nuevas']);
            $msg .= $this->eliminaReglas($arregloReglas['deprecadas']);
            $msg = '{"status":"ok"}'; // comentar para ver
        }
        return $msg;
    }

    private function obtenerSGdesdePermisos($permisos) {
        $sgids = array();
        foreach($permisos as $permiso) {
            if (! in_array($permiso['sgid'], $sgids)) {
                $sgids[] = $permiso['sgid'];
            }
        }
        return $sgids;
    }

    private function obtenerPermisosFiltadoSG($permisos, $sgid) {
        $p = array();
        foreach($permisos as $permiso) {
            if ($permiso['sgid'] == $sgid) {
                $p[] = $permiso;
            }
        }
        return $p;
    }

    private function obtenerReglasFromSG($SG, $user='') {
        $output = shell_exec('aws ec2 describe-security-group-rules --filters Name="group-id",Values="'.$SG.'"');
        $output = json_decode($output, true);
        $salida = array();
        if (!empty($user) && !empty($output)) {
            foreach($output['SecurityGroupRules'] as $rule) {
                if (isset($rule['Description']) && ($rule['Description'] == 'user-'.$user) ) {
                    $salida[]=$rule;
                }
            }
        } else {
            $salida = $output;
        }
        return $salida;
    }

    private function diferenciaDeReglas ($permisos, $reglasActuales) {
        $soloEnReglas = array();
        $soloEnPermisos = array();
        $comunes = array();
        if (!empty($permisos) && !empty($reglasActuales)) {
            foreach ($reglasActuales as $rule) {
                if ($this->buscaEnArreglo($rule['ToPort'],$permisos,'puerto')) {
                    $comunes[]=$rule;
                } else {
                    $soloEnReglas[]=$rule;
                }
            }
            foreach ($permisos as $permiso) {
                if (!$this->buscaEnArreglo($permiso['puerto'], $reglasActuales, 'ToPort')) {
                    $soloEnPermisos[]=$permiso;
                }
            }
        } elseif (!empty($permisos)) {
            $soloEnPermisos=$permisos;
        } else { # ho hay permisos
            $soloEnReglas=$reglasActuales;
        }
        return array(
            'comunes' => $comunes,
            'nuevas' => $soloEnPermisos,
            'deprecadas' => $soloEnReglas
        );
    }

    private function buscaEnArreglo($aguja, $pajar, $campo) {
        $encontrado = false;
        foreach($pajar as $elem) {
            if ($elem[$campo] == $aguja) {
                $encontrado=true;
                break;
            }
        }
        return $encontrado;
    }

    private function actualizaReglas ($rules) {
        # contiene reglas desde el SG actual, actualizar IP
        $msg = '';
        foreach ($rules as $rule) {
            $change='aws ec2 modify-security-group-rules' .
                ' --group-id '. $rule['GroupId'] .
                ' --security-group-rules SecurityGroupRuleId='. $rule['SecurityGroupRuleId'] .
                ',SecurityGroupRule=\'{Description=' . $rule['Description'] .
                ',IpProtocol=' .$rule['IpProtocol'] .
                ',FromPort=' . $rule['FromPort'].
                ',ToPort=' . $rule['ToPort'].
                ',CidrIpv4='.$this->getIP().'/32}\'';
            $out = shell_exec($change);
            $msg .= ', en '. $rule['GroupId'] . ' actualizada IP para puerto '.$rule['ToPort'];
        }
        return $msg;
    }
    
    private function agregaReglas($rules) {
        $msg = '';
        foreach ($rules as $rule) {
            $add = 'aws ec2 authorize-security-group-ingress --group-id ' .
                    $rule['sgid'] .
                    ' --ip-permissions IpProtocol=tcp,FromPort=' . $rule['puerto'] .
                    ',ToPort=' . $rule['puerto'] .
                    ',IpRanges="[{CidrIp='.$this->getIP() .
                    '/32,Description=user-'.$rule['alias'].'}]" ' .
                    '--region '.$rule['region'];
            $out = shell_exec($add);
            $msg .= ', en '. $rule['sgid'] . ' agregada IP para puerto '.$rule['puerto'];
        }
        return $msg;
    }
    private function eliminaReglas($rules) {
        $msg = '';
        foreach ($rules as $rule) {
            $del = 'aws ec2 revoke-security-group-ingress' .
                    ' --group-id ' . $rule['GroupId'] .
                    ' --protocol ' . $rule['IpProtocol'] .
                    ' --port ' . $rule['FromPort'] .
                    ' --cidr ' . $rule['CidrIpv4'];
            $out = shell_exec($del);
            $msg .= ', en '. $rule['sgid'] . ' quitada regla para puerto '.$rule['puerto'];
        }
        return $msg;
    }

    public function getIP(){
        if (isset($_SERVER["HTTP_CLIENT_IP"])){
            return $_SERVER["HTTP_CLIENT_IP"];
        }elseif (isset($_SERVER["HTTP_X_FORWARDED_FOR"])){
            return $_SERVER["HTTP_X_FORWARDED_FOR"];
        }elseif (isset($_SERVER["HTTP_X_FORWARDED"])){
            return $_SERVER["HTTP_X_FORWARDED"];
        }elseif (isset($_SERVER["HTTP_FORWARDED_FOR"])){
            return $_SERVER["HTTP_FORWARDED_FOR"];
        }elseif (isset($_SERVER["HTTP_FORWARDED"])){
            return $_SERVER["HTTP_FORWARDED"];
        }else{
            return $_SERVER["REMOTE_ADDR"];
        }
    }
}
