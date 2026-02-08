<?php
require '../../includes/funciones.php';

$auth = adminAutenticado();

if (!$auth) {
    header('Location: ../login.php');
    exit;
}

require '../../includes/config/database.php';
$db = conectarDB();

$consultaSecciones = "SELECT * FROM secciones";
$resultadoSecciones = mysqli_query($db, $consultaSecciones);
$consultaIdiomas = "SELECT * FROM idiomas";
$resultadoIdiomas = mysqli_query($db, $consultaIdiomas);

$errores = [
    'titulo' => '',
    'autor' => '',
    'idioma' => '',
    'codigo' => '',
    'editorial' => '',
    'cantidad' => '',
    'seccion' => '',
    'imagen' => '',
    'isbn' => '',
    'edicion' => '',
    'adquisicion' => '',
    'reserva' => '',
    'status' => '',
];

$titulo = "";
$autor = "";
$idioma = "";
$codigo = "";
$editorial = "";
$cantidad = "";
$status = "";
$seccionId = "";
$isbn = "";
$edicion = "";
$tomo = "";
$volumen = "";
$adquisicion = "";
$reserva = "";
$titulos = "";
$ejemplares = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $titulo = mysqli_real_escape_string($db, $_POST["titulo"] ?? '');
    $autor = mysqli_real_escape_string($db, $_POST["autor"] ?? '');
    $idioma = mysqli_real_escape_string($db, $_POST["idioma"] ?? '');
    $codigo = mysqli_real_escape_string($db, $_POST["codigo"] ?? '');
    $editorial = mysqli_real_escape_string($db, $_POST["editorial"] ?? '');
    $cantidad = mysqli_real_escape_string($db, $_POST["cantidad"] ?? '');
    $status = mysqli_real_escape_string($db, $_POST["status"] ?? '');
    $seccionId = mysqli_real_escape_string($db, $_POST["seccion"] ?? '');
    $isbn = mysqli_real_escape_string($db, $_POST["isbn"] ?? '');
    $edicion = mysqli_real_escape_string($db, $_POST["edicion"] ?? '');
    $tomo = mysqli_real_escape_string($db, $_POST["tomo"] ?? '');
    $volumen = mysqli_real_escape_string($db, $_POST["volumen"] ?? '');
    $adquisicion = mysqli_real_escape_string($db, $_POST["adquisicion"] ?? '');
    $reserva = mysqli_real_escape_string($db, $_POST["reserva"] ?? '');
    $titulos = mysqli_real_escape_string($db, $_POST["titulos"] ?? '');
    $ejemplares = mysqli_real_escape_string($db, $_POST["ejemplares"] ?? '');

    $imagen = $_FILES['imagen'];

    if (!$titulo) $errores['titulo'] = "El título es obligatorio";
    if (!$autor) $errores['autor'] = "El autor es obligatorio";
    if (!$idioma) $errores['idioma'] = "Elige un idioma";
    if (!$codigo) {
        $errores['codigo'] = "El código es obligatorio";
    } else {
        $queryCodigo = "SELECT id FROM libros WHERE codigo = '$codigo' AND seccionId = '$seccionId' LIMIT 1";
        $resultadoCodigo = mysqli_query($db, $queryCodigo);
        if ($resultadoCodigo && mysqli_num_rows($resultadoCodigo) > 0) {
            $errores['codigo'] = "Ese código ya existe en la sección seleccionada";
        }
    }
    if (!$editorial) $errores['editorial'] = "La editorial es obligatoria";
    if (!$cantidad) $errores['cantidad'] = "La cantidad es obligatoria";
    if (!$seccionId) $errores['seccion'] = "Elige una sección";
    if (!$isbn) $errores['isbn'] = "El ISBN es obligatorio";
    if (!$edicion) $errores['edicion'] = "La edición es obligatoria";
    if (!$adquisicion) $errores['adquisicion'] = "Elige el tipo de adquisición";
    if (!$reserva) $errores['reserva'] = "Elige el tipo de reserva";
    if (!$status) $errores['status'] = "Selecciona el estatus del libro";

    // Validación de imagen
    if (!$imagen['name'] || $imagen['error']) {
        $errores['imagen'] = "La imagen es obligatoria";
    } else {
        $medida = 5000 * 5000;
        if ($imagen['size'] > $medida) {
            $errores['imagen'] = "La imagen es muy pesada (máximo 5MB)";
        }
    }

    if (empty(array_filter($errores))) {


        $carpetaImagenes = "../../imagenes/";
        $nombreImagen = '';

        if (!is_dir($carpetaImagenes)) {
            if (!mkdir($carpetaImagenes, 0755, true)) {
                $errores['imagen'] = "Error fatal: No se pudo crear la carpeta de imágenes. Revisa los permisos en el servidor.";
            }
        }

        if (empty($errores['imagen'])) {
            $nombreImagen = md5(uniqid(rand(), true)) . ".jpg";
            $rutaCompleta = $carpetaImagenes . $nombreImagen;

            if (!move_uploaded_file($imagen['tmp_name'], $rutaCompleta)) {
                $errores['imagen'] = "Error al subir la imagen. Posiblemente un problema de permisos en la carpeta del servidor.";
                $nombreImagen = '';
            }
        }


        if (empty(array_filter($errores))) {
            for ($i = 0; $i < $ejemplares; $i++) {
                $codigo_libro = sprintf("%06d", (int)$codigo + $i);
                $current_titulos = ($i == $ejemplares - 1) ? $titulos : 0;
                $current_ejemplares = ($i == $ejemplares - 1) ? $ejemplares : 0;

                $query = "INSERT INTO libros (titulo, autor, imagen, codigo, editorial, isbn, edicion, tomo, volumen, cantidad, titulos, ejemplares, adquisicion, reserva, status, seccionId, idiomaId, registrado, ImagenCodigoDeBarras) VALUES (UPPER('$titulo'), UPPER('$autor'), '$nombreImagen', UPPER('$codigo_libro'), UPPER('$editorial'), UPPER('$isbn'), '$edicion', '$tomo', '$volumen', '$cantidad', '$current_titulos', '$current_ejemplares', '$adquisicion', '$reserva', '$status', '$seccionId', '$idioma', NOW(), '')";
                $resultado = mysqli_query($db, $query);
            }

            if ($resultado) {
                header("Location:../panel-libros.php?resultado=1");
                exit;
            }
        }
    }
}

incluirTemplate('sidebar-formularios');
?>

<script src="https://kit.fontawesome.com/2c36e9b7b1.js" crossorigin="anonymous"></script>
<script    src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<style>
  #imagePreview {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            margin-top: 15px;
            display: none;

        }
    @media (max-width: 600px) {
        #imagePreview {
            max-width: 100%;
            height: auto;
        }
    }

    </style>

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
            <form class="book-form" method="POST" action="../libros/crear-libro.php" enctype="multipart/form-data">
                <h1>Registro de libro</h1>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="titulo">Título</label>
                        <input type="text" id="titulo" name="titulo" value="<?php echo $titulo; ?>">
                        <?php if ($errores['titulo']): ?>
                            <div class="alerta error"><?php echo $errores['titulo']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="autor">Autor</label>
                        <input type="text" id="autor" name="autor" value="<?php echo $autor; ?>">
                        <?php if ($errores['autor']): ?>
                            <div class="alerta error"><?php echo $errores['autor']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="imagen">Imagen</label>
                        <input type="file" id="imagen" name="imagen" accept="image/jpg,jpeg,">
                        <?php if ($errores['imagen']): ?>
                            <div class="alerta error"><?php echo $errores['imagen']; ?></div>
                        <?php endif; ?>
                        <img id="imagePreview" src="#" alt="Previsualización" style="display:none; max-width: 150px; max-height: 150px;">
                    </div>
                    <div class="form-group">
                        <label for="idioma">Idioma</label>
                        <select id="idioma" name="idioma">
                            <option selected value="">--Seleccionar--</option>
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
                        <input type="text" id="codigo" name="codigo" value="<?php echo $codigo; ?>" placeholder="Selecciona una Sección" style="text-transform: uppercase;">
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
                        <input type="text" id="edicion" name="edicion" value="N/A">
                        <?php if ($errores['edicion']): ?>
                            <div class="alerta error"><?php echo $errores['edicion']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="tomo">Tomo</label>
                        <input type="text" id="tomo" name="tomo" value="N/A">
                    </div>
                    <div class="form-group">
                        <label for="volumen">Volumen</label>
                        <input type="text" id="volumen" name="volumen" value="N/A">
                    </div>
                    <div class="form-group">
                        <label for="cantidad">Disponibles</label>
                        <input type="number" min=0 id="cantidad" name="cantidad" value="1">
                        <?php if ($errores['cantidad']): ?>
                            <div class="alerta error"><?php echo $errores['cantidad']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="titulos">Títulos</label>
                        <input type="number" min=0 id="titulos" name="titulos" value="1">
                    </div>
                    <div class="form-group">
                        <label for="ejemplares">Ejemplares</label>
                        <input type="number" min=0 id="ejemplares" name="ejemplares" value="<?php echo $ejemplares; ?>">
                    </div>
                    <div class="form-group">
                        <label for="adquisicion">Tipo de adquisición</label>
                        <select name="adquisicion" id="adquisicion">
                            <option selected value="">--Seleccionar--</option>
                            <option <?php echo "Recurso propio" === $adquisicion ? 'selected' : ''; ?> selected value="Recurso propio">Recurso propio</option>
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
                            <option <?php echo "No aplica" === $reserva ? 'selected' : ''; ?> selected value="No aplica">No aplica</option>
                        </select>
                        <?php if ($errores['reserva']): ?>
                            <div class="alerta error"><?php echo htmlspecialchars($errores['reserva']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="status">Estatus</label>
                        <select name="status" id="status">
                            <option selected value="">--Seleccionar--</option>
                            <option value="Activo" <?php echo $status === "Activo" ? "selected" : ""; ?> selected>Activo</option>
                            <option value="Inactivo" <?php echo $status === "Inactivo" ? "selected" : ""; ?>>Inactivo</option>
                        </select>
                        <?php if ($errores['status']): ?>
                            <div class="alerta error"><?php echo htmlspecialchars($errores['status']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="seccion">Sección</label>
                        <select id="seccion" name="seccion">
                            <option selected value="">--Seleccionar--</option>
                            <?php while ($seccion = mysqli_fetch_assoc($resultadoSecciones)): ?>
                                <option <?php echo $seccionId === $seccion['id'] ? 'selected' : ''; ?> value="<?php echo $seccion['id']; ?>"><?php echo $seccion['nombre_seccion']; ?></option>
                            <?php endwhile ?>
                        </select>
                        <?php if ($errores['seccion']): ?>
                            <div class="alerta error"><?php echo $errores['seccion']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="submit" class="btnAceptado"><i class="fas fa-save" style="margin-right: 5px;"></i>Registrar</button>
            </form>
        </div>
    </div>
</div>
<script>
    //Funcion que nos permite previsualización de las imagenes subidas
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
<script>

    document.addEventListener('DOMContentLoaded', function() {
        
        const seccionSelect = document.getElementById('seccion');
        const codigoInput = document.getElementById('codigo');

        seccionSelect.addEventListener('change', function() {
            
            const seccionId = this.value;

            if (!seccionId) {
                codigoInput.value = '';
                return;
            }

            fetch(`obtener-siguiente-codigo.php?seccionId=${seccionId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Hubo un problema con la respuesta del servidor.');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.siguiente_codigo) {
                        codigoInput.value = data.siguiente_codigo;
                    }
                })
                .catch(error => {
                    console.error('Error al obtener el código:', error);
                    codigoInput.value = 'Error';
                });
        });
    });
</script>

<?php
    incluirTemplate('footer');
?>
<?php
incluirTemplate('footer');
?>