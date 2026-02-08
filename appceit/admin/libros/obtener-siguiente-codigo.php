<?php
// obtener-siguiente-codigo.php

require '../../includes/config/database.php';

// Verificamos que se haya enviado el id de la sección
if (!isset($_GET['seccionId']) || empty($_GET['seccionId'])) {
    // Si no se envía, devolvemos un error en formato JSON
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No se especificó la sección.']);
    exit;
}

$db = conectarDB();
$seccionId = mysqli_real_escape_string($db, $_GET['seccionId']);

// Consulta para obtener el código más alto de la sección seleccionada
// CAST(codigo AS UNSIGNED) es crucial para ordenar numéricamente y no alfabéticamente
$query = "SELECT MAX(CAST(codigo AS UNSIGNED)) AS max_codigo FROM libros WHERE seccionId = ?";

// Preparar la consulta para evitar inyección SQL
$stmt = mysqli_prepare($db, $query);
mysqli_stmt_bind_param($stmt, "i", $seccionId);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$datos = mysqli_fetch_assoc($resultado);

$siguienteCodigo = 1; // Por defecto, si no hay libros, empezamos en 1

if ($datos && $datos['max_codigo'] !== null) {
    // Si encontramos un código máximo, le sumamos 1
    $siguienteCodigo = (int)$datos['max_codigo'] + 1;
}

// Formateamos el código para que tenga ceros a la izquierda (ej: 000001)
$codigoFormateado = sprintf("%06d", $siguienteCodigo);

// Devolvemos el resultado en formato JSON para que JavaScript lo pueda leer
header('Content-Type: application/json');
echo json_encode(['siguiente_codigo' => $codigoFormateado]);

mysqli_close($db);
?>