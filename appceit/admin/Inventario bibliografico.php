<?php
require '../vendor/autoload.php';
require '../includes/funciones.php';

$auth = adminAutenticado();
if (!$auth) {
    header('Location: login.php');
    exit;
}

require '../includes/config/database.php';
$db = conectarDB();

// Configurar la conexión a la base de datos para usar UTF-8
mysqli_set_charset($db, 'utf8');

// Consulta para obtener los datos
$query = "SELECT libros.*, secciones.nombre_seccion AS seccion_nombre, secciones.color AS seccion_color 
          FROM libros 
          JOIN secciones ON libros.seccionId = secciones.id 
          ORDER BY libros.seccionId ASC, libros.codigo ASC";
$resultadoQuery = mysqli_query($db, $query);

// Incluir PhpSpreadsheet
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

// Crear una nueva hoja de cálculo
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Configurar los encabezados
$header = [
    'No.',
    'Código',
    'Título',
    'Disponibles',
    'Títulos',
    'Ejemplares',
    'Autor',
    'ISBN',
    'Editorial',
    'Edición',
    'Tomo',
    'Volumen',
    'Sección',
    'Tipo de adquisición',
    'Estatus',
    'Reserva'
];
$sheet->fromArray($header, NULL, 'A1');

// Aplicar estilos a los encabezados
$styleArrayHeader = [
    'font' => [
        'bold' => true,
        'color' => ['argb' => 'FFFFFFFF'],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FF09A787'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FF000000'],
        ],
    ],
];

// Aplicar estilos al contenido de la tabla
$styleArrayContent = [
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FF000000'],
        ],
    ],
];

// Aplicar el estilo a los encabezados
$sheet->getStyle('A1:P1')->applyFromArray($styleArrayHeader);

// Inicializar contador de filas
$fila = 2;
$numeroFila = 1;

// Rellenar la hoja de cálculo con datos
while ($libro = mysqli_fetch_assoc($resultadoQuery)) {
    $sheet->setCellValue("A$fila", $numeroFila);
    $sheet->setCellValue("B$fila", $libro['codigo']);
    $sheet->setCellValue("C$fila", $libro['titulo']);
    $sheet->setCellValue("D$fila", $libro['cantidad']);
    $sheet->setCellValue("E$fila", $libro['titulos'] ?: '');
    $sheet->setCellValue("F$fila", $libro['ejemplares'] ?: '');
    $sheet->setCellValue("G$fila", $libro['autor']);
    $sheet->setCellValue("H$fila", $libro['isbn']);
    $sheet->setCellValue("I$fila", $libro['editorial']);
    $sheet->setCellValue("J$fila", $libro['edicion']);
    $sheet->setCellValue("K$fila", $libro['tomo']);
    $sheet->setCellValue("L$fila", $libro['volumen']);
    $sheet->setCellValue("M$fila", $libro['seccion_nombre']);
    $sheet->setCellValue("N$fila", $libro['adquisicion']);
    $sheet->setCellValue("O$fila", $libro['status']);
    $sheet->setCellValue("P$fila", $libro['reserva']);

    // Aplicar el estilo al contenido de la fila actual
    $sheet->getStyle("A$fila:P$fila")->applyFromArray($styleArrayContent);

    $fila++;
    $numeroFila++;
}

// Ajustar el ancho de las columnas automáticamente
foreach (range('A', 'P') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Configurar la salida del archivo Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="inventario_bibliografico.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
