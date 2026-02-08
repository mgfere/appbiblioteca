<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

ob_start();

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

require '../includes/config/database.php';

$db = conectarDB();
$conn_tutorias = conectarDB2();
$conn_gestion = conectarDB3();

if (!$db) enviarError('Error conectando a MySQL', mysqli_connect_error());
if (!$conn_tutorias) enviarError('Error conectando a SQL Server Tutorias', print_r(sqlsrv_errors(), true));
if (!$conn_gestion) enviarError('Error conectando a SQL Server Gestion', print_r(sqlsrv_errors(), true));

// RECIBIR DATOS
$fechaInicio = $_POST['fechaInicio'] ?? date('Y-01-01');
$fechaFin = $_POST['fechaFin'] ?? date('Y-m-d');
$carreraId = $_POST['carrera'] ?? '';
$turno = $_POST['turno'] ?? '';

// VALIDAR FECHAS
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaInicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaFin)) {
    enviarError('Formato de fecha inválido. Use YYYY-MM-DD');
}

if (strtotime($fechaInicio) > strtotime($fechaFin)) {
    enviarError('La fecha de inicio no puede ser posterior a la fecha fin');
}

// INICIALIZAR ARRAYS
$prestamosPorCarrera = [];
$prestamosPorTurno = [];
$prestamos_por_mes = array_fill(0, 12, 0);
$prestamos_presenciales_por_mes = array_fill(0, 12, 0);
$usuarios_externos_por_mes = array_fill(0, 12, 0);
$prestamosPorSeccion = [];
$prestamosPresencialesPorSeccion = [];

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
        // CORRECCIÓN: Verificar que no sea NULL antes de trim()
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
        // CORRECCIÓN: Verificar que no sea NULL
        if (!empty($fila['Matricula'])) {
            $matricula = is_string($fila['Matricula']) ? trim($fila['Matricula']) : $fila['Matricula'];
            $matriculasFiltradas[] = "'" . mysqli_real_escape_string($db, $matricula) . "'";
        }
    }
    sqlsrv_free_stmt($stmtCarrera);
}

// CREAR FILTRO PARA MYSQL
$filtro_matricula = "";
$filtro_matricula_con_alias = "";

if ($hayFiltros) {
    if (!empty($matriculasFiltradas)) {
        $matriculasString = implode(',', array_unique($matriculasFiltradas));
        $filtro_matricula = " AND Matricula IN ({$matriculasString})";
        $filtro_matricula_con_alias = " AND p.Matricula IN ({$matriculasString})";
    } else {
        // Si hay filtros pero no se encontraron matrículas, no mostrar nada
        $filtro_matricula = " AND 1=0";
        $filtro_matricula_con_alias = " AND 1=0";
    }
}
// Si NO hay filtros, mostrar todo (filtro vacío)

// =================================================================================
// 2. CONSULTAS A MySQL
// =================================================================================
$fechaFinCompleta = $fechaFin . ' 23:59:59';

// --- A) PRÉSTAMOS POR MES ---
$sqlPrestamos = "SELECT DATE_FORMAT(fecha_prestamo, '%c') AS mes, COUNT(id) AS total_prestamos
                 FROM prestamos
                 WHERE fecha_prestamo BETWEEN ? AND ?
                 {$filtro_matricula}
                 GROUP BY DATE_FORMAT(fecha_prestamo, '%m-%Y')
                 ORDER BY DATE_FORMAT(fecha_prestamo, '%Y-%m') ASC";

$stmtPrestamos = mysqli_prepare($db, $sqlPrestamos);
if (!$stmtPrestamos) enviarError('Error preparando consulta de préstamos', mysqli_error($db));

if (!mysqli_stmt_bind_param($stmtPrestamos, 'ss', $fechaInicio, $fechaFinCompleta)) {
    enviarError('Error en bind_param de préstamos', mysqli_stmt_error($stmtPrestamos));
}

if (!mysqli_stmt_execute($stmtPrestamos)) {
    enviarError('Error ejecutando consulta de préstamos', mysqli_stmt_error($stmtPrestamos));
}

$resultadoPrestamos = mysqli_stmt_get_result($stmtPrestamos);
if ($resultadoPrestamos) {
    while ($fila = mysqli_fetch_assoc($resultadoPrestamos)) {
        $mes = intval($fila['mes']) - 1;
        if ($mes >= 0 && $mes < 12) {
            $prestamos_por_mes[$mes] = (int)$fila['total_prestamos'];
        }
    }
}
mysqli_stmt_close($stmtPrestamos);

// --- B) PRÉSTAMOS POR SECCIÓN ---
$sqlPrestamosSeccion = "SELECT s.nombre_seccion AS seccion, COUNT(p.id) AS total
                        FROM prestamos p
                        JOIN libros l ON p.Libros_id = l.id
                        JOIN secciones s ON l.seccionId = s.id
                        WHERE p.fecha_prestamo BETWEEN ? AND ?
                        {$filtro_matricula_con_alias}
                        GROUP BY s.nombre_seccion
                        ORDER BY total DESC";

$stmtSeccion = mysqli_prepare($db, $sqlPrestamosSeccion);
if ($stmtSeccion) {
    mysqli_stmt_bind_param($stmtSeccion, 'ss', $fechaInicio, $fechaFinCompleta);
    mysqli_stmt_execute($stmtSeccion);
    $resultadoPrestamosSeccion = mysqli_stmt_get_result($stmtSeccion);
    
    if ($resultadoPrestamosSeccion) {
        while ($fila = mysqli_fetch_assoc($resultadoPrestamosSeccion)) {
            $prestamosPorSeccion[] = $fila;
        }
    }
    mysqli_stmt_close($stmtSeccion);
}

// =================================================================================
// 3. ESTADÍSTICAS POR CARRERA Y TURNO
// =================================================================================

$sqlMatriculas = "SELECT DISTINCT Matricula, tipo_usuario
                  FROM prestamos
                  WHERE fecha_prestamo BETWEEN ? AND ?
                  {$filtro_matricula}";

$stmtMatriculas = mysqli_prepare($db, $sqlMatriculas);
if ($stmtMatriculas) {
    mysqli_stmt_bind_param($stmtMatriculas, 'ss', $fechaInicio, $fechaFinCompleta);
    mysqli_stmt_execute($stmtMatriculas);
    $resultadoMatriculas = mysqli_stmt_get_result($stmtMatriculas);
    
    $matriculasAlumnos = [];
    $matriculasDocentes = [];
    
    if ($resultadoMatriculas) {
        while ($fila = mysqli_fetch_assoc($resultadoMatriculas)) {
            // CORRECCIÓN: Verificar NULL
            if (!empty($fila['Matricula'])) {
                $matricula = is_string($fila['Matricula']) ? trim($fila['Matricula']) : $fila['Matricula'];
                $mat = "'" . $matricula . "'";
                
                if ($fila['tipo_usuario'] === 'alumno') {
                    $matriculasAlumnos[] = $mat;
                } elseif ($fila['tipo_usuario'] === 'docente') {
                    $matriculasDocentes[] = $mat;
                }
            }
        }
    }
    mysqli_stmt_close($stmtMatriculas);
    
    // --- ESTADÍSTICAS POR CARRERA ---
    if (!empty($matriculasAlumnos)) {
        $matriculasAlumnosString = implode(',', array_unique($matriculasAlumnos));
        
        $sqlCarreraStats = "SELECT c.Nombre AS carrera, COUNT(DISTINCT a.Matricula) AS total
                            FROM [GestionUsuarios].[dbo].[Alumnos] a
                            JOIN [GestionUsuarios].[dbo].[Carreras] c ON a.IdCarrera = c.IdCarrera
                            WHERE a.Matricula IN ({$matriculasAlumnosString})
                            GROUP BY c.Nombre
                            ORDER BY total DESC";
        
        $stmtCarreraStats = sqlsrv_query($conn_gestion, $sqlCarreraStats);
        if ($stmtCarreraStats) {
            while ($fila = sqlsrv_fetch_array($stmtCarreraStats, SQLSRV_FETCH_ASSOC)) {
                $prestamosPorCarrera[] = $fila;
            }
            sqlsrv_free_stmt($stmtCarreraStats);
        }
    }
    
    // --- ESTADÍSTICAS POR TURNO ---
    if (!empty($matriculasAlumnos)) {
        $matriculasAlumnosString = implode(',', array_unique($matriculasAlumnos));
        
        $sqlTurnoStats = "SELECT t.Nombre AS turno, COUNT(DISTINCT dp.Matricula) AS total
                          FROM [Tutorias].[dbo].[DatosPersonales] dp
                          JOIN [Tutorias].[dbo].[Turnoes] t ON dp.IdTurno = t.IdTurno
                          WHERE dp.Matricula IN ({$matriculasAlumnosString})
                          GROUP BY t.Nombre
                          ORDER BY total DESC";
        
        $stmtTurnoStats = sqlsrv_query($conn_tutorias, $sqlTurnoStats);
        if ($stmtTurnoStats) {
            while ($fila = sqlsrv_fetch_array($stmtTurnoStats, SQLSRV_FETCH_ASSOC)) {
                $prestamosPorTurno[] = $fila;
            }
            sqlsrv_free_stmt($stmtTurnoStats);
        }
    }
}

// =================================================================================
// 4. PRÉSTAMOS PRESENCIALES
// =================================================================================

$sqlPresenciales = "SELECT DATE_FORMAT(fechaPrestamo, '%c') AS mes, COUNT(*) AS total_presenciales
                    FROM prestamospresencial
                    WHERE fechaPrestamo BETWEEN ? AND ?
                    GROUP BY DATE_FORMAT(fechaPrestamo, '%m-%Y')
                    ORDER BY DATE_FORMAT(fechaPrestamo, '%Y-%m') ASC";

$stmtPresenciales = mysqli_prepare($db, $sqlPresenciales);
if ($stmtPresenciales) {
    mysqli_stmt_bind_param($stmtPresenciales, 'ss', $fechaInicio, $fechaFinCompleta);
    mysqli_stmt_execute($stmtPresenciales);
    $resPresenciales = mysqli_stmt_get_result($stmtPresenciales);
    
    if ($resPresenciales) {
        while ($fila = mysqli_fetch_assoc($resPresenciales)) {
            $mes = intval($fila['mes']) - 1;
            if ($mes >= 0 && $mes < 12) {
                $prestamos_presenciales_por_mes[$mes] = (int)$fila['total_presenciales'];
            }
        }
    }
    mysqli_stmt_close($stmtPresenciales);
}

$sqlPresencialesSeccion = "SELECT s.nombre_seccion AS seccion, COUNT(pp.id) AS total
                           FROM prestamospresencial pp
                           JOIN secciones s ON pp.seccionId = s.id
                           WHERE pp.fechaPrestamo BETWEEN ? AND ?
                           GROUP BY s.nombre_seccion
                           ORDER BY total DESC";

$stmtPresencialesSeccion = mysqli_prepare($db, $sqlPresencialesSeccion);
if ($stmtPresencialesSeccion) {
    mysqli_stmt_bind_param($stmtPresencialesSeccion, 'ss', $fechaInicio, $fechaFinCompleta);
    mysqli_stmt_execute($stmtPresencialesSeccion);
    $resPresencialesSeccion = mysqli_stmt_get_result($stmtPresencialesSeccion);
    
    if ($resPresencialesSeccion) {
        while ($fila = mysqli_fetch_assoc($resPresencialesSeccion)) {
            $prestamosPresencialesPorSeccion[] = $fila;
        }
    }
    mysqli_stmt_close($stmtPresencialesSeccion);
}

// CERRAR CONEXIONES
if (isset($db)) mysqli_close($db);
if ($conn_gestion) sqlsrv_close($conn_gestion);
if ($conn_tutorias) sqlsrv_close($conn_tutorias);

// ENVIAR RESPUESTA
enviarExito([
    'prestamos' => $prestamos_por_mes,
    'prestamosPresenciales' => $prestamos_presenciales_por_mes,
    'usuariosExternos' => $usuarios_externos_por_mes,
    'prestamosPorSeccion' => $prestamosPorSeccion,
    'prestamosPresencialesPorSeccion' => $prestamosPresencialesPorSeccion,
    'prestamosPorCarrera' => $prestamosPorCarrera,
    'prestamosPorTurno' => $prestamosPorTurno
]);
?>