<?php
// CAMBIO: Se establece el encabezado para una respuesta JSON correcta
header('Content-Type: application/json');

// CAMBIO: Se usa la conexión a SQL Server (conectarDB2)
require '../includes/config/database.php';
$conn = conectarDB3();

// CAMBIO: La consulta ahora apunta a la tabla y columna de SQL Server
$countQueryUsuarios = "SELECT COUNT(*) AS total_usuarios   FROM [GestionUsuarios].[dbo].[Alumnos] WHERE Habilitado = 1";

// CAMBIO: Se usan las funciones de sqlsrv para ejecutar y obtener el resultado
$resultadoCountUsuarios = sqlsrv_query($conn, $countQueryUsuarios);

$totalUsuarios = 0; // Valor por defecto si la consulta falla
if ($resultadoCountUsuarios) {
    $fila = sqlsrv_fetch_array($resultadoCountUsuarios, SQLSRV_FETCH_ASSOC);
    $totalUsuarios = $fila['total_usuarios'];
}

// Devolver el número total de usuarios en formato JSON
echo json_encode(['total_usuarios' => $totalUsuarios]);

// CAMBIO: Cerrar la conexión de SQL Server
if ($conn) {
    sqlsrv_close($conn);
}