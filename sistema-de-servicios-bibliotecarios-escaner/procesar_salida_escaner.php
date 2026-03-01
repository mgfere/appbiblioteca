<?php
require_once 'database/Database.php';
require_once 'database/DatabaseAPI.php';
require_once 'src/TokenDecrypter.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$matriculaInput = $_POST['matricula'] ?? '';

// Intentar descifrar el token
$matriculaDescifrada = TokenDecrypter::decrypt($matriculaInput);

// Si se descifró correctamente, usarla; si no, asumir que es una matrícula manual
$matricula = strtoupper(trim($matriculaDescifrada ? $matriculaDescifrada : $matriculaInput));

if (empty($matricula)) {
    echo json_encode(['success' => false, 'message' => 'Matrícula vacía']);
    exit;
}

$dbAPI = new DatabaseAPI();

try {
    // 1. Buscar registros activos para esta matrícula
    // Reutilizamos logicamente BuscarRegistroPorMatricula que devuelve lista de activos
    $activos = $dbAPI->obtenerRegistroPorMatricula($matricula);

    // Filtrar por si acaso el procedimiento devuelve historial (aunque lo definimos para activos)
    $activosReales = [];
    if ($activos) {
        foreach ($activos as $rec) {
            if (empty($rec['hora_salida']) || $rec['hora_salida'] == '0000-00-00 00:00:00') {
                $activosReales[] = $rec;
            }
        }
    }

    if (empty($activosReales)) {
        echo json_encode(['success' => false, 'message' => 'No tienes entrada activa para salir.']);
        exit;
    }

    // 2. Tomar el PRIMER registro activo (si hay múltiples, el más reciente por ORDER BY fecha DESC en proc)
    $registroASalir = $activosReales[0];
    $idRegistro = $registroASalir['id_registro'];
    $nombre = $registroASalir['nameUser'];

    // 3. Registrar Salida
    $resultado = $dbAPI->RegistrarSalida($idRegistro);

    if ($resultado) {
        echo json_encode(['success' => true, 'message' => "Salida registrada exitosamente"]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al registrar salida en BD']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error servidor: ' . $e->getMessage()]);
}
?>