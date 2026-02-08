<?php
require '../includes/config/database.php';
$db = conectarDB();

$query = "UPDATE libros SET status = 'Activo' WHERE status = 'Inactivo' AND cantidad >= 1";
$resultado = mysqli_query($db, $query);

$response = array('success' => $resultado);

header('Content-Type: application/json');
echo json_encode($response);
