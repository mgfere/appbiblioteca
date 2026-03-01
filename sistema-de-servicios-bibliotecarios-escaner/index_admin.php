<?php

session_start();

if (!isset($_SESSION['user'])) {
header("Location: https://login.uttn.app");    
exit();
}
$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;


require_once 'database/Database.php';
require_once 'database/DatabaseAPI.php';
$api = new DatabaseAPI();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio Administrador</title>
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="output.css">
        <link rel="icon" href="img/favicon.ico" type="image/x-icon">
</head>

<body>
<header>
         <div class="header-bar flex">
            <div class="flex-grow mt-3">
                <button class="text-[#09a787]">
                    ---------------
                </button>
            </div>

            <div class="flex-grow-0">
                <div>
                    <img src="img/Image.jpeg" alt="Logo" id="logo">
                </div>
            </div>

            <div class="flex-grow mt-3 flex justify-center">
                <a href="logout.php">
                    <button
                        style="width: 102px; height: 37px; background-color: #09a787; color: white; border-radius: 4px; display: flex; align-items: center; justify-content: center; transition: all 0.5s;"
                        onmouseout="this.style.backgroundColor='#09a787'">Salir
                        <img src="img/logout.png" alt="Logout" style="width: 45px; height: 45px;">
                    </button>
                </a>
            </div>
        </div>
    </header>

    
    <div class="flex items-center justify-center mt-8">
        <div class="container w-9/12 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 mb-10">

                        <div class="card p-8 mx-5">
                <a href="solicitud_servicio_admin.php" class="hover:bg-transparent">
                    <div class="rounded-xl overflow-hidden flex items-center justify-center">
                        <img src="img/PAT.png" alt="" width="430px" class="rounded-xl">
                    </div>
                    <h5 class="text-2xl mt-3 font-bold text-center">Solicitar Servicio</h5>
                    <p class="text-slate-500 text-sm mt-3 text-center">Elige un servicio de la biblioteca universitaria.</p>
                </a>
            </div>
            
            
                <div class="card p-8 mx-5">
                    <a href="registro_salida_admin.php" class="hover:bg-transparent">
                    <div class="rounded-xl overflow-hidden flex items-center justify-center">
                        <img src="img/entrevistainicial.png" alt="" width="430px" class="rounded-xl">
                    </div>
                    <h5 class="text-2xl mt-3 font-bold text-center">Registrar Salida</h5>
                    <p class="text-slate-500 text-sm mt-3 text-center">Registra tu salida al finalizar un servicio.</p>
                 </a>
                </div>
            <div class="card p-8 mx-5">
                <div class="rounded-xl overflow-hidden">
                    <a href="registro_admin.php" class="hover:bg-transparent"><img src="img/entrevista.png" alt=""></a>
                </div>
                <h5 class="text-2xl mt-3 font-bold text-center">Registro de Administradores</h5>
                <p class="text-slate-500 text-sm mt-3">Aquí podrás registrar nuevos administradores.
                </p>
            </div>
            <div class="card p-8 mx-5">
                <div class="p-5 flex flex-col">
                    <div class="rounded-xl overflow-hidden">
                        <a href="vista_admin.php" class="hover:bg-transparent"><img src="img/entrevistainicial.png" alt=""></a>
                    </div>
                    <h5 class="text-2xl mt-3 font-bold text-center">Registros de Salidas</h5>
                    <p class="text-slate-500 text-sm mt-3">Aquí podrás ver y descargar los los registros completados por los alumnos.
                    </p>
                </div>
            </div>
            <div class="card p-8 mx-5">
                <div class="p-5 flex flex-col">
                    <div class="rounded-xl overflow-hidden">
                        <a href="vista_lista_usuarios.php" class="hover:bg-transparent"><img src="img/listadoUsuarios..jpg" alt=""></a>
                    </div>
                    <h5 class="text-2xl mt-3 font-bold text-center">Lista de Usuarios</h5>
                    <p class="text-slate-500 text-sm mt-3">Visualización del listado de los usuarios (Alumnos y Profesores). Además de importar usuarios dentro del sistema.
                    </p>
                </div>
            </div>
            <div class="card p-8 mx-5">
                <div class="p-5 flex flex-col">
                    <div class="rounded-xl overflow-hidden">
                        <a href="generar_sso_biblioteca.php" class="hover:bg-transparent"><img src="img/biblioteca.png" alt=""></a>
                    </div>
                    <h5 class="text-2xl mt-3 font-bold text-center">Acceso a Biblioteca</h5>
                    <p class="text-slate-500 text-sm mt-3">Accede al sistema de servicios bibliotecarios mediante SSO (Single Sign-On) sin necesidad de iniciar sesión nuevamente.
                    </p>
                    </div>
            </div>
            
        </div>
    </div>
    <?php
    include 'footer.php';
    ?>
</body>

</html>