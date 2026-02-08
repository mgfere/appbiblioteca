<?php
require '../includes/funciones.php';

$auth = adminAutenticado();

if (!$auth) {
    header('Location: login.php');
    exit;
}

require '../includes/config/database.php';
require '../vendor/autoload.php'; // Incluye la biblioteca PhpSpreadsheet

$db = conectarDB();

if ($db->connect_error) {
    die('Error de conexión: ' . $db->connect_error);
}


// Configurar la conexión a la base de datos para usar UTF-8
mysqli_set_charset($db, 'utf8');

// Consulta para obtener los datos con el nombre de la sección y el título del libro
$query = "
       SELECT 
        p.*, 
        s.nombre_seccion AS nombreSeccion,
        l.titulo AS tituloLibro
    FROM 
        prestamospresencial p 
    LEFT JOIN 
        secciones s ON p.seccionId = s.id
    LEFT JOIN
        libros l ON p.codigoLibro = l.codigo AND l.seccionId = p.seccionId
    WHERE 
        p.estatus = 1  
";

// Verificar si la consulta tiene errores
if (!$resultadoPrestamos = $db->query($query)) {
    die('Error en la consulta: ' . $db->error);
}

// Crear un nuevo libro de Excel
$spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();

use PhpOffice\PhpSpreadsheet\Style\Fill;

// Seleccionar la hoja de cálculo activa
$sheet = $spreadsheet->getActiveSheet();

// Establecer la zona horaria correcta
date_default_timezone_set('America/Mexico_City');

// Generar la fecha actual
$fecha_actual = date('d/m/Y');

// Agregar los encabezados
$sheet->setCellValue('A1', 'No.');
$sheet->setCellValue('B1', 'Fecha de préstamo');
$sheet->setCellValue('C1', 'Fecha de devolución');
$sheet->setCellValue('D1', 'Estatus');
$sheet->setCellValue('E1', 'Código');
$sheet->setCellValue('F1', 'Sección');
$sheet->setCellValue('G1', 'Título del libro');
$sheet->setCellValue('H1', 'Disponibles');
$sheet->setCellValue('I1', 'Usuario');
$sheet->setCellValue('J1', 'Correo electrónico');

// Establecer el ancho de las columnas
$sheet->getColumnDimension('A')->setWidth(10);
$sheet->getColumnDimension('B')->setWidth(20);
$sheet->getColumnDimension('C')->setWidth(20);
$sheet->getColumnDimension('D')->setWidth(20);
$sheet->getColumnDimension('E')->setWidth(20);
$sheet->getColumnDimension('F')->setWidth(30);
$sheet->getColumnDimension('G')->setWidth(50);
$sheet->getColumnDimension('H')->setWidth(20);
$sheet->getColumnDimension('I')->setWidth(30);
$sheet->getColumnDimension('J')->setWidth(50);

// Establecer el estilo de las celdas
$styleArray = [
    'font' => [
        'bold' => true,
        'color' => ['argb' => 'FFFFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FF09A787'],
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['argb' => '000000'],
        ],
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
    ],
];

$styleArray2 = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
            'color' => ['argb' => '000000'],
        ],
    ],
    'alignment' => [
        'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
    ],
];

// Aplicar el estilo a la fila de encabezado
$sheet->getStyle('A1:J1')->applyFromArray($styleArray);

// Aplicar borde a todas las celdas con contenido
$sheet->getStyle('A1:J1')->applyFromArray($styleArray);
$sheet->getStyle('A2:J' . ($resultadoPrestamos->num_rows + 1))->applyFromArray($styleArray2);

// Inicializar el número de fila
$fila = 2;
$numeroFila = 1;

// Función para convertir estatus numérico a texto
function getEstatusTexto($estatus)
{
    return ($estatus === '1') ? 'Pendiente de entregar' : 'Devuelto';
}

// Rellenar la hoja de cálculo con datos
while ($prestamo = $resultadoPrestamos->fetch_assoc()) {
    $sheet->setCellValue("A$fila", $numeroFila);
    $sheet->setCellValue("B$fila", date('d/m/Y', strtotime($prestamo['fechaPrestamo'])));
    $sheet->setCellValue("C$fila", date('d/m/Y', strtotime($prestamo['fechaDevolucion'])));
    $sheet->setCellValue("D$fila", getEstatusTexto($prestamo['estatus']));
    $sheet->setCellValue("E$fila", $prestamo['codigoLibro']);
    $sheet->setCellValue("F$fila", $prestamo['nombreSeccion']);
    $sheet->setCellValue("G$fila", $prestamo['tituloLibro']);
    $sheet->setCellValue("H$fila", $prestamo['cantidad']);
    $sheet->setCellValue("I$fila", $prestamo['nombreCompleto']);
    $sheet->setCellValue("J$fila", $prestamo['email']);
    $fila++;
    $numeroFila++;
}

foreach (range('A', 'J') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Configurar la salida del archivo Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="prestamos_presencial.xlsx"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
$writer->save('php://output');
exit;
