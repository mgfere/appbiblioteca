<?php
require '../includes/funciones.php';
require '../includes/config/database.php';

// Verificar autenticación
$auth = adminAutenticado();
if (!$auth) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Obtener parámetros
$turno = isset($_POST['turno']) ? $_POST['turno'] : '';
$fechaInicio = isset($_POST['fechaInicio']) ? $_POST['fechaInicio'] : '';
$fechaFin = isset($_POST['fechaFin']) ? $_POST['fechaFin'] : '';

// Validar fechas
if (empty($fechaInicio) || empty($fechaFin)) {
    http_response_code(400);
    echo json_encode(['error' => 'Fechas requeridas']);
    exit;
}

$db = conectarDB();

// Construir la consulta SQL
$whereTurno = '';
if (!empty($turno)) {
    $whereTurno = " AND t.turno = '" . mysqli_real_escape_string($db, $turno) . "'";
}

$sqlDevolucionesPorTurno = "
    SELECT t.turno AS turno, COUNT(p.id) AS total_devoluciones_turno 
    FROM prestamos AS p
    JOIN usuarios AS u ON p.Estudiantes_id = u.id
    JOIN turnos AS t ON u.id_turno = t.id_turno
    WHERE p.fecha_devolucion BETWEEN '" . mysqli_real_escape_string($db, $fechaInicio) . "' AND '" . mysqli_real_escape_string($db, $fechaFin) . " 23:59:59'
    AND p.fecha_devolucion IS NOT NULL
    {$whereTurno}
    GROUP BY t.turno  
    ORDER BY total_devoluciones_turno DESC
";

$resultado = mysqli_query($db, $sqlDevolucionesPorTurno);

if (!$resultado) {
    error_log("Error en consulta de devoluciones por turno: " . mysqli_error($db));
    http_response_code(500);
    echo json_encode(['error' => 'Error en la consulta']);
    mysqli_close($db);
    exit;
}

// Arrays para la respuesta
$labels = [];
$data = [];

while ($fila = mysqli_fetch_assoc($resultado)) {
    $labels[] = $fila['turno'];
    $data[] = (int)$fila['total_devoluciones_turno'];
}

mysqli_close($db);

// Preparar respuesta JSON
$response = [
    'labels' => $labels,
    'data' => $data
];

header('Content-Type: application/json');
echo json_encode($response);
?>