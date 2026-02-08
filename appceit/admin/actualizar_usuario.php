<?php
require '../includes/funciones.php';

$auth = adminAutenticado();

if (!$auth) {
    header('Location: login.php');
    exit;
}

// Verificar que el usuario sea administrador general
$rolAdministrador = isset($_SESSION['rol']) ? $_SESSION['rol'] : null;

if ($rolAdministrador != 1) {
    header('Location: panel-usuarios.php');
    exit;
}

require '../includes/config/database.php';
$db = conectarDB();

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = mysqli_real_escape_string($db, $_POST['usuario_id']);
    $nombre = mysqli_real_escape_string($db, trim($_POST['nombre']));
    $apellido = mysqli_real_escape_string($db, trim($_POST['apellido']));
    $matricula = mysqli_real_escape_string($db, trim($_POST['matricula']));
    $email = mysqli_real_escape_string($db, trim($_POST['email']));
    $carreraId = mysqli_real_escape_string($db, $_POST['carreraId']);
    $especialidadId = mysqli_real_escape_string($db, $_POST['especialidadId']);
    $turno = mysqli_real_escape_string($db, $_POST['turno']);

    // Validaciones
    if (!$nombre) {
        $errores[] = "El nombre es obligatorio";
    }

    if (!$apellido) {
        $errores[] = "El apellido es obligatorio";
    }

    if (!$matricula) {
        $errores[] = "La matrícula es obligatoria";
    }

    if (!$email) {
        $errores[] = "El correo electrónico es obligatorio";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "El correo electrónico no es válido";
    }

    if (!$carreraId) {
        $errores[] = "La carrera es obligatoria";
    }

    if (!$especialidadId) {
        $errores[] = "La especialidad es obligatoria";
    }

    if (!$turno) {
        $errores[] = "El turno es obligatorio";
    }

    // Verificar que la matrícula no esté duplicada (excepto para el usuario actual)
    if (!empty($matricula)) {
        $queryMatricula = "SELECT id FROM usuarios WHERE matricula = '$matricula' AND id != '$usuario_id' AND estatus = 1";
        $resultadoMatricula = mysqli_query($db, $queryMatricula);
        
        if (mysqli_num_rows($resultadoMatricula) > 0) {
            $errores[] = "La matrícula ya está registrada para otro usuario";
        }
    }

    // Verificar que el email no esté duplicado (excepto para el usuario actual)
    if (!empty($email)) {
        $queryEmail = "SELECT id FROM usuarios WHERE email = '$email' AND id != '$usuario_id' AND estatus = 1";
        $resultadoEmail = mysqli_query($db, $queryEmail);
        
        if (mysqli_num_rows($resultadoEmail) > 0) {
            $errores[] = "El correo electrónico ya está registrado para otro usuario";
        }
    }

    // Verificar que la carrera existe
    if (!empty($carreraId)) {
        $queryCarrera = "SELECT id_carrera FROM carreras WHERE id_carrera = '$carreraId'";
        $resultadoCarrera = mysqli_query($db, $queryCarrera);
        
        if (mysqli_num_rows($resultadoCarrera) == 0) {
            $errores[] = "La carrera seleccionada no existe";
        }
    }

    // Verificar que la especialidad existe
    if (!empty($especialidadId)) {
        $queryEspecialidad = "SELECT id_especialidad FROM especialidades WHERE id_especialidad = '$especialidadId'";
        $resultadoEspecialidad = mysqli_query($db, $queryEspecialidad);
        
        if (mysqli_num_rows($resultadoEspecialidad) == 0) {
            $errores[] = "La especialidad seleccionada no existe";
        }
    }

    // Si no hay errores, actualizar el usuario
    if (empty($errores)) {
        $query = "UPDATE usuarios SET 
                    nombre = '$nombre',
                    apellido = '$apellido',
                    matricula = '$matricula',
                    email = '$email',
                    carreraId = '$carreraId',
                    especialidadId = '$especialidadId',
                    turno = '$turno'
                  WHERE id = '$usuario_id' AND estatus = 1";
        
        $resultado = mysqli_query($db, $query);
        
        if ($resultado) {
            $_SESSION['mensaje'] = "Usuario actualizado correctamente";
            header('Location: panel-usuarios.php?resultado=4');
        } else {
            $errores[] = "Error al actualizar el usuario: " . mysqli_error($db);
            $_SESSION['errores'] = $errores;
            header('Location: panel-usuarios.php?resultado=5');
        }
    } else {
        $_SESSION['errores'] = $errores;
        header('Location: panel-usuarios.php?resultado=5');
    }
} else {
    header('Location: panel-usuarios.php');
}

mysqli_close($db);
exit;
?>