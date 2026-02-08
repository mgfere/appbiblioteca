<?php
require '../includes/funciones.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$auth = adminAutenticado();
if (!$auth) {
    header('Location: login.php');
    exit;
}

$nombreAdministrador = $_SESSION['nombre'] ?? 'Usuario';
$rolAdministrador = (int) $_SESSION['rol'];
$idAdministrador = $_SESSION['id'] ?? null;

require '../includes/config/database.php';
$db_mysql = conectarDB();
$conn_sqlsrv = conectarDB2();
$conn_gestion = conectarDB3();

//* Obtener el límite de registros por página (por defecto 20)
$limite = isset($_GET['limite']) ? (int) $_GET['limite'] : 20;
// Validar que el límite esté entre los valores permitidos
$limitesPermitidos = [5, 10, 15, 20, 25, 30, 40, 50];
if (!in_array($limite, $limitesPermitidos)) {
    $limite = 20;
}

//* Determinar la página actual
$pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;

//* Calcular el offset para la consulta SQL
$offset = ($pagina - 1) * $limite;

//* Obtener la sección seleccionada
$seccionId = $_GET['seccion'] ?? null;

//* Obtener el estado seleccionado
$statusId = $_GET['status'] ?? null;

// Parámetros de ordenamiento
$sortColumn = $_GET['sort'] ?? 'fecha_prestamo';
$sortDirection = $_GET['dir'] ?? 'desc';

// Columnas permitidas para ordenar
$allowedColumns = [
    'fecha_prestamo',
    'fecha_devolucion',
    'status',
    'codigo',
    'cantidad',
    'usuario',
    'tipo',
    'carrera',
    'matricula',
    'entregado'
];

if (!in_array($sortColumn, $allowedColumns)) {
    $sortColumn = 'fecha_prestamo';
}
$sortDirection = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';
$query_prestamos_mysql = "SELECT 
                            p.id, p.fecha_prestamo, p.fecha_devolucion, p.status, p.cantidad, 
                            p.entregado, p.Estudiantes_id, p.Libros_id, p.matricula, 
                            l.codigo,
                            s.color
                          FROM prestamos p
                          JOIN libros l ON p.Libros_id = l.id
                          JOIN secciones s ON l.seccionId = s.id
                          WHERE p.status = '1'
                          ORDER BY p.fecha_prestamo DESC";

$resultado_prestamos_mysql = mysqli_query($db_mysql, $query_prestamos_mysql);
if ($resultado_prestamos_mysql === false) {
    die("Error en consulta MySQL de préstamos: " . mysqli_error($db_mysql));
}

$prestamos_mysql = [];
$ids_gestion = []; 
$ids_local = [];

while ($prestamo = mysqli_fetch_assoc($resultado_prestamos_mysql)) {
    $prestamos_mysql[] = $prestamo;
    
    
    if (!empty($prestamo['matricula']) && $prestamo['matricula'] != '0') {
        $ids_gestion[] = $prestamo['Estudiantes_id'];
    } else {
        $ids_local[] = $prestamo['Estudiantes_id'];
    }
}


$mapa_usuarios = [];

if (!empty($ids_gestion)) {
    $ids_unicos = array_values(array_unique($ids_gestion));
    
    $placeholders = implode(',', array_fill(0, count($ids_unicos), '?'));
    $params = array_merge($ids_unicos, $ids_unicos);

    $query_gestion = "SELECT 
                        'alumno' as tipo,
                        IdAlumno as IdPersona,
                        Matricula,
                        Nombre as Nom,
                        ApellidoPaterno as Paterno,
                        ApellidoMaterno as Materno,
                        IdCarrera
                    FROM [GestionUsuarios].[dbo].[Alumnos] 
                    WHERE IdAlumno IN ($placeholders)
                    
                    UNION ALL
                    
                    SELECT 
                        'docente' as tipo,
                        IdDocente as IdPersona,
                        Matricula,
                        Nombre as Nom,
                        ApellidoPaterno as Paterno,
                        ApellidoMaterno as Materno,
                        NULL as IdCarrera
                    FROM [GestionUsuarios].[dbo].[Docentes] 
                    WHERE IdDocente IN ($placeholders)";

    $resultado_gestion = sqlsrv_query($conn_gestion, $query_gestion, $params);

    if ($resultado_gestion) {
        while ($row = sqlsrv_fetch_array($resultado_gestion, SQLSRV_FETCH_ASSOC)) {
            $mapa_usuarios[$row['IdPersona']] = $row;
        }
    }
}

if (!empty($ids_local)) {
    $ids_unicos_local = array_values(array_unique($ids_local));
    $ids_string = implode(',', array_map('intval', $ids_unicos_local));

    if (!empty($ids_string)) {

        $query_local = "SELECT 
                            id as IdPersona,
                            nombre as Nom,
                            apellido as Paterno,
                            '' as Materno,         
                            matricula as Matricula,
                            carreraId as IdCarrera,
                            'Sistema Viejo' as tipo    
                        FROM usuarios 
                        WHERE id IN ($ids_string)";
        
        $resultado_local = mysqli_query($db_mysql, $query_local);
        
        if ($resultado_local) {
            while ($row = mysqli_fetch_assoc($resultado_local)) {
                if (empty($row['Matricula'])) {
                    $row['Matricula'] = 'S/M'; 
                }
                // Los agregamos al MISMO mapa
                $mapa_usuarios[$row['IdPersona']] = $row;
            }
        }
    }
}






$carreras_map = [];
$query_carreras = "SELECT IdCarrera, Nombre FROM [GestionUsuarios].[dbo].[Carreras]";
$res_carreras = sqlsrv_query($conn_gestion, $query_carreras);
if ($res_carreras) {
    while ($row = sqlsrv_fetch_array($res_carreras, SQLSRV_FETCH_ASSOC)) {
        $carreras_map[$row['IdCarrera']] = $row['Nombre'];
    }
}

$prestamos_completos = [];
foreach ($prestamos_mysql as $prestamo) {
    $estudiante_id = $prestamo['Estudiantes_id'];
    $usuario = $mapa_usuarios[$estudiante_id] ?? null;

    if ($usuario) {
        $nombre_completo = trim(($usuario['Nom'] ?? '') . ' ' . ($usuario['Paterno'] ?? '') . ' ' . ($usuario['Materno'] ?? ''));
        $tipo_usuario = $usuario['tipo'] ?? 'desconocido';
        $id_carrera = $usuario['IdCarrera'] ?? null;
        $nombre_carrera = $id_carrera ? ($carreras_map[$id_carrera] ?? 'No asignada') : 'N/A';
        $matricula = $usuario['Matricula'] ?? 'Sin matrícula';

        $prestamos_completos[] = [
            'id' => $prestamo['id'],
            'fecha_prestamo' => $prestamo['fecha_prestamo'],
            'fecha_devolucion' => $prestamo['fecha_devolucion'],
            'status' => $prestamo['status'],
            'codigo' => $prestamo['codigo'],
            'cantidad' => $prestamo['cantidad'],
            'entregado' => $prestamo['entregado'],
            'color' => $prestamo['color'],
            'usuario' => $nombre_completo,
            'tipo' => $tipo_usuario,
            'carrera' => $nombre_carrera,
            'matricula' => $matricula
        ];
    }
}

usort($prestamos_completos, function ($a, $b) use ($sortColumn, $sortDirection) {
    $valA = $a[$sortColumn] ?? '';
    $valB = $b[$sortColumn] ?? '';

    if (in_array($sortColumn, ['fecha_prestamo', 'fecha_devolucion'])) {
        $valA = strtotime($valA);
        $valB = strtotime($valB);
    }

    if (in_array($sortColumn, ['cantidad'])) {
        $valA = (int) $valA;
        $valB = (int) $valB;
    }

    if ($valA == $valB)
        return 0;

    if ($sortDirection === 'asc') {
        return $valA < $valB ? -1 : 1;
    } else {
        return $valA > $valB ? -1 : 1;
    }
});

// Total de préstamos
$totalPrestamos = count($prestamos_completos);

// Aplicar paginación
$prestamos_paginados = array_slice($prestamos_completos, $offset, $limite);

function getSortLink($columnName, $friendlyName, $currentSort, $currentDir)
{
    $allowedColumns = [
        'fecha_prestamo' => 'Fecha de préstamo',
        'fecha_devolucion' => 'Fecha de devolución',
        'status' => 'Estatus',
        'codigo' => 'Código',
        'cantidad' => 'Disponibles',
        'usuario' => 'Usuario',
        'tipo' => 'Tipo',
        'carrera' => 'Carrera',
        'matricula' => 'Matrícula',
        'entregado' => 'Entregado por'
    ];

    if (!isset($allowedColumns[$columnName])) {
        return $friendlyName;
    }

    $params = $_GET;
    $newDirection = 'asc';

    if ($currentSort === $columnName) {
        $newDirection = $currentDir === 'asc' ? 'desc' : 'asc';
    }

    $params['sort'] = $columnName;
    $params['dir'] = $newDirection;

    $queryString = http_build_query($params);
    $arrow = '';

    if ($currentSort === $columnName) {
        $arrow = $currentDir === 'asc' ? ' ↑' : ' ↓';
    }

    return '<a href="?' . $queryString . '" style="color: white; text-decoration: none; display: block; padding: 8px;">' .
        $friendlyName . $arrow . '</a>';
}

// Calcular total de páginas
$totalPaginas = ceil($totalPrestamos / $limite);

incluirTemplate('sidebar');
?>

<style>
    /* Estilos para el selector de límite */
    .limite-selector {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 15px 0;
        padding: 10px;
        background-color: #f8f9fa;
        border-radius: 5px;
    }

    .limite-selector label {
        font-weight: 600;
        color: #333;
    }

    .limite-selector select {
        padding: 8px 15px;
        border: 2px solid #09a787;
        border-radius: 5px;
        background-color: white;
        color: #333;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .limite-selector select:hover {
        border-color: #087d66;
        box-shadow: 0 2px 5px rgba(9, 167, 135, 0.2);
    }

    .limite-selector select:focus {
        outline: none;
        border-color: #087d66;
        box-shadow: 0 0 0 3px rgba(9, 167, 135, 0.1);
    }

    .limite-info {
        margin-left: auto;
        color: #666;
        font-size: 14px;
    }

    /* Estilos mejorados para el sistema de alertas */
    .tabular--wrapper .botones--accion--container .btnRenovar {
        background-color: #28a745 !important;
        display: inline-block;
        color: #ffffff;
        padding: 5px 10px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.85em;
        transition: all 0.3s ease;
        margin: 2px;
        min-width: 70px;
    }

    .tabular--wrapper .botones--accion--container .btnRenovar:hover {
        background-color: #218838 !important;
        transform: translateY(-1px);
    }

    .tabular--wrapper .botones--accion--container .btnAlertar {
        background-color: #dc3545 !important;
        display: inline-block;
        color: #ffffff;
        padding: 5px 10px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.85em;
        transition: all 0.3s ease;
        margin: 2px;
        min-width: 70px;
        animation: pulso 2s infinite;
    }

    .tabular--wrapper .botones--accion--container .btnAlertar:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    @keyframes pulso {
        0% {
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
        }

        70% {
            box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
        }
    }

    .fecha-devolucion {
        border-radius: 4px;
        padding: 4px 8px;
        transition: all 0.3s ease;
    }

    .fecha-vencida {
        color: #721c24 !important;
        background-color: #f8d7da !important;
        font-weight: bold !important;
    }

    .fecha-hoy {
        color: #856404 !important;
        background-color: #fff3cd !important;
        font-weight: bold !important;
    }

    .fecha-manana {
        color: #664d03 !important;
        background-color: #fff3cd !important;
        font-weight: bold !important;
    }

    .fecha-activa {
        color: #155724 !important;
        font-weight: normal !important;
    }

    .botones--accion--container {
        display: flex;
        gap: 4px;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
    }

    .tabular--wrapper .botones--accion--container .btnAceptado {
        background-color: #007bff !important;
        display: inline-block;
        color: #ffffff;
        padding: 5px 10px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.85em;
        transition: all 0.3s ease;
        margin: 2px;
        min-width: 70px;
    }

    .tabular--wrapper .botones--accion--container .btnAceptado:hover {
        background-color: #0056b3 !important;
        transform: translateY(-1px);
    }

    tr:has(.btnAlertar[style*="inline-block"]) {
        background-color: rgba(255, 243, 205, 0.3);
    }

    tr:has(.btnAlertar[style*="background-color: rgb(220, 53, 69)"]) {
        background-color: rgba(248, 215, 218, 0.3);
    }

    @media (max-width: 768px) {
        .botones--accion--container {
            flex-direction: column;
            gap: 2px;
        }

        .tabular--wrapper .botones--accion--container .btnAlertar,
        .tabular--wrapper .botones--accion--container .btnRenovar,
        .tabular--wrapper .botones--accion--container .btnAceptado {
            font-size: 0.75em;
            padding: 4px 8px;
            min-width: 60px;
        }

        .limite-selector {
            flex-direction: column;
            align-items: flex-start;
        }

        .limite-info {
            margin-left: 0;
        }
    }
</style>
<link rel="stylesheet" href="../public/css/panellibros.css">

<div class="container main--content">
    <div class="header--wrapper">
        <div class="header--title">
            <span
                style="display: flex; border: 2.3px solid #09a787; padding: 2px; margin-bottom: 10px; border-radius: 5px; color: #09a787; width: 230px; text-transform: uppercase">
                <?php if ($rolAdministrador === 1) {
                    echo 'Máster';
                } elseif ($rolAdministrador === 2) {
                    echo 'Administrador general';
                } else {
                    echo 'Administrador';
                } ?>
            </span>
            <span>Bienvenido, <?php echo ($nombreAdministrador); ?></span>
            <h2>Panel de préstamos</h2>
        </div>
        <div class="user--info">
            <div class="search--box">
                <i class="fas fa-search"></i>
                <input type="text" id="buscar" placeholder="Buscar" />
            </div>
            <img src="../public/img/logouttn.png" alt="Foto de perfil" />
        </div>
    </div>


    <div class="card--container">
        <h3 class="main--title">Datos actuales</h3>
        <div class="card--wrapper">
            <div class="payment--card">
                <div class="card--header">
                    <div class="amount">
                        <span class="title"> Préstamos activos </span>
                        <span class="amount--value" id="numPrestamos"><?php echo $totalPrestamos; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tabular--wrapper">
        <h3 class="main--title">Préstamos</h3>

        <?php if ($rolAdministrador == 1): ?>
            <div class="tabular--botones">
                <a href="escanear-qr-interno.php">
                    <button title="Registrar Prestamo" class="btnAgregar">
                        <i class="fas fa-plus"></i> Registrar préstamo
                    </button>
                </a>
                <a title="Exportar PDF" id="btnPDF" href="Reporte de prestamos.php" target="_blank"><i
                        class="fas fa-file-pdf"></i> Exportar PDF</a>
                <a title="Exportar Excel" id="btnExcel" href="Reporte de prestamos excel.php" target="_blank"><i
                        class="fas fa-file-excel"></i> Exportar Excel</a>
            </div>
        <?php endif; ?>

        <!-- Selector de límite de registros -->
        <div class="limite-selector">
            <label for="limitePrestamos">Mostrar:</label>
            <select id="limitePrestamos" onchange="cambiarLimite(this.value)">
                <option value="5" <?php echo $limite == 5 ? 'selected' : ''; ?>>5 préstamos</option>
                <option value="10" <?php echo $limite == 10 ? 'selected' : ''; ?>>10 préstamos</option>
                <option value="15" <?php echo $limite == 15 ? 'selected' : ''; ?>>15 préstamos</option>
                <option value="20" <?php echo $limite == 20 ? 'selected' : ''; ?>>20 préstamos</option>
                <option value="25" <?php echo $limite == 25 ? 'selected' : ''; ?>>25 préstamos</option>
                <option value="30" <?php echo $limite == 30 ? 'selected' : ''; ?>>30 préstamos</option>
                <option value="40" <?php echo $limite == 40 ? 'selected' : ''; ?>>40 préstamos</option>
                <option value="50" <?php echo $limite == 50 ? 'selected' : ''; ?>>50 préstamos</option>
            </select>
            <span class="limite-info">
                Mostrando <?php echo min($offset + 1, $totalPrestamos); ?> -
                <?php echo min($offset + $limite, $totalPrestamos); ?> de <?php echo $totalPrestamos; ?> préstamos
            </span>
        </div>

        <div class="table--container">
            <table>
                <thead>
                    <tr>
                        <th><?php echo getSortLink('fecha_prestamo', 'Fecha de préstamo', $sortColumn, $sortDirection); ?>
                        </th>
                        <th><?php echo getSortLink('fecha_devolucion', 'Fecha de devolución', $sortColumn, $sortDirection); ?>
                        </th>
                        <th><?php echo getSortLink('status', 'Estatus', $sortColumn, $sortDirection); ?></th>
                        <th><?php echo getSortLink('codigo', 'Código', $sortColumn, $sortDirection); ?></th>
                        <th><?php echo getSortLink('cantidad', 'Disponibles', $sortColumn, $sortDirection); ?></th>
                        <th><?php echo getSortLink('usuario', 'Usuario', $sortColumn, $sortDirection); ?></th>
                        <th><?php echo getSortLink('tipo', 'Tipo', $sortColumn, $sortDirection); ?></th>
                        <th><?php echo getSortLink('carrera', 'Carrera', $sortColumn, $sortDirection); ?></th>
                        <th><?php echo getSortLink('matricula', 'Matrícula', $sortColumn, $sortDirection); ?></th>
                        <?php if ($rolAdministrador == 1): ?>
                            <th><?php echo getSortLink('entregado', 'Entregado por', $sortColumn, $sortDirection); ?></th>
                        <?php endif; ?>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody id="tablaPrestamos" style="overflow-x: scroll;">
                    <?php if (count($prestamos_paginados) > 0): ?>
                        <?php foreach ($prestamos_paginados as $prestamo): ?>
                            <tr>
                                <td class="textosm"><?php echo date('d/m/Y', strtotime($prestamo['fecha_prestamo'])); ?></td>
                                <td class="textosm fecha-devolucion"
                                    data-fecha="<?php echo date('Y-m-d', strtotime($prestamo['fecha_devolucion'])); ?>">
                                    <?php echo date('d/m/Y', strtotime($prestamo['fecha_devolucion'])); ?>
                                </td>
                                <td class="textosm"><?php echo ($prestamo['status'] === "1") ? "Préstamo" : "Devuelto"; ?></td>
                                <td class="textosm">
                                    <button style="background-color: <?php echo $prestamo['color'] ?>"
                                        class="reservacion--libro"></button>
                                    <?php echo $prestamo['codigo']; ?>
                                </td>
                                <td class="textosm"><?php echo $prestamo['cantidad']; ?></td>
                                <td class="textosm"><?php echo htmlspecialchars($prestamo['usuario']); ?></td>
                                <td class="textosm"><?php echo ucfirst($prestamo['tipo']); ?></td>
                                <td class="textosm"><?php echo htmlspecialchars($prestamo['carrera']); ?></td>
                                <td class="textosm"><?php echo htmlspecialchars($prestamo['matricula']); ?></td>
                                <?php if ($rolAdministrador == 1): ?>
                                    <td class="textosm"><?php echo htmlspecialchars($prestamo['entregado']); ?></td>
                                <?php endif; ?>
                                <td>
                                    <div class="botones--accion--container">
                                        <button title="Devuelto" type="button" class="btnAceptado"
                                            value="<?php echo $prestamo['id']; ?>">Devuelto</button>
                                        <button title="Renovar" type="button" class="btnRenovar"
                                            value="<?php echo $prestamo['id']; ?>">Renovar</button>
                                        <button title="Alertar" type="button" class="btnAlertar" style="display: none"
                                            value="<?php echo $prestamo['id']; ?>">Alertar</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo ($rolAdministrador == 1) ? '11' : '10'; ?>">No hay préstamos activos en
                                esta página
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>


    </div>

    <div class="paginacion_contenedor">
        <?php if ($pagina > 1): ?>
            <a href="?pagina=<?php echo $pagina - 1; ?>&limite=<?php echo $limite; ?><?php
                    if ($seccionId)
                        echo "&seccion=" . $seccionId;
                    if ($statusId)
                        echo "&status=" . $statusId;
                    if (isset($_GET['sort']))
                        echo "&sort=" . $_GET['sort'];
                    if (isset($_GET['dir']))
                        echo "&dir=" . $_GET['dir'];
                    ?>">&laquo; Anterior</a>
        <?php endif; ?>

        <?php
        $maxLinks = 5;
        $start = max(1, $pagina - floor($maxLinks / 2));
        $end = min($totalPaginas, $start + $maxLinks - 1);

        if ($start > 1) {
            echo '<a href="?pagina=1&limite=' . $limite .
                ($seccionId ? "&seccion=" . $seccionId : "") .
                ($statusId ? "&status=" . $statusId : "") .
                (isset($_GET['sort']) ? "&sort=" . $_GET['sort'] : "") .
                (isset($_GET['dir']) ? "&dir=" . $_GET['dir'] : "") . '">1</a>';
            if ($start > 2) {
                echo '<span>...</span>';
            }
        }

        for ($i = $start; $i <= $end; $i++): ?>
            <a href="?pagina=<?php echo $i; ?>&limite=<?php echo $limite; ?><?php
                  if ($seccionId)
                      echo "&seccion=" . $seccionId;
                  if ($statusId)
                      echo "&status=" . $statusId;
                  if (isset($_GET['sort']))
                      echo "&sort=" . $_GET['sort'];
                  if (isset($_GET['dir']))
                      echo "&dir=" . $_GET['dir'];
                  ?>" class="<?php if ($i == $pagina)
                      echo 'active'; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>

        <?php
        if ($end < $totalPaginas) {
            if ($end < $totalPaginas - 1) {
                echo '<span>...</span>';
            }
            echo '<a href="?pagina=' . $totalPaginas . '&limite=' . $limite .
                ($seccionId ? "&seccion=" . $seccionId : "") .
                ($statusId ? "&status=" . $statusId : "") .
                (isset($_GET['sort']) ? "&sort=" . $_GET['sort'] : "") .
                (isset($_GET['dir']) ? "&dir=" . $_GET['dir'] : "") . '">' . $totalPaginas . '</a>';
        }
        ?>

        <?php if ($pagina < $totalPaginas): ?>
            <a href="?pagina=<?php echo $pagina + 1; ?>&limite=<?php echo $limite; ?><?php
                    if ($seccionId)
                        echo "&seccion=" . $seccionId;
                    if ($statusId)
                        echo "&status=" . $statusId;
                    if (isset($_GET['sort']))
                        echo "&sort=" . $_GET['sort'];
                    if (isset($_GET['dir']))
                        echo "&dir=" . $_GET['dir'];
                    ?>">Siguiente &raquo;</a>
        <?php endif; ?>
    </div>
</div>


<script>
    // Se asegura de que el código se ejecute solo cuando la página ha cargado completamente.
    document.addEventListener('DOMContentLoaded', function () {

        const hoy = new Date();
        const anio = hoy.getFullYear();
        const mes = String(hoy.getMonth() + 1).padStart(2, '0');
        const dia = String(hoy.getDate()).padStart(2, '0');
        const fechaActualFormateada = `${anio}-${mes}-${dia}`;

        // 2. Crear fecha de mañana para alertas preventivas
        const manana = new Date(hoy);
        manana.setDate(hoy.getDate() + 1);
        const anioManana = manana.getFullYear();
        const mesManana = String(manana.getMonth() + 1).padStart(2, '0');
        const diaManana = String(manana.getDate()).padStart(2, '0');
        const fechaMananaFormateada = `${anioManana}-${mesManana}-${diaManana}`;

        const celdasFecha = document.querySelectorAll('.fecha-devolucion');

        celdasFecha.forEach(function (celda) {
            const fechaDevolucion = celda.dataset.fecha;
            const fila = celda.closest('tr');
            const btnAlertar = fila.querySelector('.btnAlertar');

            celda.style.color = '';
            celda.style.fontWeight = '';
            btnAlertar.style.display = 'none';

            if (fechaDevolucion < fechaActualFormateada) {
                // PRÉSTAMOS VENCIDOS (fechas pasadas)
                celda.style.color = '#dc3545';
                celda.style.fontWeight = 'bold';
                celda.style.backgroundColor = '#f8d7da'; 
                celda.title = 'Préstamo VENCIDO - Requiere atención inmediata';
                btnAlertar.style.display = 'inline-block';
                btnAlertar.style.backgroundColor = '#dc3545';
                btnAlertar.textContent = 'Urgente';
                btnAlertar.title = 'Enviar alerta de préstamo vencido';

            } else if (fechaDevolucion === fechaActualFormateada) {
                // VENCE HOY (fecha actual)
                celda.style.color = '#fd7e14';
                celda.style.fontWeight = 'bold';
                celda.style.backgroundColor = '#fff3cd'; 
                celda.title = 'Préstamo vence HOY';
                btnAlertar.style.display = 'inline-block';
                btnAlertar.style.backgroundColor = '#fd7e14';
                btnAlertar.textContent = 'Alertar';
                btnAlertar.title = 'Enviar recordatorio - vence hoy';

            } else if (fechaDevolucion === fechaMananaFormateada) {
                // VENCE MAÑANA (alerta preventiva)
                celda.style.color = '#ffc107'; // Amarillo
                celda.style.fontWeight = 'bold';
                celda.title = 'Préstamo vence mañana';
                btnAlertar.style.display = 'inline-block';
                btnAlertar.style.backgroundColor = '#ff0707ff';
                btnAlertar.textContent = 'Recordar';
                btnAlertar.title = 'Enviar recordatorio preventivo - vence mañana';

            } else {
                celda.style.color = '#28a745';
                celda.style.fontWeight = 'normal';
                celda.title = 'Préstamo activo - sin problemas';
            }
        });

        // 5. Agregar el manejador de eventos para el botón de alertar
        $(document).on('click', '.btnAlertar', function () {
            const prestamoId = $(this).val();
            const fila = $(this).closest('tr');
            const fechaDevolucion = fila.find('.fecha-devolucion').data('fecha');
            const nombreUsuario = fila.find('td:nth-child(6)').text();
            const codigoLibro = fila.find('td:nth-child(4)').text().trim();

            // Determinar el tipo de alerta según la fecha
            let tipoAlerta = '';
            let iconoSwal = 'warning';
            let colorConfirm = '#ffc107';

            if (fechaDevolucion < fechaActualFormateada) {
                tipoAlerta = 'préstamo VENCIDO';
                iconoSwal = 'error';
                colorConfirm = '#dc3545';
            } else if (fechaDevolucion === fechaActualFormateada) {
                tipoAlerta = 'préstamo que vence HOY';
                iconoSwal = 'warning';
                colorConfirm = '#fd7e14';
            } else {
                tipoAlerta = 'recordatorio preventivo';
                iconoSwal = 'info';
                colorConfirm = '#ffc107';
            }

            Swal.fire({
                title: '¿Enviar recordatorio?',
                html: `
                <div style="text-align: left; padding: 10px;">
                    <p><strong>Usuario:</strong> ${nombreUsuario}</p>
                    <p><strong>Libro:</strong> ${codigoLibro}</p>
                    <p><strong>Tipo:</strong> ${tipoAlerta}</p>
                </div>
            `,
                icon: iconoSwal,
                showCancelButton: true,
                confirmButtonColor: colorConfirm,
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, enviar recordatorio',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Mostrar loading
                    Swal.fire({
                        title: 'Enviando recordatorio...',
                        text: 'Por favor espere',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });

                    $.ajax({
                        url: 'alertar_usuario.php',
                        method: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            id: prestamoId
                        }),
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                Swal.fire({
                                    title: '¡Recordatorio enviado!',
                                    html: `
                                    <div style="text-align: left;">
                                        <p><strong>Enviado a:</strong> ${response.usuario}</p>
                                        <p><strong>Estado:</strong> ${response.dias_restantes < 0 ? 'Vencido hace ' + Math.abs(response.dias_restantes) + ' día(s)' : response.dias_restantes === 0 ? 'Vence hoy' : 'Vence en ' + response.dias_restantes + ' día(s)'}</p>
                                    </div>
                                `,
                                    icon: 'success',
                                    confirmButtonColor: '#28a745'
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error',
                                    text: response.message,
                                    icon: 'error',
                                    confirmButtonColor: '#dc3545'
                                });
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error('Error AJAX:', xhr.responseText);
                            Swal.fire({
                                title: 'Error de conexión',
                                text: 'No se pudo contactar con el servidor',
                                icon: 'error',
                                confirmButtonColor: '#dc3545'
                            });
                        }
                    });
                }
            });
        });
    });
    class DraggableTableColumns {
        constructor() {
            this.tableElement = document.querySelector('table');
            if (!this.tableElement) {
                console.error("No se encontró el elemento <table>.");
                return;
            }

            this.selectedColumn = null;
            this.hoveredColumn = null;

            this.addDraggingEvents();
        }

        addDraggingEvents() {
            this.tableElement.querySelectorAll("thead th").forEach(th => {
                th.addEventListener("dragstart", (e) => this.dragStart(e));
                th.addEventListener("dragover", (e) => this.dragOver(e));
                th.addEventListener("dragleave", (e) => this.dragLeave(e));
                th.addEventListener("drop", (e) => this.drop(e));
                th.addEventListener("dragend", (e) => this.dragEnd(e));
            });
        }

        dragStart(e) {
            this.selectedColumn = e.currentTarget;
            this.selectedColumn.classList.add("drag-selected");
            e.dataTransfer.effectAllowed = "move";
            e.dataTransfer.setData("text/plain", "");
        }

        dragOver(e) {
            e.preventDefault();
            if (e.currentTarget === this.selectedColumn) return;

            this.clearDragHoverStyles();
            this.hoveredColumn = e.currentTarget;
            this.hoveredColumn.classList.add("drag-hovered");
        }

        dragLeave(e) {
            
            }

        drop(e) {
            e.preventDefault();

            if (this.selectedColumn && this.hoveredColumn && this.selectedColumn !== this.hoveredColumn) {
                this.moveColumn();
            }
            this.clearDragStyles();
        }

        dragEnd(e) {
            this.clearDragStyles();
        }

        moveColumn() {
            const headRow = this.tableElement.querySelector("thead tr");
            const tbody = this.tableElement.querySelector("tbody");

            if (!headRow || !tbody) return;

            const selectedIndex = Array.from(headRow.children).indexOf(this.selectedColumn);
            const hoveredIndex = Array.from(headRow.children).indexOf(this.hoveredColumn);

            if (selectedIndex === -1 || hoveredIndex === -1) return;

            if (selectedIndex < hoveredIndex) {
                headRow.insertBefore(this.selectedColumn, this.hoveredColumn.nextSibling);
            } else {
                headRow.insertBefore(this.selectedColumn, this.hoveredColumn);
            }

            Array.from(tbody.rows).forEach(row => {
                const cells = Array.from(row.children);
                const selectedCell = cells[selectedIndex];
                const hoveredCell = cells[hoveredIndex];

                if (selectedCell && hoveredCell) {
                    if (selectedIndex < hoveredIndex) {
                        row.insertBefore(selectedCell, hoveredCell.nextSibling);
                    } else {
                        row.insertBefore(selectedCell, hoveredCell);
                    }
                }
            });

            this.selectedColumn = null;
            this.hoveredColumn = null;
        }

        clearDragHoverStyles() {
            this.tableElement.querySelectorAll("thead th").forEach(th => th.classList.remove("drag-hovered"));
        }

        clearDragStyles() {
            this.tableElement.querySelectorAll("thead th").forEach(th => {
                th.classList.remove("drag-selected");
                th.classList.remove("drag-hovered");
            });
        }
    }

    new DraggableTableColumns();


    // Función para cambiar el límite de registros por página
    function cambiarLimite(limite) {
        const urlParams = new URLSearchParams(window.location.search);

        urlParams.set('limite', limite);

        urlParams.set('pagina', '1');

        const nuevaURL = window.location.pathname + '?' + urlParams.toString();

        window.location.href = nuevaURL;
    }

    function actualizarPaginacion(data) {
        const paginacionContenedor = $('.paginacion_contenedor');
        paginacionContenedor.empty();
        const totalPaginas = parseInt(data.totalPaginas);
        if (totalPaginas <= 1) return;

        const paginaActual = parseInt(data.pagina);
        let html = '';

        if (paginaActual > 1) {
            html += `<a href="#" data-pagina="${paginaActual - 1}">&laquo; Anterior</a>`;
        }

        for (let i = 1; i <= totalPaginas; i++) {
            html += `<a href="#" class="${i === paginaActual ? 'active' : ''}" data-pagina="${i}">${i}</a>`;
        }

        if (paginaActual < totalPaginas) {
            html += `<a href="#" data-pagina="${paginaActual + 1}">Siguiente &raquo;</a>`;
        }
        paginacionContenedor.html(html);
    }
</script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    //Con esto devolvemos el libro
$(document).ready(function () {
    $('#tablaPrestamos').on('click', '.btnAceptado', function () { 
        var prestamoId = $(this).val();

        $.ajax({
            url: 'devolver_prestamo.php',
            method: 'POST',
            data: {
                prestamoId: prestamoId
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    Swal.fire({
                        title: "¡Libro devuelto!",
                        icon: "success"
                    }).then(() => {
                        
                        window.location = "panel-prestamos.php";
                    });
                } else {
                    alert('Hubo un error al devolver el préstamo: ' + response.message);
                }
            },
            error: function (xhr, status, error) {
                console.log(xhr.responseText);
                alert('Error en la solicitud AJAX: ' + error);
            }
        });
    });
});

    //Con esto busco yo poder renovar las fechas de devolucion para que el alumno pueda seguir con el libro
    $(document).ready(function () {
        $('#tablaPrestamos').on('click', '.btnRenovar', function () {
            var prestamoId = $(this).val();

            Swal.fire({
                title: '¿Estás seguro?',
                text: "Se extenderá la fecha de devolución por 3 días.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, renovar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'renovar_prestamo.php',
                        method: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            id: prestamoId
                        }),
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                Swal.fire(
                                    '¡Renovado!',
                                    'El préstamo ha sido renovado hasta el ' + response.nueva_fecha_devolucion,
                                    'success'
                                ).then(() => {
                                    location.reload(); 
                                });
                            } else {
                                Swal.fire(
                                    'Error',
                                    response.message,
                                    'error'
                                );
                            }
                        },
                        error: function () {
                            Swal.fire('Error', 'No se pudo contactar al servidor.', 'error');
                        }
                    });
                }
            });
        });
    });
$(document).ready(function () {
    
    var contenidoOriginalTabla = $('#tablaPrestamos').html();
    var contenidoOriginalPaginacion = $('.paginacion_contenedor').html();

    $('#buscar').on('input', function () {
        var query = $(this).val();
        var limite = <?php echo $limite; ?>;

        if (query.trim() === '') {
            $('#tablaPrestamos').html(contenidoOriginalTabla);
            $('.paginacion_contenedor').html(contenidoOriginalPaginacion); 
        } 
        
        else {
            $.ajax({
                url: 'buscar_prestamos.php',
                method: 'GET',
                data: {
                    query: query,
                    limite: limite
                },
                success: function (data) {
                    $('#tablaPrestamos').html(data);
                    $('.paginacion_contenedor').html(''); 
                },
                error: function() {
                    console.log("Error al buscar");
                }
            });
        }
    });
});
</script>

<?php
mysqli_close($db_mysql);
sqlsrv_close($conn_sqlsrv);

incluirTemplate('footer');
?>