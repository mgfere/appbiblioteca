<?php

class Database {
    private $host = '172.16.0.155';
    private $dbName = 'bibliotecaDB';
    private $user = 'uttn_alumno1';
    private $password = 'radiofax6548';
    private $dbh;

    public function __construct() {
        try {
            $this->dbh = new PDO(
                "mysql:host=$this->host;dbname=$this->dbName", 
                $this->user, 
                $this->password,
                [
                    PDO::MYSQL_ATTR_LOCAL_INFILE => true,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]
            );
            $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("MySQL Connection failed: " . $e->getMessage());
        }
    }

    public function getDBH() {
        return $this->dbh;
    }
}

// FUNCIÓN PARA CONECTAR A SQL SERVER
function conectarSQLServer() {
   $serverName = "172.16.0.149";
    $database="GestionUsuarios";
    $username="sa";
    $password="TicUtt2017";
    
    $connectionInfo = array(
        "Database" => $database,
        "UID" => $username,
        "PWD" => $password,
        "CharacterSet" => "UTF-8",
        "ReturnDatesAsStrings" => true
    );
    
    $conn = sqlsrv_connect($serverName, $connectionInfo);
    
    if ($conn) {
        error_log("Conexión exitosa a SQL Server: $database");
        return $conn;
    } else {
        $errors = sqlsrv_errors();
        error_log("Error conectando a SQL Server:");
        error_log(print_r($errors, true));
        die("La conexión a SQL Server no se pudo establecer.<br />" . print_r($errors, true));
    }
}

// FUNCIÓN LEGACY (mantener compatibilidad)
function conectarDB3() {
    return conectarSQLServer();
}
?>