<?php
require '../includes/config/database.php';
require '../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;

// 1. CONEXIONES
$db = conectarDB();
$conn_tutorias = conectarDB2();
$conn_gestion = conectarDB3();

if (!$db || !$conn_tutorias || !$conn_gestion) {
    die("Error de conexión a las bases de datos.");
}

// 2. RECIBIR DATOS
$fechaInicio = $_GET['fechaInicio'] ?? date('Y-01-01');
$fechaFin = $_GET['fechaFin'] ?? date('Y-m-d');
$carreraId = $_GET['carrera'] ?? '';
$turno = $_GET['turno'] ?? '';

// INICIALIZAR ARRAYS
$prestamosPorCarrera = [];
$prestamosPorTurno = [];
$prestamos_por_mes = array_fill(0, 12, 0);
$prestamos_presenciales_por_mes = array_fill(0, 12, 0);
$prestamosPorSeccion = [];
$prestamosPresencialesPorSeccion = [];
$meses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

// =================================================================================
// 3. FILTRAR MATRÍCULAS (LÓGICA UNIFICADA)
// =================================================================================
$matriculasFiltradas = [];
$hayFiltros = (!empty($carreraId) || !empty($turno));

if (!empty($turno) && is_numeric($turno)) {
    // Buscar en Tutorias (DatosPersonales)
    $sqlTurno = "SELECT DISTINCT Matricula FROM [Tutorias].[dbo].[DatosPersonales] WHERE IdTurno = ?";
    $paramsTurno = [intval($turno)];
    
    if (!empty($carreraId) && is_numeric($carreraId)) {
        $sqlTurno .= " AND IdCarrera = ?";
        $paramsTurno[] = intval($carreraId);
    }
    
    $stmtTurno = sqlsrv_prepare($conn_tutorias, $sqlTurno, $paramsTurno);
    if ($stmtTurno && sqlsrv_execute($stmtTurno)) {
        while ($fila = sqlsrv_fetch_array($stmtTurno, SQLSRV_FETCH_ASSOC)) {
            if (!empty($fila['Matricula'])) {
                $matricula = is_string($fila['Matricula']) ? trim($fila['Matricula']) : $fila['Matricula'];
                $matriculasFiltradas[] = "'" . mysqli_real_escape_string($db, $matricula) . "'";
            }
        }
        sqlsrv_free_stmt($stmtTurno);
    }
} 
elseif (!empty($carreraId) && is_numeric($carreraId)) {
    // Buscar en GestionUsuarios (Alumnos)
    $sqlCarrera = "SELECT Matricula FROM [GestionUsuarios].[dbo].[Alumnos] WHERE IdCarrera = ?";
    $stmtCarrera = sqlsrv_prepare($conn_gestion, $sqlCarrera, [intval($carreraId)]);
    
    if ($stmtCarrera && sqlsrv_execute($stmtCarrera)) {
        while ($fila = sqlsrv_fetch_array($stmtCarrera, SQLSRV_FETCH_ASSOC)) {
            if (!empty($fila['Matricula'])) {
                $matricula = is_string($fila['Matricula']) ? trim($fila['Matricula']) : $fila['Matricula'];
                $matriculasFiltradas[] = "'" . mysqli_real_escape_string($db, $matricula) . "'";
            }
        }
        sqlsrv_free_stmt($stmtCarrera);
    }
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
        $filtro_matricula = " AND 1=0";
        $filtro_matricula_con_alias = " AND 1=0";
    }
}

$fechaFinCompleta = $fechaFin . ' 23:59:59';

// =================================================================================
// 4. CONSULTAS A MySQL (PRÉSTAMOS INTERNOS)
// =================================================================================

// --- A) PRÉSTAMOS POR MES ---
$sqlPrestamos = "SELECT DATE_FORMAT(fecha_prestamo, '%c') AS mes, COUNT(id) AS total_prestamos
                 FROM prestamos
                 WHERE fecha_prestamo BETWEEN ? AND ?
                 {$filtro_matricula}
                 GROUP BY DATE_FORMAT(fecha_prestamo, '%m-%Y')
                 ORDER BY DATE_FORMAT(fecha_prestamo, '%Y-%m') ASC";

$stmtPrestamos = mysqli_prepare($db, $sqlPrestamos);
if ($stmtPrestamos) {
    mysqli_stmt_bind_param($stmtPrestamos, 'ss', $fechaInicio, $fechaFinCompleta);
    mysqli_stmt_execute($stmtPrestamos);
    $resultadoPrestamos = mysqli_stmt_get_result($stmtPrestamos);
    while ($fila = mysqli_fetch_assoc($resultadoPrestamos)) {
        $mes = intval($fila['mes']) - 1;
        if ($mes >= 0 && $mes < 12) {
            $prestamos_por_mes[$mes] = (int)$fila['total_prestamos'];
        }
    }
    mysqli_stmt_close($stmtPrestamos);
}

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
    while ($fila = mysqli_fetch_assoc($resultadoPrestamosSeccion)) {
        $prestamosPorSeccion[] = $fila;
    }
    mysqli_stmt_close($stmtSeccion);
}

// =================================================================================
// 5. ESTADÍSTICAS POR CARRERA Y TURNO (CORREGIDO)
// =================================================================================

// Obtener matrículas involucradas en préstamos (filtradas por fecha y filtros previos)
$sqlMatriculas = "SELECT DISTINCT Matricula
                  FROM prestamos
                  WHERE fecha_prestamo BETWEEN ? AND ?
                  {$filtro_matricula}";

$stmtMatriculas = mysqli_prepare($db, $sqlMatriculas);
if ($stmtMatriculas) {
    mysqli_stmt_bind_param($stmtMatriculas, 'ss', $fechaInicio, $fechaFinCompleta);
    mysqli_stmt_execute($stmtMatriculas);
    $resultadoMatriculas = mysqli_stmt_get_result($stmtMatriculas);
    
    $listaMatriculas = [];
    
    while ($fila = mysqli_fetch_assoc($resultadoMatriculas)) {
        // CORRECCIÓN: Quitamos el filtro de 'tipo_usuario' === 'alumno'. Aceptamos cualquiera con matrícula.
        if (!empty($fila['Matricula'])) {
            $matricula = is_string($fila['Matricula']) ? trim($fila['Matricula']) : $fila['Matricula'];
            $listaMatriculas[] = "'" . $matricula . "'";
        }
    }
    mysqli_stmt_close($stmtMatriculas);
    
    // --- ESTADÍSTICAS POR CARRERA (Usando GestionUsuarios) ---
    if (!empty($listaMatriculas)) {
        $matriculasString = implode(',', array_unique($listaMatriculas));
        
        $sqlCarreraStats = "SELECT c.Nombre AS carrera, COUNT(a.Matricula) AS total
                            FROM [GestionUsuarios].[dbo].[Alumnos] a
                            JOIN [GestionUsuarios].[dbo].[Carreras] c ON a.IdCarrera = c.IdCarrera
                            WHERE a.Matricula IN ({$matriculasString})
                            GROUP BY c.Nombre
                            ORDER BY total DESC";
        
        $stmtCarreraStats = sqlsrv_query($conn_gestion, $sqlCarreraStats);
        if ($stmtCarreraStats) {
            while ($fila = sqlsrv_fetch_array($stmtCarreraStats, SQLSRV_FETCH_ASSOC)) {
                $prestamosPorCarrera[] = $fila;
            }
            sqlsrv_free_stmt($stmtCarreraStats);
        }
        
        // --- ESTADÍSTICAS POR TURNO (Usando Tutorias) ---
        $sqlTurnoStats = "SELECT t.Nombre AS turno, COUNT(dp.Matricula) AS total
                          FROM [Tutorias].[dbo].[DatosPersonales] dp
                          JOIN [Tutorias].[dbo].[Turnoes] t ON dp.IdTurno = t.IdTurno
                          WHERE dp.Matricula IN ({$matriculasString})
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
// 6. PRÉSTAMOS PRESENCIALES
// =================================================================================

// Por Mes
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
    while ($fila = mysqli_fetch_assoc($resPresenciales)) {
        $mes = intval($fila['mes']) - 1;
        if ($mes >= 0 && $mes < 12) {
            $prestamos_presenciales_por_mes[$mes] = (int)$fila['total_presenciales'];
        }
    }
    mysqli_stmt_close($stmtPresenciales);
}

// Por Sección
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
    while ($fila = mysqli_fetch_assoc($resPresencialesSeccion)) {
        $prestamosPresencialesPorSeccion[] = $fila;
    }
    mysqli_stmt_close($stmtPresencialesSeccion);
}

// CERRAR CONEXIONES
mysqli_close($db);
if ($conn_gestion) sqlsrv_close($conn_gestion);
if ($conn_tutorias) sqlsrv_close($conn_tutorias);


// =================================================================================
// 7. GENERACIÓN DEL EXCEL
// =================================================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Estadísticas de Préstamos');

// Estilos
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF09A787']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$dataStyle = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
$titleStyle = array_merge($headerStyle, ['font' => ['size' => 14], 'alignment' => ['horizontal' => 'center']]);

// --- Tabla 1: Totales Mensuales ---
$row = 1;
$sheet->mergeCells('A'.$row.':D'.$row)->setCellValue('A'.$row, 'Préstamos por Mes')->getStyle('A'.$row)->applyFromArray($titleStyle);
$row++;
$sheet->fromArray(['Mes', 'Préstamos Internos', 'Préstamos Externos', 'Total'], null, 'A'.$row)->getStyle('A'.$row.':D'.$row)->applyFromArray($headerStyle);
$row++;

for ($i = 0; $i < 12; $i++) {
    $totalMes = $prestamos_por_mes[$i] + $prestamos_presenciales_por_mes[$i];
    $sheet->fromArray([
        $meses[$i],
        $prestamos_por_mes[$i],
        $prestamos_presenciales_por_mes[$i],
        $totalMes
    ], null, 'A'.($row + $i));
}
$sheet->getStyle('A3:D14')->applyFromArray($dataStyle);
$row += 13;

// --- Tabla 2: Préstamos por Carrera ---
if (!empty($prestamosPorCarrera)) {
    $sheet->mergeCells('A'.$row.':B'.$row)->setCellValue('A'.$row, 'Préstamos por Carrera')->getStyle('A'.$row)->applyFromArray($titleStyle);
    $row++;
    $sheet->fromArray(['Carrera', 'Total'], null, 'A'.$row)->getStyle('A'.$row.':B'.$row)->applyFromArray($headerStyle);
    $row++;
    $startDataRowCarrera = $row;
    foreach ($prestamosPorCarrera as $data) {
        $sheet->fromArray([$data['carrera'], $data['total']], null, 'A'.$row);
        $row++;
    }
    // Gráfico de Carrera
    $endDataRow = $row - 1;
    $dataSeriesLabels = [new DataSeriesValues('String', "'Estadísticas de Préstamos'!\$A\$".($startDataRowCarrera-1), null, 1)];
    $xAxisTickValues = [new DataSeriesValues('String', "'Estadísticas de Préstamos'!\$A\$".($startDataRowCarrera).":\$A\$".($endDataRow), null, count($prestamosPorCarrera))];
    $dataSeriesValues = [new DataSeriesValues('Number', "'Estadísticas de Préstamos'!\$B\$".($startDataRowCarrera).":\$B\$".($endDataRow), null, count($prestamosPorCarrera))];
    
    $series = new DataSeries(DataSeries::TYPE_PIECHART, DataSeries::GROUPING_STANDARD, range(0, count($dataSeriesValues) - 1), $dataSeriesLabels, $xAxisTickValues, $dataSeriesValues);
    $chart1 = new Chart('chart1', new Title('Préstamos por Carrera'), new Legend(), new PlotArea(null, [$series]));
    $chart1->setTopLeftPosition('G3')->setBottomRightPosition('P15');
    $sheet->addChart($chart1);
    
    $row++;
}

// --- Tabla 3: Préstamos por Turno ---
if (!empty($prestamosPorTurno)) {
    $sheet->mergeCells('A'.$row.':B'.$row)->setCellValue('A'.$row, 'Préstamos por Turno')->getStyle('A'.$row)->applyFromArray($titleStyle);
    $row++;
    $sheet->fromArray(['Turno', 'Total'], null, 'A'.$row)->getStyle('A'.$row.':B'.$row)->applyFromArray($headerStyle);
    $row++;
    $startDataRowTurno = $row;
    foreach ($prestamosPorTurno as $data) {
        $sheet->fromArray([$data['turno'], $data['total']], null, 'A'.$row);
        $row++;
    }
    // Gráfico de Turno
    $endDataRowTurno = $row - 1;
    $dataSeriesLabels2 = [new DataSeriesValues('String', "'Estadísticas de Préstamos'!\$A\$".($startDataRowTurno-1), null, 1)];
    $xAxisTickValues2 = [new DataSeriesValues('String', "'Estadísticas de Préstamos'!\$A\$".($startDataRowTurno).":\$A\$".($endDataRowTurno), null, count($prestamosPorTurno))];
    $dataSeriesValues2 = [new DataSeriesValues('Number', "'Estadísticas de Préstamos'!\$B\$".($startDataRowTurno).":\$B\$".($endDataRowTurno), null, count($prestamosPorTurno))];
    
    $series2 = new DataSeries(DataSeries::TYPE_PIECHART, DataSeries::GROUPING_STANDARD, range(0, count($dataSeriesValues2) - 1), $dataSeriesLabels2, $xAxisTickValues2, $dataSeriesValues2);
    $chart2 = new Chart('chart2', new Title('Préstamos por Turno'), new Legend(), new PlotArea(null, [$series2]));
    $chart2->setTopLeftPosition('G17')->setBottomRightPosition('P30');
    $sheet->addChart($chart2);
    
    $row++;
}

// --- Tabla 4: Préstamos por Sección (Internos) ---
$colSeccion = 'D';
$rowSeccion = 17; 
if(isset($startDataRowCarrera)) {
    $rowSeccion = $startDataRowCarrera - 2; 
}

$sheet->mergeCells($colSeccion.$rowSeccion.':E'.$rowSeccion)->setCellValue($colSeccion.$rowSeccion, 'Préstamos por Sección (Internos)')->getStyle($colSeccion.$rowSeccion)->applyFromArray($titleStyle);
$rowSeccion++;
$sheet->fromArray(['Sección', 'Total'], null, $colSeccion.$rowSeccion)->getStyle($colSeccion.$rowSeccion.':E'.$rowSeccion)->applyFromArray($headerStyle);
$rowSeccion++;
foreach ($prestamosPorSeccion as $data) {
    $sheet->fromArray([$data['seccion'], $data['total']], null, $colSeccion.$rowSeccion);
    $rowSeccion++;
}
$rowSeccion++;

// --- Tabla 5: Préstamos por Sección (Externos) ---
$sheet->mergeCells($colSeccion.$rowSeccion.':E'.$rowSeccion)->setCellValue($colSeccion.$rowSeccion, 'Préstamos por Sección (Externos)')->getStyle($colSeccion.$rowSeccion)->applyFromArray($titleStyle);
$rowSeccion++;
$sheet->fromArray(['Sección', 'Total'], null, $colSeccion.$rowSeccion)->getStyle($colSeccion.$rowSeccion.':E'.$rowSeccion)->applyFromArray($headerStyle);
$rowSeccion++;
foreach ($prestamosPresencialesPorSeccion as $data) {
    $sheet->fromArray([$data['seccion'], $data['total']], null, $colSeccion.$rowSeccion);
    $rowSeccion++;
}

// Ajustar ancho de columnas
foreach (range('A', 'E') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// 8. EXPORTAR ARCHIVO
$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true);
$filename = 'Prestamos_CEIT_' . date('Ymd_His') . '.xlsx';

ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;
?>