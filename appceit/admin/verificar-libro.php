<?php
require '../includes/config/database.php';
$db = conectarDB();

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookId = $_POST['bookId'] ?? '';
    $usuarioId = $_POST['usuarioId'] ?? '';

    if (empty($bookId) || empty($usuarioId)) {
        $response['message'] = 'El ID del libro y del estudiante son requeridos.';
        echo json_encode($response);
        exit;
    }

    $bookId = mysqli_real_escape_string($db, $bookId);
    $usuarioId = mysqli_real_escape_string($db, $usuarioId);

    // Buscar libro por id o por código
    $queryLibros = "SELECT id, titulo, cantidad, codigo, editorial FROM libros WHERE id = '$bookId' OR codigo = '$bookId'";
    $resLibros = mysqli_query($db, $queryLibros);
    $numLibros = $resLibros ? mysqli_num_rows($resLibros) : 0;
    if ($numLibros > 1) {
    $libros = [];
    while ($libro = mysqli_fetch_assoc($resLibros)) {
    $libros[] = $libro;
    }
    $response['multiple'] = true;
    $response['libros'] = $libros;
    $response['message'] = 'Se encontraron varios libros con ese código. Selecciona uno.';
    } elseif ($numLibros === 1) {
    $libro = mysqli_fetch_assoc($resLibros);
    // Validar si ya existe una reservación activa para este libro y estudiante
    $query = "SELECT * FROM reservaciones WHERE Libros_id = '{$libro['id']}' AND Estudiantes_id = '$usuarioId' AND estado = 'pendiente' LIMIT 1";
    $resultado = mysqli_query($db, $query);
    if ($resultado && mysqli_num_rows($resultado) > 0) {
    $response['message'] = 'Ya tienes una reservación activa para este libro.';
    } else {
    if ($libro['cantidad'] > 0) {
    $response['success'] = true;
    $response['message'] = 'Libro disponible para reservar.';
    $response['bookId'] = $libro['id'];
    } else {
    $response['message'] = 'No hay existencias disponibles para este libro.';
    }
    }
    } else {
    $response['message'] = 'El libro no existe.';
    }
} else {
    $response['message'] = 'Método de solicitud no permitido.';
}

mysqli_close($db);
echo json_encode($response);
