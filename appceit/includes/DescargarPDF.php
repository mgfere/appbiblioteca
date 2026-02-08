<?php
require '../vendor/autoload.php';
use Dompdf\Dompdf;

if (!isset($_GET['img'])) {
    die('No se especificó la imagen.');
}

$imgFile = basename($_GET['img']); // Seguridad básica
$baseDir = realpath(__DIR__ . '/../imagenes');
$fullPath = realpath($baseDir . DIRECTORY_SEPARATOR . $imgFile);

if (!$fullPath || strpos($fullPath, $baseDir) !== 0 || !file_exists($fullPath)) {
    die('Archivo no válido o no encontrado.');
}

// Obtener el tipo de código (QR o Barcode) del parámetro GET
$codeType = $_GET['type'] ?? 'barcode'; // Por defecto a 'barcode' si no se especifica

// Determinar el título del PDF según el tipo
$tituloPDF = "Código de Barras"; // Título por defecto
if ($codeType === 'qr') {
    $tituloPDF = "Código QR";
} else if ($codeType === 'barcode') {
    $tituloPDF = "Código de Barras";
}

// Convertir imagen a base64
$imageData = base64_encode(file_get_contents($fullPath));
$imageMime = mime_content_type($fullPath);
$imgSrc = 'data:' . $imageMime . ';base64,' . $imageData;

// Crear HTML con la imagen incrustada
$html = '<h1 style="text-align:center;">' . htmlspecialchars($tituloPDF) . '</h1>';
$html .= '<div style="text-align:center;">
            <img src="' . $imgSrc . '" style="width:300px;">
          </div>';

// Generar PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Descargar PDF
$nombrePDF = pathinfo($imgFile, PATHINFO_FILENAME) . '_' . strtolower($codeType) . '.pdf'; // Añadir el tipo al nombre del archivo
$dompdf->stream($nombrePDF, ["Attachment" => true]);
exit;
?>