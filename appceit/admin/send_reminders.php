<?php
require '../includes/config/database.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();


$db = conectarDB();

// Consulta para contar el número total de préstamos activos
$countQuerPrestamos = "SELECT COUNT(*) AS total_prestamos FROM prestamos WHERE status = '1'";
$resultadoCountPrestamos = mysqli_query($db, $countQuerPrestamos);
$totalPrestamos = mysqli_fetch_assoc($resultadoCountPrestamos)['total_prestamos'];

// Consulta para obtener los datos de los préstamos
$consultaPrestamos = "SELECT prestamos.*, libros.codigo AS codigoLibro, libros.seccionId, secciones.nombre_seccion AS nombreSeccion, libros.titulo as tituloLibro, usuarios.nombre AS nombreUsuario, usuarios.email AS emailUsuario
                      FROM prestamos 
                      JOIN libros ON prestamos.Libros_id = libros.id
                      JOIN secciones ON libros.seccionId = secciones.id
                      JOIN usuarios ON prestamos.Estudiantes_id = usuarios.id
                      WHERE prestamos.status = '1'";

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
          <meta charset='UTF-8'>
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
          <p>Puedes solicitar una extensión de plazo visitando la biblioteca en persona o enviándonos un correo electrónico a " . $_ENV['CORREO_CONTACTO'] . ".</p>
          <hr>
          <h3>Para entregar tu libro:</h3>
          <p><strong>Horario de atención: </strong>Lunes a Viernes 07:00 AM a 10:00 PM</p>
          <p><strong>Ubicación: </strong>Centro de Información Tecnológica (CEIT).</p>
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

// Obtener la fecha actual en formato Y-m-d para la comparaci��n
$fechaActual = date('Y-m-d');

// Verificar y enviar correos electr��nicos a los usuarios que deben devolver libros hoy o antes
while ($prestamo = mysqli_fetch_assoc($resultadoPrestamos)) {
  // Convertir la fecha de devoluci��n a formato Y-m-d para la comparaci��n
  $fechaDevolucion = date('Y-m-d', strtotime($prestamo['fecha_devolucion']));
  $fechaPrestamo = date('d/m/Y', strtotime($prestamo['fecha_prestamo']));
  $fechaDevolucionFormateada = date('d/m/Y', strtotime($prestamo['fecha_devolucion']));

  // Enviar correo solo si la fecha de devoluci��n es hoy o antes
  if ($fechaDevolucion <= $fechaActual) {
    enviarCorreo($prestamo['emailUsuario'], ucwords(strtolower($prestamo['nombreUsuario'])), $prestamo['codigoLibro'], $prestamo['tituloLibro'], $prestamo['nombreSeccion'], $fechaPrestamo, $fechaDevolucionFormateada, $prestamo['entregado']);
  }
}

// Reiniciar el puntero del resultado de la consulta
mysqli_data_seek($resultadoPrestamos, 0);
