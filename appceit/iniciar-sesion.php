<?php
require 'includes/config/database.php';
$conn = conectarDB2();
$conn2 = conectarDB3();

$errores = [
  'matricula' => '',
  'password' => '',
];

// Obtener matrícula desde la URL si es necesario
$matricula = isset($_GET['matricula']) ? $_GET['matricula'] : "";
$password = "";

//? Autenticar el usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $matricula = filter_var($_POST['matricula'], FILTER_SANITIZE_STRING);
  $password = $_POST['password'];
  if (!$matricula) {
    $errores['matricula'] = "El nombre de usuario es obligatorio";
  }if (!$password) {
    $errores['password'] = "La contraseña es obligatoria";
  }if (!array_filter($errores)) {
    $query = "SELECT *   FROM [GestionUsuarios].[dbo].[Alumnos] WHERE Matricula = ? AND Habilitado = 1";
    $params = [$matricula];
    $resultado = sqlsrv_query($conn, $query, $params);
if (sqlsrv_has_rows($resultado)) {
      $usuario = sqlsrv_fetch_array($resultado, SQLSRV_FETCH_ASSOC);

      // Verificar si el password es correcto
      $contrasena_decodificada = base64_decode($usuario['Contrasena']);
      $auth = ($contrasena_decodificada === $password);


      if ($auth) {
        //OBTENER DATOS PERSONALES ----
        $query_datos = "SELECT [IdPersona],[Fecha],[Matricula],[Nombre],[Edad],
        [IdTurno],[IdCarrera],[IdGrupo],[IdGrado],[Direccion]
        ,[Celular],[Telefono],[TelEmergencia],[Email],[Foto],[Estado],[Nom],[Paterno],
        [Materno],[IdPeriodo],[Año],[Sexo],[CarreraNom],[Area],[Especialidad],[IdCiudad],
        [Calle],[NumeroDireccion],[IdColonia]
        FROM [Tutorias].[dbo].[DatosPersonales] WHERE Matricula = ?";
        $params_datos = [$usuario['Matricula']];
        $resultado_datos = sqlsrv_query($conn, $query_datos, $params_datos);
        $nombre_completo_personal = $usuario['NombreCompleto'];
        $email_personal = $usuario['CorreoElectronico'];
        $id_persona_sesion = 0;
        if ($resultado_datos && sqlsrv_has_rows($resultado_datos)) {
          $datos_personales = sqlsrv_fetch_array($resultado_datos, SQLSRV_FETCH_ASSOC);

          $id_persona_sesion = $datos_personales['IdPersona'];

          $nombre_completo_personal = trim($datos_personales['Nom'] . ' ' . $datos_personales['Paterno'] . ' ' . $datos_personales['Materno']);

          $email_personal = $datos_personales['Email'];
        }

        // El usuario está autenticado, iniciamos sesión
        session_start();

        // Llenar el arreglo de la sesión
        $_SESSION['id'] = $id_persona_sesion; 
        $_SESSION['usuario'] = $usuario['UserName'];
        $_SESSION['matricula'] = $usuario['Matricula'];
        $_SESSION['nombre'] = $nombre_completo_personal;
        $_SESSION['email'] = $email_personal;
        $_SESSION['carreraId'] = $usuario['IdCarrera'];
        $_SESSION['especialidadId'] = $usuario['IdNivel'];
        $_SESSION['login'] = true;

        // Redirigir al usuario
        header("Location: index.php");
        exit();
      } else {
        $errores['password'] = "Contraseña incorrecta";
      }
    } else {
      $errores['matricula'] = "El usuario no existe o está inactivo";
    }
  }
}

require 'includes/funciones.php';
incluirTemplate('header-forms-user');
?>

<div class="container formulario-container">
  <form class="formulario-estudiante" method="POST">
    <h1>¡Iniciar Sesión!</h1>
    <h3 style="text-align: center; color: #1ab192; margin-bottom: 20px">
      Ingresa tus credenciales
    </h3>

    <div class="formulario-grupo">
      <label for="matricula">Matrícula</label>
      <input
        type="text"
        id="matricula"
        name="matricula"
        placeholder="Ingresa tu matrícula"
        value="<?php echo htmlspecialchars($matricula); ?>"
        <?php echo isset($_GET['matricula']) ? 'readonly style="background-color: #f5f5f5;"' : ''; ?> />
      <?php if ($errores['matricula']): ?>
        <div class="alerta error" style="width: 310px;"><?php echo $errores['matricula']; ?></div>
      <?php endif; ?>
    </div>

    <div class="formulario-grupo">
      <label for="contrasena">Contraseña</label>
      <input type="password" id="contrasena" name="password" placeholder="Ingresa tu contraseña" />
      <?php if ($errores['password']): ?>
        <div class="alerta error" style="width: 310px;"><?php echo $errores['password']; ?></div>
      <?php endif; ?>
      <button type="button" class="toggle-password" onclick="mostrarPassword('contrasena')">
        Mostrar
      </button>
    </div>
    <a href="olvide-password_user.php" class="forgot-password">¿Olvidaste tu contraseña?</a>
    <div class="formulario-grupo">
      <button type="submit" class="btn-submit">Iniciar sesión</button>
    </div>

    <div class="formulario-grupo">
      <p style="text-align: center; margin-top: 15px; font-size: 14px;">
        <a href="principal.php" style="color: #1ab192;">Usar otra matrícula</a>
      </p>
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
    margin-top: 92px;
  }
</style>
<script>
  function mostrarPassword(id) {
    const input = document.getElementById(id);
    const toggleBtn = input.nextElementSibling;

    if (input.type === "password") {
      input.type = "text";
      toggleBtn.textContent = "Ocultar";
    } else {
      input.type = "password";
      toggleBtn.textContent = "Mostrar";
    }
  }
</script>

<footer style="margin-top: 92px;">
  <div class="content-slim">
    <p> &copy; <?php echo date('Y'); ?> | Universidad Tecnológica de Tamaulipas Norte - Biblioteca</p>
  </div>
</footer>

<?php
//* Cerrar la conexión  
sqlsrv_close($conn);
sqlsrv_close($conn2);
incluirTemplate('footer');
?>