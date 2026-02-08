<?php
require '../includes/funciones.php';

$auth = adminAutenticado();

if (!$auth) {
    header('Location: login.php');
    exit;
}

require '../fpdf/fpdf.php';
require '../includes/config/database.php';

$db = conectarDB();

// Configurar la conexión a la base de datos para usar UTF-8
mysqli_set_charset($db, 'utf8');

// Consulta para obtener los datos
$query = "SELECT libros.*, secciones.nombre_seccion AS seccion_nombre, secciones.color AS seccion_color FROM libros JOIN secciones ON libros.seccionId = secciones.id ORDER BY libros.seccionId ASC, libros.codigo ASC";
$resultadoQuery = mysqli_query($db, $query);

class PDF extends FPDF
{
    function Header()
    {
        // Logo
        $this->Image('logouttn.png', 14, 6, 35);
        $this->Image('logoceit.jpg', 880, 4, 35);
        $this->SetFont('Arial', 'B', 19);
        $this->Cell(0, 10, utf8_decode('INVENTARIO BIBLIOGRÁFICO'), 0, 1, 'C');
        $this->Ln(10);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', array(980, 356)); // Definir tamaño oficio
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 12);

// Establecer la zona horaria correcta
date_default_timezone_set('America/Mexico_City');

// Generar la fecha actual
$fecha_actual = date('d/m/Y');

// Agregar la celda con la fecha
$pdf->Cell(0, 10, utf8_decode('REPORTE GENERADO EL DÍA: ') . $fecha_actual, 0, 1, 'C');

$header = array('No.', 'Código', 'Título', 'Disponibles', 'Títulos', 'Ejemplares', 'Autor', 'ISBN', 'Editorial', 'Edición', 'Tomo', 'Volumen', 'Sección', 'Tipo de adquisición', 'Estatus', 'Reserva');
$widths = array(10, 30, 200, 30, 35, 30, 200, 45, 100, 20, 20, 20, 80, 50, 20, 20);

// Generar el encabezado de la tabla
for ($i = 0; $i < count($header); $i++) {
    $pdf->Cell($widths[$i], 10, utf8_decode($header[$i]), 1, 0, 'C');
}
$pdf->Ln();

$pdf->SetFont('Arial', '', 10);

// Inicializar contador de filas
$numeroFila = 1;

while ($libro = mysqli_fetch_assoc($resultadoQuery)) {

    if ($libro['titulos'] == 0) {
        $libro['titulos'] = '';
    }

    if ($libro['ejemplares'] == 0) {
        $libro['ejemplares'] = '';
    }


    // Añadir celda con el número de fila
    $pdf->Cell($widths[0], 10, $numeroFila, 1, 0, 'C');
    $pdf->Cell($widths[1], 10, utf8_decode($libro['codigo']), 1, 0, 'C');
    $pdf->Cell($widths[2], 10, utf8_decode($libro['titulo']), 1);
    $pdf->Cell($widths[3], 10, $libro['cantidad'], 1, 0, 'C');
    $pdf->Cell($widths[4], 10, $libro['titulos'], 1, 0, 'C');
    $pdf->Cell($widths[5], 10, $libro['ejemplares'], 1, 0, 'C');
    $pdf->Cell($widths[6], 10, utf8_decode($libro['autor']), 1);
    $pdf->Cell($widths[7], 10, $libro['isbn'], 1, 0, 'C');
    $pdf->Cell($widths[8], 10, $libro['editorial'], 1, 0, 'C');
    $pdf->Cell($widths[9], 10, utf8_decode($libro['edicion']), 1, 0, 'C');
    $pdf->Cell($widths[10], 10, $libro['tomo'], 1, 0, 'C');
    $pdf->Cell($widths[11], 10, $libro['volumen'], 1, 0, 'C');
    $pdf->Cell($widths[12], 10, utf8_decode($libro['seccion_nombre']), 1);
    $pdf->Cell($widths[13], 10, utf8_decode($libro['adquisicion']), 1, 0, 'C');
    $pdf->Cell($widths[14], 10, utf8_decode($libro['status']), 1, 0, 'C');
    $pdf->Cell($widths[15], 10, utf8_decode($libro['reserva']), 1, 0, 'C');
    $pdf->Ln();

    // Incrementar el contador de filas
    $numeroFila++;
}

$pdf->Output();
