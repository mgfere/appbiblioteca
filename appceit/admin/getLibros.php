<?php
require '../includes/config/database.php';
$db = conectarDB();

if (!$db) {
    die('Error al conectar a la base de datos: ' . mysqli_connect_error());
}

$query = "SELECT libros.*, secciones.nombre_seccion AS seccion_nombre, secciones.color AS seccion_color FROM libros JOIN secciones ON libros.seccionId = secciones.id";
$result = mysqli_query($db, $query);

$libros = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $libros[] = $row;
    }
} else {
    die('Error en la consulta: ' . mysqli_error($db));
}

header('Content-Type: application/json');
echo json_encode($libros);
