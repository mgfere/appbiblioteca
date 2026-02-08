<?php
session_start();
require '../includes/config/database.php';
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// --- CONFIGURACIÓN INICIAL ---
header('Content-Type: application/json');
$nombreAdministrador = $_SESSION['nombre'] ?? 'Usuario';

// Función para enviar correo mejorada
function enviarCorreo($usuarioEmail, $usuarioNombre, $libroTitulo, $libroCodigo, $libroSeccion, $cantidadPrestada, $fechaPrestamo, $fechaDevolucion, $nombreAdministrador, $tipoUsuario = 'alumno') {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = $_ENV['SMTP_AUTH'] === 'true';
        $mail->Username = $_ENV['SMTP_USERNAME'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];
        $mail->Port = $_ENV['SMTP_PORT'];
        $mail->setFrom($_ENV['SMTP_USERNAME'], $_ENV['SMTP_USERADMIN']);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->addAddress($usuarioEmail, $usuarioNombre);
        $mail->isHTML(true);
        $mail->Subject = 'Devolución confirmada - Préstamo finalizado';
        
        // Badge según tipo de usuario
        $etiquetaUsuario = ($tipoUsuario === 'docente') ? 'Docente' : ' Alumno';
        
        $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        font-size: 14px;
                        line-height: 1.6;
                        margin: 0;
                        padding: 20px;
                        background-color: #f4f4f4;
                    }
                    .container {
                        max-width: 600px;
                        margin: 0 auto;
                        background-color: white;
                        padding: 20px;
                        border-radius: 10px;
                        box-shadow: 0 0 10px rgba(0,0,0,0.1);
                    }
                    .header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 20px;
                        border-bottom: 2px solid #09a787;
                        padding-bottom: 15px;
                    }
                    .logo {
                        width: 40%;
                        height: auto;
                        max-width: 150px;
                    }
                    .user-badge {
                        display: inline-block;
                        background-color: #09a787;
                        color: white;
                        padding: 5px 10px;
                        border-radius: 15px;
                        font-size: 14px;
                        margin-left: 10px;
                    }
                    .content {
                        background-color: #f8f9fa;
                        padding: 20px;
                        border-radius: 8px;
                        margin: 20px 0;
                    }
                    .success-badge {
                        background-color: #d4edda;
                        border: 1px solid #c3e6cb;
                        color: #155724;
                        padding: 15px;
                        border-radius: 5px;
                        margin: 15px 0;
                        text-align: center;
                    }
                    .footer {
                        background-color: #09a787;
                        color: #fff;
                        font-weight: bold;
                        text-align: center;
                        padding: 15px;
                        border-radius: 8px;
                        margin-top: 20px;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <img class='logo' src='{$_ENV['LOGOUT_IMG_URL']}' alt='Universidad Tecnológica de Tamaulipas Norte'>
                        <img class='logo' src='{$_ENV['LOGOCEIT_IMG_URL']}' alt='CEIT'>
                    </div>
                    
                    <div class='success-badge'>
                        <h2 style='margin: 0; color: #155724;'>✅ ¡Devolución exitosa!</h2>
                    </div>
                    
                    <p>Hola, <strong>{$usuarioNombre}</strong></p>
                    <p>Tu préstamo del libro <strong>{$libroTitulo}</strong> ha sido devuelto correctamente. ¡Gracias por cuidar nuestros recursos!</p>
                    
                    <div class='content'>
                        <h3 style='color: #09a787; margin-top: 0;'>Detalles del préstamo:</h3>
                        <p><strong>Código:</strong> {$libroCodigo}</p>
                        <p><strong>Sección:</strong> {$libroSeccion}</p>
                        <p><strong>Cantidad devuelta:</strong> {$cantidadPrestada}</p>
                        <p><strong>Fecha de préstamo:</strong> {$fechaPrestamo}</p>
                        <p><strong>Fecha de devolución:</strong> {$fechaDevolucion}</p>
                        <p><strong>Recibido por:</strong> {$nombreAdministrador}</p>
                    </div>
                    
                    <div style='background-color: #e8f5f1;  padding: 15px; margin: 20px 0; border-radius: 5px;'>
                        <p style='margin: 0 0 10px 0; color: #333; font-weight: bold;'>¿Quieres reservar un libro?</p>
                        <p style='margin: 0 0 15px 0; color: #666; font-size: 14px;'>
                            Puedes hacer tu siguiente reserva en línea o visitarnos directamente.
                        </p>
                        <div style='text-align: center;'>
                            <a href='https://biblioteca.uttn.app/' 
                               style='display: inline-block; background-color: #09a787; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 5px;'>
                                🌐 Ir a la Biblioteca 
                            </a>
                        </div>
                        <p style='margin: 15px 0 0 0; color: #666; font-size: 13px; text-align: center;'>
                            O acércate al <strong>CEIT</strong> en la Universidad Tecnológica de Tamaulipas Norte
                        </p>
                    </div>
                    
                    <div class='footer'>
                        <p style='margin: 5px 0;'>© " . date('Y') . " | Universidad Tecnológica de Tamaulipas Norte</p>
                        <p style='margin: 5px 0;'>Centro de Información Tecnológica</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prestamoId = $_POST['prestamoId'] ?? null;
    if (!$prestamoId) {
        echo json_encode(['success' => false, 'message' => 'ID de préstamo no proporcionado.']);
        exit;
    }

    // --- PASO 1: Conectar a AMBAS bases de datos ---
    $db_mysql = conectarDB();
    $conn_sqlsrv = conectarDB3();

    // --- PASO 2: Obtener datos del préstamo de MySQL (incluye tipo_usuario) ---
    $consultaPrestamo = "SELECT Libros_id, cantidad, Estudiantes_id, fecha_prestamo, fecha_devolucion, tipo_usuario 
                         FROM prestamos 
                         WHERE id = ?";
    $stmtPrestamo = $db_mysql->prepare($consultaPrestamo);
    $stmtPrestamo->bind_param('i', $prestamoId);
    $stmtPrestamo->execute();
    $resultadoPrestamo = $stmtPrestamo->get_result();

    if ($resultadoPrestamo->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'No se encontraron datos del préstamo.']);
        mysqli_close($db_mysql);
        sqlsrv_close($conn_sqlsrv);
        exit;
    }
    
    $prestamo = $resultadoPrestamo->fetch_assoc();
    $libroId = $prestamo['Libros_id'];
    $cantidadPrestada = $prestamo['cantidad'];
    $estudianteId = $prestamo['Estudiantes_id'];
    $tipoUsuario = $prestamo['tipo_usuario'] ?? 'alumno'; // Por defecto 'alumno'

    // --- PASO 3: Obtener los datos del usuario desde SQL Server según tipo ---
    if ($tipoUsuario === 'alumno') {
        // Consulta para ALUMNOS (CORREGIDO el typo: ApellidoMaterno)
        $query_sqlsrv = "SELECT Nombre AS Nom, 
                               ApellidoPaterno AS Paterno, 
                               ApellidoMaterno AS Materno, 
                               CorreoElectronico AS Email
                        FROM [GestionUsuarios].[dbo].[Alumnos] 
                        WHERE IdAlumno = ?";
    } else {
        // Consulta para DOCENTES
        $query_sqlsrv = "SELECT Nombre AS Nom, 
                               ApellidoPaterno AS Paterno, 
                               ApellidoMaterno AS Materno, 
                               CorreoElectronico AS Email
                        FROM [GestionUsuarios].[dbo].[Docentes] 
                        WHERE IdDocente = ?";
    }

    $params_sqlsrv = [$estudianteId];
    $res_sqlsrv = sqlsrv_query($conn_sqlsrv, $query_sqlsrv, $params_sqlsrv);

    if ($res_sqlsrv === false || !sqlsrv_has_rows($res_sqlsrv)) {
        echo json_encode([
            'success' => false, 
            'message' => "No se pudo encontrar al " . ucfirst($tipoUsuario) . " en la base de datos.",
            'debug' => [
                'tipo_usuario' => $tipoUsuario,
                'estudiante_id' => $estudianteId,
                'error_sqlsrv' => sqlsrv_errors()
            ]
        ]);
        mysqli_close($db_mysql);
        sqlsrv_close($conn_sqlsrv);
        exit;
    }
    
    $usuario_sqlsrv = sqlsrv_fetch_array($res_sqlsrv, SQLSRV_FETCH_ASSOC);
    $usuarioEmail = $usuario_sqlsrv['Email'];
    $usuarioNombre = ucwords(strtolower(trim(
        $usuario_sqlsrv['Nom'] . ' ' . 
        $usuario_sqlsrv['Paterno'] . ' ' . 
        ($usuario_sqlsrv['Materno'] ?? '')
    )));

    // --- PASO 4: Iniciar transacción y actualizar registros ---
    mysqli_begin_transaction($db_mysql);

    try {
        // Actualizar estado del préstamo
        $updatePrestamo = "UPDATE prestamos SET status = '2' WHERE id = ?";
        $stmtUpdatePrestamo = $db_mysql->prepare($updatePrestamo);
        $stmtUpdatePrestamo->bind_param('i', $prestamoId);
        $stmtUpdatePrestamo->execute();

        // Devolver libros al inventario
        $updateLibro = "UPDATE libros 
                        SET status = 'Activo', 
                            cantidad = cantidad + ? 
                        WHERE id = ?";
        $stmtUpdateLibro = $db_mysql->prepare($updateLibro);
        $stmtUpdateLibro->bind_param('ii', $cantidadPrestada, $libroId);
        $stmtUpdateLibro->execute();

        if ($stmtUpdatePrestamo->affected_rows > 0) {
            // Obtener detalles del libro para el correo
            $consultaLibro = "SELECT l.titulo, l.codigo, s.nombre_seccion 
                              FROM libros l 
                              JOIN secciones s ON l.seccionId = s.id 
                              WHERE l.id = ?";
            $stmtLibro = $db_mysql->prepare($consultaLibro);
            $stmtLibro->bind_param('i', $libroId);
            $stmtLibro->execute();
            $libro = $stmtLibro->get_result()->fetch_assoc();

            $fechaPrestamo = date("d-m-Y", strtotime($prestamo['fecha_prestamo']));
            $fechaDevolucion = date("d-m-Y", strtotime($prestamo['fecha_devolucion']));

            // Confirmar transacción antes de enviar el correo
            mysqli_commit($db_mysql);

            // Enviar correo de confirmación
            $envioCorreo = enviarCorreo(
                $usuarioEmail, 
                $usuarioNombre, 
                $libro['titulo'], 
                $libro['codigo'], 
                $libro['nombre_seccion'], 
                $cantidadPrestada, 
                $fechaPrestamo, 
                $fechaDevolucion, 
                $nombreAdministrador,
                $tipoUsuario // Nuevo parámetro
            );

            if ($envioCorreo === true) {
                echo json_encode([
                    'success' => true, 
                    'message' => 'El préstamo ha sido devuelto correctamente y se envió la confirmación por correo.',
                    'tipo_usuario' => ucfirst($tipoUsuario),
                    'usuario' => $usuarioNombre
                ]);
            } else {
                echo json_encode([
                    'success' => true, 
                    'message' => 'El préstamo fue devuelto correctamente, pero hubo un problema al enviar el correo.', 
                    'error' => $envioCorreo
                ]);
            }
        } else {
            throw new Exception('No se pudo actualizar el estado del préstamo.');
        }
    } catch (Exception $e) {
        // Revertir cambios en caso de error
        mysqli_rollback($db_mysql);
        echo json_encode([
            'success' => false, 
            'message' => 'Error al procesar la devolución: ' . $e->getMessage()
        ]);
    }

    // Cerrar conexiones
    mysqli_close($db_mysql);
    sqlsrv_close($conn_sqlsrv);
    exit;
}
?>