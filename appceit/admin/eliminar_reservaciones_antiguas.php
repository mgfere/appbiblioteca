<?php

require '../includes/config/database.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$db = conectarDB();

$queryOldReservations = "SELECT 
                         r.id AS reserva_id, 
                         r.Estudiantes_id, 
                         l.id AS Libros_id,  
                         l.titulo, 
                         l.autor, 
                         l.codigo,
                         l.cantidad, 
                         s.nombre_seccion AS seccion_nombre,  
                         u.email, 
                         u.nombre AS usuario_nombre
                       FROM 
                         reservaciones r
                       JOIN 
                         usuarios u ON r.Estudiantes_id = u.id
                       JOIN 
                         libros l ON r.Libros_id = l.id
                       JOIN 
                         secciones s ON l.seccionId = s.id
                       WHERE 
                         r.fecha_reservacion < CURRENT_DATE";

$resultadoOldReservations = mysqli_query($db, $queryOldReservations);

if ($resultadoOldReservations && mysqli_num_rows($resultadoOldReservations) > 0) {
    $reservas = mysqli_fetch_all($resultadoOldReservations, MYSQLI_ASSOC);

    foreach ($reservas as $reserva) {
        $reservaId = $reserva['reserva_id'];
        $usuarioId = $reserva['Estudiantes_id'];
        $libroId = $reserva['Libros_id'];
        $librotitulo = $reserva['titulo'];
        $libroautor = $reserva['autor'];
        $librocodigo = $reserva['codigo'];
        $librocantidad = $reserva['cantidad'];
        $libroseccion = $reserva['seccion_nombre'];
        $usuarioEmail = $reserva['email'];
        $usuarioNombre = $reserva['usuario_nombre'];
        $usuarioNombre = ucwords(strtolower($reserva['usuario_nombre']));

        $deleteOldReservations = "DELETE FROM reservaciones WHERE id = $reservaId";
        mysqli_query($db, $deleteOldReservations);

        $updateBookStatus = "UPDATE libros SET status = 'Activo' WHERE id = $libroId";
        mysqli_query($db, $updateBookStatus);

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
            $mail->Subject = '⌛ ¡Su reservación ha expirado!';
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
                            gap:25px;
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
                    <h2>¡Tu reserva del libro '{$librotitulo}' ha sido eliminada!</h2>
                    <p>Hola {$usuarioNombre},</p>
                    <p>Te informamos que tu reservación ha sido eliminada porque ha expirado y el libro vuelve a estar disponible.</p>
                    <hr>
                    <h3>Detalles de la cancelación:</h3>
                    <p><strong>Libro:</strong> {$librotitulo}</p>
                    <p><strong>Autor:</strong> {$libroautor}</p>
                    <p><strong>Disponible:</strong> {$librocantidad}</p>
                    <p><strong>Código:</strong> {$librocodigo}</p>
                    <p><strong>Sección:</strong> {$libroseccion}</p>
                    <p>Si tienes alguna pregunta o inquietud, no dudes en contactarnos.</p>
                    <div style='background-color: #09a787; color: #fff; font-weight: bold; text-align: center;'>
                        <p> © " . date('Y') . " | Universidad Tecnológica de Tamaulipas Norte</p>
                    </div>
                </body>
                </html>
            ";

            $mail->send();
        } catch (Exception $e) {
            echo "Error al enviar el correo: {$mail->ErrorInfo}";
        }
    }
}
