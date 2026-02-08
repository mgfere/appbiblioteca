<?php
session_start();

// Obtener el nombre del administrador de la sesión
$nombreAdministrador = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario';

require '../includes/config/database.php';
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Establecer cabecera para respuesta JSON
header('Content-Type: application/json');

// Función para enviar correo electrónico
function enviarCorreo($usuarioEmail, $usuarioNombre, $libroTitulo, $libroCodigo, $libroSeccion, $cantidadPrestada, $fechaPrestamo, $fechaDevolucion, $nombreAdministrador)
{
    $mail = new PHPMailer(true);
    try {
        // Configuración del servidor SMTP
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = $_ENV['SMTP_AUTH'] === 'true';
        $mail->Username = $_ENV['SMTP_USERNAME'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];
        $mail->Port = $_ENV['SMTP_PORT'];
        $mail->setFrom($_ENV['SMTP_USERNAME'], $_ENV['SMTP_USERADMIN']);

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // Configuración del correo electrónico
        $mail->addAddress($usuarioEmail, $usuarioNombre);
        $mail->isHTML(true);
        $mail->Subject = '📕 ¡De vuelta en casa!';
        $mail->Body = "
            <html>
            <head>
                <style>
                    body {
                        font-family: Arial, sans-serif;
                        font-size: 14px;
                    }
                    .container {
                        display: flex;
                        justify-content: space-between;
                        gap: 25px;
                    }
                    .logo {
                        width: 40%;
                        height: auto;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <img class='logo' src=" . $_ENV['LOGOUT_IMG_URL'] . " alt='Universidad Tecnológica de Tamaulipas Norte'>
                    <img class='logo' src=" . $_ENV['LOGOCEIT_IMG_URL'] . " alt='CEIT'>
                </div>
                <h2>Misión cumplida: $libroTitulo, devuelto sano y salvo</h2>
                <p>Hola, $usuarioNombre</p>
                <p>Tu préstamo del libro <b>$libroTitulo</b> ha sido devuelto correctamente.</p>
                <hr>
                <h3>Detalles del préstamo:</h3>
                <p><strong>Código:</strong> $libroCodigo</p>
                <p><strong>Sección:</strong> $libroSeccion</p>
                <p><strong>Disponibles:</strong> $cantidadPrestada</p>
                <p><strong>Fecha de préstamo:</strong> $fechaPrestamo</p>
                <p><strong>Fecha de devolución:</strong> $fechaDevolucion</p>
                <p><strong>Recibido por:</strong> $nombreAdministrador</p>
                <div style='background-color: #09a787; color: #fff; font-weight: bold; text-align: center;'>
                    <p> © " . date('Y') . " | Universidad Tecnológica de Tamaulipas Norte</p>
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
    // Obtener el ID del préstamo desde la solicitud POST
    if (!isset($_POST['prestamoId']) || empty($_POST['prestamoId'])) {
        echo json_encode(['success' => false, 'message' => 'El ID del préstamo es obligatorio.']);
        exit;
    }
    $prestamoId = $_POST['prestamoId'];

    // Conexión a la base de datos
    $db = conectarDB();

    // Consulta para obtener datos del préstamo
    $consultaPrestamo = "SELECT p.codigoLibro, p.cantidad, p.nombreCompleto, p.fechaPrestamo, p.fechaDevolucion, p.email, p.seccionId
                         FROM prestamospresencial p
                         WHERE p.id = ?";

    // Preparar y ejecutar la consulta
    if ($stmtPrestamo = $db->prepare($consultaPrestamo)) {
        $stmtPrestamo->bind_param('i', $prestamoId);
        $stmtPrestamo->execute();
        $resultadoPrestamo = $stmtPrestamo->get_result();

        if ($resultadoPrestamo->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'No se encontraron datos del préstamo']);
            exit;
        }

        // Obtener datos del préstamo
        $prestamo = $resultadoPrestamo->fetch_assoc();
        $libroCodigo = $prestamo['codigoLibro'];
        $cantidadPrestada = $prestamo['cantidad'];
        $usuarioEmail = $prestamo['email'];
        $usuarioNombre = $prestamo['nombreCompleto'];
        $usuarioNombre = ucwords(strtolower($prestamo['nombreCompleto']));
        $fechaPrestamo = date("d-m-Y", strtotime($prestamo['fechaPrestamo']));
        $fechaDevolucion = date("d-m-Y", strtotime($prestamo['fechaDevolucion']));
        $seccionId = $prestamo['seccionId'];

        // Verificar los valores obtenidos
        if (empty($libroCodigo) || empty($seccionId)) {
            echo json_encode(['success' => false, 'message' => 'El código del libro o la sección no se obtuvieron correctamente.']);
            exit;
        }

        // Actualizar el estado del préstamo a 'Devuelto' (estatus = 2)
        $updatePrestamo = "UPDATE prestamospresencial SET estatus = '2' WHERE id = ?";
        if ($stmtUpdatePrestamo = $db->prepare($updatePrestamo)) {
            $stmtUpdatePrestamo->bind_param('i', $prestamoId);
            $stmtUpdatePrestamo->execute();
        } else {
            echo json_encode(['success' => false, 'message' => 'Error en la preparación de la consulta de actualización del préstamo: ' . $db->error]);
            exit;
        }

        // Actualizar el estado del libro y la cantidad disponible
        $updateLibro = "UPDATE libros SET status = 'Activo', cantidad = cantidad + ? WHERE codigo = ? AND seccionId = ?";
        if ($stmtUpdateLibro = $db->prepare($updateLibro)) {
            $stmtUpdateLibro->bind_param('isi', $cantidadPrestada, $libroCodigo, $seccionId);
            $stmtUpdateLibro->execute();
        } else {
            echo json_encode(['success' => false, 'message' => 'Error en la preparación de la consulta de actualización del libro: ' . $db->error]);
            exit;
        }

        // Verificar si se ejecutaron correctamente las consultas
        if ($stmtUpdatePrestamo->affected_rows > 0 && $stmtUpdateLibro->affected_rows > 0) {
            // Obtener detalles adicionales del libro para el correo electrónico
            $consultaLibro = "SELECT libros.titulo, libros.codigo, secciones.nombre_seccion AS seccion_nombre 
                              FROM libros 
                              INNER JOIN secciones ON libros.seccionId = secciones.id 
                              WHERE libros.codigo = ? AND secciones.id = ?";
            if ($stmtLibro = $db->prepare($consultaLibro)) {
                $stmtLibro->bind_param('si', $libroCodigo, $seccionId);
                $stmtLibro->execute();
                $resultadoLibro = $stmtLibro->get_result();

                if ($resultadoLibro->num_rows > 0) {
                    $libro = $resultadoLibro->fetch_assoc();
                    $libroTitulo = $libro['titulo'];
                    $libroCodigo = $libro['codigo'];
                    $libroSeccion = $libro['seccion_nombre'];

                    // Envío del correo electrónico de confirmación
                    $envioCorreo = enviarCorreo($usuarioEmail, $usuarioNombre, $libroTitulo, $libroCodigo, $libroSeccion, $cantidadPrestada, $fechaPrestamo, $fechaDevolucion, $nombreAdministrador);

                    if ($envioCorreo === true) {
                        echo json_encode(['success' => true, 'message' => 'El préstamo ha sido devuelto correctamente. Se ha enviado un correo electrónico de confirmación.']);
                    } else {
                        echo json_encode(['success' => true, 'message' => 'El préstamo ha sido devuelto correctamente, pero hubo un error al enviar el correo electrónico.', 'error' => $envioCorreo]);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'No se encontraron datos del libro para enviar el correo electrónico.']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Error en la preparación de la consulta de detalles del libro: ' . $db->error]);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar el estado del préstamo o el libro.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Error en la preparación de la consulta del préstamo: ' . $db->error]);
    }

    // Cerrar la conexión a la base de datos
    $db->close();
    exit;
}
