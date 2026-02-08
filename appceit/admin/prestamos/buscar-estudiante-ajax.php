<?php
require '../../includes/funciones.php';
require '../../includes/config/database.php';
$db = conectarDB();

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'nombreCompleto' => '',
    'email' => '',
    'carreraId' => '',
    'especialidadId' => '',
    'turno' => ''
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $matricula = $_POST['matricula'] ?? '';
    
    if (empty($matricula)) {
        $response['message'] = 'La matrícula no puede estar vacía.';
        echo json_encode($response);
        exit;
    }

    $query = "SELECT nombre, apellido, email, carreraId, especialidadId, turno
              FROM usuarios 
              WHERE matricula = ?";
    $stmt = mysqli_prepare($db, $query);
    mysqli_stmt_bind_param($stmt, 's', $matricula);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);

    if ($estudiante = mysqli_fetch_assoc($resultado)) {
        $response['success'] = true;
        $response['nombreCompleto'] = $estudiante['nombre'] . ' ' . $estudiante['apellido'];
        $response['email'] = $estudiante['email'];
        $response['carreraId'] = $estudiante['carreraId'];
        $response['especialidadId'] = $estudiante['especialidadId'];
        $response['turno'] = $estudiante['turno'];
    } else {
        $response['message'] = 'No se encontró un estudiante con esa matrícula.';
    }
    mysqli_stmt_close($stmt);
} else {
    $response['message'] = 'Método de solicitud no permitido.';
}

mysqli_close($db);
echo json_encode($response);
?>