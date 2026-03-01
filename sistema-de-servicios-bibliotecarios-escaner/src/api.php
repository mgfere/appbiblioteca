<?php
require '../database/conexion.php';

header('Content-Type: application/json'); // Indicar que la respuesta es JSON

// Obtener el método HTTP de la solicitud
$method = $_SERVER['REQUEST_METHOD'];

// Manejar la solicitud en función del método HTTP
switch ($method) {
    case 'POST':
        // Manejar una solicitud de inserción de nuevo registro
        if (isset($_POST['matricula']) && isset($_POST['nombre']) && isset($_POST['id_carrera']) && isset($_POST['id_especialidad']) && isset($_POST['id_servicio'])) {
            $matricula = $_POST['matricula'];
            $nombre = $_POST['nombre'];
            $id_carrera = $_POST['id_carrera'];
            $id_especialidad = $_POST['id_especialidad'];
            $id_servicio = $_POST['id_servicio'];
            $horaEntrada = date('Y-m-d H:i:s'); // Obtener la hora actual

            try {
                // Reemplazo de CALL insertar_registro
                // 1. Obtener/Crear usuario
                $stmtUser = $dbh->prepare("SELECT id_registeredUser FROM registereduser WHERE matricula = :matricula");
                $stmtUser->bindParam(':matricula', $matricula);
                $stmtUser->execute();
                $idRegisteredUser = $stmtUser->fetchColumn();

                if (!$idRegisteredUser) {
                    $stmtInsertUser = $dbh->prepare("INSERT INTO registereduser (matricula, nameUser, userType) VALUES (:matricula, :nombre, 'Externo')");
                    // Asumimos 'Externo' o lo que corresponda por defecto desde API
                    $stmtInsertUser->bindParam(':matricula', $matricula);
                    $stmtInsertUser->bindParam(':nombre', $nombre);
                    $stmtInsertUser->execute();
                    $idRegisteredUser = $dbh->lastInsertId();
                }

                // 2. Insertar registro
                $sql = "INSERT INTO registro (id_registeredUser, id_servicio, hora_entrada, status) VALUES (:id, :id_servicio, :horaEntrada, 0)";
                $stmt = $dbh->prepare($sql);
                $stmt->bindParam(':id', $idRegisteredUser);
                $stmt->bindParam(':id_servicio', $id_servicio);
                $stmt->bindParam(':horaEntrada', $horaEntrada);

                if ($stmt->execute()) {
                    // Devolver una respuesta de éxito en formato JSON
                    echo json_encode(['success' => true, 'message' => 'Se ha solicitado el servicio exitosamente']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Ha ocurrido un error al solicitar el servicio']);
                }
            } catch (PDOException $e) {
                echo json_encode(['success' => false, 'message' => 'Error al guardar el registro: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Datos de usuario incompletos']);
        }
        break;
    // Agregar casos para PUT y DELETE si necesitas soportar actualización y eliminación de registros.
    default:
        http_response_code(405); // Método no permitido
        echo json_encode(['success' => false, 'message' => 'Método no permitido']);
        break;
}
