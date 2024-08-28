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
            '#mensaje#'=>$msg,
            '#usuarios#'=>$this->creaSelectUsuarios(),
            '#grupos#'=>$this->creaSelectGrupos(),
            '#listaPermisos#'=>$this->creaListaPermisos()
        );
        return $this->template('admin', $param);
    }

    private function ejecutarTareaAdm ($Pdata) {
        $msg = "Entré a ejecutarTareaAdm";
        if (isset($_GET['usuario']) && isset($Pdata['user']) && isset($Pdata['token'])) {
            $id = $this->data->registrarUsuario($Pdata['user'], $Pdata['token']);
            $msg = ($id?'Registrado '.$Pdata['user'].' con ID:'.$id:'Falló el registro');
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

    public function show() {
        return $this->page;
    }
}