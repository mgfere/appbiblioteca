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
    header('Location: ../panel-libros.php');
}

//* Base de datos
require '../../includes/config/database.php';
$db = conectarDB();

//? Consulta para obtener los datos de los libros
$consultaLibros = "SELECT * FROM libros WHERE id = {$id}";
$resultadoLibros = mysqli_query($db, $consultaLibros);
$libro = mysqli_fetch_assoc($resultadoLibros);

//? Consulta para obtener las secciones
$consultaSecciones = "SELECT * FROM secciones";
$resultadoSecciones = mysqli_query($db, $consultaSecciones);

//? Consulta para obtener los idiomas
$consultaIdiomas = "SELECT * FROM idiomas";
$resultadoIdiomas = mysqli_query($db, $consultaIdiomas);

//* Arreglo con mensajes de errores
$errores = [
    'titulo' => '',
    'autor' => '',
    'idioma' => '',
    'codigo' => '',
    'editorial' => '',
    'seccion' => '',
    'imagen' => '',
    "isbn" => '',
    "edicion" => '',
    "adquisicion" => '',
    "reserva" => '',
    "status" => '',
];

//* Iniciando los valores de acuerdo a la información de la base de datos 
$titulo = $libro['titulo'];
$autor = $libro['autor'];
$idioma = $libro['idiomaId'];
$codigo = $libro['codigo'];
$editorial = $libro['editorial'];
$cantidad = $libro['cantidad'];
$status = $libro['status'];
$seccionId = $libro['seccionId'];
$isbn = $libro['isbn'];
$edicion = $libro['edicion'];
$tomo = $libro['tomo'];
$volumen = $libro['volumen'];
$adquisicion = $libro['adquisicion'];
$reserva = $libro['reserva'];
$titulos = $libro['titulos'];
$ejemplares = $libro['ejemplares'];
$imagenLibro = $libro['imagen'];

//* Ejecutar el código después de que el usuario envie el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // echo "<pre>";
    // var_dump($_POST);
    // echo "</pre>";

    // echo "<pre>";
    // var_dump($_FILES);
    // echo "</pre>";

    $titulo = mysqli_real_escape_string($db, $_POST["titulo"]);
    $autor = mysqli_real_escape_string($db, $_POST["autor"]);
    $idioma = mysqli_real_escape_string($db, $_POST["idioma"]);
    $codigo = mysqli_real_escape_string($db, $_POST["codigo"]);
    $editorial = mysqli_real_escape_string($db, $_POST["editorial"]);
    $cantidad = mysqli_real_escape_string($db, $_POST["cantidad"]);
    $status = mysqli_real_escape_string($db, $_POST["status"]);
    $seccionId = mysqli_real_escape_string($db, $_POST["seccion"]);
    $isbn = mysqli_real_escape_string($db, $_POST["isbn"]);
    $edicion = mysqli_real_escape_string($db, $_POST["edicion"]);
    $tomo = mysqli_real_escape_string($db, $_POST["tomo"]);
    $volumen = mysqli_real_escape_string($db, $_POST["volumen"]);
    $adquisicion = mysqli_real_escape_string($db, $_POST["adquisicion"]);
    $reserva = mysqli_real_escape_string($db, $_POST["reserva"]);
    $titulos = mysqli_real_escape_string($db, $_POST["titulos"]);
    $ejemplares = mysqli_real_escape_string($db, $_POST["ejemplares"]);

    //* Asignar files hacia una variable
    $imagen = $_FILES['imagen'];

    // var_dump();

    if (!$titulo) {
        $errores['titulo'] = "El título del libro es obligatorio";
    }

    if (!$autor) {
        $errores['autor'] = "El nombre del autor es obligatorio";
    }

    if (!$idioma) {
        $errores['idioma'] = "Elige un idioma";
    }

    if (!$codigo) {
        $errores['codigo'] = "El código es obligatorio";
    }

    if (!$editorial) {
        $errores['editorial'] = "El campo editorial es obligatorio";
    }

    if (!$seccionId) {
        $errores['seccion'] = "Elige una sección";
    }

    if (!$isbn) {
        $errores['isbn'] = "El ISBN es obligatorio";
    }

    if (!$edicion) {
        $errores['edicion'] = "El campo edición es obligatorio";
    }

    if (!$adquisicion) {
        $errores['adquisicion'] = "Eliga el tipo de adquisición";
    }

    if (!$reserva) {
        $errores['reserva'] = "Eliga el tipo de reserva";
    }

    if (!$status) {
        $errores['status'] = "Seleccione el status del libro";
    }


    //? Validar por tamaño (máximo 1MB)
    $medida = 1000 * 1000;

    if ($imagen['size'] > $medida) {
        $errores['imagen'] = "La imagen es muy pesada";
    }


    //* Revisar que el arreglo de errores esté vacio 
    if (!array_filter($errores)) {

        //  Crear una carpeta
        $carpetaImagenes = "../../imagenes/";

        if (!is_dir($carpetaImagenes)) {
            mkdir($carpetaImagenes);
        }

        $nombreImagen = '';

        //* Subida de archivos

        if ($imagen['name']) {
            // Eliminar la imagen previa
            unlink($carpetaImagenes . $libro['imagen']);

            // Generar un nombre único
            $nombreImagen = md5(uniqid(rand(), true)) . ".jpg";

            // Subir la imagen
            move_uploaded_file($imagen['tmp_name'], $carpetaImagenes . $nombreImagen);
        } else {
            $nombreImagen = $libro['imagen'];
        }

        //* Actualizar la base de datos
        $query = "UPDATE libros SET 
            titulo = UPPER('$titulo'), 
            autor = UPPER('$autor'), 
            imagen = '{$nombreImagen}',  
            codigo = UPPER('$codigo'), 
            editorial = UPPER('$editorial'), 
            cantidad = {$cantidad}, 
            status = '{$status}', 
            seccionId = {$seccionId}, 
            idiomaId = {$idioma}, 
            isbn = UPPER('$isbn'), 
            edicion = '{$edicion}', 
            titulos = '{$titulos}', 
            ejemplares = '{$ejemplares}', 
            tomo = '{$tomo}', 
            volumen = '{$volumen}', 
            adquisicion = '{$adquisicion}', 
            reserva = '{$reserva}', 
            actualizado = NOW() 
            WHERE id = {$id}";

        // echo $query;

        $resultado = mysqli_query($db, $query);

        if ($resultado) {
            // Redireccionar al usuario
            header("Location:../panel-libros.php?resultado=2");
        }
    }
}

incluirTemplate('sidebar-formularios');
?>

<!-- Contenido Principal -->
<div class="container main--content">
    <div class="tabular--wrapper">
        <div class="tabular--botones">
            <a href="../panel-libros.php">
                <button title="Volver" class="btnAgregar">
                    <i class="fas fa-arrow-left"></i> Volver
                </button>
            </a>
        </div>
        <div class="table--container">
            <form class="book-form" method="POST" enctype="multipart/form-data">
                <h1>Actualizar libro</h1>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="titulo">Título</label>
                        <input type="text" id="titulo" name="titulo" value="<?php echo $titulo; ?>" style="text-transform:capitalize;">
                        <?php if ($errores['titulo']): ?>
                            <div class="alerta error"><?php echo $errores['titulo']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="autor">Autor</label>
                        <input type="text" id="autor" name="autor" value="<?php echo $autor; ?>" style="text-transform:capitalize;">
                        <?php if ($errores['autor']): ?>
                            <div class="alerta error"><?php echo $errores['autor']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="imagen">Imagen</label>
                        <input type="file" id="imagen" name="imagen" accept="image/jpg,jpeg,">
                        <img id ="imagePreview"src="../../imagenes/<?php echo $imagenLibro; ?>" alt="" class="imagen-small">
                        <?php if ($errores['imagen']): ?>
                            <div class="alerta error"><?php echo $errores['imagen']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="idioma">Idioma</label>
                        <select id="idioma" name="idioma">
                            <option value="" disabled selected>Seleccione un idioma</option>
                            <?php while ($idiomas = mysqli_fetch_assoc($resultadoIdiomas)): ?>
                                <option <?php echo $idioma === $idiomas['id'] ? 'selected' : ''; ?> value="<?php echo $idiomas['id']; ?>"><?php echo $idiomas['idioma']; ?></option>
                            <?php endwhile ?>
                        </select>
                        <?php if ($errores['idioma']): ?>
                            <div class="alerta error"><?php echo $errores['idioma']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="codigo">Código</label>
                        <input type="text" id="codigo" name="codigo" value="<?php echo $codigo; ?>" style="text-transform:uppercase;">
                        <?php if ($errores['codigo']): ?>
                            <div class="alerta error"><?php echo $errores['codigo']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="editorial">Editorial</label>
                        <input type="text" id="editorial" name="editorial" value="<?php echo $editorial; ?>">
                        <?php if ($errores['editorial']): ?>
                            <div class="alerta error"><?php echo $errores['editorial']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="isbn">ISBN</label>
                        <input type="text" id="isbn" name="isbn" value="<?php echo $isbn; ?>">
                        <?php if ($errores['isbn']): ?>
                            <div class="alerta error"><?php echo $errores['isbn']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="edicion">Edición</label>
                        <input type="text" id="edicion" name="edicion" value="<?php echo $edicion; ?>">
                        <?php if ($errores['edicion']): ?>
                            <div class="alerta error"><?php echo $errores['edicion']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="tomo">Tomo</label>
                        <input type="text" id="tomo" name="tomo" value="<?php echo $tomo; ?>">
                    </div>
                    <div class="form-group">
                        <label for="volumen">Volumen</label>
                        <input type="text" id="volumen" name="volumen" value="<?php echo $volumen; ?>">
                    </div>
                    <div class="form-group">
                        <label for="cantidad">Disponibles</label>
                        <input type="number" min=0 id="cantidad" name="cantidad" value="<?php echo $cantidad; ?>">
                    </div>
                    <div class="form-group">
                        <label for="titulos">Títulos</label>
                        <input type="number" min=0 id="titulos" name="titulos" value="<?php echo $titulos; ?>">
                    </div>
                    <div class="form-group">
                        <label for="ejemplares">Ejemplares</label>
                        <input type="number" min=0 id="ejemplares" name="ejemplares" value="<?php echo $ejemplares; ?>">
                    </div>
                    <div class="form-group">
                        <label for="adquisicion">Tipo de adquisición</label>
                        <select name="adquisicion" id="adquisicion">
                            <option selected value="">--Seleccionar--</option>
                            <option <?php echo "Recurso propio" === $adquisicion ? 'selected' : ''; ?> value="Recurso propio">Recurso propio</option>
                            <option <?php echo "Donado" === $adquisicion ? 'selected' : ''; ?> value="Donado">Donado</option>
                        </select>
                        <?php if ($errores['adquisicion']): ?>
                            <div class="alerta error"><?php echo htmlspecialchars($errores['adquisicion']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="reserva">Tipo de reserva</label>
                        <select name="reserva" id="reserva">
                            <option selected value="">--Seleccionar--</option>
                            <option <?php echo "Aplica" === $reserva ? 'selected' : ''; ?> value="Aplica">Aplica</option>
                            <option <?php echo "No aplica" === $reserva ? 'selected' : ''; ?> value="No aplica">No aplica</option>
                        </select>
                        <?php if ($errores['reserva']): ?>
                            <div class="alerta error"><?php echo htmlspecialchars($errores['reserva']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="status">Estatus</label>
                        <select name="status" id="status">
                            <option selected value="">--Seleccionar--</option>
                            <option value="Activo" <?php echo $status === "Activo" ? "selected" : ""; ?>>Activo</option>
                            <option value="Inactivo" <?php echo $status === "Inactivo" ? "selected" : ""; ?>>Inactivo</option>
                        </select>
                        <?php if ($errores['status']): ?>
                            <div class="alerta error"><?php echo htmlspecialchars($errores['status']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="seccion">Sección</label>
                        <select id="seccion" name="seccion">
                            <option value="" disabled selected>Seleccione una sección</option>
                            <?php while ($seccion = mysqli_fetch_assoc($resultadoSecciones)): ?>
                                <option <?php echo $seccionId === $seccion['id'] ? 'selected' : ''; ?> value="<?php echo $seccion['id']; ?>"><?php echo $seccion['nombre_seccion']; ?></option>
                            <?php endwhile ?>
                        </select>
                        <?php if ($errores['seccion']): ?>
                            <div class="alerta error"><?php echo $errores['seccion']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="submit" class="btnAceptado"><i class="fas fa-save" style="margin-right: 5px;"></i>Guardar cambios</button>
            </form>
        </div>
    </div>
</div>

<script src="https://kit.fontawesome.com/7c36d3e4f1.js" crossorigin="anonymous"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
 document.getElementById('imagen').addEventListener('change', function(event) {
    var reader = new FileReader();
    reader.onload = function() {
        var output = document.getElementById('imagePreview');
        output.src = reader.result;
        output.style.display = 'block';
    }
    reader.readAsDataURL(event.target.files[0]);
});
</script>
<?php
incluirTemplate('footer');
?>