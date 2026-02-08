<?php
require_once 'database/DatabaseAPI.php'; 


$carreras = [];
$servicios = [];
$message = []; 

$dbAPI = new DatabaseAPI();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $matricula = $_POST['matricula'] ?? '';
    $nombre = $_POST['nombre'] ?? '';
    $userType = $_POST['userType'] ?? '';
    $id_servicio = $_POST['id_servicio'] ?? '';
    
    $id_carrera = !empty($_POST['id_carrera']) ? $_POST['id_carrera'] : null;
    $id_especialidad = !empty($_POST['id_especialidad']) ? $_POST['id_especialidad'] : null;
    
    $usuarioExistente = $dbAPI->usuarioExistenteRevisar($matricula);
    
    if (!$usuarioExistente) {
        $insertResult = $dbAPI->insertingTeachersAndStudents($matricula, $nombre, $id_especialidad, $id_carrera, $id_servicio, $userType);
        
        if ($insertResult === true) {
            $message['exito'] = 'Se ha registrado la solicitud exitosamente';
        } else {
            $message['error'] = $insertResult; 
        }
    } else {
        // Usuario existe, solo registramos la solicitud
        $registroResult = $dbAPI->insertarRegistroSolicitud($matricula, $id_servicio);
        
        if ($registroResult === true) {
            $message['exito'] = 'Se ha registrado la solicitud exitosamente';
        } else {
            $message['error'] = $registroResult;
        }
    }
}

try {
    $carreras = $dbAPI->obtenerCarrerasSQLServer();

    $servicios = $dbAPI->obtenerServicios();

} catch (Exception $e) {
    error_log("Error cargando datos iniciales: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Servicio</title>
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="output.css">
    <link rel="icon" href="img/favicon.ico" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <main>
        <?php include 'header_registros.php'; ?>
        
        <h1 class="my-5 text-center text-2xl font-bold"><b>Solicitud de Servicio</b></h1>

        <form method="POST" id="formulario" action="" class="max-w-md mx-auto p-8 bg-[#E1DDDA] rounded-lg shadow-lg">
            
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="matricula">Matrícula/Número de Empleado:</label>
                <input type="text" name="matricula" id="matrículaAlumno" placeholder="Matrícula o Número de Empleado" required maxlength="10" 
                       class="w-full px-3 py-2 placeholder-gray-300 border rounded-lg focus:shadow-outline focus:outline-none focus:ring-1 focus:ring-blue-600" autocomplete="off">
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="nombre">Nombre Completo:</label>
                <input type="text" name="nombre" id="nombre" placeholder="Nombre" required 
                       class="w-full px-3 py-2 placeholder-gray-300 border rounded-lg focus:shadow-outline focus:outline-none focus:ring-1 focus:ring-blue-600 bg-gray-100" autocomplete="off" readonly>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2">Tipo:</label>
                <span id="userTypeLabel" class="w-full px-3 py-2 bg-white border border-gray-300 rounded-lg inline-block min-h-[40px]"></span>
                <input type="hidden" name="userType" id="userType" required>
            </div>

            <div class="mb-4" id="carreraAlum">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="carrera">Carrera:</label>
                <select name="id_carrera" id="id_carrera" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-600">
                    <option value="" disabled selected>Seleccione una carrera</option>
                    <?php if (!empty($carreras)): ?>
                        <?php foreach ($carreras as $carrera) : ?>
                            <option value="<?php echo $carrera['id_carrera']; ?>"><?php echo $carrera['nombre_carrera']; ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="mb-4 inputHide" id="especialidadAlum">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="especialidad">Especialidad:</label>
                <select name="id_especialidad" id="id_especialidad" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-600">
                    <option value="" disabled selected>Seleccione una especialidad</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="id_servicio">Servicio:</label>
                <select name="id_servicio" id="servicio" required class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-1 focus:ring-blue-600">
                    <option value="" disabled selected>Seleccione un servicio</option>
                    <?php if (!empty($servicios)): ?>
                        <?php foreach ($servicios as $servicio) : ?>
                            <option value="<?php echo $servicio['id_servicio']; ?>"><?php echo $servicio['nombre_servicio']; ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>

            <div class="flex justify-center">
                <input type="submit" value="Guardar" class="w-full px-4 py-2 mt-4 text-white font-bold rounded-lg bg-[#09a787] hover:bg-[#077669]">
            </div>
        </form>
    </main>

    <br><br><br>
    <?php include 'footer.php'; ?>

    <?php if (!empty($message['exito'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    position: 'top-end',
                    icon: 'success',
                    title: '<?php echo $message['exito']; ?>',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    toast: true,
                    background: '#f0f9f0',
                    iconColor: '#28a745'
                }).then((result) => {
                    window.location.href = 'index.php'; 
                });
            });
        </script>
    <?php endif; ?>

    <?php if (!empty($message['error'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: '<?php echo $message['error']; ?>',
                    confirmButtonColor: '#d33'
                });
            });
        </script>
    <?php endif; ?>
</body>

<script>
$(document).ready(function() {

    function cargarEspecialidades(carreraId, especialidadPreseleccionada = null) {
        if (!carreraId) return;

        $.ajax({
            url: 'getEspecialidades.php',
            method: 'POST',
            data: { carreraId: carreraId },
            success: function(htmlOpciones) {
                $('#id_especialidad').html(htmlOpciones);

                if (especialidadPreseleccionada) {
                    $('#id_especialidad').val(especialidadPreseleccionada);
                }
            }
        });
    }

    function autocompletarDatos(data) {
        if (!data) return;

        $('#nombre').val(data.nameUser);
        $('#userType').val(data.userType);
        
        $('#userTypeLabel').text(data.userType)
            .removeClass("border-gray-300 border-green-500 border-blue-500 text-green-500 text-blue-500");
            
        if(data.userType === 'Profesor'){
             $('#userTypeLabel').addClass("border-green-500 text-green-500");
             $('#carreraAlum, #especialidadAlum').hide();
             $('#id_carrera, #id_especialidad').val('').prop('required', false);
             
        } else { // ALUMNO
             $('#userTypeLabel').addClass("border-blue-500 text-blue-500");
             $('#carreraAlum, #especialidadAlum').show();
             $('#id_carrera').prop('required', true);

             if (data.id_carrera) {
                $('#id_carrera').val(data.id_carrera);
                
                cargarEspecialidades(data.id_carrera, data.id_especialidad);
             }
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
                        $('#nombre').val(''); 
                        $('#userType').val(tipo);
                        if (tipo === 'Alumno') {
                            $('#carreraAlum, #especialidadAlum').show();
                        } else {
                            $('#carreraAlum, #especialidadAlum').hide();
                        }
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                }
            });
        }
    });

    $('#id_carrera').on('change', function() {
        const carreraId = $(this).val();
        cargarEspecialidades(carreraId);
    });
});
</script>
</html>