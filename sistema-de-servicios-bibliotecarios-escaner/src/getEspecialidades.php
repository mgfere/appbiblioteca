<?php
require '../database/Database.php';
require '../database/DatabaseAPI.php';

$dbAPI = new DatabaseAPI();

if (isset($_POST['carreraId'])) {
    $carreraId = $_POST['carreraId'];
    $selectedId = $_POST['selectedId'] ?? 0;
    
    try {
        $especialidades = $dbAPI->especialidadesPorCarreras($carreraId, $selectedId);
        
        echo '<option value="" disabled selected>Seleccione una especialidad</option>';
        foreach ($especialidades as $especialidad) {
            $selected = $especialidad['selected'] ? 'selected' : '';
            echo '<option value="'.$especialidad['id_especialidad'].'" '.$selected.'>'
                .htmlspecialchars($especialidad['nombre_especialidad'])
                .'</option>';
        }
    } catch (Exception $e) {
        echo '<option value="">Error al cargar especialidades</option>';
    }
}
?>