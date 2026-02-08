<?php

require 'includes/funciones.php';
$auth = usuarioAutenticado();

if (!$auth) {
    header('Location: principal.php');
    exit();
}

// Obtener datos del usuario de la sesión
$matricula = $_SESSION['usuario_matricula'] ?? 'Sin matrícula';
$nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$email = $_SESSION['usuario_correo'] ?? '';

// Usamos la conexión a SQL Server
require 'includes/config/database.php';
$conn = conectarDB2();

// --- INICIO DE LA MODIFICACIÓN ---

// Valores por defecto para carrera y especialidad
$nombreCarrera = 'No especificada';
$nombreEspecialidad = 'No especificada';

// Consulta única y optimizada para obtener carrera y especialidad desde DatosPersonales
if (!empty($matricula)) {
    $queryDatos = "SELECT CarreraNom, Especialidad FROM [Tutorias].[dbo].[DatosPersonales] WHERE Matricula = ?";
    $params = [$matricula];
    $resultado = sqlsrv_query($conn, $queryDatos, $params);
    
    if ($resultado && sqlsrv_has_rows($resultado)) {
        $datos = sqlsrv_fetch_array($resultado, SQLSRV_FETCH_ASSOC);
        
        // Asignamos los valores obtenidos de la base de datos
        $nombreCarrera = !empty($datos['CarreraNom']) ? $datos['CarreraNom'] : $nombreCarrera;
        $nombreEspecialidad = !empty($datos['Especialidad']) ? $datos['Especialidad'] : $nombreEspecialidad;
    }
}

// --- FIN DE LA MODIFICACIÓN ---

incluirTemplate('header-user');

?>
<div class="container perfil-banner">
    <div class="perfil-item">
        <h1 class="perfil-name">Usuario:</h1>
        <span class="perfil-datos"><?php echo htmlspecialchars($nombre); ?></span>
    </div>
    <div class="perfil-item">
        <p class="perfil-info"><strong>Matrícula:</strong></p>
        <span class="perfil-datos"><?php echo htmlspecialchars($matricula); ?></span>
    </div>
    <div class="perfil-item">
        <p class="perfil-info"><strong>Correo electrónico:</strong></p>
        <span class="perfil-datos" id="email"><?php echo htmlspecialchars($email); ?></span>
    </div>
    <div class="perfil-item">
        <p class="perfil-info"><strong>Carrera:</strong></p>
        <span class="perfil-datos"><?php echo htmlspecialchars($nombreCarrera); ?></span>
    </div>
    <div class="perfil-item">
        <p class="perfil-info"><strong>Especialidad:</strong></p>
        <span class="perfil-datos"><?php echo htmlspecialchars($nombreEspecialidad); ?></span>
    </div>
</div>

<div class="container perfil-botones">
    <a href="index.php" class="btn-Password"><i class="fas fa-arrow-left"></i> Volver</a>
</div>


<?php
incluirTemplate('footer');

// Cerrar la conexión de SQL Server
if ($conn) {
    sqlsrv_close($conn);
}
?>