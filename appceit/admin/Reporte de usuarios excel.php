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
$query = "
SELECT 
    id, 
    nombre, 
    apellido, 
    matricula, 
    carreraId, 
    especialidadId, 
    email,
    turno 
FROM 
    usuarios 
WHERE usuarios.estatus = 1
ORDER BY 
    apellido ASC
";
$resultadoQuery = mysqli_query($db, $query);

// Crear un nuevo libro de Excel
$spreadsheet = new Spreadsheet();

// Seleccionar la hoja de cálculo activa
$sheet = $spreadsheet->getActiveSheet();

// Encabezado de la tabla
$header = ['No.', 'Nombre', 'Matrícula', 'Carrera', 'Area', 'Correo electrónico', 'Turno'];
$columnWidths = [10, 120, 30, 100, 100, 60, 60];

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
$sheet->getStyle('A1:G1')->applyFromArray($styleArrayHeader);

// Inicializar el número de fila
$fila = 2;
$numeroFila = 1;

// Rellenar la hoja de cálculo con datos
while ($usuario = mysqli_fetch_assoc($resultadoQuery)) {
    // Añadir los datos a la hoja de cálculo
    $sheet->setCellValue('A' . $fila, $numeroFila);
    $sheet->setCellValue('B' . $fila, $usuario['apellido'] . " " . $usuario['nombre']);
    $sheet->setCellValue('C' . $fila, $usuario['matricula']);
    $sheet->setCellValue('D' . $fila, $usuario['carreraId']);
    $sheet->setCellValue('E' . $fila, $usuario['especialidadId']);
    $sheet->setCellValue('F' . $fila, $usuario['email']);
    $sheet->setCellValue('G' . $fila, $usuario['turno']);

    // Aplicar estilo al contenido de la fila
    $sheet->getStyle('A' . $fila . ':G' . $fila)->applyFromArray($styleArrayContent);

    $fila++;
    $numeroFila++;
}

// Ajustar el ancho de las columnas automáticamente
foreach (range('A', 'G') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Configurar la salida del archivo Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="usuarios_internos.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
