<?php

session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login_admin.php");
    exit();
}
$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;

require 'database/Database.php';
require 'database/DatabaseAPI.php';
$dbAPI = new DatabaseAPI();
$message = '';
$search_result = [];


if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search_matricula'])) {
    $search_matricula = $_GET['search_matricula'];
    if (!empty($search_matricula)) {
        try {
            $search_result = $dbAPI->obtenerRegistroPorMatricula($search_matricula);

            if (empty($search_result)) {
                $message = "No se encontraron registros con la matrícula ingresada.";
            } else {

                $active_records = array_filter($search_result, function ($record) {
                    return empty($record['hora_salida']) || $record['hora_salida'] == '0000-00-00 00:00:00';
                });

                if (empty($active_records)) {
                    $message = "No hay registros activos para esta matrícula.";
                    $search_result = [];
                } else {
                    $search_result = $active_records;
                }
            }
        } catch (PDOException $e) {
            $message = "Error al buscar el registro: " . $e->getMessage();
        }
    } else {
        $message = "Ingrese su matrícula completa para realizar la búsqueda.";
    }
}
?>

<style>
    /* Estilos personalizados para botones de SweetAlert */
    .swal-confirm-button {
        background-color: #3085d6 !important;
        color: white !important;
        border: none !important;
        padding: 10px 24px !important;
        border-radius: 5px !important;
        font-size: 16px !important;
        font-weight: bold !important;
        cursor: pointer !important;
        opacity: 1 !important;
        transition: background-color 0.3s ease !important;
    }

    .swal-confirm-button:hover {
        background-color: #2568ac !important;
        opacity: 1 !important;
    }

    .swal-cancel-button {
        background-color: #d33 !important;
        color: white !important;
        border: none !important;
        padding: 10px 24px !important;
        border-radius: 5px !important;
        font-size: 16px !important;
        font-weight: bold !important;
        cursor: pointer !important;
        opacity: 1 !important;
        margin-right: 10px !important;
        transition: background-color 0.3s ease !important;
    }

    .swal-cancel-button:hover {
        background-color: #b02a2a !important;
        opacity: 1 !important;
    }
</style>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro Salida</title>
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="output.css">
    <link rel="icon" href="img/favicon.ico" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>

</head>

<body class="min-h-screen flex flex-col">

    <main class="flex-1">
        
        <header>
            <div class="header-bar">
                <a href="index_admin.php"><img src="img/Image.jpeg" alt="Logo" id="logo"></a>
            </div>
        </header>

        <h1 class="my-5 text-center text-2xl font-bold"><b>Registro de Salida</b></h1>
        <div class="flex flex-col items-center justify-center mb-5">
            <div class="bg-gray-300 rounded-lg p-6 shadow-md w-full max-w-md">
                <form method="GET" class="flex flex-col sm:flex-row">
                    <label class="rounded #E1DDDA p-2"><b>Busca tu Matrícula:</b></label>
                    <input type="search" name="search_matricula" id="search-input" placeholder="Buscar" class="border-black rounded bg-gray-100 p-2 text-gray placeholder:text-gray mt-3 sm:mt-0 sm:ml-3" maxlength="10" required value="<?php echo isset($search_matricula) ? htmlspecialchars($search_matricula) : ''; ?>">
                    <button type="submit"
                        style="background-color: #09a787; color: white; font-weight: bold; padding: 8px 16px; margin-left: 4px; border-radius: 8px; border: none; cursor: pointer;"
                        onmouseover="this.style.backgroundColor='#077f6a'"
                        onmouseout="this.style.backgroundColor='#09a787'">
                        <img src="img/buscar.png" alt="buscar" style="width: 40px; height: 40px;">
                    </button>
                </form>
            </div>
        </div>

        <?php if (empty($search_result) && !empty($message)) : ?>
            <!-- Mostrar mensaje de error de búsqueda -->
            <div class="flex items-center justify-center mb-10">
                <h1 class="bg-red-600 text-white p-2 text-center"><?php echo $message; ?></h1>
            </div>
        <?php elseif (!empty($search_result)) : ?>
            <!-- Mostrar tabla de resultados de búsqueda solo si se encontraron registros -->
            <div class="flex items-center justify-center mb-10">
                <div class="overflow-x-auto w-full max-w-4xl">
                    <table class="w-full border-collapse">
                        <thead class="bg-gray-800">
                            <tr>
                                <th class="p-3 text-sm font-semibold tracking-wide text-center text-white">Matrícula</th>
                                <th class="p-3 text-sm font-semibold tracking-wide text-center text-white">Nombre</th>
                                <th class="p-3 text-sm font-semibold tracking-wide text-center text-white">Tipo</th>
                                <th class="p-3 text-sm font-semibold tracking-wide text-center text-white">Servicio</th>
                                <th class="p-3 text-sm font-semibold tracking-wide text-center text-white">Hora Entrada</th>
                                <th class="p-3 text-sm font-semibold tracking-wide text-center text-white">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($search_result as $row) : ?>
                                <tr class="bg-white border-b hover:bg-gray-50">
                                    <td class="text-center p-3 text-sm text-gray-700"><?php echo htmlspecialchars($row['matricula']); ?></td>
                                    <td class="text-center p-3 text-sm text-gray-700"><?php echo htmlspecialchars($row['nameUser']); ?></td>
                                    <td class="text-center p-3 text-sm text-gray-700"><?php echo htmlspecialchars($row['userType']); ?></td>
                                    <td class="text-center p-3 text-sm text-gray-700">
                                        <?php echo htmlspecialchars($dbAPI->obtenerNombreServicio($row['id_servicio'])); ?>
                                    </td>
                                    <td class="text-center p-3 text-sm text-gray-700">
                                        <?php echo date('d/m/Y H:i', strtotime($row['hora_entrada'])); ?>
                                    </td>
                                    <td class="text-center p-3 text-sm text-gray-700">
                                        <?php if (empty($row['hora_salida']) || $row['hora_salida'] == '0000-00-00 00:00:00') : ?>
                                            <button type="button"
                                                class=" text-white font-bold py-2 px-4 rounded transition-colors" style="background-color: #09a787;"
                                                onclick="RegistrarSalida(<?php echo $row['id_registro']; ?>)">
                                                Registrar Salida
                                            </button>
                                        <?php else : ?>
                                            <span class="text-gray-500">Salida registrada</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </main>
    <!-- cambios -->


    <?php
    include 'footer.php';
    ?>
    <script>
        // Verificar si debemos redirigir a index después de recargar
        document.addEventListener('DOMContentLoaded', function() {
            if (localStorage.getItem('redirectToIndex') === 'true') {
                // Limpiar la bandera
                localStorage.removeItem('redirectToIndex');

                // Redirigir a index después de un pequeño delay
                setTimeout(() => {
                    window.location.href = 'index_admin.php';
                }, 1000);
            }
        });
    </script>
    <script>
        //funcion para eliminar registro usando swweetalert
        function RegistrarSalida(registroId) {
            Swal.fire({
                title: '¿Está seguro que desea registrar la salida?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, registrar salida',
                cancelButtonText: 'Cancelar',
                customClass: {
                    confirmButton: 'swal-confirm-button',
                    cancelButton: 'swal-cancel-button'
                },
                buttonsStyling: false,
                showClass: {
                    popup: 'animate__animated animate__fadeInDown'
                },
                hideClass: {
                    popup: 'animate__animated animate__fadeOutUp'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        type: 'POST',
                        url: 'logica_salida.php',
                        data: {
                            marcarSalida: true,
                            registroId: registroId
                        },
                        dataType: 'json',
                        success: function(data) {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Salida registrada',
                                    text: 'La salida ha sido registrada correctamente',
                                    icon: 'success',
                                    timer: 3000,
                                    confirmButtonColor: '#3085d6',
                                    customClass: {
                                        confirmButton: 'swal-confirm-button'
                                    },
                                    buttonsStyling: false
                                }).then(() => {
                                    location.reload();
                                    localStorage.setItem('redirectToIndex', 'true');
                                    location.reload();
                                })
                            } else {
                                Swal.fire({
                                    title: 'Error al registrar la salida',
                                    text: data.error || 'Error desconocido',
                                    icon: 'error',
                                    confirmButtonColor: '#d33',
                                    customClass: {
                                        confirmButton: 'swal-confirm-button'
                                    },
                                    buttonsStyling: false
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                title: 'Error de conexión',
                                text: 'No se pudo conectar con el servidor',
                                icon: 'error',
                                confirmButtonColor: '#d33',
                                customClass: {
                                    confirmButton: 'swal-confirm-button'
                                },
                                buttonsStyling: false
                            });
                        }
                    });
                }
            });
        }



        var inputMatricula = document.querySelector('#search-input');
        inputMatricula.addEventListener('input', borrarMatricula);

        function borrarMatricula() {
            // Verificar si el contenido está vacío
            if (inputMatricula.value === '') {
                // Envía el formulario de búsqueda
                document.querySelector('form').submit();
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            //Sleccionar los elementos
            const searchInput = document.querySelector('#search-input');
            searchInput.addEventListener('blur', validar);

            function mostrarAlerta(mensaje, referencia) {
                limpiarAlerta(referencia);
                const error = document.createElement('P');
                error.textContent = mensaje;
                error.classList.add('bg-red-600', 'text-white', 'p-2', 'text-center', 'ml-2');
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

        function validar(e) {
            if (e.target.value.trim() === '') {
                mostrarAlerta(`El campo ${e.target.id} es obligatorio`, e.target.parentElement);
                return;
            }
            limpiarAlerta(e.target.parentElement);

        }

        function mostrarMensajeError(mensaje) {
            const errorMessageContainer = document.getElementById('error-message');
            errorMessageContainer.innerHTML = `<h1 class="text-red-500">${mensaje}</h1>`;

            // Ocultar la tabla de resultados si está visible
            const tableContainer = document.querySelector('.table-container');
            if (tableContainer) {
                tableContainer.style.display = 'none';
            }
        }
    </script>
</body>

</html>