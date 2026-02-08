<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio</title>
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="output.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="icon" href="img/favicon.ico" type="image/x-icon">

</head>
<!-- cambios -->
<body>
    <header>
        <div class="header-bar flex">
            <div class="flex-grow mt-3">
                <button class="text-[#09a787]">
                    --------
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
                        style="width: 56px; height: 37px; background-color: #09a787; color: white; border-radius: 4px; display: flex; align-items: center; justify-content: center; transition: all 0.5s;"
                        onmouseover="this.style.backgroundColor='#09a787'"
                        onmouseout="this.style.backgroundColor='#09a787'">
                        <img src="img/usuario_admin.png" alt="Usuario" style="width: 56px; height: 56px;">
                    </button>
                </a>
            </div>
        </div>
    </header>

    <main class="flex items-center justify-center min-h-screen">
        <div class="container mx-15 my-15 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-2 mb-10">
            
                <div class="card p-8 mx-5">
                <a href="solicitud_servicio.php" class="hover:bg-transparent">
                    <div class="rounded-xl overflow-hidden flex items-center justify-center">
                        <img src="img/PAT.png" alt="" width="430px" class="rounded-xl">
                    </div>
                    <h5 class="text-2xl mt-3 font-bold text-center">Solicitar Servicio</h5>
                    <p class="text-slate-500 text-sm mt-3 text-center">Elige un servicio de la biblioteca universitaria.</p>
                </a>
            </div>
            
            
                <div class="card p-8 mx-5">
                    <a href="registro_salida.php" class="hover:bg-transparent">
                    <div class="rounded-xl overflow-hidden flex items-center justify-center">
                        <img src="img/entrevistainicial.png" alt="" width="430px" class="rounded-xl">
                    </div>
                    <h5 class="text-2xl mt-3 font-bold text-center">Registrar Salida</h5>
                    <p class="text-slate-500 text-sm mt-3 text-center">Registra tu salida al finalizar un servicio.</p>
                 </a>
                </div>
        
        </div>
    </main>
    <?php
    include 'footer.php';
    ?>
</body>

</html> 