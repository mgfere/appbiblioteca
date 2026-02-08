<?php
require '../includes/config/database.php';
$db = conectarDB();

$query = "UPDATE libros SET status = 'Inactivo' WHERE status = 'Activo'";
$resultado = mysqli_query($db, $query);

$response = array('success' => $resultado);

header('Content-Type: application/json');
echo json_encode($response);
