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

// Consulta para obtener los datos de los préstamos
$query = "SELECT * FROM prestamos WHERE status = '1'";
$resultadoQuery = mysqli_query($db, $query);

class PDF extends FPDF
{
    function Header()
    {
        // Logo
        $this->Image('logouttn.png', 14, 6, 35);
        $this->Image('logoceit.jpg', 838, 4, 35);
        $this->SetFont('Arial', 'B', 18);
        $this->Cell(0, 10, utf8_decode('PRÉSTAMOS BIBLIOTECARIOS'), 0, 1, 'C');
        $this->Ln(10);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF('L', 'mm', array(216, 890));
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 12);

// Establecer la zona horaria correcta
date_default_timezone_set('America/Mexico_City');

// Generar la fecha actual
$fecha_actual = date('d/m/Y');

// Agregar la celda con la fecha
$pdf->Cell(0, 10, utf8_decode('REPORTE GENERADO EL DÍA: ') . $fecha_actual, 0, 1, 'C');

// Añadir la nueva columna "Número de fila"
$header = array('No.', 'Fecha de prestamo', 'Fecha de devolución', 'Estatus', 'Código', 'Sección', 'Título del libro', 'Disponibles', 'Usuario', 'Correo electrónico', 'Carrera', 'Especialidad');
$widths = array(10, 40, 45, 40, 20, 80, 200, 30, 100, 100, 100, 100);

// Generar el encabezado de la tabla
for ($i = 0; $i < count($header); $i++) {
    $pdf->Cell($widths[$i], 10, utf8_decode($header[$i]), 1, 0, 'C');
}
$pdf->Ln();

$pdf->SetFont('Arial', '', 10);

// Inicializar contador de filas
$numeroFila = 1;

while ($prestamo = mysqli_fetch_assoc($resultadoQuery)) {
    // Obtener los datos del libro
    $consultaLibro = "SELECT libros.codigo, libros.titulo AS libro_titulo, secciones.nombre_seccion AS seccion_nombre, secciones.color 
                      FROM libros 
                      INNER JOIN secciones ON libros.seccionId = secciones.id 
                      WHERE libros.id = {$prestamo['Libros_id']}";
    $resultadoLibro = mysqli_query($db, $consultaLibro);
    $libro = mysqli_fetch_assoc($resultadoLibro);

    // Obtener los datos del usuario
    $consultaUsuario = "SELECT usuarios.nombre, usuarios.apellido,usuarios.email, usuarios.carreraId, usuarios.especialidadId
                        FROM usuarios 
                        WHERE usuarios.id = {$prestamo['Estudiantes_id']}";
    $resultadoUsuario = mysqli_query($db, $consultaUsuario);
    $usuario = mysqli_fetch_assoc($resultadoUsuario);

    // Añadir celda con el número de fila
    $pdf->Cell($widths[0], 10, $numeroFila, 1, 0, 'C');
    $pdf->Cell($widths[1], 10, date('d/m/Y', strtotime($prestamo['fecha_prestamo'])), 1, 0, 'C');
    $pdf->Cell($widths[2], 10, date('d/m/Y', strtotime($prestamo['fecha_devolucion'])), 1, 0, 'C');
    $pdf->Cell($widths[3], 10, ($prestamo['status'] === "1") ? "Pendiente de entregar" : "Devuelto", 1, 0, 'C');
    $pdf->Cell($widths[4], 10, utf8_decode($libro['codigo']), 1, 0, 'C');
    $pdf->Cell($widths[5], 10, utf8_decode($libro['seccion_nombre']), 1, 0, 'C');
    $pdf->Cell($widths[6], 10, utf8_decode($libro['libro_titulo']), 1, 0, 'C');
    $pdf->Cell($widths[7], 10, $prestamo['cantidad'], 1, 0, 'C');
    $pdf->Cell($widths[8], 10, utf8_decode($usuario['apellido'] . " " . $usuario['nombre']), 1, 0, 'C');
    $pdf->Cell($widths[9], 10, utf8_decode($usuario['email']), 1, 0, 'C');
    $pdf->Cell($widths[10], 10, utf8_decode($usuario['carreraId']), 1, 0, 'C');
    $pdf->Cell($widths[11], 10, utf8_decode($usuario['especialidadId']), 1, 0, 'C');
    $pdf->Ln();

    // Incrementar el contador de filas
    $numeroFila++;
}

$pdf->Output();

// Cerrar la conexión a la base de datos
mysqli_close($db);
