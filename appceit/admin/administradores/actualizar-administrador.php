<?php
require '../../includes/funciones.php';

$auth = adminAutenticado();


if (!$auth) {
    header('Location: ../login.php');
    exit;
}

//* Validando que el id sea un número
$rol = $_SESSION['rol'];

$id = $_GET['id'];
$id = filter_var($id, FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: ../panel-administradores.php');
}

//* Conexión a base de datos
require '../../includes/config/database.php';
$db = conectarDB();

//? Consulta para obtener los datos de los administradores
$consultaAdministrador = "SELECT * FROM administradores WHERE id = {$id}";
$resultadoAdministrador = mysqli_query($db, $consultaAdministrador);
$administrador = mysqli_fetch_assoc($resultadoAdministrador);

//* Arreglo con mensajes de errores
$errores = [
    'nombre' => '',
    'matricula' => '',
    'rol' => '',
];

$nombre = $administrador['nombre'];
$matricula = $administrador['matricula'];
$rol_actual = $administrador['rol']; // Cambié el nombre para evitar conflicto

//* Ejecutar el código después de que el usuario envie el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = mysqli_real_escape_string($db, $_POST["nombre"]);
    $matricula = mysqli_real_escape_string($db, $_POST["matricula"]);
    $rol = mysqli_real_escape_string($db, $_POST["rol"]);

    if (!$nombre) {
        $errores['nombre'] = "El nombre del administrador es obligatorio";
    }

    if (!$matricula) {
        $errores['matricula'] = "La matricula del administrador es obligatoria";
    }

    //* Revisar que el arreglo de errores esté vacio 
    if (!array_filter($errores)) {
        //* Actualizar a la base de datos
        $query = "UPDATE administradores SET rol = '{$rol}' WHERE id = {$id}";
        $resultado = mysqli_query($db, $query);

        if ($resultado) {
            // Redireccionar al usuario
            header("Location: ../panel-administradores.php?resultado=3");
        }
    }
}

incluirTemplate('sidebar-formularios');
?>
<!-- Contenido Principal -->
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
            <form class="book-form" method="POST" enctype="multipart/form-data">
                <h1>Actualizar administrador</h1>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre">Nombre:</label>
                        <input type="text" id="nombre" name="nombre" value="<?php echo $nombre; ?>" readonly>
                        <?php if ($errores['nombre']): ?>
                            <div class="alerta error"><?php echo $errores['nombre']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="matricula">Matrícula:</label>
                        <input type="text" id="matricula" name="matricula" value="<?php echo $matricula; ?>" style="text-transform:capitalize;" readonly>
                        <?php if ($errores['matricula']): ?>
                            <div class="alerta error"><?php echo $errores['matricula']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="rol">Tipo de administrador:</label>
                        <select id="rol" name="rol">
                            <?php
                           //Traer los roles desde la base de datos
                           $query_roles = "SELECT IdRol, nombreRol FROM rol";
                           $resultado_roles = mysqli_query($db, $query_roles);
                           
                           while ($rol_db = mysqli_fetch_assoc($resultado_roles)) {
                               $selected = ($rol_db['IdRol'] == $rol_actual) ? 'selected' : '';
                               echo "<option value='{$rol_db['IdRol']}' $selected>{$rol_db['nombreRol']}</option>";
                           }
                           ?>
                        </select>
                        <?php if ($errores['rol']): ?>
                            <div class="alerta error"><?php echo $errores['rol']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="submit" class="btnAceptado"><i class="fas fa-save" style="margin-right: 5px;"></i>Guardar cambios</button>
            </form>
        </div>
    </div>
</div>
<?php
incluirTemplate('footer');
?>