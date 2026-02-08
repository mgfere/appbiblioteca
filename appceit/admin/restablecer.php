<?php
require '../includes/config/database.php';
$db = conectarDB();
require '../includes/funciones.php';

$token_valido = false;
$errores = [];
$token = '';

if (isset($_GET['token'])) {
    $token = mysqli_real_escape_string($db, $_GET['token']);

    // Verificar que el token exista y no haya expirado
    $query = "SELECT * FROM administradores WHERE token = '$token' AND token_expira >= NOW()";
    $resultado = mysqli_query($db, $query);

    if (mysqli_num_rows($resultado) > 0) {
        $token_valido = true;
    } else {
        $errores[] = "Token no válido o ha expirado. Por favor, solicita un nuevo restablecimiento.";
    }
} else {
    header('Location: index.php'); // Redirige si no hay token
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $token_valido) {
    $password = mysqli_real_escape_string($db, $_POST['password']);
    $password_confirm = mysqli_real_escape_string($db, $_POST['password_confirm']);

    if (!$password || strlen($password) < 6) {
        $errores[] = "La contraseña es obligatoria y debe tener al menos 6 caracteres.";
    }

    if ($password !== $password_confirm) {
        $errores[] = "Las contraseñas no coinciden.";
    }

    if (empty($errores)) {
        // Hashear la nueva contraseña
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Actualizar la contraseña y limpiar el token
        $query_update = "UPDATE administradores SET password = '$password_hash', token = NULL, token_expira = NULL WHERE token = '$token'";
        $resultado_update = mysqli_query($db, $query_update);

        if ($resultado_update) {
            $mensaje = "Tu contraseña ha sido actualizada correctamente. Ya puedes iniciar sesión.";
            $token_valido = false; // Para ocultar el formulario después de actualizar
        } else {
            $errores[] = "Hubo un error al actualizar tu contraseña. Inténtalo de nuevo.";
        }
    }
}

incluirTemplate('header-forms');
?>
<link rel="stylesheet" href="../public/css/bundle.css">
<div class="container formulario-container">
    <form class="formulario-estudiante" method="POST">
        <h1>Crear Nueva Contraseña</h1>
        <?php foreach ($errores as $error) : ?>
            <div class="alerta error"><?php echo $error; ?></div>
        <?php endforeach; ?>

        <?php if (isset($mensaje)) : ?>
            <div class="alerta exito"><?php echo $mensaje; ?></div>
            <a href="index.php" class="btn-submit" style="text-align:center; display:block; text-decoration:none; margin-top: 1rem;">Iniciar Sesión</a>
        <?php endif; ?>

        <?php if ($token_valido) : ?>
            <div class="formulario-grupo">
                <label for="password">Nueva Contraseña</label>
                <input type="password" id="password" name="password" />
            </div>
            <div class="formulario-grupo">
                <label for="password_confirm">Confirmar Contraseña</label>
                <input type="password" id="password_confirm" name="password_confirm" />
            </div>
            <div class="formulario-grupo">
                <button type="submit" class="btn-submit">Guardar Contraseña</button>
            </div>
        <?php endif; ?>
    </form>
</div>

<?php
incluirTemplate('footer');
?>