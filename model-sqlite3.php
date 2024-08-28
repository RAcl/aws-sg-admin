<?php

class DB {
    private $db;

    public function __construct () {
        $database = $this->getDB();
        if (file_exists($database)) {
            $this->db = new SQLite3($database);
        } else {
            $this->createDB($database);
        }
    }

    private function createDB($database) {
        $this->db = new SQLite3($database);
        $this->db->exec('CREATE TABLE administrador (id_usuario INTEGER);');
        $this->db->exec('CREATE TABLE usuario (alias TEXT, token TEXT, id INTEGER PRIMARY KEY AUTOINCREMENT);');
        $this->db->exec('CREATE TABLE grupoSeguridad (descripcion TEXT, sgid TEXT, region TEXT, id INTEGER PRIMARY KEY AUTOINCREMENT);');
        $this->db->exec('CREATE TABLE permiso (puerto INTEGER, id_usuario INTEGER, id_grupoSeguridad INTEGER, id INTEGER PRIMARY KEY AUTOINCREMENT);');
        $this->registrarUsuario('admin','admin1234');
        $this->db->exec('INSERT INTO administrador values (1);');
    }

    private function getDB() {
        if (file_exists('config.ini')) {
            $cfg = parse_ini_file('config.ini', true);
            return $cfg['DATABASE']['name'];
        } else {
            return 'datos.db';
        }
    }

    public function validaUsuario ($user, $token) {
        $stmt = $this->db->prepare('SELECT id, token FROM usuario WHERE alias=:user');
        $stmt->bindParam(':user', $user, SQLITE3_TEXT);
        $res = $stmt->execute();
        $reg = $res->fetchArray(SQLITE3_ASSOC);
        if (!empty($reg) && ($reg['token'] == $token)) {
            return $reg['id'];
        }
        return 0;
    }

    public function existeUsuario($user) {
        $stmt = $this->db->prepare('SELECT id FROM usuario WHERE alias=:user');
        $stmt->bindParam(':user', $user, SQLITE3_TEXT);
        $res = $stmt->execute();
        $reg = $res->fetchArray(SQLITE3_ASSOC);
        if (!empty($reg)) {
            return $reg['id'];
        }
        return 0;
    }

    public function existeGrupoSeguridad($sgid) {
        $stmt = $this->db->prepare('SELECT id FROM grupoSeguridad WHERE sgid=:sg');
        $stmt->bindParam(':sg', $sgid, SQLITE3_TEXT);
        $res = $stmt->execute();
        $reg = $res->fetchArray(SQLITE3_ASSOC);
        if (!empty($reg)) {
            return $reg['id'];
        }
        return 0;
    }

    public function existePermiso($sgid, $uid, $port) {
        $stmt = $this->db->prepare('SELECT id FROM permiso WHERE id_grupoSeguridad=:sg AND id_usuario=:ui AND puerto=:pt');
        $stmt->bindParam(':sg', $sgid, SQLITE3_INTEGER);
        $stmt->bindParam(':ui', $uid, SQLITE3_INTEGER);
        $stmt->bindParam(':pt', $port, SQLITE3_INTEGER);
        $res = $stmt->execute();
        $reg = $res->fetchArray(SQLITE3_ASSOC);
        if (!empty($reg)) {
            return $reg['id'];
        }
        return 0;
    }

    public function registrarUsuario ($user, $token) {
        if ($this->existeUsuario($user)) return false;
        $stmt = $this->db->prepare('INSERT INTO usuario (alias, token) VALUES (:user, :token);');
        $stmt->bindParam(':user', $user, SQLITE3_TEXT);
        $stmt->bindParam(':token', $token, SQLITE3_TEXT);
        $stmt->execute();
        return $this->existeUsuario($user);
    }

    public function registrarGrupoSeguridad ($sg_id, $descripcion, $region) {
        if ($this->existeGrupoSeguridad($sg_id)) return false;
        $stmt = $this->db->prepare('INSERT INTO grupoSeguridad (sgid, descripcion, region) VALUES (:sg, :de, :re);');
        $stmt->bindParam(':sg', $sg_id, SQLITE3_TEXT);
        $stmt->bindParam(':de', $descripcion, SQLITE3_TEXT);
        $stmt->bindParam(':re', $region, SQLITE3_TEXT);
        $stmt->execute();
        return $this->existeGrupoSeguridad($sg_id);
    }

    public function registrarPermiso ($id_sg, $id_usr, $port) {
        if ($this->existePermiso ($id_sg, $id_usr, $port)) return false;
        $stmt = $this->db->prepare('INSERT INTO permiso (id_usuario, id_grupoSeguridad, puerto) VALUES (:idusr, :idsg, :port);');
        $stmt->bindParam(':idusr', $id_usr, SQLITE3_INTEGER);
        $stmt->bindParam(':idsg', $id_sg, SQLITE3_INTEGER);
        $stmt->bindParam(':port', $port, SQLITE3_INTEGER);
        $stmt->execute();
        return $this->existePermiso ($id_sg, $id_usr, $port);
    }

    public function getPermisoUsuario ($userID) {
        $stmt = $this->db->prepare('SELECT per.puerto, sg.descripcion, sg.sgid, sg.region, usr.alias
                                    FROM usuario as usr
                                    LEFT JOIN permiso as per
                                    ON usr.id = per.id_usuario AND usr.id=:id
                                    INNER JOIN grupoSeguridad as sg
                                    ON per.id_grupoSeguridad = sg.id');
        $stmt->bindParam(':id', $userID, SQLITE3_INTEGER);
        $res = $stmt->execute();
        $reg = array();
        while (($row = $res->fetchArray(SQLITE3_ASSOC))) {
            $reg[] = $row;
        }
        return $reg;
    }

    public function getPermisoGrupoSeguridad ($sgID) {
        $stmt = $this->db->prepare('SELECT per.puerto, usr.alias, usr.id_usuario
                                    FROM grupoSeguridad as sg
                                    LEFT JOIN permiso as per
                                    ON per.id_grupoSeguridad = sg.id  AND sg.id=:id
                                    INNER JOIN usuario as usr
                                    ON usr.id = per.id_usuario
                                    ORDER BY usr.alias');
        $stmt->bindParam(':id', $sgID, SQLITE3_INTEGER);
        $res = $stmt->execute();
        $reg = array();
        while (($row = $res->fetchArray(SQLITE3_ASSOC))) {
            $reg[] = $row;
        }
        return $reg;
    }

    public function es_admin($uid) {
        $stmt = $this->db->prepare('SELECT id_usuario FROM administrador WHERE id_usuario=:u_id');
        $stmt->bindParam(':u_id', $uid, SQLITE3_INTEGER);
        $res = $stmt->execute();
        $reg = $res->fetchArray(SQLITE3_ASSOC);
        if (!empty($reg)) {
            return $reg['id_usuario'];
        }
        return 0;
    }

    public function listarUsuarios () {
        $stmt = $this->db->prepare('SELECT id, alias, token FROM usuario');
        $res = $stmt->execute();
        $reg = array();
        while (($row = $res->fetchArray(SQLITE3_ASSOC))) {
            $reg[] = $row;
        }
        return $reg;
    }

    public function listarGruposSeguridad () {
        $stmt = $this->db->prepare('SELECT id, sgid, descripcion, region FROM grupoSeguridad');
        $res = $stmt->execute();
        $reg = array();
        while (($row = $res->fetchArray(SQLITE3_ASSOC))) {
            $reg[] = $row;
        }
        return $reg;
    }

    // TODO: eliminar permisos, eliminar usuarios, eliminar sg, agregar y eliminar administrador
}

// $x = new DB();
// //*
// $id_u = $x->registrarUsuario('roy2','test-token');
// print_r($id_u);
// $id_g = $x->registrarGrupoSeguridad('sg-123test2','Grupo falso de test', 'us-west-2');
// print_r($id_g);
// if ($id_g && $id_u) {
//     $x->registrarPermiso($id_g, $id_u, 631);
//     $x->registrarPermiso($id_g, $id_u, 80);
//     $x->registrarPermiso($id_g, $id_u, 631);
// }
// // */
// $r = $x->validaUsuario('roy2','test-token');
// if ($r) {
//     print_r($x->getPermisoUsuario($r));
// } else {
//     print('no es correcto o no existe');
// }