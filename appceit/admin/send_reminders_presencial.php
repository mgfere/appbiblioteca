<?php
require '../includes/config/database.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();


$db = conectarDB();

// Consulta para obtener los datos de los préstamos que están activos y cuya sección coincida
$consultaPrestamos = "
    SELECT 
        prestamospresencial.*, 
        libros.codigo AS codigoLibro, 
        libros.seccionId, 
        secciones.nombre_seccion AS nombreSeccion, 
        libros.titulo AS tituloLibro
    FROM 
        prestamospresencial
    JOIN 
        libros ON prestamospresencial.codigoLibro = libros.codigo
    JOIN 
        secciones ON libros.seccionId = secciones.id
    WHERE 
        prestamospresencial.estatus = '1'
        AND prestamospresencial.seccionId = secciones.id
";
$resultadoPrestamos = mysqli_query($db, $consultaPrestamos);

// Configurar PHPMailer
function enviarCorreo($email, $nombre, $codigoLibro, $tituloLibro, $nombreSeccion, $fechaPrestamo, $fechaDevolucion, $nombreAdministrador)
{

    $nombre = ucwords(strtolower($nombre));

    $mail = new PHPMailer(true);
    try {
        // Configuración del servidor
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = $_ENV['SMTP_AUTH'] === 'true';
        $mail->Username = $_ENV['SMTP_USERNAME'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];
        $mail->Port = $_ENV['SMTP_PORT'];
        $mail->setFrom($_ENV['SMTP_USERNAME'], $_ENV['SMTP_USERADMIN']);
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

        // Activar caracteres UTF-8
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';

        // Remitente y destinatario
        $mail->addAddress($email, $nombre);
        $mail->isHTML(true);
        $mail->Subject = '📚 ¡No olvides devolver tu libro!';
        $mail->Body = "
        <html>
        <head>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    font-size: 14px;
                }
                .libro-imagen {
                    width: 100px;
                    height: 150px;
                    margin: 10px;
                    border: 1px solid #ddd;
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
            <h2>Recordatorio</h2>
            <p>Hola, $nombre</p>
            <p>Te enviamos este mensaje para recordarte que el plazo de devolución de tu libro '<strong>$tituloLibro</strong>' ha expirado. <br><br> A continuación, encontrarás los detalles del préstamo:</p>
            <hr>
            <h3>Detalles del préstamo:</h3>
            <p><strong>Título:</strong> $tituloLibro</p>
            <p><strong>Código:</strong> $codigoLibro</p>
            <p><strong>Sección:</strong> $nombreSeccion</p>
            <p><strong>Fecha de préstamo:</strong> $fechaPrestamo</p>
            <mark><strong>Fecha de devolución:</strong> $fechaDevolucion</mark>
            <p><strong>Entregado por:</strong> $nombreAdministrador</p>
            <p>Es importante que devuelvas el libro a tiempo para que otros usuarios puedan disfrutarlo también.</p>
            
            <p>Puedes solicitar una extensión de plazo visitando la biblioteca en persona o enviándonos un correo electrónico a  " . $_ENV['CORREO_CONTACTO'] . ".</p>
            
            <hr>
            
            <h3>Para entregar tu libro:</h3>
            <p><strong>Horario de atención:</strong> Lunes a Viernes 07:00 AM a 10:00 PM</p>
            <p><strong>Ubicación:</strong> Centro de Información Tecnológica (CEIT).</p>
            
            <div style='background-color: #09a787; color: #fff; font-weight: bold; text-align: center;'>
                <p>&copy; " . date('Y') . " | Universidad Tecnológica de Tamaulipas Norte</p>
            </div>
        </body>
        </html>
        ";

        if (!$mail->send()) {
            error_log("Error al enviar el correo: {$mail->ErrorInfo}");
        }
    } catch (Exception $e) {
        error_log("Error al enviar el correo: {$e->getMessage()}");
    }
}

// Obtener la fecha actual en formato Y-m-d para la comparación
$fechaActual = date('Y-m-d');

// Inicializar un array para evitar enviar múltiples correos para el mismo libro y sección
$enviados = array();

// Verificar y enviar correos electrónicos a los usuarios que deben devolver libros hoy o antes
while ($prestamo = mysqli_fetch_assoc($resultadoPrestamos)) {
    $codigoLibro = $prestamo['codigoLibro'];
    $nombreSeccion = $prestamo['nombreSeccion'];

    // Crear una clave única para el código y la sección
    $clave = $codigoLibro . '-' . $nombreSeccion;

    // Convertir la fecha de devolución a formato Y-m-d para la comparación
    $fechaDevolucion = date('Y-m-d', strtotime($prestamo['fechaDevolucion']));
    $fechaPrestamo = date('d/m/Y', strtotime($prestamo['fechaPrestamo']));
    $fechaDevolucionFormatted = date('d/m/Y', strtotime($prestamo['fechaDevolucion']));

    if ($fechaDevolucion <= $fechaActual && !isset($enviados[$clave])) {
        $nombreCompleto = ucwords(strtolower($prestamo['nombreCompleto']));
        enviarCorreo(
            $prestamo['email'],
            $nombreCompleto,
            $codigoLibro,
            $prestamo['tituloLibro'],
            $nombreSeccion,
            $fechaPrestamo,
            $fechaDevolucionFormatted,
            $prestamo['entregado']
        );
        // Marcar como enviado para el código y sección específicos
        $enviados[$clave] = true;
    }
}

// Cerrar la conexión a la base de datos
mysqli_close($db);
