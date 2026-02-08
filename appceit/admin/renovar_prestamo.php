<?php
require '../includes/config/database.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// --- CONFIGURACIÓN INICIAL ---
header('Content-Type: application/json');
session_start();
$nombreAdministrador = $_SESSION['nombre'] ?? 'Usuario';

// FUNCIÓN PARA ENVIAR CORREO DE RENOVACIÓN
function enviarCorreoRenovacion($email, $usuario, $libro, $nuevaFechaDevolucion, $nombreAdmin, $tipoUsuario = 'alumno')
{
    $mail = new PHPMailer(true);
    try {
        // Configuración del servidor de correo
        $mail->isSMTP();
        $mail->Host = $_ENV['SMTP_HOST'];
        $mail->SMTPAuth = $_ENV['SMTP_AUTH'] === 'true';
        $mail->Username = $_ENV['SMTP_USERNAME'];
        $mail->Password = $_ENV['SMTP_PASSWORD'];
        $mail->Port = $_ENV['SMTP_PORT'];
        $mail->setFrom($_ENV['SMTP_USERNAME'], $_ENV['SMTP_USERADMIN']);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64'; // Añadido para mejor compatibilidad
        $mail->addAddress($email, $usuario);
        $mail->isHTML(true);
        
        // Badge según tipo de usuario
        $etiquetaUsuario = ($tipoUsuario === 'docente') ? 'Docente' : 'Alumno';
        
        $mail->Subject = '🔄 ¡Préstamo Renovado Exitosamente!';
        $mail->Body = "
            <html>
            <head>
                <meta charset='UTF-8'>
                <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                <style>
                    body { 
                        font-family: Arial, sans-serif; 
                        line-height: 1.6;
                        margin: 0;
                        padding: 20px;
                        background-color: #f4f4f4;
                    }
                    .container {
                        max-width: 600px;
                        margin: 0 auto;
                        background-color: white;
                        padding: 20px;
                        border-radius: 10px;
                        box-shadow: 0 0 10px rgba(0,0,0,0.1);
                    }
                    .header {
                        display: flex;
                        justify-content: space-between;
                        align-items: center;
                        margin-bottom: 20px;
                        border-bottom: 2px solid #09a787;
                        padding-bottom: 15px;
                    }
                    .logo { 
                        width: 40%; 
                        height: auto; 
                        max-width: 150px;
                    }
                    .user-badge {
                        display: inline-block;
                        background-color: #09a787;
                        color: white;
                        padding: 5px 10px;
                        border-radius: 15px;
                        font-size: 14px;
                        margin-left: 10px;
                    }
                    .success-banner {
                        background-color: #d4edda;
                        border: 1px solid #c3e6cb;
                        color: #155724;
                        padding: 15px;
                        border-radius: 5px;
                        margin: 15px 0;
                        text-align: center;
                    }
                    .content {
                        background-color: #f8f9fa;
                        padding: 20px;
                        border-radius: 8px;
                        margin: 20px 0;
                    }
                    .detail-item {
                        margin: 10px 0;
                        padding: 8px 0;
                        border-bottom: 1px solid #e9ecef;
                    }
                    .detail-item:last-child {
                        border-bottom: none;
                    }
                    .footer { 
                        background-color: #09a787; 
                        color: #fff; 
                        font-weight: bold; 
                        text-align: center; 
                        padding: 15px;
                        border-radius: 8px;
                        margin-top: 20px;
                    }
                    .important-note {
                        background-color: #fff3e0;
                        padding: 15px;
                        margin: 20px 0;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <img class='logo' src='" . $_ENV['LOGOUT_IMG_URL'] . "' alt='Logo UTTN'>
                        <img class='logo' src='" . $_ENV['LOGOCEIT_IMG_URL'] . "' alt='Logo CEIT'>
                    </div>
                    
                    <div class='success-banner'>
                        <h2 style='margin: 0; color: #155724;'>¡Préstamo Renovado Exitosamente!</h2>
                    </div>
                    
                    <p>Hola, <strong>{$usuario}</strong></p>
                    <p>Te confirmamos que se ha extendido exitosamente el periodo de préstamo para el siguiente libro:</p>
                    
                    <div class='content'>
                        <h3 style='color: #09a787; margin-top: 0;'>📖 Detalles de la Renovación:</h3>
                        <div class='detail-item'>
                            <strong>📚 Título:</strong> {$libro['titulo']}
                        </div>
                        <div class='detail-item'>
                            <strong>🔢 Código:</strong> {$libro['codigo']}
                        </div>
                        <div class='detail-item'>
                            <strong>📅 Nueva Fecha de Devolución:</strong> <span style='color: #09a787; font-weight: bold;'>{$nuevaFechaDevolucion}</span>
                        </div>
                        <div class='detail-item'>
                            <strong>👤 Renovado por:</strong> {$nombreAdmin}
                        </div>
                    </div>
                    
                    <div class='important-note'>
                        <strong>⚠️ Importante:</strong> Recuerda devolver el libro en la nueva fecha indicada. El límite de renovaciones es de 3 por préstamo.
                    </div>
                    
                    <div style='background-color: #e8f5f1;  padding: 15px; margin: 20px 0; border-radius: 5px;'>
                        <p style='margin: 0 0 10px 0; color: #333; font-weight: bold;'>📚 Centro de Información Tecnológica</p>
                        <p style='margin: 0; color: #666; font-size: 14px;'>
                            Para más información, visita <a href='https://biblioteca.uttn.app/' style='color: #09a787; font-weight: bold;'>biblioteca.uttn.app</a> o acércate a nuestras instalaciones.
                        </p>
                    </div>
                    
                    <div class='footer'>
                        <p style='margin: 5px 0;'>© " . date('Y') . " | Universidad Tecnológica de Tamaulipas Norte</p>
                        <p style='margin: 5px 0;'>Centro de Información Tecnológica</p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Error PHPMailer: " . $mail->ErrorInfo);
        return "Error de Mailer: {$mail->ErrorInfo}";
    }
}

// --- LÓGICA PRINCIPAL ---
$data = json_decode(file_get_contents("php://input"), true);
$prestamoId = $data['id'] ?? null;

if (!$prestamoId) {
    echo json_encode(['success' => false, 'message' => 'ID de préstamo no proporcionado.']);
    exit;
}

// Conectar a ambas bases de datos
$db_mysql = conectarDB();
$conn_gestion = conectarDB3();

// Verificar conexiones
if (!$db_mysql) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a MySQL']);
    exit;
}

if (!$conn_gestion) {
    echo json_encode(['success' => false, 'message' => 'Error de conexión a SQL Server']);
    exit;
}

// 1. Obtener datos del préstamo de MySQL
$query_prestamo = "SELECT Estudiantes_id, Libros_id, fecha_devolucion, fecha_prestamo, renovaciones, Matricula, tipo_usuario FROM prestamos WHERE id = ?";
$stmt_prestamo = $db_mysql->prepare($query_prestamo);

if (!$stmt_prestamo) {
    echo json_encode(['success' => false, 'message' => 'Error preparando consulta: ' . $db_mysql->error]);
    exit;
}

$stmt_prestamo->bind_param('i', $prestamoId);
$stmt_prestamo->execute();
$resultado = $stmt_prestamo->get_result();

if ($resultado->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Préstamo no encontrado.']);
    exit;
}

$prestamo = $resultado->fetch_assoc();
$renovaciones_actuales = $prestamo['renovaciones'];
$estudiante_id = $prestamo['Estudiantes_id'];
$matricula = $prestamo['Matricula'];
$libro_id = $prestamo['Libros_id'];
$tipo_usuario = $prestamo['tipo_usuario'] ?? 'alumno'; // Obtener tipo de usuario
$fecha_devolucion_actual = new DateTime($prestamo['fecha_devolucion']);
$fecha_prestamo_actual = new DateTime($prestamo['fecha_prestamo']);

// 2. Validar el límite de renovaciones
if ($renovaciones_actuales >= 3) {
    echo json_encode(['success' => false, 'message' => 'Este préstamo ya ha alcanzado el límite de 3 renovaciones.']);
    exit;
}

// 3. Calcular nueva fecha de devolución
$nueva_fecha_devolucion = clone $fecha_devolucion_actual;
$nueva_fecha_devolucion->modify('+3 days');
$nueva_fecha_devolucion_str = $nueva_fecha_devolucion->format('Y-m-d H:i:s');
$nueva_cantidad_renovaciones = $renovaciones_actuales + 1;

// 4. Actualizar la fecha de préstamo (usando la fecha de devolución anterior)
$fecha_prestamo_nueva = $fecha_devolucion_actual->format('Y-m-d H:i:s');

// 5. Actualizar el préstamo en MySQL con ambas fechas
$query_update = "UPDATE prestamos SET fecha_prestamo = ?, fecha_devolucion = ?, renovaciones = ? WHERE id = ?";
$stmt_update = $db_mysql->prepare($query_update);

if (!$stmt_update) {
    echo json_encode(['success' => false, 'message' => 'Error preparando actualización: ' . $db_mysql->error]);
    exit;
}

$stmt_update->bind_param('ssii', $fecha_prestamo_nueva, $nueva_fecha_devolucion_str, $nueva_cantidad_renovaciones, $prestamoId);

if ($stmt_update->execute()) {
    // 6. Obtener datos del usuario según tipo_usuario
    $usuario_encontrado = null;
    
    if ($tipo_usuario === 'alumno') {
        // BUSCAR EN ALUMNOS
        $query_usuario = "SELECT 
                        IdAlumno,
                        Nombre as Nom, 
                        ApellidoPaterno as Paterno, 
                        ApellidoMaterno as Materno, 
                        CorreoElectronico as Email,
                        Matricula
                    FROM [GestionUsuarios].[dbo].[Alumnos] 
                    WHERE IdAlumno = ?";
        
        $params_usuario = [$estudiante_id];
        $res_usuario = sqlsrv_query($conn_gestion, $query_usuario, $params_usuario);
        
        if ($res_usuario !== false && sqlsrv_has_rows($res_usuario)) {
            $usuario_encontrado = sqlsrv_fetch_array($res_usuario, SQLSRV_FETCH_ASSOC);
            error_log("Usuario alumno encontrado - ID: $estudiante_id");
        }
    } else {
        // BUSCAR EN DOCENTES
        $query_usuario = "SELECT 
                        IdDocente,
                        Nombre as Nom, 
                        ApellidoPaterno as Paterno, 
                        ApellidoMaterno as Materno, 
                        CorreoElectronico as Email,
                        Matricula
                    FROM [GestionUsuarios].[dbo].[Docentes] 
                    WHERE IdDocente = ?";
        
        $params_usuario = [$estudiante_id];
        $res_usuario = sqlsrv_query($conn_gestion, $query_usuario, $params_usuario);
        
        if ($res_usuario !== false && sqlsrv_has_rows($res_usuario)) {
            $usuario_encontrado = sqlsrv_fetch_array($res_usuario, SQLSRV_FETCH_ASSOC);
            error_log("Usuario docente encontrado - ID: $estudiante_id");
        }
    }

    // Obtener datos del libro
    $query_libro = "SELECT titulo, codigo FROM libros WHERE id = ?";
    $stmt_libro = $db_mysql->prepare($query_libro);
    
    if (!$stmt_libro) {
        echo json_encode(['success' => false, 'message' => 'Error preparando consulta de libro: ' . $db_mysql->error]);
        exit;
    }
    
    $stmt_libro->bind_param('i', $libro_id);
    $stmt_libro->execute();
    $resultado_libro = $stmt_libro->get_result();
    
    if ($resultado_libro->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Libro no encontrado.']);
        exit;
    }
    
    $libro = $resultado_libro->fetch_assoc();

    // Enviar correo si se encontró el usuario
    $mensaje_correo = '';
    if ($usuario_encontrado && $libro) {
        $nombre_usuario = ucwords(strtolower(trim(
            ($usuario_encontrado['Nom'] ?? '') . ' ' . 
            ($usuario_encontrado['Paterno'] ?? '') . ' ' . 
            ($usuario_encontrado['Materno'] ?? '')
        )));
        $email_usuario = $usuario_encontrado['Email'];
        
        // Enviar correo solo si tenemos un email válido
        if (!empty($email_usuario) && filter_var($email_usuario, FILTER_VALIDATE_EMAIL)) {
            $resultado_correo = enviarCorreoRenovacion(
                $email_usuario, 
                $nombre_usuario, 
                $libro, 
                $nueva_fecha_devolucion->format('d-m-Y'), 
                $nombreAdministrador,
                $tipo_usuario
            );
            
            if ($resultado_correo !== true) {
                error_log("Error enviando correo: " . $resultado_correo);
                $mensaje_correo = " (Error al enviar correo: servidor no disponible)";
            } else {
                error_log("Correo enviado exitosamente a: " . $email_usuario);
                $mensaje_correo = " y correo de confirmación enviado";
            }
        } else {
            error_log("Email no válido o vacío para el usuario ID: $estudiante_id");
            $mensaje_correo = " (Email no disponible)";
        }
    } else {
        error_log("Usuario no encontrado en GestionUsuarios - ID: $estudiante_id, Tipo: $tipo_usuario");
        $mensaje_correo = " (Usuario no encontrado para envío de correo)";
    }
    
    echo json_encode([
        'success' => true, 
        'message' => '¡Préstamo renovado con éxito!' . $mensaje_correo,
        'nueva_fecha_devolucion' => $nueva_fecha_devolucion->format('d-m-Y'),
        'nueva_fecha_prestamo' => $fecha_devolucion_actual->format('d-m-Y'),
        'renovaciones' => $nueva_cantidad_renovaciones,
        'renovaciones_restantes' => (3 - $nueva_cantidad_renovaciones),
        'usuario_encontrado' => $usuario_encontrado ? true : false,
        'tipo_usuario' => ucfirst($tipo_usuario),
        'correo_enviado' => ($resultado_correo ?? false) === true
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar el préstamo: ' . $db_mysql->error]);
}

// Cerrar conexiones
$stmt_prestamo->close();
$stmt_update->close();
if (isset($stmt_libro)) $stmt_libro->close();
mysqli_close($db_mysql);
sqlsrv_close($conn_gestion);
?>