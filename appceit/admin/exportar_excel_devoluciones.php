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

// --- 1. CONEXIONES ---
$db = conectarDB();              // MySQL
$conn_tutorias = conectarDB2();  // SQL Server: Tutorias (Turnos)
$conn_gestion = conectarDB3();   // SQL Server: GestionUsuarios (Alumnos, Carreras)

if (!$db || !$conn_tutorias || !$conn_gestion) {
    die("Error de conexión a las bases de datos.");
}

// --- 2. CAPTURAR FILTROS ---
$fechaInicio = $_GET['fechaInicio'] ?? date('Y-01-01');
$fechaFin = $_GET['fechaFin'] ?? date('Y-m-d');
$carreraId = $_GET['carrera'] ?? '';
$turno = $_GET['turno'] ?? '';

// INICIALIZAR ARRAYS
$devolucionesPorCarrera = [];
$devolucionesPorTurno = [];
$devoluciones_internas_por_mes = array_fill(0, 12, 0);
$devoluciones_externas_por_mes = array_fill(0, 12, 0);
$meses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

// =================================================================================
// 3. FILTRAR MATRÍCULAS (LÓGICA UNIFICADA SQL SERVER -> MySQL)
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

if ($hayFiltros) {
    if (!empty($matriculasFiltradas)) {
        $matriculasString = implode(',', array_unique($matriculasFiltradas));
        // Nota: En MySQL hacemos JOIN con usuarios, así que filtramos por u.matricula
        $filtro_matricula = " AND u.matricula IN ({$matriculasString})";
    } else {
        $filtro_matricula = " AND 1=0"; // Filtros activos pero sin resultados
    }
}

$fechaFinCompleta = $fechaFin . ' 23:59:59';

// =================================================================================
// 4. CONSULTAS A MySQL (DEVOLUCIONES)
// =================================================================================

// --- A) DEVOLUCIONES INTERNAS POR MES ---
// Condición clave: p.fecha_devolucion IS NOT NULL
$sqlDevolucionesInternas = "SELECT DATE_FORMAT(p.fecha_devolucion, '%c') AS mes, COUNT(p.id) AS total
                            FROM prestamos p
                            JOIN usuarios u ON p.Estudiantes_id = u.id
                            WHERE p.fecha_devolucion BETWEEN ? AND ?
                            AND p.fecha_devolucion IS NOT NULL
                            {$filtro_matricula}
                            GROUP BY DATE_FORMAT(p.fecha_devolucion, '%m-%Y')";

$stmtDevInt = mysqli_prepare($db, $sqlDevolucionesInternas);
if ($stmtDevInt) {
    mysqli_stmt_bind_param($stmtDevInt, 'ss', $fechaInicio, $fechaFinCompleta);
    mysqli_stmt_execute($stmtDevInt);
    $resDevInt = mysqli_stmt_get_result($stmtDevInt);
    while ($fila = mysqli_fetch_assoc($resDevInt)) {
        $mes = intval($fila['mes']) - 1;
        if ($mes >= 0 && $mes < 12) {
            $devoluciones_internas_por_mes[$mes] = (int)$fila['total'];
        }
    }
    mysqli_stmt_close($stmtDevInt);
}

// --- B) DEVOLUCIONES EXTERNAS POR MES (Sin filtro de matrícula) ---
$sqlDevolucionesExternas = "SELECT DATE_FORMAT(fechaDevolucion, '%c') AS mes, COUNT(*) AS total
                            FROM prestamospresencial
                            WHERE fechaDevolucion BETWEEN ? AND ?
                            AND fechaDevolucion IS NOT NULL
                            GROUP BY DATE_FORMAT(fechaDevolucion, '%m-%Y')";

$stmtDevExt = mysqli_prepare($db, $sqlDevolucionesExternas);
if ($stmtDevExt) {
    mysqli_stmt_bind_param($stmtDevExt, 'ss', $fechaInicio, $fechaFinCompleta);
    mysqli_stmt_execute($stmtDevExt);
    $resDevExt = mysqli_stmt_get_result($stmtDevExt);
    while ($fila = mysqli_fetch_assoc($resDevExt)) {
        $mes = intval($fila['mes']) - 1;
        if ($mes >= 0 && $mes < 12) {
            $devoluciones_externas_por_mes[$mes] = (int)$fila['total'];
        }
    }
    mysqli_stmt_close($stmtDevExt);
}

// =================================================================================
// 5. ESTADÍSTICAS POR CARRERA Y TURNO (Consolidado)
// =================================================================================

// Paso 1: Obtener matrículas de MySQL que hayan devuelto libros en este periodo
$sqlMatriculasDev = "SELECT DISTINCT u.matricula
                     FROM prestamos p
                     JOIN usuarios u ON p.Estudiantes_id = u.id
                     WHERE p.fecha_devolucion BETWEEN ? AND ?
                     AND p.fecha_devolucion IS NOT NULL
                     {$filtro_matricula}";

$stmtMatDev = mysqli_prepare($db, $sqlMatriculasDev);
if ($stmtMatDev) {
    mysqli_stmt_bind_param($stmtMatDev, 'ss', $fechaInicio, $fechaFinCompleta);
    mysqli_stmt_execute($stmtMatDev);
    $resMatDev = mysqli_stmt_get_result($stmtMatDev);
    
    $listaMatriculas = [];
    while ($fila = mysqli_fetch_assoc($resMatDev)) {
        if (!empty($fila['matricula'])) {
            $mat = is_string($fila['matricula']) ? trim($fila['matricula']) : $fila['matricula'];
            $listaMatriculas[] = "'" . $mat . "'";
        }
    }
    mysqli_stmt_close($stmtMatDev);

    // Paso 2: Consultar SQL Server para agrupar
    if (!empty($listaMatriculas)) {
        $matriculasString = implode(',', array_unique($listaMatriculas));

        // --- CARRERA (GestionUsuarios) ---
        $sqlCarrera = "SELECT c.Nombre AS carrera, COUNT(a.Matricula) AS total
                       FROM [GestionUsuarios].[dbo].[Alumnos] a
                       JOIN [GestionUsuarios].[dbo].[Carreras] c ON a.IdCarrera = c.IdCarrera
                       WHERE a.Matricula IN ({$matriculasString})
                       GROUP BY c.Nombre
                       ORDER BY total DESC";
        
        $stmtCarrera = sqlsrv_query($conn_gestion, $sqlCarrera);
        if ($stmtCarrera) {
            while ($fila = sqlsrv_fetch_array($stmtCarrera, SQLSRV_FETCH_ASSOC)) {
                $devolucionesPorCarrera[] = $fila;
            }
            sqlsrv_free_stmt($stmtCarrera);
        }

        // --- TURNO (Tutorias) ---
        $sqlTurno = "SELECT t.Nombre AS turno, COUNT(dp.Matricula) AS total
                     FROM [Tutorias].[dbo].[DatosPersonales] dp
                     JOIN [Tutorias].[dbo].[Turnoes] t ON dp.IdTurno = t.IdTurno
                     WHERE dp.Matricula IN ({$matriculasString})
                     GROUP BY t.Nombre
                     ORDER BY total DESC";
        
        $stmtTurno = sqlsrv_query($conn_tutorias, $sqlTurno);
        if ($stmtTurno) {
            while ($fila = sqlsrv_fetch_array($stmtTurno, SQLSRV_FETCH_ASSOC)) {
                $devolucionesPorTurno[] = $fila;
            }
            sqlsrv_free_stmt($stmtTurno);
        }
    }
}

// CERRAR CONEXIONES
mysqli_close($db);
if ($conn_gestion) sqlsrv_close($conn_gestion);
if ($conn_tutorias) sqlsrv_close($conn_tutorias);


// =================================================================================
// 6. GENERACIÓN DEL EXCEL
// =================================================================================
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Estadisticas_Devoluciones');

// Estilos
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF09A787']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$dataStyle = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];
$titleStyle = array_merge($headerStyle, ['font' => ['size' => 14], 'alignment' => ['horizontal' => 'center']]);

// --- Tabla 1: Devoluciones Mensuales ---
$row = 1;
$sheet->mergeCells('A'.$row.':D'.$row)->setCellValue('A'.$row, 'Devoluciones Mensuales')->getStyle('A'.$row)->applyFromArray($titleStyle);
$row++;
$sheet->fromArray(['Mes', 'Devoluciones Internas', 'Devoluciones Externas', 'Total'], null, 'A'.$row)->getStyle('A'.$row.':D'.$row)->applyFromArray($headerStyle);
$row++;

for ($i = 0; $i < 12; $i++) {
    $total = $devoluciones_internas_por_mes[$i] + $devoluciones_externas_por_mes[$i];
    $sheet->fromArray([
        $meses[$i], 
        $devoluciones_internas_por_mes[$i], 
        $devoluciones_externas_por_mes[$i],
        $total
    ], null, 'A'.($row + $i));
}
$sheet->getStyle('A3:D14')->applyFromArray($dataStyle);
$row += 13;

// --- Tabla 2: Devoluciones por Carrera ---
if (!empty($devolucionesPorCarrera)) {
    $sheet->mergeCells('A'.$row.':B'.$row)->setCellValue('A'.$row, 'Devoluciones por Carrera')->getStyle('A'.$row)->applyFromArray($titleStyle);
    $row++;
    $sheet->fromArray(['Carrera', 'Total'], null, 'A'.$row)->getStyle('A'.$row.':B'.$row)->applyFromArray($headerStyle);
    $row++;
    $startDataRowCarrera = $row;
    foreach ($devolucionesPorCarrera as $data) {
        $sheet->fromArray([$data['carrera'], $data['total']], null, 'A'.$row);
        $row++;
    }
    
    // Gráfico Carrera
    $endDataRow = $row - 1;
    $dataSeriesLabels = [new DataSeriesValues('String', "'Estadisticas_Devoluciones'!\$A\$".($startDataRowCarrera-1), null, 1)];
    $xAxisTickValues = [new DataSeriesValues('String', "'Estadisticas_Devoluciones'!\$A\$".($startDataRowCarrera).":\$A\$".($endDataRow), null, count($devolucionesPorCarrera))];
    $dataSeriesValues = [new DataSeriesValues('Number', "'Estadisticas_Devoluciones'!\$B\$".($startDataRowCarrera).":\$B\$".($endDataRow), null, count($devolucionesPorCarrera))];
    
    $series = new DataSeries(DataSeries::TYPE_PIECHART, DataSeries::GROUPING_STANDARD, range(0, count($dataSeriesValues) - 1), $dataSeriesLabels, $xAxisTickValues, $dataSeriesValues);
    $chart = new Chart('chartCarrera', new Title('Devoluciones por Carrera'), new Legend(), new PlotArea(null, [$series]));
    $chart->setTopLeftPosition('G3')->setBottomRightPosition('P18');
    $sheet->addChart($chart);
    
    $row++;
}

// --- Tabla 3: Devoluciones por Turno ---
if (!empty($devolucionesPorTurno)) {
    $sheet->mergeCells('A'.$row.':B'.$row)->setCellValue('A'.$row, 'Devoluciones por Turno')->getStyle('A'.$row)->applyFromArray($titleStyle);
    $row++;
    $sheet->fromArray(['Turno', 'Total'], null, 'A'.$row)->getStyle('A'.$row.':B'.$row)->applyFromArray($headerStyle);
    $row++;
    $startDataRowTurno = $row;
    foreach ($devolucionesPorTurno as $data) {
        $sheet->fromArray([$data['turno'], $data['total']], null, 'A'.$row);
        $row++;
    }
    
    // Gráfico Turno
    $endDataRow = $row - 1;
    $dataSeriesLabels2 = [new DataSeriesValues('String', "'Estadisticas_Devoluciones'!\$A\$".($startDataRowTurno-1), null, 1)];
    $xAxisTickValues2 = [new DataSeriesValues('String', "'Estadisticas_Devoluciones'!\$A\$".($startDataRowTurno).":\$A\$".($endDataRow), null, count($devolucionesPorTurno))];
    $dataSeriesValues2 = [new DataSeriesValues('Number', "'Estadisticas_Devoluciones'!\$B\$".($startDataRowTurno).":\$B\$".($endDataRow), null, count($devolucionesPorTurno))];
    
    $series2 = new DataSeries(DataSeries::TYPE_PIECHART, DataSeries::GROUPING_STANDARD, range(0, count($dataSeriesValues2) - 1), $dataSeriesLabels2, $xAxisTickValues2, $dataSeriesValues2);
    $chart2 = new Chart('chartTurno', new Title('Devoluciones por Turno'), new Legend(), new PlotArea(null, [$series2]));
    $chart2->setTopLeftPosition('G20')->setBottomRightPosition('P35');
    $sheet->addChart($chart2);
}

// Gráfico de Barras Mensuales (Internos vs Externos)
// Lo ponemos al lado de la primera tabla
$dataSeriesLabelsBar = [
    new DataSeriesValues('String', "'Estadisticas_Devoluciones'!\$B\$2", null, 1),
    new DataSeriesValues('String', "'Estadisticas_Devoluciones'!\$C\$2", null, 1)
];
$xAxisTickValuesBar = [new DataSeriesValues('String', "'Estadisticas_Devoluciones'!\$A\$3:\$A\$14", null, 12)];
$dataSeriesValuesBar = [
    new DataSeriesValues('Number', "'Estadisticas_Devoluciones'!\$B\$3:\$B\$14", null, 12),
    new DataSeriesValues('Number', "'Estadisticas_Devoluciones'!\$C\$3:\$C\$14", null, 12)
];
$seriesBar = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, range(0, count($dataSeriesValuesBar) - 1), $dataSeriesLabelsBar, $xAxisTickValuesBar, $dataSeriesValuesBar);
$chartBar = new Chart('chartMeses', new Title('Devoluciones Mensuales'), new Legend(), new PlotArea(null, [$seriesBar]));
// Posicionamos el gráfico de barras debajo de los pasteles o a un lado
$chartBar->setTopLeftPosition('Q3')->setBottomRightPosition('Z20');
$sheet->addChart($chartBar);

// Ajustar columnas
foreach (range('A', 'E') as $col) { $sheet->getColumnDimension($col)->setAutoSize(true); }

// --- 7. EXPORTAR ---
$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true);
$filename = 'Estadisticas_Devoluciones_' . date('Ymd_His') . '.xlsx';

ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');
$writer->save('php://output');
exit;
?>