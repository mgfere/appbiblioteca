<?php
require_once '../database/Database.php';
require_once '../database/DatabaseAPI.php';
include 'header_registros.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['user'];
    $password = $_POST['password'];

    // Crear una instancia de la clase DatabaseAPI
    $databaseAPI = new DatabaseAPI();

    try {
        // Llamar al método obtenerPasswordHash de la API para obtener el hash de la contraseña
        $hashedPassword = $databaseAPI->obtenerPasswordHash($user);

        // Verificar la contraseña utilizando password_verify() en PHP
        if (password_verify($password, $hashedPassword)) {
            session_start();
            $_SESSION['user'] = $user;
            header("Location: ./index_admin.php");
            exit();
        } else {
            $message = 'Credenciales Inválidas. Intente de nuevo.';
        }
    } catch (PDOException $e) {
        die("Error en la base de datos: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Inicio de Sesión</title>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="stylesheet" href="../output.css">
    <link rel="icon" href="img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Agregar jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>


</head>

<body>
    <main class="flex-1">
        <h1 class="my-5 text-center text-2xl font-bold mg-20"><b>Inicio de Sesión de Administrador</b></h1>

        <?php if (!empty($message)) : ?>
            <h2 class="flex justify-center m-5 text-red-500"><?php echo $message; ?></h2>
        <?php endif; ?>

        <form method="POST" class="max-w-md mx-auto p-8 bg-[#E1DDDA] rounded-lg shadow-lg">
    <div>
        <label class="block text-gray-700 text-sm font-bold mb-2">Usuario</label>
        <input type="text" name="user" id="usuario" placeholder="Usuario" class="w-full px-3 py-2 placeholder-gray-300 border rounded-lg focus:shadow-outline focus:outline-none focus:ring-1 focus:ring-blue-600" required>
    </div>
    <br>
    
    <div>
        <label class="block text-gray-700 text-sm font-bold mb-2">Contraseña</label>
        <div class="relative">
            <input type="password" id="contrasena" name="password" placeholder="Contraseña"
                class="w-full px-3 py-2 pr-10 placeholder-gray-300 border rounded-lg focus:shadow-outline focus:outline-none focus:ring-1 focus:ring-blue-600" required>
            <button id="show_password" class="absolute inset-y-0 right-0 pr-3 flex items-center" type="button" onclick="mostrarPassword()">
                <span class="fa fa-eye-slash icon text-gray-600"></span>
            </button>
        </div>
    </div>
    <br><br>
    
    <div class="flex justify-center">
        <input class="w-full px-4 py-2 mt-2 text-white font-bold rounded-lg"
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        //Sleccionar los elementos
        const inputUsuario = document.querySelector('#usuario');
        const inputContra = document.querySelector('#contrasena');
        inputUsuario.addEventListener('blur', validar);
        inputContra.addEventListener('blur', validar)

        function validar(e) {

            if (e.target.value.trim() === '') {
                mostrarAlerta(`El campo ${e.target.id} es obligatorio`, e.target.parentElement);
                return;
            }
            limpiarAlerta(e.target.parentElement);
        }

        function mostrarAlerta(mensaje, referencia) {
            limpiarAlerta(referencia);
            const error = document.createElement('P');
            error.textContent = mensaje;
            error.classList.add('bg-red-600', 'text-white', 'p-2', 'text-center');
            referencia.appendChild(error);
        }

        function limpiarAlerta(referencia) {
            const alerta = referencia.querySelector('.bg-red-600');
            if (alerta) {
                alerta.remove();
            }
            console.log('desde limpiar alerta');
        }
    });


    function mostrarPassword() {
        var cambio = document.getElementById("contrasena");
        if (cambio.type == "password") {
            cambio.type = "text";
            $('.icon').removeClass('fa fa-eye-slash').addClass('fa fa-eye');
        } else {
            cambio.type = "password";
            $('.icon').removeClass('fa fa-eye').addClass('fa fa-eye-slash');
        }
    }
</script>

</html>