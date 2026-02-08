<?php
require __DIR__ . '/../../vendor/autoload.php';


use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');

$dotenv->load();

function conectarDB(): mysqli
{
    $host = $_ENV['HOST'];
    $usuario = $_ENV['USUARIO'];
    $password = $_ENV['PASSWORD'];
    $database = $_ENV['DATABASE'];

    $db = mysqli_connect($host, $usuario, $password, $database);

    if (!$db) {
        echo "Error: la base de datos MySQL no conectó";
        exit;
    }
    
    mysqli_set_charset($db, 'utf8mb4');

    return $db;
}

function conectarDB2()
{
    $serverName = $_ENV['SERVERNAME'];
    $connectionInfo = array(
        "Database" => $_ENV['DB_TUTORIAS'], 
        "UID" => $_ENV['DB_UID'], 
        "PWD" => $_ENV['DB_PWD'],
        "CharacterSet" => "UTF-8" 
    );
    $conn = sqlsrv_connect($serverName, $connectionInfo);
    if ($conn) {
        return $conn;
    } else {
        echo "La conexión a SQL Server no se pudo establecer.<br />";
        die(print_r(sqlsrv_errors(), true));
    }
}

$conn = conectarDB2();

function conectarDB3(){
    $serverName = $_ENV['SERVERNAME'];
    $connectionInfo = array(
        "Database" => $_ENV['DB_Gestion'], 
        "UID" => $_ENV['DB_UID'], 
        "PWD" => $_ENV['DB_PWD'],
        "CharacterSet" => "UTF-8" 
    );

    $conn = sqlsrv_connect($serverName, $connectionInfo);

    if ($conn) {
        return $conn;
    } else {
        echo "La conexión a SQL Server no se pudo establecer.<br />";
        die(print_r(sqlsrv_errors(), true));
    }

}

//Conectamos la db de ceitregistros
function conectarDB_registros(): mysqli
{
    $host = $_ENV['HOST'];
    $usuario = $_ENV['USUARIO'];
    $password = $_ENV['PASSWORD'];
    $database = $_ENV['DATABASE2'];

    $db = mysqli_connect($host, $usuario, $password, $database);

    if (!$db) {
        echo "Error: la base de datos MySQL de biblioteca no conectó";
        exit;
    }
    
    mysqli_set_charset($db, 'utf8mb4');

    return $db;
}