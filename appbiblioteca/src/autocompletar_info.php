<?php
require_once '../database/Database.php';
require_once '../database/DatabaseAPI.php';

header('Content-Type: application/json');

$dbAPI = new DatabaseAPI();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['matricula'])) {
    try {
        $userData = $dbAPI->obtenerDatosCompletosUsuario($_POST['matricula']);
        
        if ($userData) {
            $response = [
                'success' => true,
                'userData' => [
                    'nameUser' => $userData['nameUser'],
                    'userType' => $userData['userType']
                ]
            ];
            
            if ($userData['userType'] === 'Alumno') {
                $response['userData']['id_carrera'] = $userData['id_carrera'] ?? null;
                $response['userData']['nombre_carrera'] = $userData['nombre_carrera'] ?? null;
                $response['userData']['id_especialidad'] = $userData['id_especialidad'] ?? null;
                $response['userData']['nombre_especialidad'] = $userData['nombre_especialidad'] ?? null;
            }
            
            echo json_encode($response);
        } else {
            echo json_encode(['success' => false]);
        }
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false, 
            'error' => 'Error al obtener datos: ' . $e->getMessage()
        ]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Solicitud inválida']);
?> 