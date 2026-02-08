<?php
// Asegúrate de que las rutas a estos archivos sean correctas desde la carpeta /includes/
require_once 'config/database.php'; 
require_once 'funciones.php';

// Establecer la cabecera como JSON desde el inicio
header('Content-Type: application/json; charset=utf-8');

// Función para enviar una respuesta de error en JSON y terminar el script
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
    $type = strtolower($type[1]); // jpg, png, gif

    if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png'])) {
        enviarError('Tipo de imagen no válido.');
    }
    $data = base64_decode($data);
    if ($data === false) {
        enviarError('El código base64 es inválido.');
    }
} else {
    enviarError('El formato de la URL de datos es incorrecto.');
}

// 3. Crear el nombre del archivo y la ruta
function slugify($text) {
    return preg_replace('/[^a-z0-9-]+/', '-', strtolower(trim($text)));
}

$nombreArchivo = slugify($libroCodigo) . '_' . slugify($libroTitulo) . '.png';

// ✅ ¡PUNTO CRÍTICO! Asegúrate de que esta ruta sea correcta en tu servidor
// dirname(__DIR__) obtiene la carpeta 'admin', así que salimos de ella para entrar a 'public'
$rutaGuardado = dirname(__DIR__) . '/imagenes';

// 4. Verificar y crear la carpeta si no existe
if (!is_dir($rutaGuardado)) {
    // El segundo parámetro (0755) son los permisos, el tercero (true) permite crear carpetas anidadas
    if (!mkdir($rutaGuardado, 0755, true)) {
        enviarError('Error: No se pudo crear la carpeta para los códigos QR. Verifica los permisos.');
    }
}

// 5. Guardar el archivo
$rutaCompleta = $rutaGuardado . $nombreArchivo;
if (file_put_contents($rutaCompleta, $data)) {
    // Si se guardó, ahora actualizamos la base de datos
    $db = conectarDB();
    $query = "UPDATE libros SET QR = 1 WHERE id = ?";
    $stmt = mysqli_prepare($db, $query);
    mysqli_stmt_bind_param($stmt, 'i', $libroId);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode([
            'status' => 'success',
            'message' => 'QR guardado y base de datos actualizada.',
            'fileName' => $nombreArchivo
        ]);
    } else {
        // Si falló el UPDATE, borramos el archivo para no dejar basura
        unlink($rutaCompleta);
        enviarError('El QR se guardó, pero no se pudo actualizar la base de datos.');
    }
    mysqli_stmt_close($stmt);
    mysqli_close($db);
} else {
    enviarError('Error fatal: No se pudo guardar el archivo de imagen. ¡Verifica los permisos de la carpeta en el servidor!');
}
?>