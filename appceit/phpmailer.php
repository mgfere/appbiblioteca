<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Dotenv\Dotenv;

require 'vendor/autoload.php';

// Cargar el archivo .env
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();


if (isset($_SESSION['usuario_nombre'])) {
  // Redirige al usuario a la secciónn deseada
  $id = $_SESSION['usuario_id'];
  header("Location: " . $_ENV['MIS_RESERVACIONES'] . "?id=$id");
  exit();
}

function enviarCorreoReserva($usuario, $libro, $cantidad, $libro_seccion_nombre)
{
  $mail = new PHPMailer(true);

  $id = $_SESSION['usuario_id'];

  try {
    // Configuración de la librería phpmailer
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

    // Destinatarios
$mail->addAddress($usuario['email'], $usuario['nombre']);
    // Contenido de email
    $mail->isHTML(true);
    $mail->Subject = '📆 Reserva de libro';

    // Obtener la extensión de la imagen si es dinámica
    $libro_imagen = $libro['imagen'] . ".jpg";

    // Verificar si la imagen existe
    $imagen_path = __DIR__ . '/imagenes/' . $libro_imagen;
    if (!file_exists($imagen_path)) {
      $libro_imagen = 'default.jpg';
    }

    // Crear el cuerpo del correo electrónico con estilos
    $body = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .container-email {
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
        .success-badge {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            text-align: center;
        }
        .content-box {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .info-box {
            background-color: #e8f5f1;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }
        .cta-button {
            display: inline-block;
            background-color: #09a787;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 10px 0;
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
    <div class="container-email">
        <div class="header">
            <img class="logo" src="' . $_ENV['LOGOUT_IMG_URL'] . '" alt="Universidad Tecnológica de Tamaulipas Norte">
            <img class="logo" src="' . $_ENV['LOGOCEIT_IMG_URL'] . '" alt="CEIT">
        </div>
        
        <div class="success-badge">
            <h2 style="margin: 0; color: #155724;">📚 ¡Tu libro está listo para recoger!</h2>
        </div>
        
        <h2 style="color: #09a787;">¡No te pierdas ni una página!</h2>
        <p>Hola, <strong>' . ucwords(strtolower($usuario['nombre'])) . '</strong></p>
        <p>¡Tenemos buenas noticias! El libro que reservaste, <strong>' . $libro['titulo'] . '</strong>, ya está disponible para ti en nuestra biblioteca.</p>
        
        <div class="content-box">
            <h3 style="color: #09a787; margin-top: 0;">📖 Detalles de tu reserva:</h3>
            <p><strong>Libro:</strong> ' . $libro['titulo'] . '</p>
            <p><strong>Código:</strong> ' . $libro['codigo'] . '</p>
            <p><strong>Disponibles:</strong> ' . $cantidad . '</p>
            <p><strong>Sección:</strong> ' . $libro_seccion_nombre . '</p>
        </div>
        
        <div class="info-box">
            <h3 style="color: #09a787; margin-top: 0;">📍 Para recoger tu libro:</h3>
            <p style="margin: 5px 0;"><strong>📅 Horario:</strong> Lunes a Viernes 07:00 AM a 10:00 PM</p>
            <p style="margin: 5px 0;"><strong>🏢 Ubicación:</strong> Centro de Información Tecnológica (CEIT)</p>
            <p style="margin: 5px 0;"><strong>📋 Trae contigo:</strong> Código del libro y documento de identidad</p>
        </div>
        
        <div class="warning-box">
            <p style="margin: 0 0 10px 0;"><strong>⚠️ ¡Atención! Importante:</strong></p>
            <ul style="margin: 0; padding-left: 20px;">
                <li>Tu reserva <strong>solo estará disponible para su retiro hoy mismo</strong> a partir de este correo</li>
                <li>Si no lo recoges hoy, tu reserva se <strong>cancelará automáticamente</strong></li>
                <li>Puedes cancelarla manualmente en cualquier momento</li>
            </ul>
            
        </div>
        
        <div style="background-color: #e8f5f1; padding: 15px; border-radius: 5px; margin: 20px 0; text-align: center;">
            <p style="margin: 0 0 10px 0; color: #333; font-weight: bold;">¿Quieres hacer otra reserva?</p>
            <a href="https://biblioteca.uttn.app/" class="cta-button">
                🌐 Ir a la Biblioteca Virtual
            </a>
        </div>
        
        <p style="color: #666; text-align: center; font-size: 13px;">
            Esperamos verte pronto en nuestra biblioteca
        </p>
        
        <div class="footer">
            <p style="margin: 5px 0;">© ' . date('Y') . ' | Universidad Tecnológica de Tamaulipas Norte</p>
            <p style="margin: 5px 0;">Sistema de Gestión Bibliotecaria</p>
        </div>
    </div>
</body>
</html>
';

    $mail->Body = $body;
    $mail->send();
    // echo 'El mensaje ha sido enviado';
  } catch (Exception $e) {
    echo "El mensaje no se pudo enviar. Mailer Error: {$mail->ErrorInfo}";
  }
}
