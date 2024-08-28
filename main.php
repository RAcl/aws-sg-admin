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
            return $this->admin($id,);
        } elseif (empty($_GET) && empty($_POST)) {
            return $this->template('index');
        } elseif ( isset($_GET['login']) && isset($_POST['user']) && isset($_POST['token']) ) {
            return $this->login($_POST);
        } else {
            return print_r($_POST,true); //$this->template('index');
        }
    }

    private function login ($Pdata) {
        # $Pdata = $this->limpia($Pdata);
        $id = $this->data->validaUsuario($Pdata['user'],$Pdata['token']);
        if ($id) {
            if ($this->data->es_admin($id))
                return $this->admin($id, $Pdata);
            else 
                return $this->sg->autoriza($Pdata['user'], $this->data->getPermisoUsuario($id));
        } else 
            return 'Oops!';
    }

    private function admin ($id, $Pdata=array()) {
        $msg = '';
        if (isset($_SESSION['id'])) {
            $msg = '';
        } else {
            $_SESSION['id'] = $id;
            $msg = 'Bienvenido '.$Pdata['user'];
        }
        return $this->template('admin',array('#mensaje#'=>$msg));
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