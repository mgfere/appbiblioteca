<?php
require '../includes/config/database.php';
require __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Encabezados para la respuesta JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// ... (tu función enviarCorreoCancelacion se mantiene igual) ...

try {
    // --- PASO 1: Conectar a AMBAS bases de datos ---
    $db_mysql = conectarDB();
    $conn_sqlsrv = conectarDB2();
    $conn_sqlsrv2 = conectarDB3();

    // --- PASO 2: OBTENER RESERVAS QUE SERÁN CANCELADAS (ANTES DE CANCELARLAS) ---
    $fecha_hoy = date('Y-m-d');

    // Consulta para obtener reservas vencidas
    $query_obtener_vencidas = "SELECT 
                                    r.id,
                                    r.Estudiantes_id,
                                    r.Libros_id,
                                    r.fecha_reservacion,
                                    l.titulo,
                                    l.codigo
                                FROM reservaciones r
                                JOIN libros l ON r.Libros_id = l.id
                                WHERE DATE(r.fecha_reservacion) < ? 
                                AND r.estado = 'activa'";

    $stmt_vencidas = $db_mysql->prepare($query_obtener_vencidas);
    $reservas_para_cancelar = [];

    if ($stmt_vencidas) {
        $stmt_vencidas->bind_param('s', $fecha_hoy);
        $stmt_vencidas->execute();
        $resultado_vencidas = $stmt_vencidas->get_result();
        
        while ($row = $resultado_vencidas->fetch_assoc()) {
            $reservas_para_cancelar[] = $row;
        }
        $stmt_vencidas->close();
    }

    // --- PASO 3: ELIMINAR LAS RESERVAS VENCIDAS Y LIBERAR LOS LIBROS ---
    if (!empty($reservas_para_cancelar)) {
        // Primero obtener los IDs de libros que serán liberados
        $libros_a_liberar = [];
        foreach ($reservas_para_cancelar as $reserva) {
            $libros_a_liberar[] = $reserva['Libros_id']; 
        }
        
        // --- PRIMERO ACTUALIZAR LOS LIBROS A "ACTIVO" ---
        if (!empty($libros_a_liberar)) {
            $placeholders = implode(',', array_fill(0, count($libros_a_liberar), '?'));
            $query_actualizar_libros = "UPDATE libros SET status = 'Activo' WHERE id IN ($placeholders)";
            
            $stmt_actualizar = $db_mysql->prepare($query_actualizar_libros);
            if ($stmt_actualizar) {
                // Vincular parámetros dinámicamente
                $types = str_repeat('i', count($libros_a_liberar));
                $stmt_actualizar->bind_param($types, ...$libros_a_liberar);
                $stmt_actualizar->execute();
                $libros_actualizados = $stmt_actualizar->affected_rows;
                $stmt_actualizar->close();
                
                error_log("Libros liberados: $libros_actualizados");
            }
        }
        
        // --- LUEGO ELIMINAR LAS RESERVAS ---
        $query_eliminar = "DELETE FROM reservaciones 
                           WHERE DATE(fecha_reservacion) < ? 
                           AND estado = 'activa'";
        
        $stmt_eliminar = $db_mysql->prepare($query_eliminar);
        if ($stmt_eliminar) {
            $stmt_eliminar->bind_param('s', $fecha_hoy);
            $stmt_eliminar->execute();
            $reservas_eliminadas = $stmt_eliminar->affected_rows;
            $stmt_eliminar->close();
            
            error_log("Reservas eliminadas: $reservas_eliminadas");
            
            // --- PASO 4: ENVIAR CORREOS A LOS USUARIOS AFECTADOS ---
            if ($reservas_eliminadas > 0) {
                $ids_estudiantes_afectados = array_unique(array_column($reservas_para_cancelar, 'Estudiantes_id'));
                
                if (!empty($ids_estudiantes_afectados)) {
                    $mapa_usuarios_email = [];
                    
                    // ✅ PRIMERO BUSCAR EN DATOS PERSONALES (ALUMNOS)
                    $query_usuarios = "SELECT IdPersona, Nom, Paterno, Materno, Email
                                      FROM [Tutorias].[dbo].[DatosPersonales] 
                                      WHERE IdPersona IN (" . implode(',', array_fill(0, count($ids_estudiantes_afectados), '?')) . ")";
                    
                    $resultado_usuarios = sqlsrv_query($conn_sqlsrv, $query_usuarios, $ids_estudiantes_afectados);
                    
                    if ($resultado_usuarios) {
                        while ($row = sqlsrv_fetch_array($resultado_usuarios, SQLSRV_FETCH_ASSOC)) {
                            $nombre_completo = ucwords(strtolower(trim(
                                $row['Nom'] . ' ' . $row['Paterno'] . ' ' . $row['Materno']
                            )));
                            $mapa_usuarios_email[$row['IdPersona']] = [
                                'nombre' => $nombre_completo,
                                'email' => $row['Email']
                            ];
                        }
                    }
                    
                    // ✅ LUEGO BUSCAR EN DOCENTES (PARA LOS QUE NO SE ENCONTRARON COMO ALUMNOS)
                    $ids_no_encontrados = array_diff($ids_estudiantes_afectados, array_keys($mapa_usuarios_email));
                    
                    if (!empty($ids_no_encontrados)) {
                        foreach ($ids_no_encontrados as $id_docente) {
                            $query_docentes = "SELECT [IdDocente], [Nombre], [ApellidoPaterno], [ApellidoMaterno], [CorreoElectronico] 
                                              FROM [GestionUsuarios].[dbo].[Docentes] 
                                              WHERE IdDocente = ?";
                            
                            $params = array($id_docente);
                            $resultado_docentes = sqlsrv_query($conn_sqlsrv2, $query_docentes, $params);
                            
                            if ($resultado_docentes && sqlsrv_has_rows($resultado_docentes)) {
                                $row = sqlsrv_fetch_array($resultado_docentes, SQLSRV_FETCH_ASSOC);
                                $nombre_completo = ucwords(strtolower(trim(
                                    $row['Nombre'] . ' ' . $row['ApellidoPaterno'] . ' ' . $row['ApellidoMaterno']
                                )));
                                $mapa_usuarios_email[$row['IdDocente']] = [
                                    'nombre' => $nombre_completo,
                                    'email' => $row['CorreoElectronico']
                                ];
                                error_log("Docente encontrado: " . $nombre_completo . " - Email: " . $row['CorreoElectronico']);
                            }
                        }
                    }
                    
                    // ✅ ENVIAR CORREOS A TODOS LOS USUARIOS ENCONTRADOS
                    foreach ($reservas_para_cancelar as $reserva) {
                        $usuario_id = $reserva['Estudiantes_id'];
                        if (isset($mapa_usuarios_email[$usuario_id])) {
                            $usuario_data = $mapa_usuarios_email[$usuario_id];
                            $fecha_formateada = date('d-m-Y', strtotime($reserva['fecha_reservacion']));
                            
                            enviarCorreoCancelacion(
                                $usuario_data['email'],
                                $usuario_data['nombre'],
                                $reserva['titulo'],
                                $reserva['codigo'],
                                $fecha_formateada
                            );
                            error_log("Correo enviado a: " . $usuario_data['nombre'] . " (" . $usuario_data['email'] . ")");
                        } else {
                            error_log("Usuario no encontrado (ID: $usuario_id) para enviar correo");
                        }
                    }
                }
                
                error_log("Reservas eliminadas automáticamente: " . $reservas_eliminadas . " - Correos enviados");
            }
        }
    }

    // --- PASO 5: Crear mapas de referencia para Carreras y Turnos ---
    $carreras_map = [];
    $query_carreras = "SELECT IdCarrera, Nombre FROM [Tutorias].[dbo].[Carreras]";
    $res_carreras = sqlsrv_query($conn_sqlsrv, $query_carreras);
    if ($res_carreras) {
        while ($row = sqlsrv_fetch_array($res_carreras, SQLSRV_FETCH_ASSOC)) {
            $carreras_map[$row['IdCarrera']] = $row['Nombre'];
        }
    }

    $turnos_map = [];
    $query_turnos = "SELECT IdTurno, Nombre FROM [Tutorias].[dbo].[Turnoes]";
    $res_turnos = sqlsrv_query($conn_sqlsrv, $query_turnos);
    if ($res_turnos) {
        while ($row = sqlsrv_fetch_array($res_turnos, SQLSRV_FETCH_ASSOC)) {
            $turnos_map[$row['IdTurno']] = $row['Nombre'];
        }
    }

    // --- PASO 6: Obtener todas las reservaciones activas de MySQL ---
    $query_mysql = "SELECT 
                        r.id, 
                        r.fecha_reservacion AS fecha, 
                        r.cantidad, 
                        r.Estudiantes_id,
                        r.estado,
                        l.codigo, 
                        s.color AS color_libro
                    FROM reservaciones r
                    JOIN libros l ON r.Libros_id = l.id
                    JOIN secciones s ON l.seccionId = s.id
                    WHERE r.estado = 'activa'
                    ORDER BY r.fecha_reservacion DESC";
    
    $resultado_mysql = mysqli_query($db_mysql, $query_mysql);
    
    if (!$resultado_mysql) {
        throw new Exception("Error en consulta MySQL: " . mysqli_error($db_mysql));
    }
    
    $reservas_iniciales = [];
    $ids_estudiantes = [];
    while ($row = mysqli_fetch_assoc($resultado_mysql)) {
        $reservas_iniciales[] = $row;
        $ids_estudiantes[] = $row['Estudiantes_id'];
    }

    // --- PASO 7: Obtener los datos completos de los usuarios desde SQL Server ---
    $mapa_usuarios = [];
    if (!empty($ids_estudiantes)) {
        $ids_unicos = array_unique($ids_estudiantes);
        
        // ✅ PRIMERO BUSCAR EN DATOS PERSONALES (ALUMNOS)
        $query_sqlsrv = "SELECT 
                            IdPersona,
                            Nom,
                            Paterno,
                            Materno,
                            IdCarrera,
                            IdTurno,
                            Matricula
                        FROM [Tutorias].[dbo].[DatosPersonales] 
                        WHERE IdPersona IN (" . implode(',', array_fill(0, count($ids_unicos), '?')) . ")";

        $resultado_sqlsrv = sqlsrv_query($conn_sqlsrv, $query_sqlsrv, $ids_unicos);

        if ($resultado_sqlsrv) {
            while ($row = sqlsrv_fetch_array($resultado_sqlsrv, SQLSRV_FETCH_ASSOC)) {
                $nombre_construido = trim(($row['Nom'] ?? '') . ' ' . ($row['Paterno'] ?? '') . ' ' . ($row['Materno'] ?? ''));
                $nombre_construido = !empty(trim($nombre_construido)) ? $nombre_construido : 'Nombre no disponible';
                
                $mapa_usuarios[$row['IdPersona']] = [
                    'NombreEstudiante' => $nombre_construido,
                    'IdCarrera' => $row['IdCarrera'] ?? null,
                    'IdTurno' => $row['IdTurno'] ?? null,
                    'Matricula' => $row['Matricula'] ?? 'N/A'
                ];
            }
        }

        // ✅ LUEGO BUSCAR EN DOCENTES (PARA LOS QUE NO SE ENCONTRARON COMO ALUMNOS)
        $ids_no_encontrados = array_diff($ids_unicos, array_keys($mapa_usuarios));
        
        if (!empty($ids_no_encontrados)) {
            foreach ($ids_no_encontrados as $id_docente) {
                $query_gestion = "SELECT [IdDocente], [Nombre], [ApellidoPaterno], [ApellidoMaterno], [Matricula]
                              FROM [GestionUsuarios].[dbo].[Docentes] 
                              WHERE IdDocente = ? AND Habilitado = 1";
                
                $params = array($id_docente);
                $resultado_docente = sqlsrv_query($conn_sqlsrv2, $query_gestion, $params);
                
                if ($resultado_docente && sqlsrv_has_rows($resultado_docente)) {
                    $row = sqlsrv_fetch_array($resultado_docente, SQLSRV_FETCH_ASSOC);
                    $nombre_construido = trim(($row['Nombre'] ?? '') . ' ' . ($row['ApellidoPaterno'] ?? '') . ' ' . ($row['ApellidoMaterno'] ?? ''));
                    $nombre_construido = !empty(trim($nombre_construido)) ? $nombre_construido : 'Nombre no disponible';
                    
                    $mapa_usuarios[$row['IdDocente']] = [
                        'NombreEstudiante' => $nombre_construido,
                        'IdCarrera' => null, // Los docentes no tienen carrera
                        'IdTurno' => null,   // Los docentes no tienen turno
                        'Matricula' => $row['Matricula'] ?? 'N/A'
                    ];
                    error_log("Docente encontrado en reservas: " . $nombre_construido);
                }
            }
        }
    }

    // --- PASO 8: Combinar todos los datos en PHP ---
    $reservas_finales = [];
    foreach ($reservas_iniciales as $reserva) {
        $estudiante_id = $reserva['Estudiantes_id'];
        $datos_estudiante = $mapa_usuarios[$estudiante_id] ?? null;

        if ($datos_estudiante) {
            $id_carrera = $datos_estudiante['IdCarrera'] ?? 0;
            $id_turno = $datos_estudiante['IdTurno'] ?? 0;
            $nombre_carrera = $carreras_map[$id_carrera] ?? 'No asignada';
            $nombre_turno = $turnos_map[$id_turno] ?? 'No asignado';

            $reservas_finales[] = [
                'id' => $reserva['id'],
                'fecha' => $reserva['fecha'],
                'cantidad' => $reserva['cantidad'],
                'codigo' => $reserva['codigo'],
                'color_libro' => $reserva['color_libro'],
                'estado' => $reserva['estado'],
                'estudiante' => $datos_estudiante['NombreEstudiante'],
                'matricula' => $datos_estudiante['Matricula'],
                'carrera' => $nombre_carrera,
                'turno' => $nombre_turno
            ];
        } else {
            $reservas_finales[] = [
                'id' => $reserva['id'],
                'fecha' => $reserva['fecha'],
                'cantidad' => $reserva['cantidad'],
                'codigo' => $reserva['codigo'],
                'color_libro' => $reserva['color_libro'],
                'estado' => $reserva['estado'],
                'estudiante' => 'Usuario no encontrado (ID: ' . $estudiante_id . ')',
                'matricula' => 'N/A',
                'carrera' => 'No asignada',
                'turno' => 'No asignado'
            ];
        }
    }

    echo json_encode($reservas_finales, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error al procesar la solicitud: " . $e->getMessage()]);
} finally {
    if (isset($db_mysql)) mysqli_close($db_mysql);
    if (isset($conn_sqlsrv)) sqlsrv_close($conn_sqlsrv);
    if (isset($conn_sqlsrv2)) sqlsrv_close($conn_sqlsrv2);
}
?>