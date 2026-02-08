<?php
include 'header.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio</title>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="stylesheet" href="output.css">
</head>

<body>
    <div class="flex items-center justify-center">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 mb-10">
            <div class="card p-8 mx-5">
                <div class="rounded-xl overflow-hidden">
                    <a href="registro_alumno.php" class="hover:bg-transparent"><img src="../img/entrevista.png" alt=""></a>
                </div>
                <h5 class="text-2xl mt-3 font-bold text-center">Registro de Alumno</h5>
                <p class="text-slate-500 text-lg mt-3">Para solicitar un Servicio, primero debes de registrarte como alumno. Este registro de alumno solo se hace una vez.
                </p>
            </div>
            <div class="card p-8 mx-5">
                <div class="rounded-xl overflow-hidden">
                    <a href="registro_salida.php" class="hover:bg-transparent"><img src="../img/PAT.png" alt=""></a>
                </div>
                <h5 class="text-2xl mt-3 font-bold text-center">Solicitar Servicio</h5>
                <p class="text-slate-500 text-lg mt-3"> Puedes solicitar un servicio una vez ya hayas hecho tu registro de alumno.
                </p>
            </div>
            <div class="card p-8 mx-5">
                <div class="p-5 flex flex-col">
                    <div class="rounded-xl overflow-hidden">
                        <a href="registro_alumno.php" class="hover:bg-transparent"><img src="../img/entrevistainicial.png" alt=""></a>
                    </div>
                    <h5 class="text-2xl mt-3 font-bold text-center">Registrar Salida</h5>
                    <p class="text-slate-500 text-lg mt-3">Cuando termines de utilizar un servicio debes de registrar tu salida.
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