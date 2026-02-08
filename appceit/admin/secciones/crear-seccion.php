<?php
require '../../includes/funciones.php';

$auth = adminAutenticado();

if (!$auth) {
    header('Location: ../login.php');
    exit;
}

//* Conexión a base de datos
require '../../includes/config/database.php';
$db = conectarDB();

//* Arreglo con mensajes de errores
$errores = [
    'nombre_seccion' => '',
    'color' => '',
];

$nombre_seccion = "";
$color = "";

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
        $errores['nombre_seccion'] = "El nombre de la sección es obligatorio";
    } else {
        // Validar si el nombre de la sección ya existe en la base de datos
        $queryNombreSeccion = "SELECT id FROM secciones WHERE nombre_seccion = '$nombre_seccion' LIMIT 1";
        $resultadoNombreSeccion = mysqli_query($db, $queryNombreSeccion);

        if (mysqli_num_rows($resultadoNombreSeccion) > 0) {
            $errores['nombre_seccion'] = "Esta sección ya existe";
        }
    }

    if (!$color) {
        $errores['color'] = "Selecciona un color";
    } else {
        // Validar si el color de la sección ya existe en la base de datos
        $queryColor = "SELECT id FROM secciones WHERE color = '$color' LIMIT 1";
        $resultadoColor = mysqli_query($db, $queryColor);

        if (mysqli_num_rows($resultadoColor) > 0) {
            $errores['color'] = "Esta color ya está registrado";
        }
    }

    //* Revisar que el arreglo de errores esté vacio 
    if (!array_filter($errores)) {

        //* Insertar a la base de datos
        $query = "INSERT INTO secciones (nombre_seccion, color,registrado) 
        VALUES ('$nombre_seccion', '$color', NOW())";

        // echo $query;

        $resultado = mysqli_query($db, $query);

        if ($resultado) {
            // Redireccionar al usuario
            header("Location:../panel-secciones.php?resultado=1");
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
            <form class="book-form" method="POST" action="../secciones/crear-seccion.php" enctype="multipart/form-data">
                <h1>Registro de sección</h1>
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
                <button type="submit" class="btnAceptado"><i class="fas fa-save" style="margin-right: 5px;"></i>Registrar</button>
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