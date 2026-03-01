<?php
require '../database/Database.php';
require '../database/DatabaseAPI.php';

$dbAPI = new DatabaseAPI(); 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcarSalida']) && isset($_POST['registroId'])) {
    $dbAPI = new DatabaseAPI();
    $registroId = $_POST['registroId'];
    
    try {
        $result = $dbAPI->RegistrarSalida($registroId);
        echo json_encode(['success' => $result]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Solicitud inválida']);
exit;
?>
