<?php
require '../includes/funciones.php';
header('Content-Type: application/json; charset=utf-8');

function responder_json($exito, $mensaje, $http_code = 200) {
    http_response_code($http_code);
    echo json_encode(['success' => $exito, 'message' => $mensaje]);
    exit;
}

// --- Autenticación ---
$auth = adminAutenticado();
if (!$auth || ($_SESSION['rol'] ?? null) != 1) {
    responder_json(false, 'No autorizado.', 403);
}

// --- Validar parámetros ---
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$estatus = filter_input(INPUT_POST, 'estatus', FILTER_VALIDATE_INT);

if ($id === false || $id === null || !in_array($estatus, [0, 1])) {
    responder_json(false, 'Error: Parámetros no válidos.', 400);
}

// --- Conexión a las bases de datos ---
require '../includes/config/database.php';
$db_mysql = conectarDB();
$conn_sqlsrv = conectarDB3(); // Para estudiantes

// --- Obtener la matrícula del estudiante desde SQL Server ---
$query_matricula = "SELECT Matricula FROM [GestionUsuarios].[dbo].[Alumnos] WHERE IdAlumno = ?";
$params_matricula = [$id];
$stmt_matricula = sqlsrv_query($conn_sqlsrv, $query_matricula, $params_matricula);

if ($stmt_matricula === false) {
    responder_json(false, 'Error al obtener datos del estudiante.', 500);
}

$estudiante = sqlsrv_fetch_array($stmt_matricula, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmt_matricula);

if (!$estudiante) {
    responder_json(false, 'Estudiante no encontrado.', 404);
}

$matricula = $estudiante['Matricula'];
$tipoUsuario = 'alumno';

// --- Verificar si el estudiante tiene préstamos activos ---
if ($estatus === 0) {
    $query_prestamos = "SELECT COUNT(*) as total FROM prestamos WHERE Estudiantes_id = ? AND status = '1'";
    $stmt_prestamos = $db_mysql->prepare($query_prestamos);
    $stmt_prestamos->bind_param('i', $id);
    $stmt_prestamos->execute();
    $resultado_prestamos = $stmt_prestamos->get_result()->fetch_assoc();

    if ($resultado_prestamos['total'] > 0) {
        responder_json(false, "Error: No se puede desactivar un estudiante con {$resultado_prestamos['total']} préstamo(s) activo(s).", 409);
    }
}

// --- NUEVA LÓGICA: Verificar si existe el registro con ID y Matrícula correctos ---
$query_verificar = "SELECT IdUsuario, Matricula FROM control_acceso WHERE IdUsuario = ? AND TipoUsuario = ?";
$stmt_verificar = $db_mysql->prepare($query_verificar);
$stmt_verificar->bind_param('is', $id, $tipoUsuario);
$stmt_verificar->execute();
$resultado_verificar = $stmt_verificar->get_result();
$registro_existente = $resultado_verificar->fetch_assoc();

if ($registro_existente) {
    // ✅ Si existe, verificar si la matrícula coincide
    if ($registro_existente['Matricula'] === $matricula) {
        // Matrícula coincide, actualizar normalmente
        $query_actualizar = "UPDATE control_acceso SET estatus = ? WHERE IdUsuario = ? AND TipoUsuario = ? AND Matricula = ?";
        $stmt_actualizar = $db_mysql->prepare($query_actualizar);
        $stmt_actualizar->bind_param('iiss', $estatus, $id, $tipoUsuario, $matricula);
        $resultado = $stmt_actualizar->execute();
    } else {
        // ❌ Matrícula NO coincide, es un ID duplicado de otro usuario
        responder_json(false, "Error: Conflicto de ID. Ya existe otro usuario con este ID pero diferente matrícula.", 409);
    }
} else {
    // ✅ No existe, insertar nuevo registro
    $query_insertar = "INSERT INTO control_acceso (IdUsuario, TipoUsuario, Matricula, estatus) VALUES (?, ?, ?, ?)";
    $stmt_insertar = $db_mysql->prepare($query_insertar);
    $stmt_insertar->bind_param('issi', $id, $tipoUsuario, $matricula, $estatus);
    $resultado = $stmt_insertar->execute();
}

// --- Enviar respuesta JSON final ---
if ($resultado) {
    $accion = ($estatus === 1) ? 'activado' : 'desactivado';
    responder_json(true, "Estudiante {$accion} correctamente.");
} else {
    responder_json(false, 'Error al actualizar el estatus del estudiante.', 500);
}

// --- Cerrar conexiones ---
sqlsrv_close($conn_sqlsrv);
mysqli_close($db_mysql);
?>