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

// Consulta para obtener los datos de los préstamos
$query = "SELECT * FROM prestamos WHERE status = '1'";
$resultadoQuery = mysqli_query($db, $query);

// Crear un nuevo libro de Excel
$spreadsheet = new Spreadsheet();

// Seleccionar la hoja de cálculo activa
$sheet = $spreadsheet->getActiveSheet();

// Establecer la zona horaria correcta
date_default_timezone_set('America/Mexico_City');

// Generar la fecha actual
$fecha_actual = date('d/m/Y');

// Añadir la nueva columna "Número de fila"
$header = ['No.', 'Fecha de préstamo', 'Fecha de devolución', 'Estatus', 'Código', 'Sección', 'Título del libro', 'Disponibles', 'Usuario', 'Correo electrónico', 'Carrera', 'Especialidad'];

// Agregar los encabezados
$column = 'A';
foreach ($header as $i => $head) {
    $sheet->setCellValue($column . '1', $head);
    $column++;
}

// Estilo para el encabezado
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
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => 'FF000000'],
        ],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
    ],
];

// Estilo para el contenido
$styleArray2 = [
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => '000000'],
        ],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
    ],
    'font' => [
        'bold' => false,
    ],
];

// Aplicar estilo al encabezado
$sheet->getStyle('A1:L1')->applyFromArray($styleArray);

// Inicializar el número de fila
$fila = 2;
$numeroFila = 1;

// Función para convertir estatus numérico a texto
function getEstatusTexto($estatus)
{
    return ($estatus === '1') ? 'Pendiente de entregar' : 'Devuelto';
}

// Rellenar la hoja de cálculo con datos
while ($prestamo = mysqli_fetch_assoc($resultadoQuery)) {
    // Obtener los datos del libro
    $consultaLibro = "SELECT libros.codigo, libros.titulo AS libro_titulo, secciones.nombre_seccion AS seccion_nombre 
                      FROM libros 
                      INNER JOIN secciones ON libros.seccionId = secciones.id 
                      WHERE libros.id = {$prestamo['Libros_id']}";
    $resultadoLibro = mysqli_query($db, $consultaLibro);
    $libro = mysqli_fetch_assoc($resultadoLibro);

    // Obtener los datos del usuario
    $consultaUsuario = "SELECT usuarios.nombre, usuarios.apellido, usuarios.email, usuarios.carreraId, usuarios.especialidadId
                        FROM usuarios 
                        WHERE usuarios.id = {$prestamo['Estudiantes_id']}";
    $resultadoUsuario = mysqli_query($db, $consultaUsuario);
    $usuario = mysqli_fetch_assoc($resultadoUsuario);

    // Añadir los datos a la hoja de cálculo
    $sheet->setCellValue('A' . $fila, $numeroFila);
    $sheet->setCellValue('B' . $fila, date('d/m/Y', strtotime($prestamo['fecha_prestamo'])));
    $sheet->setCellValue('C' . $fila, date('d/m/Y', strtotime($prestamo['fecha_devolucion'])));
    $sheet->setCellValue('D' . $fila, getEstatusTexto($prestamo['status']));
    $sheet->setCellValue('E' . $fila, $libro['codigo']);
    $sheet->setCellValue('F' . $fila, $libro['seccion_nombre']);
    $sheet->setCellValue('G' . $fila, $libro['libro_titulo']);
    $sheet->setCellValue('H' . $fila, $prestamo['cantidad']);
    $sheet->setCellValue('I' . $fila, $usuario['apellido'] . " " . $usuario['nombre']);
    $sheet->setCellValue('J' . $fila, $usuario['email']);
    $sheet->setCellValue('K' . $fila, $usuario['carreraId']);
    $sheet->setCellValue('L' . $fila, $usuario['especialidadId']);

    // Aplicar estilo al contenido de la fila
    $sheet->getStyle('A' . $fila . ':L' . $fila)->applyFromArray($styleArray2);

    $fila++;
    $numeroFila++;
}

// Ajustar el ancho de las columnas automáticamente
foreach (range('A', 'L') as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
}

// Configurar la salida del archivo Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="prestamos_bibliotecarios.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
