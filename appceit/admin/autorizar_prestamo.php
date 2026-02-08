<?php
// IMPORTANTE: No debe haber NINGUNA salida antes de este punto
// Ni espacios, ni líneas en blanco, ni BOM

// Limpiar cualquier salida previa y configurar buffer
ob_start();

// Capturar TODOS los errores PHP
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => "Error PHP: $errstr en $errfile línea $errline",
        'debug' => [
            'errno' => $errno,
            'file' => $errfile,
            'line' => $errline
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
});

// Capturar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Error fatal PHP: ' . $error['message'],
            'debug' => $error
        ], JSON_UNESCAPED_UNICODE);
    }
});

session_start();

// Configurar errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

require '../includes/config/database.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

// Función para responder con JSON y terminar
function jsonResponse($data, $statusCode = 200) {
    // Limpiar cualquier salida previa
    ob_clean();
    
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Verificar que el archivo .env existe
if (!file_exists(__DIR__ . '/../.env')) {
    jsonResponse(['success' => false, 'error' => 'Archivo de configuración no encontrado'], 500);
}

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
try {
    $dotenv->load();
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => 'Error cargando configuración'], 500);
}

// Verificar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Método no permitido'], 405);
}

// Verificar sesión de administrador
if (!isset($_SESSION['adminId']) || !isset($_SESSION['nombre'])) {
    jsonResponse(['success' => false, 'error' => 'No autorizado. Por favor inicie sesión nuevamente.'], 401);
}

$nombreAdministrador = $_SESSION['nombre'];

// Obtener y validar datos JSON
$input = file_get_contents("php://input");
if (empty($input)) {
    jsonResponse(['success' => false, 'error' => 'No se recibieron datos'], 400);
}

$data = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    jsonResponse(['success' => false, 'error' => 'Datos JSON inválidos'], 400);
}

$id_reservacion = $data['id'] ?? '';

if (empty($id_reservacion) || !is_numeric($id_reservacion)) {
    jsonResponse(['success' => false, 'error' => 'ID de reservación no válido'], 400);
}

// Función para enviar correo
function enviarCorreo($email, $usuario, $codigoLibro, $tituloLibro, $seccionLibro, $fechaPrestamo, $fechaDevolucion, $nombreAdministrador, $cantidad)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = $_ENV['SMTP_AUTH'] === 'true';
        $mail->Username = $_ENV['SMTP_USERNAME'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];
        $mail->Port = (int)$_ENV['SMTP_PORT'];
        $mail->SMTPSecure = $_ENV['SMTP_SECURE'] ?? 'tls';

        $mail->setFrom($_ENV['SMTP_USERNAME'], $_ENV['SMTP_USERADMIN'] ?? 'Biblioteca UTTN');
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->addAddress($email, $usuario);

        $mail->isHTML(true);
        $mail->Subject = '¡✅ Libro Entregado!';
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: Arial, sans-serif; font-size: 14px; line-height: 1.6; margin: 0; padding: 20px; background-color: #f4f4f4; }
                .container { max-width: 600px; margin: 0 auto; background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
                .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #09a787; padding-bottom: 15px; }
                .logo { width: 40%; height: auto; max-width: 150px; }
                .success-badge { background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; text-align: center; }
                .content { background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
                .warning { background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .cta-section { background-color: #e8f5f1; padding: 15px; margin: 20px 0; border-radius: 5px; }
                .btn-primary { display: inline-block; background-color: #09a787; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 5px; }
                .footer { background-color: #09a787; color: #fff; font-weight: bold; text-align: center; padding: 15px; border-radius: 8px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <img class='logo' src='{$_ENV['LOGOUT_IMG_URL']}' alt='Universidad Tecnológica de Tamaulipas Norte'>
                    <img class='logo' src='{$_ENV['LOGOCEIT_IMG_URL']}' alt='CEIT'>
                </div>
                <div class='success-badge'><h2 style='margin: 0; color: #155724;'>¡Préstamo entregado exitosamente!</h2></div>
                <h2 style='color: #09a787;'>¡Tu libro está listo!</h2>
                <p>Hola, <strong>{$usuario}</strong></p>
                <p>Tu libro ha sido entregado correctamente. A continuación encontrarás todos los detalles de tu préstamo:</p>
                <div class='content'>
                    <h3 style='color: #09a787; margin-top: 0;'>📖 Detalles del préstamo:</h3>
                    <p><strong>Código:</strong> {$codigoLibro}</p>
                    <p><strong>Título:</strong> {$tituloLibro}</p>
                    <p><strong>Sección:</strong> {$seccionLibro}</p>
                    <p><strong>Cantidad:</strong> {$cantidad}</p>
                    <p><strong>Fecha de préstamo:</strong> {$fechaPrestamo}</p>
                    <p><strong>Fecha de devolución:</strong> <span style='color: #dc3545; font-weight: bold;'>{$fechaDevolucion}</span></p>
                    <p><strong>Entregado por:</strong> {$nombreAdministrador}</p>
                </div>
                <div class='warning'>
                    <p style='margin: 0 0 10px 0;'><strong>⚠️ Recordatorio importante</strong></p>
                    <ul style='margin: 0; padding-left: 20px;'>
                        <li>Devuelve el libro en la <strong>fecha indicada</strong> para evitar sanciones</li>
                        <li>Cuida el libro durante el período de préstamo</li>
                        <li>En caso de pérdida o daño, se aplicarán las políticas correspondientes</li>
                    </ul>
                </div>
                <div class='cta-section'>
                    <p style='margin: 0 0 10px 0; color: #333; font-weight: bold;'>📚 ¿Necesitas renovar o reservar otro libro?</p>
                    <p style='margin: 0 0 15px 0; color: #666; font-size: 14px;'>Gestiona tus préstamos en línea o visítanos en el CEIT.</p>
                    <div style='text-align: center;'><a href='https://biblioteca.uttn.app/' class='btn-primary'>🌐 Ir a la Biblioteca Virtual</a></div>
                    <p style='margin: 15px 0 0 0; color: #666; font-size: 13px; text-align: center;'>También puedes acercarte al <strong>CEIT</strong> en la Universidad Tecnológica de Tamaulipas Norte</p>
                </div>
                <div class='footer'>
                    <p style='margin: 5px 0;'>© " . date('Y') . " | Universidad Tecnológica de Tamaulipas Norte</p>
                    <p style='margin: 5px 0;'>Sistema de Gestión Bibliotecaria</p>
                </div>
            </div>
        </body>
        </html>";

        $mail->send();
        return ['success' => true];
    } catch (Exception $e) {
        error_log("Error enviando correo: " . $mail->ErrorInfo);
        return ['success' => false, 'error' => $mail->ErrorInfo];
    }
}

// Variables de conexión
$db_mysql = null;
$conn_sqlsrv = null;

try {
    // --- CONEXIONES A BASES DE DATOS ---
    $db_mysql = conectarDB();
    if (!$db_mysql) {
        throw new Exception('Error conectando a la base de datos MySQL');
    }

    $conn_sqlsrv = conectarDB3();
    if (!$conn_sqlsrv) {
        throw new Exception('Error conectando a la base de datos SQL Server');
    }

    // --- OBTENER DATOS DE LA RESERVACIÓN ---
    $query_reservacion = "SELECT Estudiantes_id, Libros_id, cantidad, Matricula, tipo_usuario FROM reservaciones WHERE id = ?";
    $stmt_reservacion = $db_mysql->prepare($query_reservacion);
    
    if (!$stmt_reservacion) {
        throw new Exception('Error preparando consulta de reservación');
    }
    
    $stmt_reservacion->bind_param('i', $id_reservacion);
    
    if (!$stmt_reservacion->execute()) {
        throw new Exception('Error ejecutando consulta de reservación');
    }
    
    $resultado_reservacion = $stmt_reservacion->get_result();

    if ($resultado_reservacion->num_rows === 0) {
        throw new Exception('No se encontró la reservación especificada');
    }

    $reservacion = $resultado_reservacion->fetch_assoc();
    $estudiante_id = $reservacion['Estudiantes_id'];
    $libro_id = $reservacion['Libros_id'];
    $cantidad = $reservacion['cantidad'];
    $matricula_o_numero = $reservacion['Matricula'];
    $tipo_usuario = $reservacion['tipo_usuario'];

    // --- OBTENER DATOS DEL USUARIO ---
    if ($tipo_usuario === 'alumno') {
        $query_sqlsrv = "SELECT Nombre, ApellidoPaterno, ApellidoMaterno, CorreoElectronico
                         FROM [GestionUsuarios].[dbo].[Alumnos]
                         WHERE IdAlumno = ? AND Matricula = ?";
        $params_sqlsrv = [$estudiante_id, $matricula_o_numero];
    } else if ($tipo_usuario === 'docente') {
        $query_sqlsrv = "SELECT Nombre, ApellidoPaterno, ApellidoMaterno, CorreoElectronico
                         FROM [GestionUsuarios].[dbo].[Docentes]
                         WHERE IdDocente = ? AND NumeroEmpleado = ?";
        $params_sqlsrv = [$estudiante_id, $matricula_o_numero];
    } else {
        throw new Exception('Tipo de usuario no válido');
    }

    $res_sqlsrv = sqlsrv_query($conn_sqlsrv, $query_sqlsrv, $params_sqlsrv);

    if ($res_sqlsrv === false) {
        $errors = sqlsrv_errors();
        error_log('Error SQL Server: ' . print_r($errors, true));
        throw new Exception('Error consultando datos del usuario');
    }

    if (!sqlsrv_has_rows($res_sqlsrv)) {
        throw new Exception('No se encontraron datos del usuario');
    }

    $usuario_sqlsrv = sqlsrv_fetch_array($res_sqlsrv, SQLSRV_FETCH_ASSOC);
    $email_usuario = $usuario_sqlsrv['CorreoElectronico'];
    $nombre_usuario = ucwords(strtolower(trim($usuario_sqlsrv['Nombre'] . ' ' . $usuario_sqlsrv['ApellidoPaterno'] . ' ' . $usuario_sqlsrv['ApellidoMaterno'])));

    // --- INICIAR TRANSACCIÓN ---
    $db_mysql->begin_transaction();

    try {
        // Calcular fechas
        $fecha_actual = new DateTime();
        $fecha_devolucion_obj = clone $fecha_actual;
        $fecha_devolucion_obj->modify('+5 days');
        $fecha_devolucion = $fecha_devolucion_obj->format('Y-m-d H:i:s');

        // Insertar en préstamos
        $query_insert_prestamo = "INSERT INTO prestamos (Estudiantes_id, Libros_id, fecha_prestamo, cantidad, fecha_devolucion, status, entregado, tipo_usuario, matricula) 
                                  VALUES (?, ?, NOW(), ?, ?, '1', ?, ?, ?)";
        $stmt_insert = $db_mysql->prepare($query_insert_prestamo);
        
        if (!$stmt_insert) {
            throw new Exception('Error preparando inserción de préstamo');
        }
        
        $stmt_insert->bind_param('iisssss', 
            $estudiante_id, 
            $libro_id, 
            $cantidad, 
            $fecha_devolucion, 
            $nombreAdministrador, 
            $tipo_usuario, 
            $matricula_o_numero
        );

        if (!$stmt_insert->execute()) {
            throw new Exception('Error al crear el préstamo');
        }

        // Actualizar estado del libro
        $update_query = "UPDATE libros SET status = 'Inactivo' WHERE id = ?";
        $stmt_update = $db_mysql->prepare($update_query);
        
        if (!$stmt_update) {
            throw new Exception('Error preparando actualización de libro');
        }
        
        $stmt_update->bind_param('i', $libro_id);
        
        if (!$stmt_update->execute()) {
            throw new Exception('Error al actualizar estado del libro');
        }

        // Eliminar reservación
        $delete_query = "DELETE FROM reservaciones WHERE id = ?";
        $stmt_delete = $db_mysql->prepare($delete_query);
        
        if (!$stmt_delete) {
            throw new Exception('Error preparando eliminación de reservación');
        }
        
        $stmt_delete->bind_param('i', $id_reservacion);
        
        if (!$stmt_delete->execute()) {
            throw new Exception('Error al eliminar la reservación');
        }

        // Confirmar transacción
        $db_mysql->commit();

        // --- OBTENER DATOS DEL LIBRO PARA EL CORREO ---
        $select_libro_query = "SELECT l.codigo, l.titulo, s.nombre_seccion 
                               FROM libros l 
                               JOIN secciones s ON l.seccionId = s.id 
                               WHERE l.id = ?";
        $stmt_libro = $db_mysql->prepare($select_libro_query);
        
        if (!$stmt_libro) {
            throw new Exception('Error preparando consulta de libro');
        }
        
        $stmt_libro->bind_param('i', $libro_id);
        
        if (!$stmt_libro->execute()) {
            throw new Exception('Error consultando datos del libro');
        }
        
        $libro_info = $stmt_libro->get_result()->fetch_assoc();

        if (!$libro_info) {
            throw new Exception('No se encontraron datos del libro');
        }

        // Formatear fechas para el correo
        $fechaPrestamo = date('d-m-Y');
        $fechaDevolucion = date('d-m-Y', strtotime($fecha_devolucion));

        // Enviar correo
        $resultadoCorreo = enviarCorreo(
            $email_usuario, 
            $nombre_usuario, 
            $libro_info['codigo'], 
            $libro_info['titulo'], 
            $libro_info['nombre_seccion'], 
            $fechaPrestamo, 
            $fechaDevolucion, 
            $nombreAdministrador, 
            $cantidad
        );

        if ($resultadoCorreo['success']) {
            jsonResponse([
                'success' => true, 
                'message' => 'Préstamo autorizado correctamente y correo enviado al usuario'
            ]);
        } else {
            jsonResponse([
                'success' => true, 
                'message' => 'Préstamo autorizado correctamente, pero hubo un problema al enviar el correo de notificación',
                'warning' => 'El correo no pudo ser enviado'
            ]);
        }

    } catch (Exception $e) {
        // Revertir transacción en caso de error
        $db_mysql->rollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Error en autorizar_prestamo.php: " . $e->getMessage());
    jsonResponse([
        'success' => false, 
        'error' => 'Error al procesar el préstamo: ' . $e->getMessage()
    ], 500);
} finally {
    // Cerrar conexiones
    if (isset($stmt_reservacion)) $stmt_reservacion->close();
    if (isset($stmt_insert)) $stmt_insert->close();
    if (isset($stmt_update)) $stmt_update->close();
    if (isset($stmt_delete)) $stmt_delete->close();
    if (isset($stmt_libro)) $stmt_libro->close();
    
    if ($db_mysql) {
        mysqli_close($db_mysql);
    }
    if ($conn_sqlsrv) {
        sqlsrv_close($conn_sqlsrv);
    }
}
?>