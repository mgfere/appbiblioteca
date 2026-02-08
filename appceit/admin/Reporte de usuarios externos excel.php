<?php
require '../includes/funciones.php';

$auth = adminAutenticado();

if (!$auth) {
    header('Location: login.php');
    exit;
}

require '../includes/config/database.php';
require '../vendor/autoload.php'; // Incluye la biblioteca PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$db = conectarDB();

// Configurar la conexión a la base de datos para usar UTF-8
mysqli_set_charset($db, 'utf8');

// Consulta para obtener los datos
$query = "SELECT * FROM usuariosexternos";
$resultadoQuery = mysqli_query($db, $query);

// Crear un nuevo libro de Excel
$spreadsheet = new Spreadsheet();

// Seleccionar la hoja de cálculo activa
$sheet = $spreadsheet->getActiveSheet();

// Establecer la zona horaria correcta
date_default_timezone_set('America/Mexico_City');

// Generar la fecha actual
$fecha_actual = date('d/m/Y');

// Encabezado de la tabla
$header = ['No.', 'Nombre completo', 'Identificación', 'Correo electrónico', 'Celular', 'Domicilio'];
$widths = [10, 105, 30, 45, 25, 165];

// Agregar los encabezados
$column = 'A';
foreach ($header as $i => $head) {
    $sheet->setCellValue($column . '1', $head);
    $column++;
}

// Estilo para el encabezado
$styleArrayHeader = [
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
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => '000000'],
        ],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
    ],
];

// Estilo para el contenido
$styleArrayContent = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => '000000'],
        ],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
    ],
];

// Aplicar estilo al encabezado
$sheet->getStyle('A1:F1')->applyFromArray($styleArrayHeader);

// Inicializar el número de fila
$fila = 2;
$numeroFila = 1;

// Rellenar la hoja de cálculo con datos
while ($usuario = mysqli_fetch_assoc($resultadoQuery)) {
    // Añadir los datos a la hoja de cálculo
    $sheet->setCellValue('A' . $fila, $numeroFila);
    $sheet->setCellValue('B' . $fila, $usuario['nombreCompleto']);
    $sheet->setCellValue('C' . $fila, $usuario['identificacion']);
    $sheet->setCellValue('D' . $fila, $usuario['email']);
    $sheet->setCellValue('E' . $fila, $usuario['celular']);
    $sheet->setCellValue('F' . $fila, "Calle: " . $usuario['calle'] . " Col: " . $usuario['colonia'] . " CP: " . $usuario['CP'] . " Ciudad: " . $usuario['ciudad']);

    // Aplicar estilo al contenido de la fila
    $sheet->getStyle('A' . $fila . ':F' . $fila)->applyFromArray($styleArrayContent);

    $fila++;
    $numeroFila++;
}

// Ajustar el ancho de las columnas automáticamente
foreach (range('A', 'F') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Configurar la salida del archivo Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="usuarios_externos.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
