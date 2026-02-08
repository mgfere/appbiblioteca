<?php
//* Conexión a base de datos
require 'includes/config/database.php';
$db = conectarDB();

//* Arreglo con errores 
$errores = [
  'matricula' => '',
];

$matricula = "";

//? Verificar matrícula del usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $matricula = mysqli_real_escape_string($db, $_POST['matricula']);

  if (!$matricula) {
    $errores['matricula'] = "La matrícula es obligatoria";
  }

  if (!array_filter($errores)) {
    // Revisar si la matrícula existe en la base de datos
    $query = "SELECT id, matricula, password FROM usuarios WHERE matricula = '$matricula' AND estatus = 1";
    $resultado = mysqli_query($db, $query);

    if ($resultado->num_rows == 0) {
      $errores['matricula'] = "La cuenta asociada a esta matrícula está inactiva. Contacta al administrador.";
    }
    
    if ($resultado->num_rows) {
      // La matrícula existe, obtener los datos
      $usuario = mysqli_fetch_assoc($resultado);
      
      if (!empty($usuario['password'])) {
        // Usuario existe con contraseña → redirigir a login
        header("Location: iniciar-sesion.php?matricula=" . urlencode($matricula));
        exit();
      } else {
        // Usuario existe sin contraseña → redirigir a activar cuenta
        header("Location: activar-cuenta.php?matricula=" . urlencode($matricula));
        exit();
      }
    }
    
  }
}

require 'includes/funciones.php';
incluirTemplate('header-forms-user');
?>

<div class="container formulario-container">
  <div class="bienvenida-biblioteca">
    
    <div class="mensaje-bienvenida">
      <h1 style="text-align: center; margin-bottom: 30px;">
        ¡Bienvenido al sistema de la Biblioteca UTTN!
</h1>
      <p style="text-align: center; font-size: 16px; color: #666; margin-bottom: 20px;">
        Para acceder al sistema, ingresa tu matrícula y te guiaremos al siguiente paso.
      </p>
    </div>
  </div>

  <form class="formulario-estudiante" method="POST">
    <h3 style="text-align: center; color: #1ab192; margin-bottom: 25px;">
      Ingresa tu matrícula para continuar
    </h3>
    
    <div class="formulario-grupo">
      <input 
        type="text" 
        id="matricula" 
        name="matricula" 
        placeholder="Ingresa tu matrícula"
        value="<?php echo htmlspecialchars($matricula); ?>" 
        style="font-size: 16px;"
      />
      <?php if ($errores['matricula']): ?>
        <div class="alerta error" style="width: 310px;"><?php echo $errores['matricula']; ?></div>
      <?php endif; ?>
    </div>

    <div class="formulario-grupo">
      <button type="submit" class="btn-submit" style="background-color: #1ab192; font-size: 16px; padding: 12px;">
        Continuar
      </button>
    </div>


  </form>
</div>

<footer style="margin-top: 60px;">
  <div class="content-slim">
    <p> &copy; <?php echo date('Y'); ?> | Universidad Tecnológica de Tamaulipas Norte - Biblioteca</p>
  </div>
</footer>

<?php
//* Cerrar la conexión  
mysqli_close($db);
incluirTemplate('footer');
?>