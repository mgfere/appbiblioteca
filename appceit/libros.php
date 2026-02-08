<?php
header("Access-Control-Allow-Origin: http://localhost:8000");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Código para crear una API para el buscador del lado del usuario
require 'includes/config/database.php';
$db = conectarDB();

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

$sql = "SELECT * FROM libros WHERE status = 'Activo'";
$result = $db->query($sql);

$libros = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $libros[] = $row;
    }
}

$db->close();

echo json_encode($libros);
