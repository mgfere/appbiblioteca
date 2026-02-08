<?php
require '../includes/funciones.php';

$auth = adminAutenticado();
if (!$auth) {
    header('Location: login.php');
    exit;
}

// Solo el administrador general puede reactivar
$rolAdministrador = $_SESSION['rol'] ?? null;
if ($rolAdministrador != 1) {
    header('Location: panel-usuarios.php');
    exit;
}

require '../includes/config/database.php';
$db = conectarDB();

// Validar que el ID sea un entero válido
$usuario_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$usuario_id) {
    $_SESSION['mensaje'] = "ID de usuario no válido.";
    header('Location: panel-usuarios.php?estatus=inactivos&resultado=9'); // 9 = error
    exit;
}

// Usar prepared statements para seguridad
$query = "UPDATE usuarios SET estatus = 1 WHERE id = ?";
$stmt = mysqli_prepare($db, $query);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $usuario_id);
    $resultado = mysqli_stmt_execute($stmt);

    if ($resultado && mysqli_stmt_affected_rows($stmt) > 0) {
        $_SESSION['mensaje'] = "Usuario reactivado correctamente.";
        header('Location: panel-usuarios.php?estatus=inactivos&resultado=8'); // 8 = éxito
    } else {
        $_SESSION['mensaje'] = "Error al reactivar el usuario o el usuario no requería activación.";
        header('Location: panel-usuarios.php?estatus=inactivos&resultado=9'); // 9 = error
    }
    mysqli_stmt_close($stmt);
} else {
    $_SESSION['mensaje'] = "Error en la preparación de la consulta.";
    header('Location: panel-usuarios.php?estatus=inactivos&resultado=9'); // 9 = error
}

mysqli_close($db);
exit;
?>