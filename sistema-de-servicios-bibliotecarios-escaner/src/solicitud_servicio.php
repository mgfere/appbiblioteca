<?php
require_once '../database/Database.php';
require_once '../database/DatabaseAPI.php';
$dbAPI = new DatabaseAPI();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 
    $matricula = $_POST['matricula'];
    $nombre = $_POST['nombre'];
    $id_carrera = $_POST['id_carrera'];
    $id_especialidad = $_POST['id_especialidad'];
    $userType = $_POST['userType'];
    $id_servicio = $_POST['id_servicio'];
    $horaEntrada = date('Y-m-d H:i:s');

    $usuarioExistente = $dbAPI->usuarioExistenteRevisar($matricula);
    
    if (!$usuarioExistente) {
        
        $insertResult = $dbAPI->insertingTeachersAndStudents($matricula, $nombre, $id_servicio, $userType, $id_especialidad, $id_carrera);
        
        if ($insertResult === true) {
            $message = 'Se ha registrado la solicitud exitosamente';
            $url = "./index.php";
            $tiempoespera = 1;
            header("refresh: $tiempoespera; url=$url");
            exit();
        } else {
            $message = $insertResult;
        }
    } else {
        $registroResult = $dbAPI->insertarRegistroSolicitud($matricula, $id_servicio);
        
        if ($registroResult === true) {
            $message = 'Se ha registrado la solicitud exitosamente';
            $url = "./index.php";
            $tiempoespera = 1;
            header("refresh: $tiempoespera; url=$url");
            exit();
        } else {
            $message = $registroResult;
        }
        }
}

try {
    $carreras = $dbAPI->obtenerCarreras();
} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}
try {
    $especialidades = $dbAPI->obtenerEspecialidades();
} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}
try {
    $servicios = $dbAPI->obtenerServicios();
} catch (PDOException $e) {
    die("Error en la consulta: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Servicio</title>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="stylesheet" href="output.css">
    <script src="script.js"></script>
</head>

<body>
    <main>
        <?php include 'header_registros.php'; ?>
        <h1 class="my-5 text-center text-2xl font-bold"><b>Solicitud de Servicio</b></h1>

        <?php if (!empty($message)) : ?>
            <p class="text-center text-red-500 font-bold mb-3"><?php echo $message; ?></p>
        <?php endif; ?>

        <form method="POST" id="formulario" class="max-w-md mx-auto p-8 bg-[#E1DDDA] rounded-lg shadow-lg">
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="matricula">Matrícula/Número de Empleado:</label>

                <input type="text" name="matricula" id="matrículaAlumno" placeholder="Matrícula o Número de Empleado" required maxlength="10" class="w-full px-3 py-2 placeholder-gray-300 border rounded-lg focus:shadow-outline focus:outline-none focus:ring-1 focus:ring-blue-600" autocomplete="off">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="nombre">Nombre Completo:</label>
                <input type="text" name="nombre" id="nombre" placeholder="Nombre" required class="w-full px-3 py-2 placeholder-gray-300 border rounded-lg focus:shadow-outline focus:outline-none focus:ring-1 focus:ring-blue-600" autocomplete="off">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Tipo:</label>
                <span id="userTypeLabel" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg focus:shadow-outline focus:outline-none focus:ring-1 focus:ring-blue-600 inline-block">
                </span>
                <input type="hidden" name="userType" id="userType" required>
            </div>
            <div class="mb-4" id="carreraAlum">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="carrera">Carrera:</label>
                <select name="id_carrera" required id="id_carrera" class="w-full px-3 py-2 border rounded-lg focus:shadow-outline focus:outline-none focus:ring-1 focus:ring-blue-600">
                    <option value="" disabled selected>Seleccione una carrera</option>
                    <?php foreach ($carreras as $carrera) : ?>
                        <option value="<?php echo $carrera['id_carrera']; ?>"><?php echo $carrera['nombre_carrera']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4 inputHide" id="especialidadAlum">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="especialidad">Especialidad:</label>
                <select name="id_especialidad" required id="id_especialidad" class="w-full px-3 py-2 border rounded-lg focus:shadow-outline focus:outline-none focus:ring-1 focus:ring-blue-600">
                    <option value="" disabled selected>Seleccione una especialidad</option>
                    <?php foreach ($especialidades as $especialidad) : ?>
                        <option value="<?php echo $especialidad['id_especialidad']; ?>"><?php echo $especialidad['nombre_especialidad']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="id_servicio">Servicio:</label>
                <select name="id_servicio" id="servicio" required class="w-full px-3 py-2 border rounded-lg focus:shadow-outline focus:outline-none focus:ring-1 focus:ring-blue-600">
                    <option value="" disabled selected>Seleccione un servicio</option>
                    <?php foreach ($servicios as $servicio) : ?>
                        <option value="<?php echo $servicio['id_servicio']; ?>"><?php echo $servicio['nombre_servicio']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex justify-center">
                <input type="submit" value="Guardar" class="w-full px-4 py-2 mt-4 text-white font-bold rounded-lg"
                    style="background-color: #09a787;"
                    onmouseover="this.style.backgroundColor='#077669'"
                    onmouseout="this.style.backgroundColor='#09a787'">
            </div>
        </form>
        <main>
            <br><br><br>
            <?php include 'footer.php'; ?>
</body>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- document.addEventListener('DOMContentLoaded', function() {
        event.preventDefault();
        //Sleccionar los elementos
        const inputMatricula = document.querySelector('#matrículaAlumno');
        const inputNombre = document.querySelector('#nombre');
        const inputCarrera = document.querySelector('#carrera');
        const inputEspecialidad = document.querySelector('#especialidad');
        const inputServicio = document.querySelector('#servicio');
        inputMatricula.addEventListener('blur', validar);
        inputNombre.addEventListener('blur', validar);
        inputCarrera.addEventListener('blur', validar);
        inputEspecialidad.addEventListener('blur', validar);
        inputServicio.addEventListener('blur', validar);


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

    $(document).ready(function() {
        $('#carrera').change(function() {
            var carreraId = $(this).val();
            $.ajax({
                url: './getEspecialidades.php',
                method: 'POST',
                data: {
                    carreraId: carreraId
                },
                success: function(data) {
                    $('#especialidad').html(data);
                }
            });
        });
    }); -->
<!-- <script> -->

<script>
 $(document).ready(function() {
    // Función para autocompletar datos
    function autocompletarDatos(data) {
        if (!data) return;
        
        // Campos comunes
        $('#nombre').val(data.nameUser);
        $('#userType').val(data.userType);
        $('#userTypeLabel').text(data.userType)
            .removeClass("border-gray-300")
            .addClass(data.userType === 'Profesor' ? 
                    "border-green-500 text-green-500" : 
                    "border-blue-500 text-blue-500");
        
        if (data.userType === 'Alumno') {
            $('#carreraAlum, #especialidadAlum').show();
            
            if (data.id_carrera) {
                $('#id_carrera').val(data.id_carrera).trigger('change');
                
                setTimeout(() => {
                    if (data.id_especialidad) {
                        $('#id_especialidad').val(data.id_especialidad);
                    }
                }, 300);
            }
        } else {
            $('#carreraAlum, #especialidadAlum').hide();
            $('#id_carrera, #id_especialidad').val('').prop('required', false);
        }
    }

    $("#matrículaAlumno").on("blur", function() {
        const matricula = $(this).val().trim();
        
        if (matricula.length <= 4 || matricula.length === 10) {
            $.ajax({
                url: 'autocompletar_info.php',
                type: 'POST',
                data: { matricula: matricula },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        autocompletarDatos(response.userData);
                    } else {
                        const tipo = matricula.length <= 4 ? 'Profesor' : 'Alumno';
                        $('#userType').val(tipo);
                        $('#userTypeLabel').text(tipo)
                            .removeClass("border-gray-300")
                            .addClass(tipo === 'Profesor' ? 
                                    "border-green-500 text-green-500" : 
                                    "border-blue-500 text-blue-500");
                        $('#carreraAlum, #especialidadAlum').hide().prop('required', false);
                        $('#id_carrera, #id_especialidad').val('').prop('required', false);
                        
                        if (tipo === 'Alumno') {
                            $('#carreraAlum, #especialidadAlum').show();
                        }
                    }
                },
                error: function() {
                    console.error('Error en la solicitud AJAX');
                }
            });
        } else {
            $('#userTypeLabel').text("Matrícula inválida (4 para profesor, 10 para alumno)")
                .removeClass("border-gray-300")
                .addClass("border-red-500 text-red-500");
            $('#userType').val("");
        }
    });

    // Cargar especialidades cuando cambia la carrera
    $('#id_carrera').on('change', function() {
        const carreraId = $(this).val();
        if (carreraId) {
            $.ajax({
                url: 'getEspecialidades.php',
                method: 'POST',
                data: { carreraId: carreraId },
                success: function(data) {
                    $('#id_especialidad').html(data);
                }
            });
        }
    });
});
</script>
</html>