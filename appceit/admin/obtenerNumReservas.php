<?php
require '../includes/config/database.php';
$db = conectarDB();

// Consulta para contar el número total de reservaciones
$countQueryReservaciones = "SELECT COUNT(*) AS total_reservaciones FROM reservaciones";
$resultadoCountReservaciones = mysqli_query($db, $countQueryReservaciones);
$totalReservaciones = mysqli_fetch_assoc($resultadoCountReservaciones)['total_reservaciones'];

// Devolver el número total de reservaciones en formato JSON
echo json_encode(['total_reservaciones' => $totalReservaciones]);

// Cerrar la conexión de la base de datos
mysqli_close($db);
