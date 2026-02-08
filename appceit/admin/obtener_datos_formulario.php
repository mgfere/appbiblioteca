<?php
require '../includes/funciones.php';
require '../includes/config/database.php';

$auth = adminAutenticado();

if (!$auth) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$db = conectarDB();

$tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';

switch ($tipo) {
    case 'carreras':
        $query = "SELECT id_carrera, nombre_carrera FROM carreras ORDER BY nombre_carrera ASC";
        $resultado = mysqli_query($db, $query);
        $carreras = [];
        
        while ($carrera = mysqli_fetch_assoc($resultado)) {
            $carreras[] = [
                'id' => $carrera['id_carrera'],
                'nombre' => $carrera['nombre_carrera']
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($carreras);
        break;
        
    case 'especialidades':
        $query = "SELECT id_especialidad, nombre_especialidad FROM especialidades ORDER BY nombre_especialidad ASC";
        $resultado = mysqli_query($db, $query);
        $especialidades = [];
        
        while ($especialidad = mysqli_fetch_assoc($resultado)) {
            $especialidades[] = [
                'id' => $especialidad['id_especialidad'],
                'nombre' => $especialidad['nombre_especialidad']
            ];
        }
        
        header('Content-Type: application/json');
        echo json_encode($especialidades);
        break;
        
    case 'usuario':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if ($id > 0) {
            $query = "SELECT 
                usuarios.id,
                usuarios.nombre,
                usuarios.apellido,
                usuarios.matricula,
                usuarios.email,
                usuarios.carreraId,
                usuarios.especialidadId,
                usuarios.turno,
                carreras.nombre_carrera,
                especialidades.nombre_especialidad
            FROM usuarios
            LEFT JOIN carreras ON usuarios.carreraId = carreras.id_carrera
            LEFT JOIN especialidades ON usuarios.especialidadId = especialidades.id_especialidad
            WHERE usuarios.id = $id AND usuarios.estatus = 1";
            
            $resultado = mysqli_query($db, $query);
            
            if ($usuario = mysqli_fetch_assoc($resultado)) {
                header('Content-Type: application/json');
                echo json_encode($usuario);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Usuario no encontrado']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'ID de usuario no válido']);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Tipo de dato no válido']);
        break;
}

mysqli_close($db);
?>