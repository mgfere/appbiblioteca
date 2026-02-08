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
                // Llamar al procedimiento almacenado 'insertar_registro'
                $sql = "CALL insertar_registro(:matricula, :nombre, :id_carrera, :id_especialidad, :id_servicio, :horaEntrada)";
                $stmt = $dbh->prepare($sql);
                $stmt->bindParam(':matricula', $matricula);
                $stmt->bindParam(':nombre', $nombre);
                $stmt->bindParam(':id_carrera', $id_carrera);
                $stmt->bindParam(':id_especialidad', $id_especialidad);
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
