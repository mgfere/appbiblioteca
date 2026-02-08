<?php
require '../includes/funciones.php'; 
$auth = adminAutenticado();
if (!$auth) {
    header('Location: login.php');
    exit;
}
$nombreAdministrador = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario';
$rolAdministrador = isset($_SESSION['rol']) ? $_SESSION['rol'] : null;
$idAdministrador = isset($_SESSION['id']) ? $_SESSION['id'] : null;
require '../includes/config/database.php';
$db = conectarDB();

// Obtener secciones
$secciones = [];
$resSecciones = mysqli_query($db, "SELECT id, nombre_seccion FROM secciones ORDER BY id ASC");
while ($row = mysqli_fetch_assoc($resSecciones)) {
    $secciones[] = $row;
}
// Obtener libros con sección
$libros = [];
$resLibros = mysqli_query($db, "SELECT id, titulo, codigo, editorial, seccionId FROM libros WHERE CodigoDeBarras = 0 ORDER BY codigo ASC");
while ($row = mysqli_fetch_assoc($resLibros)) {
    $libros[] = $row;
}

$mensaje = '';
$imagenGenerada = '';if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_codigo'])) {
    $libroId = $_POST['libro'];

    if (empty($libroId)) {
        $mensaje = "Por favor, selecciona un libro para generar el código de barras.";
    } else {
        $res = mysqli_query($db, "SELECT codigo, titulo FROM libros WHERE id = $libroId");
        $libro = mysqli_fetch_assoc($res);

        if ($libro) {
            $codigo = $libro['codigo'];
            $titulo = $libro['titulo'];

            $codigoLimpio = preg_replace('/[^A-Za-z0-9_\-]/', '_', $codigo);
            $codigoLimpio = preg_replace('/_+/', '_', $codigoLimpio);
            $codigoLimpio = trim($codigoLimpio, '_');

            $tituloLimpio = preg_replace('/[^A-Za-z0-9_\-]/', '_', $titulo);
            $tituloLimpio = preg_replace('/_+/', '_', $tituloLimpio);
            $tituloLimpio = trim($tituloLimpio, '_');

            $nombreImagen = $tituloLimpio . '_' . $codigoLimpio . '.png';

            $basePath = realpath(__DIR__ . '/..');
            $javaPath = $basePath . '/JavaBarcode';
            $binPath = $javaPath . '/bin';
            $libPath = $javaPath . '/lib/*';
            
            // --- MODIFICACIÓN: Aquí se crea la ruta absoluta al archivo .env
            $envPath = $basePath . '/.env'; 

            $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
            $classpathSeparator = $isWindows ? ';' : ':';

            $rutaImagenCompleta = $basePath . '/imagenes/' . $nombreImagen;
            $imagenGenerada = '../imagenes/' . $nombreImagen;

            // --- MODIFICACIÓN: Se añade la ruta del .env como un argumento más
            $cmd = 'java -cp ' . escapeshellarg($binPath . $classpathSeparator . $libPath)
                . ' BarcodeGeneratorApp '
                . escapeshellarg($codigo) . ' '
                . escapeshellarg($titulo) . ' '
                . escapeshellarg($rutaImagenCompleta) . ' '
                . escapeshellarg($envPath) . ' 2>&1'; // ¡Aquí se pasa la ruta del .env!

            $output = [];
            $status = 0;

            exec($cmd, $output, $status);
            $errorOutput = implode(PHP_EOL, $output);

            if ($status === 0 && file_exists($rutaImagenCompleta)) {
                $mensaje = "Código de barras generado exitosamente.";

                $stmt = $db->prepare("UPDATE libros SET CodigoDeBarras = 1, ImagenCodigoDeBarras = ? WHERE id = ?");
                $stmt->bind_param("si", $nombreImagen, $libroId);
                if (!$stmt->execute()) {
                    $mensaje .= "<br>Error al actualizar la base de datos: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $mensaje = "Error al generar el código de barras.<br><pre>$errorOutput</pre>";
                $archivos = scandir($basePath . '/imagenes/');
                $mensaje .= "<br>Archivos en ../imagenes/: " . implode(', ', $archivos);
            }
        } else {
            $mensaje = "Error: El libro seleccionado no fue encontrado.";
        }
    }
}

incluirTemplate('sidebar');
?>
<style>
/* Estilos generales */
.tabular-wrapper h2 {
    margin-top: 0;
    color: #09a787;
    font-size: 24px;
    font-weight: 600;
}
.btn-volver-wrapper {
    margin: 20px;
}

.btn-volver {
    background-color: #0978a7;
    color: white;
    padding: 10px 20px;
    border: none;
    margin:10px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 16px;
    margin-top: 20px;
    text-decoration: none; /* Asegura que no se subraye */
    display: inline-block; /* Para que padding y margin funcionen como en un botón */
}

a {
    /* Esto es para todos los enlaces, pero los botones tendrán su propio color */
    color:white !important; /* Changed to inherit so button styles can override */
}

.btn-volver:hover {
    background-color:rgb(111, 159, 161);
}
.tabular-wrapper { 
    width: 100%; 
    margin-top: 20px; 
    padding: 20px; 
    background-color: #f8f9fa; 
    border-radius: 8px; 
    box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
    clear: both; 
}
    
.tabular-wrapper label { 
    display: block; 
    font-weight: 600; 
    color: #333;
    margin-bottom: 10px; 
    font-size: 14px; 
}
.tabular-wrapper select {
    width: 100%; 
    padding: 12px 15px; 
    border: 2px solid #ddd; 
    border-radius: 6px; 
    font-size: 14px; 
    background-color: white; 
    color: #333; 
    transition: border-color 0.3s ease, box-shadow 0.3s ease; 
    margin-bottom: 15px; 
    appearance: none; 
    background-image: url('data:image/svg+xml;charset=US-ASCII,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 4 5\'><path fill=\'%23666\' d=\'M2 0L0 2h4zm0 5L0 3h4z\'/></svg>'); 
    background-repeat: no-repeat; 
    background-position: right 12px center; 
    background-size: 12px; 
    cursor: pointer; 
}
.tabular-wrapper select:hover {
    border-color: #09a787;
}
.tabular-wrapper select:focus { 
    outline: none; 
    border-color: #09a787;
    box-shadow: 0 0 0 3px rgba(9,167,135,0.1); 
}
.tabular-wrapper button[type="submit"] { /* Estilo para el botón de "Generar Código de Barras" */
    background-color: #09a787;
    color: white; 
    border: none; 
    padding: 12px 24px; 
    border-radius: 6px; 
    font-size: 14px; 
    font-weight: 600; 
    cursor: pointer; 
    transition: background-color 0.3s ease, transform 0.2s ease;
    box-shadow: 0 2px 4px rgba(9,167,135,0.2);
    margin: 20px 0; /* Ajuste el margen superior e inferior */
    display: inline-flex; /* Para centrar icono y texto */
    align-items: center;
    justify-content: center;
}
.tabular-wrapper button[type="submit"] i { /* Espacio para el ícono en el botón de generar */
    margin-right: 8px;
}
.tabular-wrapper button[type="submit"]:hover { 
    background-color:rgb(61,180,148); 
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(9,167,135,0.3);
}
.tabular-wrapper button[type="submit"]:active { 
    transform: translateY(0); 
}

/* ESTILO PARA EL ÚNICO BOTÓN DE DESCARGA (PDF) */
.btn-descarga-pdf {
    background-color: #dc3545; /* Rojo de "peligro" o "PDF" */
    color: white !important; /* Asegura que el color del texto sea blanco */
    border: none;
    padding: 12px 24px;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
    box-shadow: 0 3px 6px rgba(0,0,0,0.2);
    display: inline-flex; /* Para centrar el icono y texto */
    align-items: center;
    justify-content: center;
    text-decoration: none; /* Eliminar subrayado */
    margin-top: 15px; /* Más espacio arriba */
    min-width: 180px; /* Ancho mínimo */
}

.btn-descarga-pdf i {
    margin-right: 8px; /* Espacio entre el icono y el texto */
    font-size: 18px; /* Icono un poco más grande */
}

.btn-descarga-pdf:hover {
    background-color: #c82333; /* Tono más oscuro al pasar el mouse */
    transform: translateY(-2px); /* Un poco más de elevación */
    box-shadow: 0 6px 12px rgba(0,0,0,0.3); /* Sombra más grande */
}

.btn-descarga-pdf:active {
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

#barcode-area { 
    margin:20px;
    text-align: center;
}

@media (max-width: 768px) { 
    .tabular-wrapper { padding: 15px; margin-top: 15px; } 
    .tabular-wrapper select, 
    .tabular-wrapper button[type="submit"],
    .btn-descarga-pdf { 
        font-size: 13px; 
        padding: 10px 20px; 
        min-width: unset; 
    }
    .btn-descarga-pdf i {
        margin-right: 5px; 
        font-size: 16px; 
    }
}
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<div class="container main--content">
    <div class="header--wrapper">
        <div class="header--title">
            <span style="display: flex; border: 2.3px solid #09a787; padding: 2px; margin-bottom: 10px; border-radius: 5px; color: #09a787; width: 230px; text-transform: uppercase">
                <?php if ($rolAdministrador === '1') { echo 'Administrador general'; } else { echo 'Administrador'; } ?>
            </span>
            <span>Bienvenido, <?php echo htmlspecialchars($nombreAdministrador); ?></span>
            <h2>Generar Código de Barras</h2>
        </div>
        <div class="user--info">
            <div class="search--box">
                <i class="fas fa-search"></i>
                <input type="text" id="buscar" placeholder="Buscar" disabled />
            </div>
            <img src="../public/img/logouttn.png" alt="Foto de perfil" />
        </div>
    </div>
    <div class="tabular-wrapper">
        <div class="btn-volver-wrapper">
            <a href="panel-libros.php" class="btn-volver">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
        <div>
            <h2>Selecciona una sección y luego un libro para generar el código de barras.</h2>
            <form method="post">
                <label for="seccion">Selecciona una sección:</label>
                <select id="seccion" name="seccion">
                    <option value="">-- Todas las secciones --</option>
                    <?php foreach ($secciones as $seccion): ?>
                        <option value="<?php echo $seccion['id']; ?>"><?php echo htmlspecialchars($seccion['nombre_seccion']); ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="libro">Selecciona un libro:</label>
                <select id="libro" name="libro">
                    <option value="">-- Selecciona un libro --</option>
                    <?php foreach ($libros as $libro): ?>
                        <option value="<?php echo $libro['id']; ?>" data-codigo="<?php echo htmlspecialchars($libro['codigo']); ?>" data-titulo="<?php echo htmlspecialchars($libro['titulo']); ?>" data-seccion="<?php echo $libro['seccionId']; ?>">
                            <?php echo htmlspecialchars($libro['titulo']) . ' - ' . htmlspecialchars($libro['codigo']) . ' - ' . htmlspecialchars($libro['editorial']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" name="generar_codigo">
                    <i class="fas fa-barcode"></i> Generar Código de Barras
                </button>
            </form>
            <?php if ($mensaje): ?>
                <?php if ($imagenGenerada): ?>
                    <div id="barcode-area"> 
                        <p><?php echo $mensaje; ?></p>
                        <img src="<?php echo $imagenGenerada; ?>" alt="Código de Barras" style="margin-top: 20px; max-width: 300px;">
                        <br>
                        <a href="<?php echo $imagenGenerada; ?>" download style="color: #09a787; text-decoration: underline; margin-top: 10px; display: inline-block;">Descargar Código (PNG)</a>
                        <br>
                        <a href="../includes/DescargarPDF.php?img=<?php echo urlencode(basename($imagenGenerada)); ?>&type=barcode" class="btn-descarga-pdf">
                            <i class="fas fa-file-pdf"></i> Descargar Código (PDF)
                        </a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://rawgit.com/schmich/instascan-builds/master/instascan.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Funcionalidad para filtrar libros por sección
const selectSeccion = document.getElementById('seccion');
const selectLibro = document.getElementById('libro');
const allOptions = Array.from(selectLibro.options).slice(1); // Guardar todas las opciones excepto la primera

selectSeccion.addEventListener('change', function() {
    const seccionId = this.value;
    
    // Limpiar el select de libros
    selectLibro.innerHTML = '<option value="">-- Selecciona un libro --</option>';
    
    // Filtrar y mostrar libros según la sección seleccionada
    allOptions.forEach(opt => {
        if (!seccionId || opt.getAttribute('data-seccion') === seccionId) {
            selectLibro.appendChild(opt);
        }
    });
});

// Mensaje de SweetAlert para errores o éxitos
<?php if ($mensaje): ?>
    <?php 
    // Detectar si el mensaje indica éxito o error para el SweetAlert
    $swal_icon = (strpos($mensaje, '✅') !== false) ? 'success' : 'error';
    $swal_title = ($swal_icon === 'success') ? 'Éxito' : 'Error';
    $swal_text = str_replace('✅ ', '', $mensaje); // Limpiar el checkmark del mensaje
    ?>
    Swal.fire({
        icon: '<?php echo $swal_icon; ?>',
        title: '<?php echo $swal_title; ?>',
        html: '<?php echo $swal_text; ?>', // Usar html para <pre> si hay errorOutput
    });

    // Código para limpiar la imagen y resetear el select después de un éxito
    <?php if ($swal_icon === 'success'): ?>
        setTimeout(function() {
            var barcodeArea = document.getElementById('barcode-area');
            if (barcodeArea) {
                barcodeArea.innerHTML = ''; // Limpia todo el contenido del div
            }
            var libroSelect = document.getElementById('libro');
            if (libroSelect) {
                libroSelect.selectedIndex = 0; // Restablece la selección del libro
                // Opcional: recargar opciones para mostrar los libros que ya tienen código de barras ocultos
                // selectSeccion.dispatchEvent(new Event('change')); 
            }
        }, 60000); // 60 segundos
    <?php endif; ?>
<?php endif; ?>

// Manejo de SweetAlert para "selecciona un libro" antes de enviar el formulario
document.querySelector('form').addEventListener('submit', function(event) {
    const libroId = document.getElementById('libro').value;
    if (!libroId) {
        event.preventDefault(); // Detener el envío del formulario
        Swal.fire({ 
            icon: 'error', 
            title: 'Error de Selección', // Título más específico
            text: 'Por favor, selecciona un libro para generar el código de barras.', 
        });
    }
});
</script>
</body>
</html>