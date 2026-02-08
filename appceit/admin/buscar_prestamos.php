<?php
require '../includes/funciones.php';
$auth = adminAutenticado();
if (!$auth) exit('No autorizado');

require '../includes/config/database.php';
$db_mysql = conectarDB();
$conn_gestion = conectarDB3();

$query = isset($_GET['query']) ? trim($_GET['query']) : '';
if (empty($query)) { echo '<tr><td colspan="12">Ingrese búsqueda</td></tr>'; exit; }
$searchTerm = '%' . $query . '%';

$prestamos_encontrados = [];
$ids_usuarios_encontrados = [];


$sql_alumnos = "SELECT IdAlumno as Id FROM [GestionUsuarios].[dbo].[Alumnos]
                WHERE Nombre LIKE ? OR ApellidoPaterno LIKE ? OR Matricula LIKE ? 
                OR CONCAT(Nombre, ' ', ApellidoPaterno) LIKE ?";
$res_al = sqlsrv_query($conn_gestion, $sql_alumnos, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
if($res_al) while($row = sqlsrv_fetch_array($res_al)) $ids_usuarios_encontrados[] = $row['Id'];

$sql_docentes = "SELECT IdDocente as Id FROM [GestionUsuarios].[dbo].[Docentes]
                 WHERE Nombre LIKE ? OR ApellidoPaterno LIKE ? OR Matricula LIKE ? 
                 OR NumeroEmpleado LIKE ?";
$res_doc = sqlsrv_query($conn_gestion, $sql_docentes, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
if($res_doc) while($row = sqlsrv_fetch_array($res_doc)) $ids_usuarios_encontrados[] = $row['Id'];


$sql_local = "SELECT id FROM usuarios 
              WHERE nombre LIKE ? OR apellido LIKE ? OR matricula LIKE ?
              OR CONCAT(nombre, ' ', apellido) LIKE ?";
$stmt = $db_mysql->prepare($sql_local);
$stmt->bind_param('ssss', $searchTerm, $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();
$res = $stmt->get_result();
while($row = $res->fetch_assoc()) $ids_usuarios_encontrados[] = $row['id'];

$ids_libros = [];
$sql_libros = "SELECT id FROM libros WHERE codigo LIKE ? OR titulo LIKE ?";
$stmt_l = $db_mysql->prepare($sql_libros);
$stmt_l->bind_param('ss', $searchTerm, $searchTerm);
$stmt_l->execute();
$res_l = $stmt_l->get_result();
while($row = $res_l->fetch_assoc()) $ids_libros[] = $row['id'];


if (!empty($ids_usuarios_encontrados) || !empty($ids_libros)) {
    $sql_p = "SELECT p.*, l.codigo, s.color 
              FROM prestamos p 
              JOIN libros l ON p.Libros_id = l.id 
              JOIN secciones s ON l.seccionId = s.id 
              WHERE p.status = '1'";
    
    $condiciones = [];
    $params = [];
    $tipos = "";

    if (!empty($ids_usuarios_encontrados)) {
        $ids_u = array_values(array_unique($ids_usuarios_encontrados)); // Limpiar duplicados
        $in = implode(',', array_fill(0, count($ids_u), '?'));
        $condiciones[] = "p.Estudiantes_id IN ($in)";
        $params = array_merge($params, $ids_u);
        $tipos .= str_repeat('i', count($ids_u));
    }

    if (!empty($ids_libros)) {
        $in = implode(',', array_fill(0, count($ids_libros), '?'));
        $condiciones[] = "p.Libros_id IN ($in)";
        $params = array_merge($params, $ids_libros);
        $tipos .= str_repeat('i', count($ids_libros));
    }

    $sql_p .= " AND (" . implode(' OR ', $condiciones) . ") ORDER BY p.fecha_prestamo DESC";
    
    $stmt_p = $db_mysql->prepare($sql_p);
    $stmt_p->bind_param($tipos, ...$params);
    $stmt_p->execute();
    $res_p = $stmt_p->get_result();
    
    while($row = $res_p->fetch_assoc()) {
        $prestamos_encontrados[] = $row;
    }
}


if (count($prestamos_encontrados) > 0) {
    
    $mapa_usuarios = [];
    $ids_gestion = []; 
    $ids_local = [];
    
    foreach ($prestamos_encontrados as $p) {
        if (!empty($p['matricula']) && $p['matricula'] != '0') {
            $ids_gestion[] = $p['Estudiantes_id'];
        } else {
            $ids_local[] = $p['Estudiantes_id'];
        }
    }

    if (!empty($ids_gestion)) {
        $ids_unicos = array_values(array_unique($ids_gestion));
        $placeholders = implode(',', array_fill(0, count($ids_unicos), '?'));
        $params_u = array_merge($ids_unicos, $ids_unicos);
        
        $query_usuarios = "SELECT 'alumno' as tipo, IdAlumno as IdPersona, Matricula, Nombre as Nom, ApellidoPaterno as Paterno, ApellidoMaterno as Materno, IdCarrera
                           FROM [GestionUsuarios].[dbo].[Alumnos] WHERE IdAlumno IN ($placeholders)
                           UNION ALL
                           SELECT 'docente' as tipo, IdDocente as IdPersona, Matricula, Nombre as Nom, ApellidoPaterno as Paterno, ApellidoMaterno as Materno, NULL as IdCarrera
                           FROM [GestionUsuarios].[dbo].[Docentes] WHERE IdDocente IN ($placeholders)";
        
        $res_usuarios = sqlsrv_query($conn_gestion, $query_usuarios, $params_u);
        if ($res_usuarios) {
            while ($row = sqlsrv_fetch_array($res_usuarios, SQLSRV_FETCH_ASSOC)) {
                $mapa_usuarios[$row['IdPersona']] = $row;
            }
        }
    }

    if (!empty($ids_local)) {
        $ids_unicos_local = array_values(array_unique($ids_local));
        $ids_string = implode(',', array_map('intval', $ids_unicos_local));
        
        if (!empty($ids_string)) {
            $query_local_data = "SELECT id as IdPersona, nombre as Nom, apellido as Paterno, '' as Materno, 
                                 matricula as Matricula, carreraId as IdCarrera, 'local' as tipo
                                 FROM usuarios WHERE id IN ($ids_string)";
            $res_local_data = mysqli_query($db_mysql, $query_local_data);
            if ($res_local_data) {
                while ($row = mysqli_fetch_assoc($res_local_data)) {
                    if (empty($row['Matricula'])) $row['Matricula'] = 'S/M';
                    $mapa_usuarios[$row['IdPersona']] = $row;
                }
            }
        }
    }

    $carreras_map = [];
    $res_carreras = sqlsrv_query($conn_gestion, "SELECT IdCarrera, Nombre FROM [GestionUsuarios].[dbo].[Carreras]");
    if ($res_carreras) {
        while ($row = sqlsrv_fetch_array($res_carreras, SQLSRV_FETCH_ASSOC)) {
            $carreras_map[$row['IdCarrera']] = $row['Nombre'];
        }
    }

    // Generar HTML
    foreach ($prestamos_encontrados as $prestamo) {
        $estudiante_id = $prestamo['Estudiantes_id'];
        
        if (!isset($mapa_usuarios[$estudiante_id])) continue;
        
        $usuario = $mapa_usuarios[$estudiante_id];
        
        $nombre_completo = ucwords(strtolower(trim(
            ($usuario['Nom'] ?? '') . " " . 
            ($usuario['Paterno'] ?? '') . " " . 
            ($usuario['Materno'] ?? '')
        )));
        
        $tipo_usuario = $usuario['tipo'] ?? 'desconocido';
        $id_carrera = $usuario['IdCarrera'] ?? null;
        $nombre_carrera = $id_carrera ? ($carreras_map[$id_carrera] ?? 'No asignada') : 'N/A';
        $matricula = $usuario['Matricula'] ?? 'Sin matrícula';

        echo '<tr>';
        echo '<td class="textosm fecha-devolucion" data-fecha="' . date('Y-m-d', strtotime($prestamo['fecha_devolucion'])) . '">' . date('d/m/Y', strtotime($prestamo['fecha_prestamo'])) . '</td>';
        echo '<td class="textosm fecha-devolucion" data-fecha="' . date('Y-m-d', strtotime($prestamo['fecha_devolucion'])) . '">' . date('d/m/Y', strtotime($prestamo['fecha_devolucion'])) . '</td>';
        echo '<td class="textosm">' . (($prestamo['status'] === "1") ? "Préstamo" : "Devuelto") . '</td>';
        echo '<td class="textosm"><button style="background-color: ' . $prestamo['color'] . '" class="reservacion--libro"></button>' . htmlspecialchars($prestamo['codigo']) . '</td>';
        echo '<td class="textosm">' . htmlspecialchars($prestamo['cantidad']) . '</td>';
        echo '<td class="textosm">' . htmlspecialchars($nombre_completo) . '</td>';
        echo '<td class="textosm">' . ucfirst($tipo_usuario) . '</td>';
        echo '<td class="textosm">' . htmlspecialchars($nombre_carrera) . '</td>';
        echo '<td class="textosm">' . htmlspecialchars($matricula) . '</td>';
        
        if ($rolAdministrador == 1) {
            echo '<td class="textosm">' . htmlspecialchars($prestamo['entregado']) . '</td>';
        }
        
        echo '<td>';
        echo '<div class="botones--accion--container">';
        echo '<button title="Devuelto" type="button" class="btnAceptado" value="' . $prestamo['id'] . '">Devuelto</button>';
        echo '<button title="Renovar" type="button" class="btnRenovar" value="' . $prestamo['id'] . '">Renovar</button>';
        echo '<button title="Alertar" type="button" class="btnAlertar" style="display: none" value="' . $prestamo['id'] . '">Alertar</button>';
        echo '</div>';
        echo '</td>';
        echo '</tr>';
    }

} else {
    echo '<tr><td colspan="12">No se encontraron resultados para "' . htmlspecialchars($query) . '"</td></tr>';
}

// Cerrar conexiones
mysqli_close($db_mysql);
sqlsrv_close($conn_gestion);
?>