<?php
// Mantén todo tu código PHP de la parte superior sin cambios
session_start();
require './includes/config/database.php';
$db = conectarDB();
require './includes/funciones.php';

$errores = [];
$mensaje = '';
$mostrar_form_password = false;
$token = '';

// Lógica para verificar el token (asumo que está aquí arriba)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['token'])) {
    $token = mysqli_real_escape_string($db, $_POST['token']);
    if (!$token) {
        $errores[] = "Debes ingresar un token.";
    } else {
        $query = "SELECT * FROM usuarios WHERE token = '$token' AND token_expira >= NOW()";
        $resultado = mysqli_query($db, $query);

        if (mysqli_num_rows($resultado) > 0) {
            $mostrar_form_password = true;
            $_SESSION['token_valido'] = $token;
        } else {
            $errores[] = "Token no válido o ha expirado.";
        }
    }
}

// Lógica para actualizar la contraseña (asumo que está aquí)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if (!isset($_SESSION['token_valido'])) {
        header('Location: olvide-password_user.php');
        exit;
    }
    
    $token = $_SESSION['token_valido'];
    $password = mysqli_real_escape_string($db, $_POST['password']);
    $password_confirm = mysqli_real_escape_string($db, $_POST['password_confirm']);

    if (!$password || strlen($password) < 6) {
        $errores[] = "La contraseña debe tener al menos 6 caracteres.";
    } elseif ($password !== $password_confirm) {
        $errores[] = "Las contraseñas no coinciden.";
    }

    if (empty($errores)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $query_update = "UPDATE usuarios SET password = '$password_hash', token = NULL, token_expira = NULL WHERE token = '$token'";
        $resultado_update = mysqli_query($db, $query_update);

        if ($resultado_update) {
            $mensaje = "Tu contraseña ha sido actualizada correctamente.";
            unset($_SESSION['token_valido']);
        } else {
            $errores[] = "Hubo un error al actualizar la contraseña.";
            $mostrar_form_password = true;
        }
    } else {
        $mostrar_form_password = true;
    }
}

incluirTemplate('header-forms');
?>
<link rel="stylesheet" href="../public/css/bundle.css">
<style>
    .formulario-container .formulario-grupo .toggle-password{
        top:auto;
    }
    .password-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }
    .password-wrapper input {
        width: 100%;
        padding-right: 80px; /* Espacio para el botón */
    }
    .toggle-password {
        position: absolute;
        right: 10px;
        background: #f0f0f0;
        border: 1px solid #ccc;
        border-radius: 4px;
        padding: 5px 10px;
        cursor: pointer;
    }
</style>

<div class="container formulario-container">
    <?php if ($mensaje): ?>
        <div class="alerta exito" style="text-align: center;"><?php echo $mensaje; ?></div>
        <a href="index.php" class="btn-submit" style="text-align:center; display:block; text-decoration:none; margin-top: 1rem;">Iniciar Sesión</a>
    <?php else: ?>
        <form class="formulario-estudiante" method="POST">
            <?php if ($mostrar_form_password): ?>
                <h1>Crear Nueva Contraseña</h1>
                <?php foreach ($errores as $error): ?><div class="alerta error"><?php echo $error; ?></div><?php endforeach; ?>
                
                <div class="formulario-grupo">
                    <label for="password">Nueva Contraseña</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" />
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility('password')">Mostrar</button>
                    </div>
                </div>

                <div class="formulario-grupo">
                    <label for="password_confirm">Confirmar Contraseña</label>
                    <div class="password-wrapper">
                        <input type="password" id="password_confirm" name="password_confirm" />
                        <button type="button" class="toggle-password" onclick="togglePasswordVisibility('password_confirm')">Mostrar</button>
                    </div>
                </div>

                <div class="formulario-grupo">
                    <button type="submit" class="btn-submit">Guardar Contraseña</button>
                </div>
            <?php else: ?>
                 <h1>Verificar Código</h1>
                <p style="text-align: center; margin-bottom: 1rem;">Revisa tu correo e ingresa el código que te enviamos.</p>
                <?php foreach ($errores as $error): ?><div class="alerta error"><?php echo $error; ?></div><?php endforeach; ?>
                <div class="formulario-grupo">
                    <label for="token">Código de Verificación</label>
                    <input type="text" id="token" name="token" />
                </div>
                <div class="formulario-grupo">
                    <button type="submit" class="btn-submit">Verificar</button>
                </div>
                <a href="olvide-password.php">¿No recibiste el código?</a>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<script>
    function togglePasswordVisibility(inputId) {
        const input = document.getElementById(inputId);
        const button = input.nextElementSibling; // El botón es el siguiente elemento

        if (input.type === 'password') {
            input.type = 'text';
            button.textContent = 'Ocultar';
        } else {
            input.type = 'password';
            button.textContent = 'Mostrar';
        }
    }
</script>

<?php
incluirTemplate('footer');
?>