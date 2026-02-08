<?php
//* Conexión a base de datos
require 'includes/config/database.php';
$db = conectarDB();

//* Verificar que se recibió la matrícula
if (!isset($_GET['matricula']) || empty($_GET['matricula'])) {
  header('Location: index.php');
  exit();
}

$matricula = mysqli_real_escape_string($db, $_GET['matricula']);

//* Verificar que la matrícula existe y no tiene contraseña
$query = "SELECT * FROM usuarios WHERE matricula = '$matricula' AND estatus = 1";
$resultado = mysqli_query($db, $query);

if (!$resultado->num_rows) {
  header('Location: index.php');
  exit();
}

$usuario = mysqli_fetch_assoc($resultado);
if (!empty($usuario['password'])) {
  // Si ya tiene contraseña, redirigir al login
  header("Location: iniciar-sesion.php?matricula=" . urlencode($matricula));
  exit();
}

//* Arreglo con errores 
$errores = [
  'password' => '',
  'confirmar_password' => '',
];

//? Procesar la creación de contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $password = $_POST['password'];
  $confirmar_password = $_POST['confirmar_password'];

  if (!$password) {
    $errores['password'] = "La contraseña es obligatoria";
  } elseif (strlen($password) < 6) {
    $errores['password'] = "La contraseña debe tener al menos 6 caracteres";
  }

  if (!$confirmar_password) {
    $errores['confirmar_password'] = "Confirma tu contraseña";
  } elseif ($password !== $confirmar_password) {
    $errores['confirmar_password'] = "Las contraseñas no coinciden";
  }

  if (!array_filter($errores)) {
    // Encriptar la contraseña (ajusta según tu método actual)
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Actualizar la contraseña en la base de datos
    $query_update = "UPDATE usuarios SET password = '$password_hash' WHERE matricula = '$matricula' AND estatus = 1";
    $resultado_update = mysqli_query($db, $query_update);

    if ($resultado_update) {
      // Contraseña creada exitosamente, iniciar sesión automáticamente
      session_start();
      $_SESSION['id'] = $usuario['id'];
      $_SESSION['usuario'] = $usuario['matricula'];
      $_SESSION['nombre'] = $usuario['nombre'];
      $_SESSION['apellido'] = $usuario['apellido'];
      $_SESSION['email'] = $usuario['email'];
      $_SESSION['carreraId'] = $usuario['carreraId'];
      $_SESSION['especialidadId'] = $usuario['especialidadId'];
      $_SESSION['login'] = true;

      // Redirigir al dashboard o página principal
      header("Location: index.php?first_login=1");
      exit();
    } else {
      $errores['password'] = "Error al crear la contraseña. Intenta de nuevo.";
    }
  }
}

require 'includes/funciones.php';
incluirTemplate('header-forms-user');
?>

<div class="container formulario-container">
  <div class="activar-cuenta-header">
    <h1 style="text-align: center;">¡Activa tu Cuenta!</h1>
    <p style="text-align: center; color: #666; margin-bottom: 30px;">
      Solo necesitas crear una contraseña para acceder al sistema
    </p>
  </div>

  <form class="formulario-estudiante" method="POST">
    <div class="info-usuario" style="background-color: #f5f5f5; padding: 15px; margin-bottom: 25px;">
      <p style="margin: 0; text-align: center; color: #555;">
        <strong>Estudiante:</strong> <?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?><br>
        <strong>Matrícula:</strong> <?php echo htmlspecialchars($usuario['matricula']); ?>
      </p>
    </div>

    <h3 style="text-align: center; color: #1ab192; margin-bottom: 25px;">
      Crea tu contraseña de acceso
    </h3>
    
    <div class="formulario-grupo">
      <label for="password">
        Crear Contraseña 
      </label>
      <input type="password" id="password" name="password" placeholder="Mínimo 6 caracteres" />
      <div id="password-strength" class="password-feedback"></div>
      <div id="error-password" class="validation-message"></div>
      <?php if ($errores['password']): ?>
        <div class="alerta error" style="width: 310px;"><?php echo $errores['password']; ?></div>
      <?php endif; ?>
      <button type="button" class="toggle-password" onclick="mostrarPassword('password')">
        Mostrar
      </button>
    </div>

    <div class="formulario-grupo">
      <label for="confirmar_password">Confirmar Contraseña</label>
      <input type="password" id="confirmar_password" name="confirmar_password" placeholder="Repite la contraseña" />
      <div id="confirm-password-feedback" class="password-feedback"></div>
      <div id="error-confirmar_password" class="validation-message"></div>
      <?php if ($errores['confirmar_password']): ?>
        <div class="alerta error" style="width: 310px;"><?php echo $errores['confirmar_password']; ?></div>
      <?php endif; ?>
      <button type="button" class="toggle-password" onclick="mostrarPassword('confirmar_password')">
        Mostrar
      </button>
    </div>

    <div class="formulario-grupo">
      <button type="submit" class="btn-submit" id="btn-activar" style="background-color: #1ab192;" disabled>
        Activar Cuenta y Acceder
      </button>
      <p id="form-status" style="text-align: center; font-size: 14px; color: #666; margin-top: 10px;">
        Crea una contraseña de al menos 6 caracteres
      </p>
    </div>


<script>
document.addEventListener('DOMContentLoaded', function() {
  const passwordField = document.getElementById('password');
  const confirmPasswordField = document.getElementById('confirmar_password');
  const btnActivar = document.getElementById('btn-activar');
  const formStatus = document.getElementById('form-status');
  const strengthDiv = document.getElementById('password-strength');
  const confirmFeedbackDiv = document.getElementById('confirm-password-feedback');

  let passwordValid = false;
  let confirmValid = false;

  function updateButtonState() {
    const allValid = passwordValid && confirmValid;
    btnActivar.disabled = !allValid;
    
    if (allValid) {
      formStatus.textContent = 'Listo para activar cuenta';
      formStatus.style.color = '#666';
    } else if (!passwordValid) {
      formStatus.textContent = 'La contraseña debe tener al menos 6 caracteres';
      formStatus.style.color = '#b72e2b';
    } else {
      formStatus.textContent = 'Las contraseñas deben coincidir';
      formStatus.style.color = '#b72e2b';
    }
  }

  // Validación de contraseña
  passwordField.addEventListener('input', function() {
    const value = this.value;
    
    if (!value) {
      passwordValid = false;
      strengthDiv.textContent = '';
      strengthDiv.className = 'password-feedback';
      this.classList.remove('field-valid', 'field-invalid');
    } else if (value.length < 6) {
      passwordValid = false;
      strengthDiv.textContent = `Faltan ${6 - value.length} caracteres más (${value.length}/6)`;
      strengthDiv.className = 'password-feedback weak';
      this.classList.remove('field-valid');
      this.classList.add('field-invalid');
    } else {
      passwordValid = true;
      strengthDiv.textContent = 'Contraseña válida';
      strengthDiv.className = 'password-feedback good';
      this.classList.remove('field-invalid');
      this.classList.add('field-valid');
    }
    
    checkPasswordMatch();
    updateButtonState();
  });

  // Validación de confirmación
  confirmPasswordField.addEventListener('input', checkPasswordMatch);

  function checkPasswordMatch() {
    const password = passwordField.value;
    const confirmPassword = confirmPasswordField.value;
    
    if (!confirmPassword) {
      confirmValid = false;
      confirmFeedbackDiv.textContent = '';
      confirmFeedbackDiv.className = 'password-feedback';
      confirmPasswordField.classList.remove('field-valid', 'field-invalid');
    } else if (password !== confirmPassword) {
      confirmValid = false;
      confirmFeedbackDiv.textContent = 'No coinciden';
      confirmFeedbackDiv.className = 'password-feedback no-match';
      confirmPasswordField.classList.remove('field-valid');
      confirmPasswordField.classList.add('field-invalid');
    } else if (password && confirmPassword && password === confirmPassword && password.length >= 6) {
      confirmValid = true;
      confirmFeedbackDiv.textContent = '';
      confirmFeedbackDiv.className = 'password-feedback match';
      confirmPasswordField.classList.remove('field-invalid');
      confirmPasswordField.classList.add('field-valid');
    }
    
    updateButtonState();
  }

  // Validación inicial
  updateButtonState();
});
</script>
    <div style="text-align: center; margin-top: 20px;">
      <a href="principal.php" style="color: #1ab192; font-size: 14px;">
        Regresar al inicio
      </a>
    </div>
  </form>
</div>

<footer style="margin-top: 60px;">
  <div class="content-slim">
    <p> &copy; <?php echo date('Y'); ?> | Universidad Tecnológica de Tamaulipas Norte - Biblioteca</p>
  </div>
</footer>

<?php
mysqli_close($db);
incluirTemplate('footer');
?>