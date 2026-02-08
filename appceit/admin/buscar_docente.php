<?php
// ✅ Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '../includes/funciones.php';

// --- Conexiones a la DB ---
require '../includes/config/database.php';
$db_mysql = conectarDB();
$conn_sqlsrv = conectarDB3(); // ✅ Para docentes

// --- Obtener rol del administrador ---
$rolAdministrador = isset($_SESSION['rol']) ? (int)$_SESSION['rol'] : 0;

// --- Obtener estatus de control_acceso ---
$mapa_estatus = [];
$resultado_estatus = mysqli_query($db_mysql, "SELECT IdUsuario, estatus FROM control_acceso WHERE TipoUsuario = 'docente'");
while ($row = mysqli_fetch_assoc($resultado_estatus)) {
    $mapa_estatus[$row['IdUsuario']] = (int)$row['estatus'];
}

// --- Parámetros ---
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'todos'; // 'todos', 'activos', 'inactivos'
$por_pagina = 20;
$offset = ($pagina - 1) * $por_pagina;

if (strlen($query) < 2) {
    echo json_encode([
        'html' => '<tr><td colspan="6">Ingrese al menos 2 caracteres</td></tr>',
        'paginacion' => '',
        'total' => 0
    ]);
    exit;
}

// --- LÓGICA DE BÚSQUEDA PARA DOCENTES ---
$searchTerm = '%' . $query . '%';
$params_sqlsrv = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];

// ⭐ Condición de búsqueda para docentes
$whereClause = "(
    (COALESCE(Nombre, '') + ' ' + COALESCE(ApellidoPaterno, '') + ' ' + COALESCE(ApellidoMaterno, '')) LIKE ? 
    OR Matricula LIKE ? 
    OR CorreoElectronico LIKE ?
    OR NumeroEmpleado LIKE ?
) AND Habilitado = 1";

// --- 1. CONTAR TOTAL DE RESULTADOS ---
$query_count = "SELECT COUNT(*) as total
                FROM [GestionUsuarios].[dbo].[Docentes] 
                WHERE $whereClause";

$resultado_count = sqlsrv_query($conn_sqlsrv, $query_count, $params_sqlsrv);
$totalResultados = 0;
if ($resultado_count && $row = sqlsrv_fetch_array($resultado_count, SQLSRV_FETCH_ASSOC)) {
    $totalResultados = $row['total'];
}

// --- 2. OBTENER RESULTADOS PAGINADOS ---
$query_sqlsrv = "SELECT 
                    IdDocente as IdUsuario,
                    Nombre as Nom, 
                    ApellidoPaterno as Paterno, 
                    ApellidoMaterno as Materno, 
                    Matricula as UserName, 
                    CorreoElectronico as Email,
                    NumeroEmpleado
                 FROM [GestionUsuarios].[dbo].[Docentes] 
                 WHERE $whereClause
                 ORDER BY Nombre, ApellidoPaterno, ApellidoMaterno
                 OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

$params_data = array_merge($params_sqlsrv, [$offset, $por_pagina]);
$resultado_sqlsrv = sqlsrv_query($conn_sqlsrv, $query_sqlsrv, $params_data);

// --- 3. FILTRAR SEGÚN ESTATUS Y GENERAR HTML ---
ob_start();
if ($resultado_sqlsrv) {
    $contador = $offset + 1;
    $hayResultados = false;
    $resultadosFiltrados = 0;
    
    while ($usuario = sqlsrv_fetch_array($resultado_sqlsrv, SQLSRV_FETCH_ASSOC)) {
        // Obtener el estatus desde MySQL
        $estatus_actual = $mapa_estatus[$usuario['IdUsuario']] ?? 1;
        
        // ✅ APLICAR FILTRO DE ESTATUS
        if ($filtro === 'activos' && $estatus_actual !== 1) continue;
        if ($filtro === 'inactivos' && $estatus_actual !== 0) continue;
        
        $hayResultados = true;
        $resultadosFiltrados++;
        
        // Construir nombre completo
        $nombre_completo = ucwords(strtolower(trim(
            $usuario['Nom'] . ' ' . $usuario['Paterno'] . ' ' . $usuario['Materno']
        )));
        ?>
        <tr style="<?php echo ($estatus_actual === 0) ? 'background-color: #ffebee; opacity: 0.7;' : ''; ?>">
            <td class="textosm"><?php echo $contador; ?></td>
            <td class="textosm"><?php echo htmlspecialchars($nombre_completo); ?></td>
            <td class="textosm"><?php echo htmlspecialchars($usuario['UserName']); ?></td>
            <td class="textosm"><?php echo htmlspecialchars($usuario['NumeroEmpleado'] ?? 'N/A'); ?></td>
            <td class="textosm">
                <?php if (!empty($usuario['Email'])): ?>
                    <a target="_blank" href="mailto:<?php echo htmlspecialchars($usuario['Email']); ?>">
                        <?php echo htmlspecialchars($usuario['Email']); ?>
                    </a>
                <?php else: ?>
                    No disponible
                <?php endif; ?>
            </td>
            <td>
                <div class="botones--accion--container">
                    <?php if ($rolAdministrador === 1 || $rolAdministrador === 2 || $rolAdministrador === 3): ?>
                        <?php if ($estatus_actual === 1): ?>
                            <a href="#" class="btn-delete"
                                onclick="confirmarEstatusDocente(event, <?php echo $usuario['IdUsuario']; ?>, 0)"
                                title="Desactivar Usuario">
                                <i class="fas fa-user-slash"></i>
                            </a>
                            <a href="escanear_qr_docente.php?usuarioId=<?php echo $usuario['IdUsuario']; ?>">
                                <button title="Prestamo" class="btnAceptado">Préstamo</button>
                            </a>
                        <?php else: ?>
                            <a href="#" class="btn-edit"
                                onclick="confirmarEstatusDocente(event, <?php echo $usuario['IdUsuario']; ?>, 1)" 
                                title="Activar Usuario">
                                <i class="fas fa-user-check"></i>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php
        $contador++;
    }
    
    if (!$hayResultados) {
        echo '<tr><td colspan="6">No se encontraron docentes que coincidan con la búsqueda y el filtro seleccionado.</td></tr>';
    }
} else {
    echo '<tr><td colspan="6">Error en la búsqueda: ' . htmlspecialchars(print_r(sqlsrv_errors(), true)) . '</td></tr>';
}
$htmlTabla = ob_get_clean();

// --- 4. CALCULAR PAGINACIÓN BASADA EN RESULTADOS FILTRADOS ---
$totalPaginas = ceil($totalResultados / $por_pagina);

// --- 5. GENERAR HTML DE LA PAGINACIÓN ---
ob_start();
if ($totalResultados > 0 && $totalPaginas > 1) {
    if ($pagina > 1) {
        echo '<a href="#" class="pagina-busqueda" data-pagina="' . ($pagina - 1) . '">&laquo; Anterior</a>';
    }

    $maxLinks = 5;
    $start = max(1, $pagina - floor($maxLinks / 2));
    $end = min($totalPaginas, $start + $maxLinks - 1);

    if ($start > 1) {
        echo '<a href="#" class="pagina-busqueda" data-pagina="1">1</a>';
        if ($start > 2) {
            echo '<span>...</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $activeClass = ($i == $pagina) ? 'active' : '';
        echo '<a href="#" class="pagina-busqueda ' . $activeClass . '" data-pagina="' . $i . '">' . $i . '</a>';
    }

    if ($end < $totalPaginas) {
        if ($end < $totalPaginas - 1) {
            echo '<span>...</span>';
        }
        echo '<a href="#" class="pagina-busqueda" data-pagina="' . $totalPaginas . '">' . $totalPaginas . '</a>';
    }

    if ($pagina < $totalPaginas) {
        echo '<a href="#" class="pagina-busqueda" data-pagina="' . ($pagina + 1) . '">Siguiente &raquo;</a>';
    }
}
$htmlPaginacion = ob_get_clean();

// --- 6. RESPUESTA JSON ---
header('Content-Type: application/json');
echo json_encode([
    'html' => $htmlTabla,
    'paginacion' => $htmlPaginacion,
    'total' => $totalResultados
]);

sqlsrv_close($conn_sqlsrv);
mysqli_close($db_mysql);
?>