<?php
//* Conexión a base de datos
require '../includes/config/database.php';
$db = conectarDB();

//* Arreglo con errores 
$errores = [
  'matricula' => '',
  'password' => '',
];

$matricula = "";
$password = "";//? Autenticar el usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {// echo '<pre>';+ // var_dump($_POST); // echo '</pre>';
  $matricula = mysqli_real_escape_string($db, $_POST['matricula']);
  $password = mysqli_real_escape_string($db, $_POST['password']);
  if (!$matricula) {
    $errores['matricula'] = "La matrícula es obligatoria";
  }
  if (!$password) { $errores['password'] = "La contraseña es obligatoria";
  }
  if (!array_filter($errores)) {// Revisar si el ad
    $query = "SELECT * FROM administradores WHERE matricula = '$matricula'";
    $resultado = mysqli_query($db, $query);
    if ($resultado->num_rows) { // Revisar si la contraseña es correcta
      $administrador = mysqli_fetch_assoc($resultado);  // Verificar si el password es correcto
      $auth = password_verify($password, $administrador['password']);
      if ($auth) { // El usuario está autenticado
        session_start();
        // LLenar el arreglo de la sesión
        $_SESSION['administrador'] = $administrador['matricula'];
        $_SESSION['nombre'] = $administrador['nombre'];
        $_SESSION['rol'] = $administrador['rol'];
        $_SESSION['id'] = $administrador['id'];
        $_SESSION['timestamp'] = time();
        $_SESSION['login'] = true;
        // Redirigir al usuario a otra página
        header("Location: panel-control.php");
        exit();
      } else {
        $errores['password'] = "Contraseña incorrecta";
      }
    } else {
      $errores['matricula'] = "El administrador no existe";
    }
  }
}


require '../includes/funciones.php';
incluirTemplate('header-forms');
?>
<link rel="stylesheet" href="../public/css/bundle.css">
<div class="container formulario-container">
    <form class="formulario-estudiante" method="POST">
        <h1>¡Bienvenidos!</h1>
        <div class="formulario-grupo">
            <label for="matricula">Matrícula</label>
            <input type="text" id="matrícula" name="matricula" value="<?php echo htmlspecialchars($matricula); ?>" />
            <?php if ($errores['matricula']) : ?>
                <div class="alerta error" style="width: 420px;"><?php echo $errores['matricula']; ?></div>
            <?php endif; ?>
        </div>
        <div class="formulario-grupo">
            <label for="contrasena">Contraseña</label>
            <input type="password" id="contrasena" name="password" />
            <?php if ($errores['password']) : ?>
                <div class="alerta error" style="width: 420px;"><?php echo $errores['password']; ?></div>
            <?php endif; ?>
            <button type="button" class="toggle-password" onclick="mostrarPassword('contrasena')">
                Mostrar
            </button>
        </div>
        <a href="olvide-password.php" class="forgot-password">¿Olvidaste tu contraseña?</a>
        <div class="formulario-grupo">
            <button type="submit" class="btn-submit">Iniciar sesión</button>
        </div>
    </form>
</div>
<style>
    /* Mejora para el enlace "¿Olvidaste tu contraseña?" */
.forgot-password {
    display: inline-block;
    color: #4CAF50;
    text-decoration: none;
    font-size: 0.9rem;
    padding: 8px 16px;
    border-radius: 20px;
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
    margin: 10px 0;
}


.forgot-password:hover {
    background-color: #1ab192;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
}

/* Más margen para el footer */
footer {
    margin-top: 60px; /* Aumenta este valor según necesites */
}
    </style>
<footer >
  <div class="content">
    <p> &copy; <?php echo date('Y'); ?> | Universidad Tecnológica de Tamaulipas Norte</p>
  </div>
  </div>
</footer>
<script src="../public/js/bundle.js"></script>
<?php
incluirTemplate('footer');
?>