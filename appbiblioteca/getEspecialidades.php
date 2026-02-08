<?php
require_once 'database/Database.php';
require_once 'database/DatabaseAPI.php';

header('Content-Type: text/html; charset=utf-8');

if (isset($_POST['carreraId'])) {
    
    try {
        $dbAPI = new DatabaseAPI();
        
        $carreraId = intval($_POST['carreraId']);
        $selectedId = isset($_POST['selectedId']) ? intval($_POST['selectedId']) : 0;
        
        $especialidades = $dbAPI->especialidadesPorCarrerasSQL($carreraId);
        
        // Opción por defecto
        echo '<option value="" disabled selected>Seleccione una especialidad</option>';
        
        if (empty($especialidades)) {
            echo '<option value="" disabled>No hay especialidades disponibles para esta carrera</option>';
        } else {
            foreach ($especialidades as $especialidad) {
                $id = $especialidad['id_especialidad'];
                $nombre = htmlspecialchars($especialidad['nombre_especialidad']);
                
                $isSelected = ($selectedId > 0 && $selectedId == $id) ? 'selected' : '';
                
                echo "<option value='{$id}' {$isSelected}>{$nombre}</option>";
            }
        }
        
    } catch (Exception $e) {
        error_log("Error en getEspecialidades.php: " . $e->getMessage());
        echo '<option value="">Error al cargar datos</option>';
    }
} else {
    echo '<option value="">Seleccione una carrera primero</option>';
}
?>