<?php
require_once '../database/Database.php';
require_once '../database/DatabaseAPI.php';

header('Content-Type: application/json');

try {
    // Validaciones
    if (!isset($_FILES['archivo'])) {
        throw new Exception('No se ha subido ningún archivo');
    }

    $archivo = $_FILES['archivo'];
    $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);

    if (strtolower($extension) !== 'csv') {
        throw new Exception('Solo los archivos .csv son admitidos');
    }

    if ($archivo['size'] > 5 * 1024 * 1024) {
        throw new Exception('El archivo puede ser solo de 5MB ');
    }

    $db = new DatabaseAPI();
    
    if (!$db->tablaTemporalAlumnos()) {
        throw new Exception('No se pudo crear la tabla temporal');
    }
    
   
    $rutaTemporal = $archivo['tmp_name'];
    if (!$db->cargarCSVaTemporal($rutaTemporal)) {
        throw new Exception('Error al cargar datos a tabla temporal');
    }
    
    $stmt = $db->getPDO()->query("SELECT COUNT(*) FROM temporalcsvdatos");
    $count = $stmt->fetchColumn();
    if ($count == 0) {
        throw new Exception('El archivo CSV no contenía datos válidos');
    }
  
    $resultado = $db->importarAlumnosDesdeCSV();
    
    echo json_encode([
        'success' => true,
        'message' => $resultado,
        'insertados' => $count
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'error' => 'Error en la base de datos: ' . $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}