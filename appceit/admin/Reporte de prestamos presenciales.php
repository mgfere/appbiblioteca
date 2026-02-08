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

$resultadoPrestamos = $db->query($query);

class PDF extends FPDF
{
    // Cabecera de página
    function Header()
    {
        // Establecer la zona horaria correcta
        date_default_timezone_set('America/Mexico_City');

        // Generar la fecha actual
        $fecha_actual = date('d/m/Y');

        // Logo
        $this->Image('logouttn.png', 14, 6, 35);
        $this->Image('logoceit.jpg', 655, 4, 35);
        $this->SetFont('Arial', 'B', 19); // Aumentar la fuente del título
        $this->Cell(0, 10, utf8_decode('PRÉSTAMOS BIBLIOTECARIOS A USUARIOS EXTERNOS'), 0, 1, 'C');
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(380, 10, utf8_decode('REPORTE GENERADO EL DÍA: ') . $fecha_actual, 0, 1, 'R'); // Añadir fecha de generación
        $this->Ln(10);
        $this->SetFont('Arial', 'B', 12); // Restablecer la fuente para los encabezados de columna
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(10, 10, utf8_decode('No.'), 1, 0, 'C');
        $this->Cell(50, 10, utf8_decode('Fecha de préstamo'), 1, 0, 'C');
        $this->Cell(50, 10, utf8_decode('Fecha de devolución'), 1, 0, 'C');
        $this->Cell(80, 10, utf8_decode('Estatus'), 1, 0, 'C');
        $this->Cell(25, 10, utf8_decode('Código'), 1, 0, 'C');
        $this->Cell(90, 10, utf8_decode('Sección'), 1, 0, 'C');
        $this->Cell(200, 10, utf8_decode('Título del libro'), 1, 0, 'C');
        $this->Cell(30, 10, utf8_decode('Disponibles'), 1, 0, 'C');
        $this->Cell(80, 10, utf8_decode('Usuario'), 1, 0, 'C');
        $this->Cell(70, 10, utf8_decode('Correo electrónico'), 1, 0, 'C');
        $this->Ln();
    }

    // Pie de página
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', array(216, 700));
$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

$registroNumero = 1;

while ($prestamo = mysqli_fetch_assoc($resultadoPrestamos)) {
    $fechaPrestamo = date('d/m/Y', strtotime($prestamo['fechaPrestamo']));
    $fechaDevolucion = date('d/m/Y', strtotime($prestamo['fechaDevolucion']));
    $estatus = ($prestamo['estatus'] === '1') ? 'Pendiente de entregar' : 'Devuelto';
    $codigoLibro = $prestamo['codigoLibro'];
    $seccion = $prestamo['nombreSeccion']; // Obtener el nombre de la sección
    $tituloLibro = $prestamo['tituloLibro']; // Obtener el título del libro
    $cantidad = $prestamo['cantidad'];
    $nombreCompleto = $prestamo['nombreCompleto'];
    $email = $prestamo['email'];

    $pdf->Cell(10, 10, $registroNumero, 1, 0, 'C');
    $pdf->Cell(50, 10, $fechaPrestamo, 1, 0, 'C');
    $pdf->Cell(50, 10, $fechaDevolucion, 1, 0, 'C');
    $pdf->Cell(80, 10, $estatus, 1, 0, 'C');
    $pdf->Cell(25, 10, $codigoLibro, 1, 0, 'C');
    $pdf->Cell(90, 10, utf8_decode($seccion), 1, 0, 'C');
    $pdf->Cell(200, 10, utf8_decode($tituloLibro), 1, 0, 'C');
    $pdf->Cell(30, 10, $cantidad, 1, 0, 'C');
    $pdf->Cell(80, 10, utf8_decode($nombreCompleto), 1, 0, 'C');
    $pdf->Cell(70, 10, $email, 1, 0, 'C');
    $pdf->Ln();

    $registroNumero++;
}

$pdf->Output();
