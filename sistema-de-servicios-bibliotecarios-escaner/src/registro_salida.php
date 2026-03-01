<?php
require '../database/Database.php';
require '../database/DatabaseAPI.php';
$dbAPI = new DatabaseAPI();
$message = '';
$search_result = [];

// Verificar si se está realizando una búsqueda por matrícula (método GET)

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search_matricula'])) {
    $search_matricula = $_GET['search_matricula'];
    if (!empty($search_matricula)) {
        try {
            $search_result = $dbAPI->obtenerRegistroPorMatricula($search_matricula);

            if (empty($search_result)) {
                $message = "No se encontraron registros con la matrícula ingresada.";
            } else {
                
                $active_records = array_filter($search_result, function($record) {
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


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro Salida</title>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="stylesheet" href="output.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body class="min-h-screen flex flex-col">
<main class="flex-1">
    <?php include 'header_registros.php'; ?>
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
            <img src="../img/buscar.png" alt="buscar" style="width: 40px; height: 40px;">
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

<button class="text-white">
            --------------
                </button>

                <button class="text-white">
            -------------
                </button>

                <button class="text-white">
            -------------
                </button>

                <button class="text-white">
            -------------
                </button>

                <button class="text-white">
            -------------
                </button>
                <button class="text-white">
            -------------
                </button>
                <button class="text-white">
            -------------
                </button>

    <?php
    include 'footer.php';
    ?>

    <script>
    function RegistrarSalida(registroId) {
        if (confirm('¿Está seguro que desea registrar la salida?')) {
            fetch('./logica_salida.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `marcarSalida=1&registroId=${registroId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Salida registrada correctamente');
                    window.location.href = "./index.php";
                } else {
                    alert('Error: ' + (data.error || 'No se pudo registrar la salida'));
                }
            })
            .catch(error => {
                alert('Error de conexión: ' + error.message);
            });
        }
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