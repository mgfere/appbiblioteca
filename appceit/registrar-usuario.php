<?php
//* Conexión a base de datos
require 'includes/config/database.php';
$db = conectarDB();

$matricula_prellenada = isset($_GET['matricula']) ? $_GET['matricula'] : "";


// Si es petición AJAX para especialidades (debe estar al inicio)
if (isset($_GET['id_carrera'])) {
    header('Content-Type: application/json; charset=utf-8');

    $idCarrera = intval($_GET['id_carrera']);
    $sql = "SELECT id_especialidad, nombre_especialidad FROM especialidades WHERE id_carrera = $idCarrera";
    $result = $db->query($sql);

    $especialidades = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $especialidades[] = $row;
        }
    }

    echo json_encode($especialidades);
    exit;
}


$errores = [
  'matricula' => '',
  'nombre' => '',
  'apellido' => '',
  'email' => '',
  'carreraId' => '',             
  'especialidadId' => '',
  'turno' => '',
  'password' => '',
  'confirmar_password' => '',
];

// Inicializar variables
$matricula = "";
$nombre = "";
$apellido = "";
$email = "";
$carreraId = "";              
$especialidadId = "";
$turno = "";
$password = "";
$confirmar_password = "";

//? Registrar el usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // echo '<pre>';
  // var_dump($_POST);
  // echo '</pre>';
  
  // Sanitizar datos de entrada
  $matricula = mysqli_real_escape_string($db, $_POST['matricula']);
  $nombre = mysqli_real_escape_string($db, $_POST['nombre']);
  $apellido = mysqli_real_escape_string($db, $_POST['apellido']);
  $email = mysqli_real_escape_string($db, $_POST['email']);
  $carreraId = mysqli_real_escape_string($db, $_POST['carreraId']);
  $especialidadId = mysqli_real_escape_string($db, $_POST['especialidadId']);
  $turno = mysqli_real_escape_string($db, $_POST['turno']);
  $password = mysqli_real_escape_string($db, $_POST['password']);
  $confirmar_password = mysqli_real_escape_string($db, $_POST['confirmar_password']);
  
  // Validaciones
  if (!$matricula) {
    $errores['matricula'] = "La matrícula es obligatoria";
  }
  
  if (!$nombre) {
    $errores['nombre'] = "El nombre es obligatorio";
  }
  
  if (!$apellido) {
    $errores['apellido'] = "El apellido es obligatorio";
  }
  
  if (!$email) {
    $errores['email'] = "El email es obligatorio";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores['email'] = "El email no es válido";
  }

  if (!$carreraId) {
    $errores['carrera'] = "La carrera es obligatorio";
  }
  
  if (!$especialidadId) {
    $errores['especialidadId'] = "La especialidad es obligatoria";
  }
  
  if (!$turno) {
    $errores['turno'] = "El turno es obligatorio";
  } elseif (!in_array($turno, ['Matutino', 'Vespertino'])) {
    $errores['turno'] = "El turno debe ser matutino o vespertino";
  }
  
  if (!$password) {
    $errores['password'] = "La contraseña es obligatoria";
  } elseif (strlen($password) < 6) {
    $errores['password'] = "La contraseña debe tener al menos 6 caracteres";
  }
  
  if (!$confirmar_password) {
    $errores['confirmar_password'] = "Confirmar contraseña es obligatorio";
  } elseif ($password !== $confirmar_password) {
    $errores['confirmar_password'] = "Las contraseñas no coinciden";
  }
  
  // Si no hay errores, proceder con el registro
  if (!array_filter($errores)) {
    // Verificar si la matrícula ya existe
    $query_verificar = "SELECT * FROM usuarios WHERE matricula = '$matricula'";
    $resultado_verificar = mysqli_query($db, $query_verificar);
    
    if ($resultado_verificar->num_rows > 0) {
      $errores['matricula'] = "Esta matrícula ya está registrada";
    } else {
      // Verificar si el email ya existe
      $query_email = "SELECT * FROM usuarios WHERE email = '$email'";
      $resultado_email = mysqli_query($db, $query_email);
      
      if ($resultado_email->num_rows > 0) {
        $errores['email'] = "Este email ya está registrado";
      } else {
        // Hash de la contraseña
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $registrado = date('Y-m-d');

        // Insertar nuevo usuario (incluyendo el campo turno)
        $query_insertar = "INSERT INTO usuarios (matricula, nombre, apellido, email, carreraId, especialidadId, turno, password, estatus, registrado) 
                          VALUES ('$matricula', '$nombre', '$apellido', '$email', '$carreraId', '$especialidadId', '$turno', '$password_hash', 1, '$registrado')";

        $resultado_insertar = mysqli_query($db, $query_insertar);

        if ($resultado_insertar) {
          // Registro exitoso - iniciar sesión automáticamente
          $usuario_id = mysqli_insert_id($db);
          
          session_start();
          $_SESSION['id'] = $usuario_id;
          $_SESSION['usuario'] = $matricula;
          $_SESSION['nombre'] = $nombre;
          $_SESSION['apellido'] = $apellido;
          $_SESSION['email'] = $email;
          $_SESSION['especialidadId'] = $especialidadId;
          $_SESSION['turno'] = $turno;
          $_SESSION['login'] = true;
          
          // Redirigir al usuario
          header("Location: index.php");
          exit();
        } else {
          $errores['general'] = "Error al registrar el usuario. Intente de nuevo.";
        }
      }
    }
  }
}

$sqlCarreras = "SELECT id_carrera, nombre_carrera FROM carreras";
$resultCarreras = $db->query($sqlCarreras);

if (isset($_GET['id_carrera'])) {
    $idCarrera = intval($_GET['id_carrera']);
    $sql = "SELECT id_especialidad, nombre_especialidad 
            FROM especialidad 
            WHERE id_carrera = $idCarrera";
    $result = $db->query($sql);

    $especialidades = [];
    while ($row = $result->fetch_assoc()) {
        $especialidades[] = $row;
    }

    echo json_encode($especialidades);
}

require 'includes/funciones.php';
incluirTemplate('header-forms-user');
?>
<div class="container formulario-container">
  <form class="formulario-estudiante" method="POST" id="form-registro">
    <h1>¡Crea tu cuenta!</h1>
    <h3 style="text-align: center; color: #1ab192; margin-bottom: 20px">
      Completa los datos para acceder al sistema de la Biblioteca
    </h3>
    <?php if (isset($errores['general']) && $errores['general']): ?>
      <div class="alerta error" style="width: 100%; margin-bottom: 20px;"><?php echo $errores['general']; ?></div>
    <?php endif; ?>

    <div class="formulario-grupo">
      <label for="matricula">Matrícula</label>
      <input 
        type="text" 
        id="matricula" 
        name="matricula" 
        placeholder="Ingresa tu matrícula"
        value="<?php echo htmlspecialchars($matricula_prellenada ?: $matricula); ?>"
        <?php echo $matricula_prellenada ? 'readonly style="background-color: #f5f5f5;"' : ''; ?>
      />
      <div id="error-matricula" class="validation-message"></div>
      <?php if ($errores['matricula']): ?>
        <div class="alerta error" style="width: 310px;"><?php echo $errores['matricula']; ?></div>
      <?php endif; ?>
    </div>

    <div class="formulario-grupo">
      <label for="nombre">Nombre</label>
      <input type="text" id="nombre" name="nombre" placeholder="Ingresa tu nombre" value="<?php echo htmlspecialchars($nombre); ?>" />
            <div id="error-nombre" class="validation-message"></div>
      <?php if ($errores['nombre']): ?>
        <div class="alerta error" style="width: 310px;"><?php echo $errores['nombre']; ?></div>
      <?php endif; ?>
    </div>

    <div class="formulario-grupo">
      <label for="apellido">Apellido</label>
      <input type="text" id="apellido" name="apellido" placeholder="Ingresa tu apellido" value="<?php echo htmlspecialchars($apellido); ?>" />
      <div id="error-apellido" class="validation-message"></div>
      <?php if ($errores['apellido']): ?>
        <div class="alerta error" style="width: 310px;"><?php echo $errores['apellido']; ?></div>
      <?php endif; ?>
    </div>

    <div class="formulario-grupo">
      <label for="email">Correo Electronico Personal</label>
      <input type="email" id="email" name="email" placeholder="Ingresa tu correo personal" value="<?php echo htmlspecialchars($email); ?>" />
      <div id="error-email" class="validation-message"></div>
      <?php if ($errores['email']): ?>
        <div class="alerta error" style="width: 310px;"><?php echo $errores['email']; ?></div>
      <?php endif; ?>
    </div>

    <div class="formulario-grupo">
      <label for="carreraId">Carrera</label>
      <select id="carreraId" name="carreraId">
        <option value="">Selecciona una carrera</option>
        <?php while($row = $resultCarreras->fetch_assoc()): ?>
          <option value="<?php echo $row['id_carrera']; ?>" 
            <?php echo ($carreraId == $row['id_carrera']) ? 'selected' : ''; ?>>
            <?php echo $row['nombre_carrera']; ?>
          </option>
        <?php endwhile; ?>
      </select>
      <div id="error-carreraId" class="validation-message"></div>
    </div>

    <div class="formulario-grupo">
      <label for="especialidadId">Especialidad</label>
      <select id="especialidadId" name="especialidadId">
        <option value="">Selecciona una especialidad</option>
      </select>
      <div id="error-especialidadId" class="validation-message"></div>
      <?php if (!empty($errores['especialidadId'])): ?>
        <div class="alerta error" style="width: 310px;"><?php echo $errores['especialidadId']; ?></div>
      <?php endif; ?>
    </div>

    <div class="formulario-grupo">
      <label for="turno">Turno</label>
      <select id="turno" name="turno">
        <option value="">Selecciona un turno</option>
        <option value="Matutino" <?php echo ($turno == 'Matutino') ? 'selected' : ''; ?>>Matutino</option>
        <option value="Vespertino" <?php echo ($turno == 'Vespertino') ? 'selected' : ''; ?>>Vespertino</option>
      </select>
      <div id="error-turno" class="validation-message"></div>
      <?php if ($errores['turno']): ?>
        <div class="alerta error" style="width: 310px;"><?php echo $errores['turno']; ?></div>
      <?php endif; ?>
    </div>

    <div class="formulario-grupo">
      <label for="password">
        Contraseña 
      </label>
      <input type="password" id="password" name="password" placeholder="Mínimo 6 caracteres"/>
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
      </button>
    </div>

    <div class="formulario-grupo">
      <button type="submit" class="btn-submit" id="btn-registrar" disabled>
        Registrarse
      </button>
      <p id="form-status" style="text-align: center; font-size: 14px; color: #666; margin-top: 10px;">
        Completa todos los campos para continuar
      </p>
    </div>

        <div class="formulario-grupo">
      <p style="text-align: center; margin-top: 15px; font-size: 14px;">
        <a href="principal.php" style="color: #1ab192;">Usar otra matrícula</a>
      </p>
    </div>

  </form>
</div>

<footer style="margin-top: 92px;">
  <div class="content-slim">
    <p> &copy; <?php echo date('Y'); ?> | Universidad Tecnológica de Tamaulipas Norte</p>
  </div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Estados de validación
  const validationState = {
    matricula: false,
    nombre: false,
    apellido: false,
    email: false,
    carreraId: false,
    especialidadId: false,
    turno: false,
    password: false,
    confirmar_password: false
  };

  // Elementos del formulario
  const form = document.getElementById('form-registro');
  const btnRegistrar = document.getElementById('btn-registrar');
  const formStatus = document.getElementById('form-status');

  // Función para actualizar el estado del botón
  function updateButtonState() {
    const allValid = Object.values(validationState).every(state => state);
    btnRegistrar.disabled = !allValid;
    
    if (allValid) {
      formStatus.textContent = 'Listo para registrarse';
      formStatus.style.color = '#666';
    } else {
      const pending = Object.keys(validationState).filter(key => !validationState[key]).length;
      formStatus.textContent = `Faltan ${pending} campos por completar`;
      formStatus.style.color = '#666';
    }
  }

    // Validar campo de matrícula prerrellenado
  const matriculaField = document.getElementById('matricula');
  if (matriculaField.value.trim()) {
    validationState.matricula = true;
    matriculaField.classList.add('field-valid');
    updateButtonState();
  }

  // Función para mostrar mensaje de validación
  function showValidation(fieldId, isValid, message) {
    const errorDiv = document.getElementById(`error-${fieldId}`);
    const field = document.getElementById(fieldId);
    
    if (isValid) {
      errorDiv.textContent = message || '';
      errorDiv.className = 'validation-message success';
      field.classList.remove('field-invalid');
      field.classList.add('field-valid');
    } else {
      errorDiv.textContent = message || '';
      errorDiv.className = 'validation-message error';
      field.classList.remove('field-valid');
      field.classList.add('field-invalid');
    }
    
    validationState[fieldId] = isValid;
    updateButtonState();
  }

  // Validación de matrícula
  document.getElementById('matricula').addEventListener('input', function() {
    const value = this.value.trim();
    if (!value) {
      showValidation('matricula', false, 'La matrícula es obligatoria');
    } else {
      showValidation('matricula', true);
    }
  });

  // Validación de nombre
  document.getElementById('nombre').addEventListener('input', function() {
    const value = this.value.trim();
    if (!value) {
      showValidation('nombre', false, 'El nombre es obligatorio');
    } else if (value.length < 2) {
      showValidation('nombre', false, 'El nombre debe tener al menos 2 caracteres');
    } else {
      showValidation('nombre', true);
    }
  });

  // Validación de apellido
  document.getElementById('apellido').addEventListener('input', function() {
    const value = this.value.trim();
    if (!value) {
      showValidation('apellido', false, 'El apellido es obligatorio');
    } else if (value.length < 2) {
      showValidation('apellido', false, 'El apellido debe tener al menos 2 caracteres');
    } else {
      showValidation('apellido', true);
    }
  });

  // Validación de email
  document.getElementById('email').addEventListener('input', function() {
    const value = this.value.trim();
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (!value) {
      showValidation('email', false, 'El correo es obligatorio');
    } else if (!emailRegex.test(value)) {
      showValidation('email', false, 'Formato de correo inválido');
    } else {
      showValidation('email', true);
    }
  });

  // Validación de carrera
  document.getElementById('carreraId').addEventListener('change', function() {
    const value = this.value;
    if (!value) {
      showValidation('carreraId', false, 'Selecciona una carrera');
    } else {
      showValidation('carreraId', true);
    }

    // Limpiar especialidad cuando cambie la carrera
    const especialidadSelect = document.getElementById('especialidadId');
    especialidadSelect.innerHTML = '<option value="">Cargando especialidades...</option>';
    validationState.especialidadId = false;
    updateButtonState();
    
    // Cargar especialidades
    if (value) {
      fetch('registrar-usuario.php?id_carrera=' + value)
        .then(response => response.json())
        .then(data => {
          especialidadSelect.innerHTML = '<option value="">Selecciona una especialidad</option>';
          data.forEach(function(esp) {
            const option = document.createElement('option');
            option.value = esp.id_especialidad;
            option.textContent = esp.nombre_especialidad;
            especialidadSelect.appendChild(option);
          });
        })
        .catch(error => {
          console.error('Error:', error);
          especialidadSelect.innerHTML = '<option value="">Error al cargar</option>';
        });
    }
  });

  // Validación de especialidad
  document.getElementById('especialidadId').addEventListener('change', function() {
    const value = this.value;
    if (!value) {
      showValidation('especialidadId', false, 'Selecciona una especialidad');
    } else {
      showValidation('especialidadId', true);
    }
  });

  // Validación de turno
  document.getElementById('turno').addEventListener('change', function() {
    const value = this.value;
    if (!value) {
      showValidation('turno', false, 'Selecciona un turno');
    } else {
      showValidation('turno', true);
    }
  });

  // Validación de contraseña
  document.getElementById('password').addEventListener('input', function() {
    const value = this.value;
    const strengthDiv = document.getElementById('password-strength');
    
    if (!value) {
      showValidation('password', false, 'La contraseña es obligatoria');
      strengthDiv.textContent = '';
      strengthDiv.className = 'password-feedback';
    } else if (value.length < 6) {
      showValidation('password', false, `Faltan ${6 - value.length} caracteres más`);
      strengthDiv.textContent = `Demasiado corta (${value.length}/6 mínimo)`;
      strengthDiv.className = 'password-feedback weak';
    } else {
      showValidation('password', true);
      strengthDiv.textContent = 'Contraseña válida';
      strengthDiv.className = 'password-feedback good';
    }
    
    // Revisar confirmación cuando cambie la contraseña
    checkPasswordMatch();
  });

  // Validación de confirmación de contraseña
  document.getElementById('confirmar_password').addEventListener('input', checkPasswordMatch);

  function checkPasswordMatch() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirmar_password').value;
    const feedbackDiv = document.getElementById('confirm-password-feedback');
    
    if (!confirmPassword) {
      showValidation('confirmar_password', false, 'Confirma tu contraseña');
      feedbackDiv.textContent = '';
      feedbackDiv.className = 'password-feedback';
    } else if (password !== confirmPassword) {
      showValidation('confirmar_password', false, 'Las contraseñas no coinciden');
      feedbackDiv.textContent = 'No coinciden';
      feedbackDiv.className = 'password-feedback no-match';
    } else {
      showValidation('confirmar_password', true);
      feedbackDiv.textContent='';
      feedbackDiv.className = 'password-feedback match';
    }
  }

  // Validación en el envío del formulario (por si acaso)
  form.addEventListener('submit', function(e) {
    const allValid = Object.values(validationState).every(state => state);
    if (!allValid) {
      e.preventDefault();
      alert('Por favor completa todos los campos correctamente antes de continuar.');
    }
  }); 
});

</script>

<?php
//* Cerrar la conexión  
mysqli_close($db);

incluirTemplate('footer');
?>