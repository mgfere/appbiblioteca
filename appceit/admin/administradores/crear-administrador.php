<?php
require '../../includes/funciones.php';

$auth = adminAutenticado();

if (!$auth) {
    header('Location: ../login.php');
    exit;
}

//* Base de datos
require '../../includes/config/database.php';
$db = conectarDB();

//* Obtener el rol del administrador en sesión
$rolAdminActual = $_SESSION['rol'] ?? null;

//* Consultar roles disponibles desde la base de datos
$queryRoles = "SELECT IdRol, nombreRol FROM rol ORDER BY IdRol DESC";
$resultadoRoles = mysqli_query($db, $queryRoles);
$rolesDisponibles = [];

while ($rolRow = mysqli_fetch_assoc($resultadoRoles)) {
    // Si el admin actual NO es Máster (IdRol = 1), excluir el rol Máster
    if ($rolAdminActual != 1 && $rolRow['IdRol'] == 1) {
        continue;
    }
    $rolesDisponibles[] = $rolRow;
}

//* Arreglo con mensajes de errores
$errores = [
    'nombre' => '',
    'correo' => '', 
    'matricula' => '',
    'password' => '',
    'confirmarPassword' => '',
    'rol' => '',
];

$nombre = "";
$correo = "";
$matricula = "";
$password = "";
$confirmarPassword = "";
$rol = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre = mysqli_real_escape_string($db, $_POST["nombre"]);
    $correo = mysqli_real_escape_string($db, filter_var($_POST["correo"], FILTER_SANITIZE_EMAIL));
    $matricula = mysqli_real_escape_string($db, $_POST["matricula"]);
    $password = mysqli_real_escape_string($db, $_POST["password"]);
    $confirmarPassword = mysqli_real_escape_string($db, $_POST["confirmarPassword"]);
    $rol = mysqli_real_escape_string($db, $_POST["rol"]);

    if (!$nombre) {
        $errores['nombre'] = "El nombre es obligatorio";
    }

    if (!$correo) {
        $errores['correo'] = "El correo es obligatorio";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores['correo'] = "Formato de correo no válido";
    } else {
        $consultaCorreo = "SELECT id FROM administradores WHERE correo = '$correo'";
        $resultadoCorreo = mysqli_query($db, $consultaCorreo);
        if (mysqli_num_rows($resultadoCorreo) > 0) {
            $errores['correo'] = "Este correo ya está registrado";
        }
    }

    if (!$matricula) {
        $errores['matricula'] = "El número de matrícula es obligatorio";
    } else {
        $consultaMatricula = "SELECT id FROM administradores WHERE matricula = '$matricula'";
        $resultadoMatricula = mysqli_query($db, $consultaMatricula);
        if (mysqli_num_rows($resultadoMatricula) > 0) {
            $errores['matricula'] = "Esta matrícula ya está registrada";
        }
    }

    if (!$password || strlen($password) < 8) {
        $errores['password'] = "La contraseña es obligatoria y debe ser de al menos 8 caracteres";
    }

    if ($password !== $confirmarPassword) {
        $errores['confirmarPassword'] = "Las contraseñas no coinciden";
    }

    if ($rol === '') {
        $errores['rol'] = "El tipo de administrador es obligatorio";
    } else {
        // Validar que un admin no-Máster no pueda crear un Máster
        if ($rolAdminActual != 1 && $rol == 1) {
            $errores['rol'] = "No tienes permisos para crear un administrador Máster";
        }
        
        // Validar que el rol exista en la tabla de roles
        $validarRol = "SELECT IdRol FROM rol WHERE IdRol = '$rol'";
        $resultadoValidarRol = mysqli_query($db, $validarRol);
        if (mysqli_num_rows($resultadoValidarRol) == 0) {
            $errores['rol'] = "El rol seleccionado no es válido";
        }
    }

    if (!array_filter($errores)) {

        $passwordHash = password_hash($password, PASSWORD_BCRYPT);

        $query = "INSERT INTO administradores (nombre, correo, matricula, password, rol, registrado) VALUES ('$nombre', '$correo', '$matricula', '$passwordHash', '$rol', NOW())";

        $resultado = mysqli_query($db, $query);

        if ($resultado) {
            header("Location:../panel-administradores.php?resultado=1");
        }
    }
}

incluirTemplate('sidebar-formularios');
?>
<link rel="stylesheet" href="../../public/css/bundle.css">
<div class="container main--content">
    <div class="tabular--wrapper">
        <div class="tabular--botones">
            <a href="../panel-administradores.php">
                <button title="Volver" class="btnAgregar">
                    <i class="fas fa-arrow-left"></i> Volver
                </button>
            </a>
        </div>
        <div class="table--container">
            <form class="book-form" method="POST" action="../administradores/crear-administrador.php" enctype="multipart/form-data">
                <h1>Registro de administrador</h1>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre">Nombre:</label>
                        <input type="text" id="nombre" name="nombre" value="<?php echo htmlspecialchars($nombre); ?>">
                        <?php if ($errores['nombre']): ?>
                            <div class="alerta error"><?php echo $errores['nombre']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="correo">Correo Electrónico (Personal @gmail o @outlook):</label>
                        <input type="email" id="correo" name="correo" value="<?php echo htmlspecialchars($correo); ?>">
                        <?php if ($errores['correo']): ?>
                            <div class="alerta error"><?php echo $errores['correo']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="matricula">Matrícula:</label>
                        <input type="text" id="matricula" name="matricula" value="<?php echo htmlspecialchars($matricula); ?>" style="text-transform:capitalize;">
                        <?php if ($errores['matricula']): ?>
                            <div class="alerta error"><?php echo $errores['matricula']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="contrasena">Contraseña:</label>
                        <input type="password" id="password" name="password" value="" />
                        <?php if ($errores['password']): ?>
                            <div class="alerta error"><?php echo $errores['password']; ?></div>
                        <?php endif; ?>
                        <button type="button" class="toggle-password" onclick="mostrarPassword('password')">
                            Mostrar
                        </button>
                    </div>
                    <div class="form-group">
                        <label for="confirmar-contrasena">Confirmar Contraseña:</label>
                        <input type="password" id="confirmar-contrasena" name="confirmarPassword" />
                        <?php if ($errores['confirmarPassword']): ?>
                            <div class="alerta error"><?php echo $errores['confirmarPassword']; ?></div>
                        <?php endif; ?>
                        <button type="button" class="toggle-password" onclick="mostrarPassword('confirmar-contrasena')">
                            Mostrar
                        </button>
                    </div>
                    <div class="form-group">
                        <label for="rol">Tipo de administrador:</label>
                        <select name="rol">
                            <option value="" selected disabled>Seleccionar</option>
                            <?php foreach ($rolesDisponibles as $rolDisponible): ?>
                                <option value="<?php echo $rolDisponible['IdRol']; ?>" 
                                    <?php echo $rol == $rolDisponible['IdRol'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($rolDisponible['nombreRol']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($errores['rol']): ?>
                            <div class="alerta error"><?php echo $errores['rol']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="submit" class="btnAceptado"><i class="fas fa-save" style="margin-right: 5px;"></i>Registrar</button>
            </form>
        </div>
    </div>
</div>
<script src="../../public/js/bundle.js"></script>

<?php
//* Cerrar la conexión de base de datos
mysqli_close($db);

incluirTemplate('footer');
?>