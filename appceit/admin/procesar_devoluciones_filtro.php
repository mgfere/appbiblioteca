<?php
// ====================================================================================
// CONFIGURACIÓN DE ERRORES
// ====================================================================================
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

ob_start();

// ====================================================================================
// MANEJADORES DE ERRORES
// ====================================================================================
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $error = [
        'error' => true,
        'type' => 'PHP Error',
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline
    ];
    
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode($error, JSON_UNESCAPED_UNICODE);
    exit;
});

set_exception_handler(function($exception) {
    $error = [
        'error' => true,
        'type' => 'Exception',
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine()
    ];
    
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode($error, JSON_UNESCAPED_UNICODE);
    exit;
});

// ====================================================================================
// FUNCIONES HELPER
// ====================================================================================
function enviarError($mensaje, $detalles = null) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    
    $respuesta = [
        'error' => true,
        'message' => $mensaje
    ];
    
    if ($detalles !== null) {
        $respuesta['details'] = $detalles;
    }
    
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE);
    exit;
}

function enviarExito($data) {
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ====================================================================================
// CONEXIONES
// ====================================================================================
require '../includes/config/database.php';

$db = conectarDB();
$conn_tutorias = conectarDB2();
$conn_gestion = conectarDB3();

if (!$db) enviarError('Error conectando a MySQL', mysqli_connect_error());
if (!$conn_tutorias) enviarError('Error conectando a SQL Server Tutorias', print_r(sqlsrv_errors(), true));
if (!$conn_gestion) enviarError('Error conectando a SQL Server Gestion', print_r(sqlsrv_errors(), true));

// ====================================================================================
// RECIBIR Y VALIDAR DATOS
// ====================================================================================
$fechaInicio = $_POST['fechaInicio'] ?? date('Y-01-01');
$fechaFin = $_POST['fechaFin'] ?? date('Y-m-d');
$carreraId = $_POST['carrera'] ?? '';
$turno = $_POST['turno'] ?? '';

// Validar formato de fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)) {
    enviarError('Formato de fecha inválido. Use YYYY-MM-DD');
}

if (strtotime($fechaInicio) > strtotime($fechaFin)) {
    enviarError('La fecha de inicio no puede ser posterior a la fecha fin');
}

// ====================================================================================
// INICIALIZAR ARRAYS
// ====================================================================================
$devoluciones_internas_por_mes = array_fill(0, 12, 0);
$devoluciones_presenciales_por_mes = array_fill(0, 12, 0);
$devolucionesInternasPorSeccion = [];
$devolucionesPresencialesPorSeccion = [];
$devolucionesPorCarrera = [];
$devolucionesPorTurno = [];

// =================================================================================
// 1. FILTRAR MATRÍCULAS DESDE SQL SERVER (SOLO SI HAY FILTROS)
// =================================================================================
$matriculasFiltradas = [];
$hayFiltros = (!empty($carreraId) || !empty($turno));

if (!empty($turno) && is_numeric($turno)) {
    $sqlTurno = "SELECT DISTINCT Matricula 
                 FROM [Tutorias].[dbo].[DatosPersonales]
                 WHERE IdTurno = ?";
    $paramsTurno = [intval($turno)];
    
    if (!empty($carreraId) && is_numeric($carreraId)) {
        $sqlTurno .= " AND IdCarrera = ?";
        $paramsTurno[] = intval($carreraId);
    }
    
    $stmtTurno = sqlsrv_prepare($conn_tutorias, $sqlTurno, $paramsTurno);
    if (!$stmtTurno) enviarError('Error preparando consulta de turnos', sqlsrv_errors());
    if (!sqlsrv_execute($stmtTurno)) enviarError('Error ejecutando consulta de turnos', sqlsrv_errors());
    
    while ($fila = sqlsrv_fetch_array($stmtTurno, SQLSRV_FETCH_ASSOC)) {
        if (!empty($fila['Matricula'])) {
            $matricula = is_string($fila['Matricula']) ? trim($fila['Matricula']) : $fila['Matricula'];
            $matriculasFiltradas[] = "'" . mysqli_real_escape_string($db, $matricula) . "'";
        }
    }
    sqlsrv_free_stmt($stmtTurno);
} 
elseif (!empty($carreraId) && is_numeric($carreraId)) {
    $sqlCarrera = "SELECT Matricula 
                   FROM [GestionUsuarios].[dbo].[Alumnos]
                   WHERE IdCarrera = ?";
    $stmtCarrera = sqlsrv_prepare($conn_gestion, $sqlCarrera, [intval($carreraId)]);
    
    if (!$stmtCarrera) enviarError('Error preparando consulta de carreras', sqlsrv_errors());
    if (!sqlsrv_execute($stmtCarrera)) enviarError('Error ejecutando consulta de carreras', sqlsrv_errors());
    
    while ($fila = sqlsrv_fetch_array($stmtCarrera, SQLSRV_FETCH_ASSOC)) {
        if (!empty($fila['Matricula'])) {
            $matricula = is_string($fila['Matricula']) ? trim($fila['Matricula']) : $fila['Matricula'];
            $matriculasFiltradas[] = "'" . mysqli_real_escape_string($db, $matricula) . "'";
        }
    }
    sqlsrv_free_stmt($stmtCarrera);
}

// CREAR FILTRO PARA MYSQL
$filtro_matricula = "";

if ($hayFiltros) {
    if (!empty($matriculasFiltradas)) {
        $matriculasString = implode(',', array_unique($matriculasFiltradas));
        $filtro_matricula = " AND Matricula IN ({$matriculasString})";
    } else {
        $filtro_matricula = " AND 1=0";
    }
}

// =================================================================================
// 2. DEVOLUCIONES INTERNAS (MySQL) - CORREGIDO
// =================================================================================
$fechaFinCompleta = $fechaFin . ' 23:59:59';

// --- A) DEVOLUCIONES INTERNAS POR MES ---
// CORRECCIÓN: Se agrega AND status = 2 para contar solo lo realmente devuelto
$sqlDevolucionesInternas = "SELECT DATE_FORMAT(fecha_devolucion, '%c') AS mes, COUNT(id) AS total_devoluciones
                            FROM prestamos
                            WHERE fecha_devolucion BETWEEN ? AND ?
                            AND fecha_devolucion IS NOT NULL
                            AND status = 2 
                            {$filtro_matricula}
                            GROUP BY DATE_FORMAT(fecha_devolucion, '%m-%Y')
                            ORDER BY DATE_FORMAT(fecha_devolucion, '%Y-%m') ASC";

$stmtDevInternas = mysqli_prepare($db, $sqlDevolucionesInternas);
if (!$stmtDevInternas) enviarError('Error preparando consulta de devoluciones internas', mysqli_error($db));

mysqli_stmt_bind_param($stmtDevInternas, 'ss', $fechaInicio, $fechaFinCompleta);

if (!mysqli_stmt_execute($stmtDevInternas)) {
    enviarError('Error ejecutando consulta de devoluciones internas', mysqli_stmt_error($stmtDevInternas));
}

$resultadoDevInternas = mysqli_stmt_get_result($stmtDevInternas);
if ($resultadoDevInternas) {
    while ($fila = mysqli_fetch_assoc($resultadoDevInternas)) {
        $mes = intval($fila['mes']) - 1;
        if ($mes >= 0 && $mes < 12) {
            $devoluciones_internas_por_mes[$mes] = (int)$fila['total_devoluciones'];
        }
    }
}
mysqli_stmt_close($stmtDevInternas);

// --- B) DEVOLUCIONES INTERNAS POR SECCIÓN ---
$filtro_matricula_con_alias = "";
if ($hayFiltros) {
    if (!empty($matriculasFiltradas)) {
        $matriculasString = implode(',', array_unique($matriculasFiltradas));
        $filtro_matricula_con_alias = " AND p.Matricula IN ({$matriculasString})";
    } else {
        $filtro_matricula_con_alias = " AND 1=0";
    }
}

// CORRECCIÓN: Se agrega AND p.status = 2
$sqlDevInternasPorSeccion = "SELECT s.nombre_seccion AS seccion, COUNT(p.id) AS total
                             FROM prestamos p
                             JOIN libros l ON p.Libros_id = l.id
                             JOIN secciones s ON l.seccionId = s.id
                             WHERE p.fecha_devolucion BETWEEN ? AND ?
                             AND p.fecha_devolucion IS NOT NULL
                             AND p.status = 2
                             {$filtro_matricula_con_alias}
                             GROUP BY s.nombre_seccion
                             ORDER BY total DESC";

$stmtDevInternasSec = mysqli_prepare($db, $sqlDevInternasPorSeccion);
if ($stmtDevInternasSec) {
    mysqli_stmt_bind_param($stmtDevInternasSec, 'ss', $fechaInicio, $fechaFinCompleta);
    mysqli_stmt_execute($stmtDevInternasSec);
    $resultadoDevInternasSec = mysqli_stmt_get_result($stmtDevInternasSec);
    
    if ($resultadoDevInternasSec) {
        while ($fila = mysqli_fetch_assoc($resultadoDevInternasSec)) {
            $devolucionesInternasPorSeccion[] = $fila;
        }
    }
    mysqli_stmt_close($stmtDevInternasSec);
}

// =================================================================================
// 3. ESTADÍSTICAS POR CARRERA Y TURNO
// =================================================================================

// Obtener matrículas que hicieron devoluciones (solo alumnos y STATUS = 2)
$sqlMatriculasDev = "SELECT DISTINCT Matricula, tipo_usuario
                     FROM prestamos
                     WHERE fecha_devolucion BETWEEN ? AND ?
                     AND fecha_devolucion IS NOT NULL
                     AND status = 2
                     {$filtro_matricula}";

$stmtMatriculasDev = mysqli_prepare($db, $sqlMatriculasDev);
if ($stmtMatriculasDev) {
    mysqli_stmt_bind_param($stmtMatriculasDev, 'ss', $fechaInicio, $fechaFinCompleta);
    mysqli_stmt_execute($stmtMatriculasDev);
    $resultadoMatriculasDev = mysqli_stmt_get_result($stmtMatriculasDev);
    
    $matriculasAlumnos = [];
    
    if ($resultadoMatriculasDev) {
        while ($fila = mysqli_fetch_assoc($resultadoMatriculasDev)) {
            if (!empty($fila['Matricula']) && $fila['tipo_usuario'] === 'alumno') {
                $matricula = is_string($fila['Matricula']) ? trim($fila['Matricula']) : $fila['Matricula'];
                $matriculasAlumnos[] = "'" . $matricula . "'";
            }
        }
    }
    mysqli_stmt_close($stmtMatriculasDev);
    
    // --- DEVOLUCIONES POR CARRERA (solo alumnos) ---
    if (!empty($matriculasAlumnos)) {
        $matriculasAlumnosString = implode(',', array_unique($matriculasAlumnos));
        
        $sqlDevCarrera = "SELECT c.Nombre AS carrera, COUNT(DISTINCT a.Matricula) AS total
                          FROM [GestionUsuarios].[dbo].[Alumnos] a
                          JOIN [GestionUsuarios].[dbo].[Carreras] c ON a.IdCarrera = c.IdCarrera
                          WHERE a.Matricula IN ({$matriculasAlumnosString})
                          GROUP BY c.Nombre
                          ORDER BY total DESC";
        
        $stmtDevCarrera = sqlsrv_query($conn_gestion, $sqlDevCarrera);
        if ($stmtDevCarrera) {
            while ($fila = sqlsrv_fetch_array($stmtDevCarrera, SQLSRV_FETCH_ASSOC)) {
                $devolucionesPorCarrera[] = $fila;
            }
            sqlsrv_free_stmt($stmtDevCarrera);
        }
    }
    
    // --- DEVOLUCIONES POR TURNO (solo alumnos) ---
    if (!empty($matriculasAlumnos)) {
        $matriculasAlumnosString = implode(',', array_unique($matriculasAlumnos));
        
        $sqlDevTurno = "SELECT t.Nombre AS turno, COUNT(DISTINCT dp.Matricula) AS total
                        FROM [Tutorias].[dbo].[DatosPersonales] dp
                        JOIN [Tutorias].[dbo].[Turnoes] t ON dp.IdTurno = t.IdTurno
                        WHERE dp.Matricula IN ({$matriculasAlumnosString})
                        GROUP BY t.Nombre
                        ORDER BY total DESC";
        
        $stmtDevTurno = sqlsrv_query($conn_tutorias, $sqlDevTurno);
        if ($stmtDevTurno) {
            while ($fila = sqlsrv_fetch_array($stmtDevTurno, SQLSRV_FETCH_ASSOC)) {
                $devolucionesPorTurno[] = $fila;
            }
            sqlsrv_free_stmt($stmtDevTurno);
        }
    }
}

// =================================================================================
// 4. DEVOLUCIONES PRESENCIALES (sin filtros de usuario)
// =================================================================================
// NOTA: Como en 'prestamospresencial' no hay status visible, asumimos que si tiene
// fechaDevolucion llena es que ya se devolvió.

$sqlDevPresenciales = "SELECT DATE_FORMAT(fechaDevolucion, '%c') AS mes, COUNT(*) AS total_devoluciones_presenciales
                       FROM prestamospresencial
                       WHERE fechaDevolucion BETWEEN ? AND ?
                       AND fechaDevolucion IS NOT NULL
                       GROUP BY DATE_FORMAT(fechaDevolucion, '%m-%Y')
                       ORDER BY DATE_FORMAT(fechaDevolucion, '%Y-%m') ASC";

$stmtDevPresenciales = mysqli_prepare($db, $sqlDevPresenciales);
if ($stmtDevPresenciales) {
    mysqli_stmt_bind_param($stmtDevPresenciales, 'ss', $fechaInicio, $fechaFinCompleta);
    mysqli_stmt_execute($stmtDevPresenciales);
    $resDevPresenciales = mysqli_stmt_get_result($stmtDevPresenciales);
    
    if ($resDevPresenciales) {
        while ($fila = mysqli_fetch_assoc($resDevPresenciales)) {
            $mes = intval($fila['mes']) - 1;
            if ($mes >= 0 && $mes < 12) {
                $devoluciones_presenciales_por_mes[$mes] = (int)$fila['total_devoluciones_presenciales'];
            }
        }
    }
    mysqli_stmt_close($stmtDevPresenciales);
}

// Por sección
$sqlDevPresencialesSec = "SELECT s.nombre_seccion AS seccion, COUNT(pp.id) AS total
                          FROM prestamospresencial pp
                          JOIN secciones s ON pp.seccionId = s.id
                          WHERE pp.fechaDevolucion BETWEEN ? AND ?
                          AND pp.fechaDevolucion IS NOT NULL
                          GROUP BY s.nombre_seccion
                          ORDER BY total DESC";

$stmtDevPresencialesSec = mysqli_prepare($db, $sqlDevPresencialesSec);
if ($stmtDevPresencialesSec) {
    mysqli_stmt_bind_param($stmtDevPresencialesSec, 'ss', $fechaInicio, $fechaFinCompleta);
    mysqli_stmt_execute($stmtDevPresencialesSec);
    $resDevPresencialesSec = mysqli_stmt_get_result($stmtDevPresencialesSec);
    
    if ($resDevPresencialesSec) {
        while ($fila = mysqli_fetch_assoc($resDevPresencialesSec)) {
            $devolucionesPresencialesPorSeccion[] = $fila;
        }
    }
    mysqli_stmt_close($stmtDevPresencialesSec);
}

// =================================================================================
// CERRAR CONEXIONES Y ENVIAR RESPUESTA
// =================================================================================

if (isset($db)) mysqli_close($db);
if ($conn_gestion) sqlsrv_close($conn_gestion);
if ($conn_tutorias) sqlsrv_close($conn_tutorias);

enviarExito([
    'devolucionesInternas' => $devoluciones_internas_por_mes,
    'devolucionesPresenciales' => $devoluciones_presenciales_por_mes,
    'devolucionesInternasPorSeccion' => $devolucionesInternasPorSeccion,
    'devolucionesPresencialesPorSeccion' => $devolucionesPresencialesPorSeccion,
    'devolucionesPorCarrera' => $devolucionesPorCarrera,
    'devolucionesPorTurno' => $devolucionesPorTurno
]);
?>