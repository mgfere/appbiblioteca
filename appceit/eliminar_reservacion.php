<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Dotenv\Dotenv;

require 'vendor/autoload.php';

// Cargar el archivo .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();


require 'includes/funciones.php';
$auth = usuarioAutenticado();

if (!$auth) {
    header('Location: principal.php');
    exit;
}

// Obtener el nombre del usuario de la sesión
$nombreUsuario = isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : 'Usuario';
// Convertir el nombre a minúsculas y luego aplicar ucfirst() a la primera letra
$nombreUsuario = ucwords(strtolower($nombreUsuario));

$idusuario = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 0;
$emailUsuario = isset($_SESSION['usuario_correo']) ? $_SESSION['usuario_correo'] : '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_reservacion = $_POST['id_reservacion'];

    //* Importar la conexión 
    require 'includes/config/database.php';
    $db = conectarDB();

    // Paso 1: Obtener el Libros_id de la reservación
    $sqlSelect = "SELECT Libros_id FROM reservaciones WHERE id = ? AND Estudiantes_id = ?";
    $stmtSelect = $db->prepare($sqlSelect);
    $stmtSelect->bind_param("ii", $id_reservacion, $idusuario);
    $stmtSelect->execute();
    $resultSelect = $stmtSelect->get_result();

    if ($resultSelect->num_rows > 0) {
        $row = $resultSelect->fetch_assoc();
        $libros_id = $row['Libros_id'];

        // Obtener detalles del libro para el correo electrónico
        $sqlLibro = "SELECT l.titulo, l.codigo, l.autor, s.nombre_seccion AS seccion_nombre 
             FROM libros l 
             INNER JOIN secciones s ON l.seccionId = s.id 
             WHERE l.id =?";
        $stmtLibro = $db->prepare($sqlLibro);
        $stmtLibro->bind_param("i", $libros_id);
        $stmtLibro->execute();
        $resultLibro = $stmtLibro->get_result();
        $libro = $resultLibro->fetch_assoc();

        // Paso 2: Actualizar el estado del libro a "Activo"
        $sqlUpdate = "UPDATE libros SET status = 'Activo' WHERE id = ?";
        $stmtUpdate = $db->prepare($sqlUpdate);
        $stmtUpdate->bind_param("i", $libros_id);

        // Consulta para eliminar la reservación
        $sqlDelete = "DELETE FROM reservaciones WHERE id = ? AND Estudiantes_id = ?";
        $stmtDelete = $db->prepare($sqlDelete);
        $stmtDelete->bind_param("ii", $id_reservacion, $idusuario);

        if ($stmtUpdate->execute() && $stmtDelete->execute()) {
            $mail = new PHPMailer;

            $mail->isSMTP();
            $mail->Host = $_ENV['SMTP_HOST'];
            $mail->SMTPAuth = $_ENV['SMTP_AUTH'] === 'true';
            $mail->Username = $_ENV['SMTP_USERNAME'];
            $mail->Password = $_ENV['SMTP_PASSWORD'];
            $mail->Port = $_ENV['SMTP_PORT'];
            $mail->setFrom($_ENV['SMTP_USERNAME'], $_ENV['SMTP_USERADMIN']);

            // Activar caracteres UTF-8
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            $mail->addAddress($emailUsuario, $nombreUsuario);

            $mail->isHTML(true);
            $mail->Subject = '📢 ¡Cambio de planes!';
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

                <h2>Reservación Cancelada</h2>
                <p>Hola, {$nombreUsuario}</p>

                <p>Te informamos que has cancelado tu reservación correctamente y el libro está ahora disponible</p>
                <hr>
                <h3>Detalles de la cancelación</h3>
                <p><strong>Libro: </strong>{$libro['titulo']}</p>
                <p><strong>Código: </strong>{$libro['codigo']}</p>
                <p><strong>Sección: </strong>{$libro['seccion_nombre']}</p>
                <p><strong>Autor: </strong>{$libro['autor']}</p>

                <p>Si tienes alguna pregunta o inquietud, no dudes en contactarnos. </p>

                <div style='background-color: #09a787; color: #fff; font-weight: bold; text-align: center;'>
                <p> © " . date('Y') . " | Universidad Tecnológica de Tamaulipas Norte</p>
            </div>
            </body>
            </html>
            ";

            if ($mail->send()) {
                // echo "Reservación eliminada con éxito, libro actualizado a estado activo y notificación enviada por correo electrónico.";
            } else {
                echo "Reservación eliminada con éxito y libro actualizado a estado activo, pero falló el envío del correo electrónico.";
            }

            // Redireccionar de vuelta a la página de reservaciones
            header("Location: index.php");
            exit;
        } else {
            echo "Error al eliminar la reservación o actualizar libro: " . $stmtDelete->error . " - " . $stmtUpdate->error;
        }

        $stmtUpdate->close();
        $stmtDelete->close();
    } else {
        echo "No se encontró la reservación.";
    }

    $stmtSelect->close();
    $db->close();
} else {
    echo "Método no permitido.";
}
