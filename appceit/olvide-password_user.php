<?php
// Importar clases de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// La conexión a la DB ya carga las variables de entorno y el autoloader.
require './includes/config/database.php';
$db = conectarDB();
require './includes/funciones.php';

$errores = [];
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matricula = mysqli_real_escape_string($db, $_POST['matricula']);

    if (!$matricula) {
        $errores[] = "La matrícula es obligatoria.";
    }

    if (empty($errores)) {
        $query = "SELECT nombre, email FROM usuarios WHERE matricula = '$matricula'";
        $resultado = mysqli_query($db, $query);

        if (mysqli_num_rows($resultado) > 0) {
            $admin = mysqli_fetch_assoc($resultado);

            if (empty($admin['email'])) {
                $errores[] = "No tienes un correo electrónico registrado.";
            } else {
                $token = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyz"), 0, 8);
                $expiracion = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                $query_update = "UPDATE usuarios SET token = '$token', token_expira = '$expiracion' WHERE matricula = '$matricula'";
                mysqli_query($db, $query_update);

                $mail = new PHPMailer(true);
                try {
                    // Usamos las variables de entorno cargadas con $_ENV
                    $mail->isSMTP();
                    $mail->Host       = $_ENV['SMTP_HOST'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $_ENV['SMTP_USERNAME'];
                    $mail->Password   = $_ENV['SMTP_PASSWORD'];
                    $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
                    $mail->Port       = $_ENV['SMTP_PORT'];

                    $mail->setFrom($_ENV['SMTP_USERNAME'], $_ENV['SMTP_USERADMIN']);
                    $mail->addAddress($admin['email'], $admin['nombre']);

                    $mail->isHTML(true);
                    $mail->Subject = 'Tu codigo de recuperacion';
                    $mail->Body    = "
                        <html><body>
                            <h2>Hola " . htmlspecialchars($admin['nombre']) . ",</h2>
                            <p>Tu código para restablecer la contraseña es:</p>
                            <h3 style='font-size: 24px; letter-spacing: 2px; text-align:center;'>" . $token . "</h3>
                            <p>Este código expirará en 15 minutos.</p>
                        </body></html>";
                    $mail->AltBody = 'Tu codigo de recuperacion es: ' . $token;

                    $mail->send();
                    
                    header('Location: verificar-token_user.php');
                    exit;

                } catch (Exception $e) {
                    $errores[] = "El mensaje no pudo ser enviado. Error: {$mail->ErrorInfo}";
                }
            }
        } else {
            $errores[] = "La matrícula no existe o los datos son incorrectos.";
        }
    }
}

incluirTemplate('header-forms');
?>
<link rel="stylesheet" href="../public/css/bundle.css">
<div class="container formulario-container">
    <form class="formulario-estudiante" method="POST">
        <h1>Recuperar Contraseña</h1>
        <p style="text-align: center; margin-bottom: 1rem;">Ingresa tu matrícula para enviarte un código de recuperación.</p>
        <?php foreach ($errores as $error) : ?>
            <div class="alerta error"><?php echo $error; ?></div>
        <?php endforeach; ?>
        <div class="formulario-grupo">
            <label for="matricula">Matrícula</label>
            <input type="text" id="matricula" name="matricula" placeholder="Ingresa tu matrícula" />
        </div>
        <div class="formulario-grupo">
            <button type="submit" class="btn-submit">Enviar Código</button>
        </div>
        <a href="iniciar-sesion.php">Volver a inicio de sesión</a>
    </form>
</div>
<?php incluirTemplate('footer'); ?>