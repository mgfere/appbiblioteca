<?php
require_once 'database/Database.php';
require_once 'database/DatabaseAPI.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['user'];
    $password = $_POST['password'];

    $databaseAPI = new DatabaseAPI();

    try {
        $hashedPassword = $databaseAPI->obtenerPasswordHash($user);

        if (!$hashedPassword) {
            $message = 'Usuario inexistente. Intente de nuevo.';
        } else {
            if (password_verify($password, $hashedPassword)) {

                $userData = $databaseAPI->verificarUsuarioExistente($user);

                if ($userData) {
                    session_start();
                    $_SESSION['user'] = $user;
                 
                    header("Location: ./index_admin.php");
                    exit();
                } else {
                    $message = 'Error interno al cargar datos del usuario.';
                }
            } else {
                $message = 'Credenciales Inválidas. Intente de nuevo.';
            }
        }
    } catch (PDOException $e) {
        die("Error en la base de datos: " . $e->getMessage());
    }
}

include 'header_registros.php';
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de Sesión</title>
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="output.css">
    <link rel="icon" href="img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</head>

<body>
    <main class="flex-1">
        <h1 class="my-10 text-center text-2xl font-bold"><b>Inicio de Sesión de Administrador</b></h1>


        <form method="POST" class="max-w-md mx-auto p-8 bg-[#E1DDDA] rounded-lg shadow-lg">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Usuario</label>
                <input type="text" name="user" id="usuario" placeholder="Usuario"
                    class="w-full px-3 py-2 placeholder-gray-300 border rounded-lg focus:shadow-outline focus:outline-none focus:ring-1 focus:ring-blue-600"
                    required>
                <div id="alerta-usuario" class="mt-2"></div>
            </div>

            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2">Contraseña</label>
                <div class="password-toggle-wrapper relative">
                    <input type="password" id="contrasena" name="password" placeholder="Contraseña"
                        class="password-input-with-toggle w-full px-3 py-2 placeholder-gray-300 border rounded-lg focus:shadow-outline focus:outline-none focus:ring-1 focus:ring-blue-600 pr-10"
                        required>
                    <button type="button" class="password-toggle-btn absolute right-3 top-1/2 transform -translate-y-1/2"
                        onclick="mostrarPassword()">
                        <span class="fa fa-eye-slash icon text-gray-600"></span>
                    </button>
                </div>
                <div id="alerta-contrasena" class="mt-2"></div>
            </div>

            <div class="flex justify-center">
                <input class="w-full px-4 py-2 mt-2 text-white font-bold rounded-lg cursor-pointer transition-colors duration-300"
                    style="background-color: #09a787;"
                    onmouseover="this.style.backgroundColor='#077669'"
                    onmouseout="this.style.backgroundColor='#09a787'"
                    type="submit" name="login" value="Iniciar Sesión">
            </div>
        </form>
    </main>
    <?php
    include './footer.php';
    ?>

</body>
<!--Si hay alertas las mostramos con un sweetalert-->
<?php if (isset($message) && !empty($message)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                position: 'top-end',
                icon: 'error',
                title: 'Error',
                text: '<?php echo $message; ?>',
                showConfirmButton: false,
                timer: 4000,
                timerProgressBar: true,
                toast: true,
                background: '#ffffffff',
                iconColor: '#dc3545'
            });
        });
    </script>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const inputUsuario = document.querySelector('#usuario');
        const inputContra = document.querySelector('#contrasena');
        const alertaUsuario = document.querySelector('#alerta-usuario');
        const alertaContrasena = document.querySelector('#alerta-contrasena');

        [inputUsuario, inputContra].forEach(input => {
            input.addEventListener('blur', validarCampo);
            input.addEventListener('input', limpiarAlertaInput);
        });

        function validarCampo(e) {
            const campo = e.target;
            const esContrasena = campo.id === 'contrasena';
            const contenedorAlerta = esContrasena ? alertaContrasena : alertaUsuario;
            limpiarAlerta(contenedorAlerta);
            if (campo.value.trim() === '') {
                const nombreCampo = esContrasena ? 'contraseña' : 'usuario';
                mostrarAlerta(`El campo ${nombreCampo} es obligatorio`, contenedorAlerta);
            }
        }

        function limpiarAlertaInput(e) {
            const campo = e.target;
            const esContrasena = campo.id === 'contrasena';
            const contenedorAlerta = esContrasena ? alertaContrasena : alertaUsuario;

            // Solo limpiar si el campo ya no está vacío
            if (campo.value.trim() !== '') {
                limpiarAlerta(contenedorAlerta);
            }
        }

        function mostrarAlerta(mensaje, contenedor) {
            const error = document.createElement('P');
            error.textContent = mensaje;
            error.classList.add('bg-red-600', 'text-white', 'p-2', 'text-center', 'rounded');
            contenedor.appendChild(error);
        }

        function limpiarAlerta(contenedor) {
            while (contenedor.firstChild) {
                contenedor.removeChild(contenedor.firstChild);
            }
        }


    });


    function mostrarPassword() {
        var cambio = document.getElementById("contrasena");
        var icon = document.querySelector('.icon');

        if (cambio.type == "password") {
            cambio.type = "text";
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        } else {
            cambio.type = "password";
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    }
</script>

</html>