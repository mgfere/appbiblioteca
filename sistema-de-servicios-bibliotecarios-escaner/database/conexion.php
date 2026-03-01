<?php

// $host = '192.168.10.20'; 
//$host = 'localhost'; 
//$dbName = 'biblioteca';
//$user = 'luis';
//$password = '1234';



$host = 'localhost';
$dbName = 'bibliotecaDB';
$user = 'root';
$password = '';

try {
    $dbh = new PDO("mysql:host=$host;dbname=$dbName", $user, $password);
    // $dbh = new PDO("mysql:host=$host;dbname=$dbName", $user, $password);

    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}




?>