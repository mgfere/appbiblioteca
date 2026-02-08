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

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $usuario_id = (int)$_GET['id'];
    
    // Verificar que el usuario existe y está activo
    $queryVerificar = "SELECT id, nombre, apellido FROM usuarios WHERE id = $usuario_id AND estatus = 1";
    $resultadoVerificar = mysqli_query($db, $queryVerificar);
    
    if (mysqli_num_rows($resultadoVerificar) > 0) {
        // Verificar si el usuario tiene préstamos activos
        $queryPrestamos = "SELECT COUNT(*) as prestamos_activos FROM prestamos 
                          WHERE Estudiantes_id = $usuario_id AND status IN ('prestado', 'pendiente', 'activo', 1)";
        $resultadoPrestamos = mysqli_query($db, $queryPrestamos);
        
        if ($resultadoPrestamos) {
            $prestamosActivos = mysqli_fetch_assoc($resultadoPrestamos)['prestamos_activos'];
        } else {
            // Si hay error en la consulta, asumir que no hay préstamos activos
            $prestamosActivos = 0;
        }
        
        if ($prestamosActivos > 0) {
            $_SESSION['mensaje'] = "No se puede eliminar el usuario porque tiene $prestamosActivos préstamo(s) activo(s)";
            header('Location: panel-usuarios.php?resultado=7');
            exit;
        }
        
        // Cambiar el estatus a 0 en lugar de eliminar físicamente
        $queryEliminar = "UPDATE usuarios SET estatus = 0 WHERE id = $usuario_id";
        $resultado = mysqli_query($db, $queryEliminar);
        
        if ($resultado) {
            $_SESSION['mensaje'] = "Usuario eliminado correctamente";
            header('Location: panel-usuarios.php?resultado=6');
        } else {
            $_SESSION['mensaje'] = "Error al eliminar el usuario: " . mysqli_error($db);
            header('Location: panel-usuarios.php?resultado=7');
        }
    } else {
        $_SESSION['mensaje'] = "El usuario no existe o ya ha sido eliminado";
        header('Location: panel-usuarios.php?resultado=7');
    }
} else {
    $_SESSION['mensaje'] = "ID de usuario no válido";
    header('Location: panel-usuarios.php?resultado=7');
}

mysqli_close($db);
exit;
?>