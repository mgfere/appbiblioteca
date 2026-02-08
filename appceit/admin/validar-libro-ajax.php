<?php
require '../includes/config/database.php';
$db = conectarDB();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['bookId'])) {
    echo json_encode(['success' => false, 'message' => 'Acceso inválido.']);
    exit;
}

$identificador = trim($_POST['bookId']);

// Función para buscar en la base de datos
function buscarLibros($db, $identificador) {
    $libros = [];
    $query = "SELECT l.id, l.codigo, l.titulo, l.status, l.cantidad, l.editorial, s.nombre_seccion 
              FROM libros l 
              LEFT JOIN secciones s ON l.seccionId = s.id 
              WHERE ";

    if (ctype_digit($identificador)) {
        $query .= "l.id = ? OR l.codigo = ?";
        $stmt = mysqli_prepare($db, $query);
        mysqli_stmt_bind_param($stmt, 'is', $identificador, $identificador);
    } else {
        $query .= "l.codigo = ?";
        $stmt = mysqli_prepare($db, $query);
        mysqli_stmt_bind_param($stmt, 's', $identificador);
    }
    
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    while ($libro = mysqli_fetch_assoc($resultado)) {
        $libros[] = $libro;
    }
    mysqli_stmt_close($stmt);
    return $libros;
}

// Lógica principal
try {
    $librosEncontrados = buscarLibros($db, $identificador);
    
    if (empty($librosEncontrados)) {
        echo json_encode(['success' => false, 'message' => 'No se encontró ningún libro con ese ID o código.']);
    } elseif (count($librosEncontrados) === 1) {
        $libro = $librosEncontrados[0];
        if ($libro['status'] !== 'Activo') {
            echo json_encode(['success' => false, 'message' => 'El libro está inactivo y no se puede prestar.']);
        } elseif ($libro['cantidad'] <= 0) {
            echo json_encode(['success' => false, 'message' => 'No hay ejemplares disponibles de este libro.']);
        } else {
            // ÉXITO ÚNICO: Le decimos que redirija
            echo json_encode(['success' => true, 'action' => 'redirect', 'bookId' => $libro['id']]);
        }
    } else {
        // ✅ ÉXITO MÚLTIPLE: Le decimos que muestre el selector
        echo json_encode(['success' => true, 'action' => 'select', 'libros' => $librosEncontrados]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error en el servidor.']);
}

mysqli_close($db);
?>