<?php


require_once 'config/database.php';
require_once 'funciones.php';

// Establecer la cabecera como JSON
header('Content-Type: application/json; charset=utf-8');

function enviarError($mensaje) {
    echo json_encode(['status' => 'error', 'message' => $mensaje]);
    exit;
}

// 1. Validar la entrada
$datos = json_decode(file_get_contents('php://input'), true);

if (!$datos || !isset($datos['libroId'], $datos['qrData'], $datos['libroCodigo'], $datos['libroTitulo'])) {
    enviarError('Faltan datos para generar el QR.');
}

$libroId = $datos['libroId'];
$dataUrl = $datos['qrData'];
$libroCodigo = $datos['libroCodigo'];
$libroTitulo = $datos['libroTitulo'];

// 2. Procesar la imagen del QR
if (preg_match('/^data:image\/(\w+);base64,/', $dataUrl, $type)) {
    $data = substr($dataUrl, strpos($dataUrl, ',') + 1);
    $data = base64_decode($data);
    if ($data === false) {
        enviarError('El código base64 es inválido.');
    }
} else {
    enviarError('El formato de la URL de datos es incorrecto.');
}

// 3. Crear el nombre del archivo y la ruta de guardado
function slugify($text) {
    return preg_replace('/[^a-z0-9-]+/', '-', strtolower(trim($text)));
}

// Se mantiene el nombre sin slugify para conservar mayúsculas
$nombreArchivo = $libroCodigo . '_' . $libroTitulo . '.png';

$rutaGuardado = dirname(__DIR__) . '/imagenes';

// 4. Verificar y crear la carpeta si no existe
if (!is_dir($rutaGuardado)) {
    if (!mkdir($rutaGuardado, 0755, true)) {
        enviarError('Error: No se pudo crear la carpeta para los códigos QR. Verifica los permisos.');
    }
}

// 5. Guardar el archivo y actualizar la base de datos
$rutaCompleta = $rutaGuardado . '/' . $nombreArchivo;
if (file_put_contents($rutaCompleta, $data)) {
    $db = conectarDB();
    
    // La consulta ahora actualiza el nombre del QR
    $query = "UPDATE libros SET QR = 1, ImagenQR = ? WHERE id = ?";
    $stmt = mysqli_prepare($db, $query);

    // Ahora enlazamos el nombre del archivo (s) y el ID (i)
    mysqli_stmt_bind_param($stmt, 'si', $nombreArchivo, $libroId);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            'status' => 'success',
            'message' => 'QR guardado y base de datos actualizada.',
            'fileName' => $nombreArchivo
        ]);
    } else {
        unlink($rutaCompleta);
        enviarError('El QR se guardó, pero no se pudo actualizar la base de datos.');
    }
    mysqli_stmt_close($stmt);
    mysqli_close($db);
} else {
    enviarError('Error fatal: No se pudo guardar el archivo de imagen. ¡Verifica los permisos de la carpeta en el servidor!');
}
?>