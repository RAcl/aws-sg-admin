<?php

include "model-sqlite3.php";
include "sg-main.php";

class Main {

    private $page;

    public function __construct() {
        $this->page = $this->loadPage();
    }

    private function loadPage() {
        if (empty($_GET) && empty($_POST)) {
            return $this->template('index');
        }
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