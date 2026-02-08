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
$conn_sqlsrv = conectarDB2();

// Configurar la conexión a la base de datos para usar UTF-8
mysqli_set_charset($db, 'utf8');

//Consulta de usaios con SQl Server

$usuario_sqlsrv = "SELECT IdPersona, IdTurno, Nom, Paterno, Materno, 
                          Area, Email, CarreraNom, Matricula
                   FROM [Tutorias].[dbo].[DatosPersonales]
                   WHERE Estado = 1
                   ORDER BY Paterno ASC"; // Agregué un ORDER BY para que el reporte sea consistente

$stmt = sqlsrv_query($conn_sqlsrv, $usuario_sqlsrv);

if ($stmt === false) {
    die(print_r(sqlsrv_errors(), true));
}


/*
// Se usan LEFT JOIN para unir las tablas y obtener los nombres (usuarios de la db de MariaDB)
$query = "
SELECT 
    u.id, 
    u.nombre, 
    u.apellido, 
    u.matricula, 
    COALESCE(c.nombre_carrera, 'No asignada') as nombre_carrera, 
    COALESCE(e.nombre_especialidad, 'No asignada') as nombre_especialidad, 
    u.email 
FROM 
    usuarios u
LEFT JOIN 
    carreras c ON u.carreraId = c.id_carrera
LEFT JOIN 
    especialidades e ON u.especialidadId = e.id_especialidad
WHERE 
    u.estatus = 1
ORDER BY 
    u.apellido ASC
";
$resultadoQuery = mysqli_query($db, $query);
*/

class PDF extends FPDF
{
    function Header()
    {
        // Logo
        $this->Image('logouttn.png', 14, 6, 35);
        $this->Image('logoceit.jpg', 390, 4, 35);
        $this->SetFont('Arial', 'B', 19);
        $this->Cell(0, 10, 'USUARIOS INTERNOS', 0, 1, 'C');
        $this->Ln(10);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// 'L' para orientación horizontal, 'mm' para milímetros, array(216, 440) para tamaño oficio
$pdf = new PDF('L', 'mm', array(216, 440));
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
$header = array('No.', 'Nombre', utf8_decode('Matrícula'), 'Carrera', utf8_decode('Área'), utf8_decode('Correo electrónico'));
$widths = array(10, 120, 30, 100, 100, 60);

// Generar el encabezado de la tabla
for ($i = 0; $i < count($header); $i++) {
    $pdf->Cell($widths[$i], 10, $header[$i], 1, 0, 'C');
}
$pdf->Ln();

$pdf->SetFont('Arial', '', 10);

// Inicializar contador de filas
$numeroFila = 1;


/*
while ($usuario = mysqli_fetch_assoc($resultadoQuery)) {
    // Añadir celda con el número de fila
    $pdf->Cell($widths[0], 10, $numeroFila, 1, 0, 'C');
    $pdf->Cell($widths[1], 10, utf8_decode($usuario['apellido']) . " " . utf8_decode($usuario['nombre']), 1);
    $pdf->Cell($widths[2], 10, $usuario['matricula'], 1, 0, 'C');

    // --- CELDAS MODIFICADAS ---
    // Ahora usamos los nombres de la carrera y especialidad obtenidos de la consulta
    $pdf->Cell($widths[3], 10, utf8_decode($usuario['nombre_carrera']), 1, 0);
    $pdf->Cell($widths[4], 10, utf8_decode($usuario['nombre_especialidad']), 1, 0);
    
    $pdf->Cell($widths[5], 10, utf8_decode($usuario['email']), 1, 0, 'C');
    $pdf->Ln();

    // Incrementar el contador de filas
    $numeroFila++;
}*/

while ($fila = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
    $nombreCompleto = trim(($fila['Paterno'] ?? '') . " " . ($fila['Materno'] ?? '') ." ". ($fila['Nom'] ?? ''));

    $pdf->Cell($widths[0], 10, $numeroFila, 1, 0, 'C');
    $pdf->Cell($widths[1], 10, utf8_decode($nombreCompleto), 1, 0);
    $pdf->Cell($widths[2], 10, $fila['Matricula'] ?? 'N/A', 1, 0, 'C');
    $pdf->Cell($widths[3], 10, utf8_decode($fila['CarreraNom'] ?? 'N/A'), 1, 0);
    $pdf->Cell($widths[4], 10, utf8_decode($fila['Area'] ?? 'N/A'), 1, 0);
    $pdf->Cell($widths[5], 10, utf8_decode($fila['Email'] ?? 'N/A'), 1, 0, 'C');
    $pdf->Ln();
    
    $numeroFila++;
}
$pdf->Output();