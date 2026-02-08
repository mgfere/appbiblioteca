<?php
require '../includes/funciones.php';

$auth = adminAutenticado();

if (!$auth) {
  header('Location: login.php');
  exit;
}

// Conexión a la base de datos
require '../includes/config/database.php';
$db = conectarDB();

// Eliminar todos los usuarios (que en realidad es cambiarlos de estatus para ocultarlos)
$query = "UPDATE usuarios SET estatus = 2 WHERE usuarios.estatus = 1";
$resultado = mysqli_query($db, $query);

if ($resultado) {
  // Reiniciar la tabla de prestamos del autoincrement a 1
  $query = "DELETE FROM prestamos WHERE prestamos.status = 1";
  mysqli_query($db, $query);

  header('Location: panel-usuarios.php?resultado=1');
  exit;
} else {
  header('Location: panel-usuarios.php?resultado=0');
  exit;
}
