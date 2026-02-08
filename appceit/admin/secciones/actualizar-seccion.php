<?php
require '../../includes/funciones.php';

$auth = adminAutenticado();

if (!$auth) {
    header('Location: ../login.php');
    exit;
}

//* Validando que el id sea un número
$id = $_GET['id'];
$id = filter_var($id, FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: ../panel-secciones.php');
}

//* Conexión a base de datos
require '../../includes/config/database.php';
$db = conectarDB();

//? Consulta para obtener los datos de las secciones
$consultaSecciones = "SELECT * FROM secciones WHERE id = {$id}";
$resultadoSecciones = mysqli_query($db, $consultaSecciones);
$seccion = mysqli_fetch_assoc($resultadoSecciones);

//* Arreglo con mensajes de errores
$errores = [
    'nombre_seccion' => '',
    'color' => '',
];

$nombre_seccion = $seccion['nombre_seccion'];
$color = $seccion['color'];

//* Ejecutar el código después de que el usuario envie el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // echo "<pre>";
    // var_dump($_POST);
    // echo "</pre>";

    // echo "<pre>";
    // var_dump($_FILES);
    // echo "</pre>";

    $nombre_seccion = mysqli_real_escape_string($db, $_POST["nombre_seccion"]);
    $color = mysqli_real_escape_string($db, $_POST["color"]);


    if (!$nombre_seccion) {
        $errores['nombre_seccion'] = "El nombre de la sección obligatorio";
    }

    if (!$color) {
        $errores['color'] = "Selecciona un color";
    }

    //* Revisar que el arreglo de errores esté vacio 
    if (!array_filter($errores)) {

        //* Insertar a la base de datos
        $query = "UPDATE secciones SET nombre_seccion = '{$nombre_seccion}', color = '{$color}', actualizado = NOW() WHERE id = {$id}";

        // echo $query;

        $resultado = mysqli_query($db, $query);

        if ($resultado) {
            // Redireccionar al usuario
            header("Location:../panel-secciones.php?resultado=2");
        }
    }
}

incluirTemplate('sidebar-formularios');
?>
<!-- Contenido Principal -->
<div class="container main--content">
    <div class="tabular--wrapper">
        <div class="tabular--botones">
            <a href="../panel-secciones.php">
                <button title="Volver" class="btnAgregar">
                    <i class="fas fa-arrow-left"></i> Volver
                </button>
            </a>
        </div>
        <div class="table--container">
            <form class="book-form" method="POST" enctype="multipart/form-data">
                <h1>Actualizar Sección</h1>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre_seccion">Sección</label>
                        <input type="text" id="nombre_seccion" name="nombre_seccion" value="<?php echo $nombre_seccion ?>">
                        <?php if ($errores['nombre_seccion']): ?>
                            <div class="alerta error"><?php echo $errores['nombre_seccion']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="color_picker">Color</label>
                        <input type="color" id="color_picker" name="color_picker" style="height: 40px;" value="<?php echo $color ?>">
                        <?php if ($errores['color']): ?>
                            <div class="alerta error"><?php echo $errores['color']; ?></div>
                        <?php endif; ?>
                    </div>
                    <!-- Input que almacena el color para enviarlo a la base de datos  -->
                    <input type="hidden" id="color" name="color" value="#ff0000" readonly>
                </div>
                <button type="submit" class="btnAceptado"><i class="fas fa-save" style="margin-right: 5px;"></i>Guardar cambios</button>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('color_picker').addEventListener('input', function() {
        const hex = this.value;
        document.getElementById('color').value = hex;
    });

    // Inicializar el campo hexadecimal con el valor por defecto del input color
    document.getElementById('color').value = document.getElementById('color_picker').value;
</script>
<?php
incluirTemplate('footer');
?>