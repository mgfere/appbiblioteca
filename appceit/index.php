<?php
require 'includes/funciones.php';
$auth = usuarioAutenticado();

if (!$auth) {
    header('Location: principal.php');
    exit;
}

// Obtener el nombre del usuario de la sesión
$nombreUsuario = $_SESSION['usuario_nombre'] ?? '';
$idusuario = $_SESSION['usuario_id'] ?? 0;

if (!$auth || empty($nombreUsuario) || empty($idusuario)) {
    $_SESSION = [];
    session_destroy();
    
    header('Location: https://login.uttn.app/'); 
    exit;
}

// Convertir el nombre a minúsculas y luego aplicar ucfirst() a la primera letra
$nombreUsuario = ucwords(strtolower($nombreUsuario));

//* Importar la conexión 
require 'includes/config/database.php';
$db = conectarDB();
$db2 = conectarDB3();

//* Determinar la página actual
$busqueda = trim($_GET['busqueda'] ?? '');

//* Determinar la página actual
if ($busqueda) {
    $pagina = 1;
} else {
    $pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
}
//* Calcular el offset para la consulta SQL
$offset = ($pagina - 1) * 20;

//* Obtener la sección seleccionada
$seccionId = $_GET['seccion'] ?? null;
$seccionId = intval($seccionId);

$query = "SELECT 
    MIN(libros.id) AS id,
    libros.titulo,
    libros.imagen,
    libros.status,
    GROUP_CONCAT(DISTINCT libros.autor SEPARATOR ' / ') AS autores,
    GROUP_CONCAT(DISTINCT libros.codigo SEPARATOR ', ') AS codigos,
    MIN(secciones.nombre_seccion) AS seccion_nombre,
    MIN(secciones.color) AS seccion_color,
    COUNT(*) AS cantidad
FROM libros 
LEFT JOIN reservaciones ON libros.id = reservaciones.Libros_id
JOIN secciones ON libros.seccionId = secciones.id 
WHERE libros.status = 'Activo'
AND libros.id NOT IN (
      SELECT r.Libros_id 
      FROM reservaciones r
  )";

// Filtro de búsqueda
if (!empty($busqueda)) {
    $busqueda = mysqli_real_escape_string($db, $busqueda);
    // MODIFICACIÓN: Buscar tanto por título como por código
    $query .= " AND (libros.titulo LIKE '%$busqueda%' OR libros.codigo LIKE '%$busqueda%')";
}

// Filtro por sección
if ($seccionId) {
    $query .= " AND libros.seccionId = " . $seccionId;
}

// Agrupación y ordenación
$query .= " GROUP BY libros.titulo";
$query .= " ORDER BY libros.titulo ASC LIMIT 20 OFFSET " . $offset;

// Ejecutamos la consulta
$resultadoQuery = mysqli_query($db, $query);

//* Consulta para contar el número total de libros
// MODIFICACIÓN: Corregir la consulta de conteo
$countQuery = "
    SELECT COUNT(DISTINCT titulo) AS total_libros 
    FROM libros
    WHERE status = 'Activo'";

// Añadir filtros a la consulta de conteo
if ($seccionId) {
    $countQuery .= " AND seccionId = " . $seccionId;
}

if (!empty($busqueda)) {
    $busquedaEscaped = mysqli_real_escape_string($db, $busqueda);
    $countQuery .= " AND (titulo LIKE '%$busquedaEscaped%' OR codigo LIKE '%$busquedaEscaped%')";
}

$resultadoCount = mysqli_query($db, $countQuery);
$totalLibros = mysqli_fetch_assoc($resultadoCount)['total_libros'];

//* Calcular el número total de páginas
if ($totalLibros <= 20) {
    $totalPaginas = 1;
} else {
    $totalPaginas = ceil($totalLibros / 20);
}

//? Consulta para obtener las secciones
$consultaSecciones = "SELECT * FROM secciones";
$resultadoSecciones = mysqli_query($db, $consultaSecciones);

//* Verificar si hay libros activos 
$allInactive = (mysqli_num_rows($resultadoQuery) === 0);
mysqli_data_seek($resultadoQuery, 0);

incluirTemplate('header-user');
?>

<style>
    .mensaje-reservar {
        font-size: 20px;
        color: #666;
        margin-bottom: 20px;
        text-align: center;
        font-weight: 600;
    }

    @media (max-width: 991px) {
        .menu #menu_input:checked~.navbar { 
            display: initial;
            z-index: 1 !important;
        }
    }
    /* Contenedor del input y botón de búsqueda */
.search-input-wrapper {
    display: flex;
    align-items: center;
}

/* Input de búsqueda */
#busqueda_bars-input {
    border-radius: 5px 0 0 5px;
    border-right: none;
    padding: 10px;
    width: 300px;
    height: 40px;
    box-sizing: border-box;
    border: 1px solid #ccc;
}

/* Botón de búsqueda */
.search-btn {
    background: #1ab192 !important;
    border: none !important;
    border-radius: 0 5px 5px 0 !important;
    cursor: pointer;
    color: white !important;
    transition: background 0.3s;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    min-width: 50px;
    height: 40px !important;
    box-sizing: border-box;
    position: static !important;
    right: auto !important;
    width: auto !important;
    font-size: 14px !important;
}

.search-btn:hover {
    background: #148c70;
}

.search-btn i {
    font-size: 16px;
    line-height: 1;
}

/* Botón clear (X) */
.clear-btn {
    background: #dc3545 !important;
    border: none !important;
    border-radius: 5px !important;
    cursor: pointer;
    color: white !important;
    text-decoration: none !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    width: 40px !important;
    height: 40px !important;
    margin-left: 5px !important;
    position: static !important;
    font-size: 14px !important;
    transition: background 0.3s;
}

.clear-btn:hover {
    background: #c82333 !important;
}

.clear-btn i {
    font-size: 14px;
    line-height: 1;
}
</style>

<!-- Input de Búsqueda y Géneros -->
<div class="container busqueda">
    <h1>Hola, <?php echo $nombreUsuario; ?></h1>
    <h2 class="mensaje-reservar">Haz click en un libro para hacer una reserva</h2>
    <div class="busqueda_container">
        <form method="GET" action="">
            <select name="seccion" id="secciion" onchange="this.form.submit()">
                <option value="">Todas las secciones</option>
                <?php while ($seccion = mysqli_fetch_assoc($resultadoSecciones)): ?>
                    <option value="<?php echo $seccion['id']; ?>" <?php if ($seccion['id'] == $seccionId) echo 'selected'; ?>>
                        <?php echo $seccion['nombre_seccion']; ?>
                    </option>
                <?php endwhile ?>
            </select>
        </form>
        <form method="GET" action="" class="search-form" id="searchForm">
            <?php if ($seccionId): ?>
                <input type="hidden" name="seccion" value="<?php echo $seccionId; ?>">
            <?php endif; ?>
            <input type="hidden" name="pagina" value="1">

            <div class="search-input-wrapper">
                <input
                    name="busqueda"
                    id="busqueda_bars-input"
                    type="text"
                    placeholder="Busca un libro por título o código"
                    value="<?php echo htmlspecialchars($busqueda); ?>" 
                    onkeypress="if(event.keyCode == 13) { event.preventDefault(); }" />
                <button type="submit" class="search-btn">
                    <i class="fa fa-search" aria-hidden="true"></i>
                </button>
                <?php if (!empty($busqueda)): ?>
                    <a href="?<?php echo $seccionId ? 'seccion=' . $seccionId : ''; ?>" class="clear-btn">
                        <i class="fa fa-times" aria-hidden="true"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if ($allInactive && empty($busqueda) && !$seccionId): ?>
    <div class="container mensaje-container">
        <p class="mensaje">El catálogo de libros está deshabilitado por inventario</p>
    </div>
<?php elseif (mysqli_num_rows($resultadoQuery) === 0): ?>
    <div class="container mensaje-container">
        <p class="mensaje">No se encontraron resultados para "<?php echo htmlspecialchars($busqueda); ?>"</p>
    </div>
<?php else: ?>
    <!-- Libros -->
    <div class="container">
        <div class="libros_contenedor" id="libros_contenedor">
            <?php while ($libro = mysqli_fetch_assoc($resultadoQuery)): ?>
                <a href="detalle-libro.php?id=<?php echo $libro['id']; ?>" title="<?php echo htmlspecialchars($libro['titulo']); ?>">
                    <div class="libro-item">
                        <div class="libro-img">
                            <?php if (!empty($libro['imagen'])): ?>
                                <img src="imagenes/<?php echo htmlspecialchars($libro['imagen']); ?>" alt="<?php echo htmlspecialchars($libro['titulo']); ?>" />
                            <?php else: ?>
                                <div class="imagen-placeholder">Sin imagen</div>
                            <?php endif; ?>
                        </div>
                        <div class="libro-info">
                            <h3><?php echo htmlspecialchars($libro['titulo']); ?></h3>
                            <p><?php echo htmlspecialchars($libro['autores']); ?></p>
                            <p class="codigo-libro">Código: <?php echo htmlspecialchars($libro['codigos']); ?></p>
                        </div>
                    </div>
                </a>
            <?php endwhile; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Paginación -->
<div class="paginacion_contenedor">
    <?php if ($pagina > 1): ?>
        <a href="?pagina=<?php echo $pagina - 1; ?><?php if ($seccionId) echo "&seccion=" . $seccionId; ?><?php if (!empty($busqueda)) echo "&busqueda=" . urlencode($busqueda); ?>">&laquo;</a>
    <?php endif; ?>

    <?php
    // Número máximo de enlaces de página que se mostrarán
    $maxLinks = 3;

    // Calcula el rango de páginas a mostrar
    $start = max(1, $pagina - floor($maxLinks / 2));
    $end = min($totalPaginas, $start + $maxLinks - 1);

    if ($start > 1) {
        echo '<a href="?pagina=1' . ($seccionId ? "&seccion=" . $seccionId : "") . (!empty($busqueda) ? "&busqueda=" . urlencode($busqueda) : "") . '">1</a>';
        if ($start > 2) {
            echo '<span>...</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++): ?>
        <a href="?pagina=<?php echo $i; ?><?php if ($seccionId) echo "&seccion=" . $seccionId; ?><?php if (!empty($busqueda)) echo "&busqueda=" . urlencode($busqueda); ?>" class="<?php if ($i == $pagina) echo 'active'; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>

    <?php
    if ($end < $totalPaginas) {
        if ($end < $totalPaginas - 1) {
            echo '<span>...</span>';
        }
        echo '<a href="?pagina=' . $totalPaginas . ($seccionId ? "&seccion=" . $seccionId : "") . (!empty($busqueda) ? "&busqueda=" . urlencode($busqueda) : "") . '">' . $totalPaginas . '</a>';
    }
    ?>

    <?php if ($pagina < $totalPaginas): ?>
        <a href="?pagina=<?php echo $pagina + 1; ?><?php if ($seccionId) echo "&seccion=" . $seccionId; ?><?php if (!empty($busqueda)) echo "&busqueda=" . urlencode($busqueda); ?>">&raquo;</a>
    <?php endif; ?>
</div>

<script>
// JavaScript para prevenir la búsqueda automática al escribir
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('busqueda_bars-input');
    const searchForm = document.getElementById('searchForm');
    
    // Prevenir que el formulario se envíe al presionar Enter
    searchInput.addEventListener('keypress', function(e) {
        if (e.keyCode === 13) {
            e.preventDefault();
        }
    });
    
    // Forzar el envío del formulario solo al hacer clic en el botón
    document.querySelector('.search-btn').addEventListener('click', function() {
        searchForm.submit();
    });
});
</script>

<?php
mysqli_close($db);

incluirTemplate('footer');
?>