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
$query = "SELECT * FROM usuariosexternos";
$resultadoQuery = mysqli_query($db, $query);

class PDF extends FPDF
{
    function Header()
    {
        // Logo
        $this->Image('logouttn.png', 14, 6, 35);
        $this->Image('logoceit.jpg', 355, 4, 35);
        $this->SetFont('Arial', 'B', 19);
        $this->Cell(0, 10, 'USUARIOS EXTERNOS', 0, 1, 'C');
        $this->Ln(10);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', array(216, 400)); // 'L' para orientación horizontal, 'mm' para milímetros, array(216, 356) para tamaño oficio
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 10);

// Establecer la zona horaria correcta
date_default_timezone_set('America/Mexico_City');

// Generar la fecha actual
$fecha_actual = date('d/m/Y');

// Agregar la celda con la fecha
$pdf->Cell(0, 10, utf8_decode('REPORTE GENERADO EL DÍA: ') . $fecha_actual, 0, 1, 'C');

// Encabezado de la tabla
$header = array('No.', 'Nombre completo', 'Identificación', 'Correo electrónico', 'Celular', 'Domicilio');
$widths = array(10, 105, 30, 45, 25, 165);

// Generar el encabezado de la tabla
for ($i = 0; $i < count($header); $i++) {
    $pdf->Cell($widths[$i], 10, utf8_decode($header[$i]), 1, 0, 'C');
}
$pdf->Ln();

$pdf->SetFont('Arial', '', 10);

// Inicializar contador de filas
$numeroFila = 1;

while ($usuario = mysqli_fetch_assoc($resultadoQuery)) {
    // Añadir celda con el número de fila
    $pdf->Cell($widths[0], 10, $numeroFila, 1, 0, 'C');
    $pdf->Cell($widths[1], 10, utf8_decode($usuario['nombreCompleto']), 1, 0, 'C');
    $pdf->Cell($widths[2], 10, utf8_decode($usuario['identificacion']), 1, 0, 'C');
    $pdf->Cell($widths[3], 10, utf8_decode($usuario['email']), 1, 0, 'C');
    $pdf->Cell($widths[4], 10, utf8_decode($usuario['celular']), 1, 0, 'C');
    $pdf->Cell($widths[5], 10, "Calle: " . utf8_decode($usuario['calle']) . " Col: " . utf8_decode($usuario['colonia']) . " CP: " . utf8_decode($usuario['CP']) . " Ciudad: " . utf8_decode($usuario['ciudad']), 1, 0);
    $pdf->Ln();

    // Incrementar el contador de filas
    $numeroFila++;
}

$pdf->Output();
