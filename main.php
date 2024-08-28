<?php

include "model-sqlite3.php";
include "sg-admin.php";

class Main {

    private $page;
    private $data;
    private $sg;

    public function __construct() {
        $this->data = new DB();
        $this->sg = new SG();
        $this->page = $this->loadPage();
    }

    private function loadPage() {
        session_start();
        if (isset($_SESSION['id'])) {
            $id = $_SESSION['id'];
            return $this->admin($id, $_POST);
        } elseif (empty($_GET) && empty($_POST)) {
            return $this->template('index');
        } elseif ( isset($_GET['login']) && isset($_POST['user']) && isset($_POST['token']) ) {
            return $this->login($_POST);
        } else {
            return $this->template('index');
        }
    }

    private function login ($Pdata) {
        # $Pdata = $this->limpia($Pdata);
        $id = $this->data->validaUsuario($Pdata['user'],$Pdata['token']);
        if ($id) {
            if ($this->data->es_admin($id)) {
                return $this->admin($id, $Pdata);
            } else {
                session_destroy();
                return $this->sg->autoriza($Pdata['user'], $this->data->getPermisoUsuario($id));
            }
        } else {
            return 'Oops!';
        }
    }

    private function admin ($id, $Pdata) {
        $msg = '';
        if (isset($_SESSION['id'])) {
            if (!empty($Pdata)) {
                $msg = $this->ejecutarTareaAdm($Pdata);
            } else {
                $msg = '<!-- By happy -->';
            }
        } else {
            $_SESSION['id'] = $id;
            $msg = 'Bienvenido '.$Pdata['user'];
        }
        $param = array(
            '#mensaje#' => $msg,
            '#usuarios#' => $this->creaSelectUsuarios(),
            '#grupos#' => $this->creaSelectGrupos(),
            '#listaGrupos#' => $this->creaListaGrupos(),
            '#listaPermisos#' => $this->creaListaPermisos(),
            '#token-sugerido#' => $this->tokenSugerido()
        );
        return $this->template('admin', $param);
    }

    private function ejecutarTareaAdm ($Pdata) {
        $msg = '"Entré a ejecutarTareaAdm"'.print_r($Pdata, true);
        if (isset($_GET['usuario']) && isset($Pdata['user']) && isset($Pdata['token'])) {
            $id = $this->data->registrarUsuario($Pdata['user'], $Pdata['token']);
            $msg = ($id?'Registrado usuario '.$Pdata['user'].' con ID:'.$id:'Falló el registro del usaurio '.$Pdata['user']);
        } elseif (isset($_GET['grupo']) && isset($Pdata['sgid']) && isset($Pdata['region']) && isset($Pdata['descripcion'])) {
            $id = $this->data->registrarGrupoSeguridad ($Pdata['sgid'], $Pdata['descripcion'], $Pdata['region']);
            $msg = ($id?'Registrado grupo '.$Pdata['sgid'].' con ID:'.$id:'Falló el registro del grupo '.$Pdata['sgid']);
        } elseif (isset($_GET['permiso'])) {
            $ports = preg_split("/[\s,]+/", $Pdata['port'], -1, PREG_SPLIT_NO_EMPTY);
            return print_r($ports, true);
        }
        return $msg;
    }

    private function creaSelect ( $name, $opciones, $campoValue, $campoTexto ) {
        $select = '<select name="'.$name.'">';
        $select .= '<option value="0">Seleccione una opción</option>';
        foreach ($opciones as $opcion) {
            $select .= '<option value="'.$opcion[$campoValue].'">'.$opcion[$campoTexto].'</option>';
        }
        $select .= '</select>';
        return $select;
    }

    private function creaSelectUsuarios () {
        $usrs = $this->data->listarUsuarios();
        return $this->creaSelect( 'usuario', $usrs, 'id', 'alias');
    }

    private function creaSelectGrupos () {
        $sgs = $this->data->listarGruposSeguridad();
        return $this->creaSelect( 'sg', $sgs, 'id', 'descripcion');
    }

    private function creaListaGrupos () {
        $msg = '<table><tr><th class="gris1">SG id</th><th>Descripci&oacute;n</th><th class="gris1">regi&oacute;n</th></tr>';
        $sgs = $this->data->listarGruposSeguridad();
        foreach($sgs as $sg) {
            $msg .= '<tr><td class="gris1">'.$sg['sgid'].'</td><td>'.$sg['descripcion'].'</td><td class="gris1">'.$sg['region'].'</td><tr>';
        }
        return $msg.'</table>';
    }

    private function creaListaPermisos () {
        return 'TO DO';
    }

    private function template($temp, $param=array()) {
        $buf='';
        try {
            $buf=file_get_contents('tpl/'.$temp.'.html');
            if (!empty($param)) {
                $idx=array();
                $val=array();
                foreach($param as $k => $v) {
                    $idx[] = $k;
                    $val[] = $v;
                }
                $buf=str_replace($idx, $val, $buf);
            }
            return $buf;
        } catch( ErrorException $e ) {
            return '';
        }
    }

    private function tokenSugerido () {
        return shell_exec('date | md5sum | awk \'{print $1}\'');
    }

    public function show() {
        return $this->page;
    }
}