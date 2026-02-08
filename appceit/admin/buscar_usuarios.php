<?php
require '../includes/funciones.php';
header('Content-Type: application/json; charset=utf-8');

// --- Autenticación ---
$auth = adminAutenticado();
if (!$auth) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

// --- Conexiones a la DB ---
require '../includes/config/database.php';
$db_mysql = conectarDB();
$conn_sqlsrv = conectarDB3();

// --- Obtener mapa de estatus desde MySQL (SOLO ALUMNOS) ---
$mapa_estatus = [];
$resultado_estatus = mysqli_query($db_mysql, "SELECT IdUsuario, estatus FROM control_acceso WHERE TipoUsuario = 'alumno'");
while ($row = mysqli_fetch_assoc($resultado_estatus)) {
    $mapa_estatus[$row['IdUsuario']] = (int)$row['estatus'];
}

// --- Parámetros ---
$query = isset($_GET['query']) ? trim($_GET['query']) : '';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$por_pagina = 20;

if (strlen($query) < 2) {
    echo json_encode([
        'usuarios' => [], 
        'paginacion' => [
            'html' => '',
            'pagina' => 1,
            'totalPaginas' => 0
        ]
    ]);
    exit;
}

// --- LÓGICA DE BÚSQUEDA ---
$searchTerm = '%' . $query . '%';
$params_sqlsrv = [
    $searchTerm, $searchTerm, $searchTerm, 
    $searchTerm, $searchTerm, $searchTerm
];

$whereClause = "(
    (COALESCE(a.Nombre, '') + ' ' + COALESCE(a.ApellidoPaterno, '') + ' ' + COALESCE(a.ApellidoMaterno, '')) LIKE ? 
    OR a.Matricula LIKE ? 
    OR a.CorreoElectronico LIKE ?
    OR c.Nombre LIKE ?
    OR a.ApellidoPaterno LIKE ?
    OR a.ApellidoMaterno LIKE ?
) AND a.Habilitado = 1";

// --- 1. Contar el TOTAL de resultados ---
$query_count = "SELECT COUNT(*) as total
                FROM [GestionUsuarios].[dbo].[Alumnos] a
                LEFT JOIN [GestionUsuarios].[dbo].[Carreras] c ON a.IdCarrera = c.IdCarrera
                WHERE $whereClause";

$resultado_count = sqlsrv_query($conn_sqlsrv, $query_count, $params_sqlsrv);
$totalFilas = 0;
if ($resultado_count && $row = sqlsrv_fetch_array($resultado_count, SQLSRV_FETCH_ASSOC)) {
    $totalFilas = $row['total'];
}
$totalPaginas = ceil($totalFilas / $por_pagina);

// --- 2. Obtener los resultados para la PÁGINA ACTUAL ---
$offset = ($pagina - 1) * $por_pagina;
$query_sqlsrv = "SELECT 
                    a.IdAlumno as IdUsuario,
                    a.Nombre as Nom, 
                    a.ApellidoPaterno as Paterno, 
                    a.ApellidoMaterno as Materno, 
                    a.Matricula as UserName, 
                    a.CorreoElectronico as Email, 
                    a.IdCarrera,
                    a.Cuatrimestre,
                    a.Habilitado as Estado,
                    c.Nombre as CarreraNom
                 FROM [GestionUsuarios].[dbo].[Alumnos] a
                 LEFT JOIN [GestionUsuarios].[dbo].[Carreras] c ON a.IdCarrera = c.IdCarrera
                 WHERE $whereClause
                 ORDER BY a.Nombre, a.ApellidoPaterno, a.ApellidoMaterno
                 OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

$params_data = array_merge($params_sqlsrv, [$offset, $por_pagina]);
$resultado_sqlsrv = sqlsrv_query($conn_sqlsrv, $query_sqlsrv, $params_data);

$usuarios = [];
if ($resultado_sqlsrv) {
    while ($usuario = sqlsrv_fetch_array($resultado_sqlsrv, SQLSRV_FETCH_ASSOC)) {
        $usuario['estatus'] = $mapa_estatus[$usuario['IdUsuario']] ?? 1;
        $usuario['NombreCompleto'] = ucwords(strtolower(trim(
            $usuario['Nom'] . ' ' . $usuario['Paterno'] . ' ' . $usuario['Materno']
        )));
        $usuario['Area'] = 'N/A';
        $usuario['IdTurno'] = 'N/A';
        $usuarios[] = $usuario;
    }
}

// --- GENERAR HTML DE PAGINACIÓN CON LÍMITE DE ENLACES ---
$paginacionHTML = '';
if ($totalPaginas > 1) {
    $maxLinks = 5; // Solo mostrar 5 enlaces máximo
    $start = max(1, $pagina - floor($maxLinks / 2));
    $end = min($totalPaginas, $start + $maxLinks - 1);
    
    // Ajustar el inicio si estamos cerca del final
    if ($end - $start < $maxLinks - 1) {
        $start = max(1, $end - $maxLinks + 1);
    }
    
    $paginacionHTML = '<div class="paginacion_contenedor">';
    
    // Enlace Anterior
    if ($pagina > 1) {
        $paginacionHTML .= '<a href="#" class="pagina-busqueda" data-pagina="' . ($pagina - 1) . '">&laquo; Anterior</a>';
    }
    
    // Primer página + elipsis si es necesario
    if ($start > 1) {
        $paginacionHTML .= '<a href="#" class="pagina-busqueda" data-pagina="1">1</a>';
        if ($start > 2) {
            $paginacionHTML .= '<span>...</span>';
        }
    }
    
    // Enlaces de páginas
    for ($i = $start; $i <= $end; $i++) {
        $claseActiva = ($i == $pagina) ? 'active' : '';
        $paginacionHTML .= '<a href="#" class="pagina-busqueda ' . $claseActiva . '" data-pagina="' . $i . '">' . $i . '</a>';
    }
    
    // Última página + elipsis si es necesario
    if ($end < $totalPaginas) {
        if ($end < $totalPaginas - 1) {
            $paginacionHTML .= '<span>...</span>';
        }
        $paginacionHTML .= '<a href="#" class="pagina-busqueda" data-pagina="' . $totalPaginas . '">' . $totalPaginas . '</a>';
    }
    
    // Enlace Siguiente
    if ($pagina < $totalPaginas) {
        $paginacionHTML .= '<a href="#" class="pagina-busqueda" data-pagina="' . ($pagina + 1) . '">Siguiente &raquo;</a>';
    }
    
    $paginacionHTML .= '</div>';
}

// --- GENERAR HTML DE LA TABLA ---
$tablaHTML = '';
if (count($usuarios) > 0) {
    $contadorFilas = (($pagina - 1) * $por_pagina) + 1;
    
    foreach ($usuarios as $usuario) {
        $estatus_actual = $usuario['estatus'];
        $nombre_completo = $usuario['NombreCompleto'];
        $estatusStyle = ($estatus_actual == 0) ? 'style="background-color: #ffebee; opacity: 0.7;"' : '';
        
        $emailLink = !empty($usuario['Email']) ? 
            '<a target="_blank" href="mailto:' . htmlspecialchars($usuario['Email']) . '">' . htmlspecialchars($usuario['Email']) . '</a>' : 
            'No disponible';
        
        // Botones de acción
        $botonesAccion = '';
        if ($estatus_actual === 1) {
            $botonesAccion = '
                <a href="#" class="btn-delete" onclick="confirmarEstatus(event, ' . $usuario['IdUsuario'] . ', 0)" title="Desactivar Usuario">
                    <i class="fas fa-user-slash"></i>
                </a>
                <a href="escanear-qr-usuario-interno.php?usuarioId=' . $usuario['IdUsuario'] . '">
                    <button title="Prestamo" class="btnAceptado">Préstamo</button>
                </a>';
        } else {
            $botonesAccion = '
                <a href="#" class="btn-edit" onclick="confirmarEstatus(event, ' . $usuario['IdUsuario'] . ', 1)" title="Activar Usuario">
                    <i class="fas fa-user-check"></i>
                </a>';
        }
        
        $tablaHTML .= '
        <tr ' . $estatusStyle . '>
            <td class="textosm">' . $contadorFilas . '</td>
            <td class="textosm">' . htmlspecialchars($nombre_completo) . '</td>
            <td class="textosm">' . htmlspecialchars($usuario['UserName']) . '</td>
            <td class="textosm">' . htmlspecialchars($usuario['CarreraNom'] ?? 'N/A') . '</td>
            <td class="textosm">' . htmlspecialchars($usuario['Cuatrimestre'] ?? 'N/A') . '</td>
            <td class="textosm">' . $emailLink . '</td>
            <td>
                <div class="botones--accion--container">' . $botonesAccion . '</div>
            </td>
        </tr>';
        
        $contadorFilas++;
    }
} else {
    $tablaHTML = '<tr><td colspan="7">No se encontraron resultados.</td></tr>';
}

// --- RESPUESTA FINAL ---
echo json_encode([
    'html' => $tablaHTML,
    'paginacion' => $paginacionHTML,
    'total' => $totalFilas,
    'pagina' => $pagina,
    'totalPaginas' => $totalPaginas
], JSON_INVALID_UTF8_SUBSTITUTE);

sqlsrv_close($conn_sqlsrv);
mysqli_close($db_mysql);
?>