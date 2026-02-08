<?php
session_start();
require '../includes/config/database.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// CORRECCIÓN CRÍTICA: Establecer zona horaria de México
date_default_timezone_set('America/Mexico_City');

header('Content-Type: application/json');
$nombreAdministrador = $_SESSION['nombre'] ?? 'Usuario';

// FUNCIÓN PARA ENVIAR RECORDATORIO DE DEVOLUCIÓN
function enviarRecordatorioDevolucion($email, $usuario, $libro, $fechaDevolucion, $diasRestantes, $nombreAdmin, $tipoUsuario)
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
        $mail->addAddress($email, $usuario);
        $mail->isHTML(true);

        // Lógica para los días restantes
        if ($diasRestantes < 0) {
            $mail->Subject = '🚨 ¡URGENTE! Préstamo Vencido - Acción Requerida';
            $mensajeUrgencia = '¡Tu préstamo está VENCIDO!';
            $colorAlerta = '#dc3545';
            $iconoEstado = '⚠️';
        } elseif ($diasRestantes == 0) {
            $mail->Subject = '⏰ ¡HOY Vence tu Préstamo! - Devolución Inmediata';
            $mensajeUrgencia = '¡Tu préstamo vence HOY!';
            $colorAlerta = '#fd7e14';
            $iconoEstado = '⏰';
        } elseif ($diasRestantes == 1) {
            $mail->Subject = '📅 Recordatorio: Tu Préstamo Vence Mañana';
            $mensajeUrgencia = 'Tu préstamo vence mañana';
            $colorAlerta = '#ffc107';
            $iconoEstado = '📅';
        } else {
            $mail->Subject = '📚 Recordatorio: Próximo Vencimiento de Préstamo';
            $mensajeUrgencia = "Tu préstamo vence en {$diasRestantes} días";
            $colorAlerta = '#17a2b8';
            $iconoEstado = '📚';
        }

        // Etiqueta de tipo de usuario
        $etiquetaUsuario = ($tipoUsuario === 'docente') ? '👨‍🏫 Docente' : '👨‍🎓 Alumno';

        // Cuerpo del email
        $mail->Body = "
           <html>
    <head>
        <style>
            body { 
                font-family: 'Arial', sans-serif; 
                line-height: 1.6; 
                margin: 0; 
                padding: 20px; 
                background-color: #f8f9fa; 
            }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                background-color: #ffffff; 
                border-radius: 10px; 
                overflow: hidden; 
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); 
            }
            .header { 
                background: linear-gradient(135deg, #09a787, #0c8a6b); 
                color: #ffffff; 
                padding: 30px 20px; 
                text-align: center; 
            }
            
            
            .logo-container { 
                width: 100%;
                text-align: center;
                margin-bottom: 20px; 
            }
            
            .logo-wrapper {
                display: inline-block;
                width: 35%;
                vertical-align: middle;
                text-align: center;
            }
            
            .logo-separator {
                display: inline-block;
                width: 30px;
                vertical-align: middle;
            }
            
            .logo {
                width: 100%;
                max-width: 150px;
                height: auto;
                display: block;
                margin: 0 auto;
            }
            
            
            @media only screen and (max-width: 600px) {
                .logo-wrapper {
                    display: block !important;
                    width: 60% !important;
                    margin: 10px auto !important;
                }
                .logo-separator {
                    display: none !important;
                }
                .logo {
                    max-width: 120px !important;
                }
            }
            
            .alert-banner { 
                background-color: {$colorAlerta}; 
                color: white; 
                padding: 15px; 
                text-align: center; 
                font-weight: bold; 
                font-size: 18px; 
            }
            .content { 
                padding: 30px; 
            }
            .greeting { 
                font-size: 20px; 
                margin-bottom: 20px; 
                color: #2c3e50; 
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
            .book-details { 
                background-color: #f8f9fa; 
                padding: 20px; 
                margin: 20px 0; 
                border-radius: 0 8px 8px 0; 
            }
            .book-details h3 { 
                margin-top: 0; 
                color: #09a787; 
            }
            .detail-item { 
                margin: 10px 0; 
                padding: 8px 0; 
                border-bottom: 1px solid #e9ecef; 
            }
            .detail-item:last-child { 
                border-bottom: none; 
            }
            .detail-label { 
                font-weight: bold; 
                color: #495057; 
                display: inline-block; 
                width: 140px; 
            }
            .action-section { 
                background-color: #e3f2fd; 
                padding: 20px; 
                border-radius: 8px; 
                margin: 20px 0; 
                text-align: center; 
            }
            .action-title { 
                color: #1976d2; 
                font-size: 18px; 
                font-weight: bold; 
                margin-bottom: 15px; 
            }
            .contact-info { 
                background-color: #fff3e0; 
                padding: 15px; 
                border-radius: 8px; 
                margin: 20px 0; 
            }
            .footer { 
                background-color: #09a787; 
                color: #ffffff; 
                text-align: center; 
                padding: 20px; 
                font-size: 14px; 
            }
            .countdown { 
                font-size: 24px; 
                font-weight: bold; 
                color: {$colorAlerta}; 
                text-align: center; 
                margin: 20px 0; 
            }
            .important-note { 
                background-color: #fff8e1; 
                padding: 15px; 
                margin: 20px 0; 
                font-weight: 500; 
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <div class='logo-container'>
                    <div class='logo-wrapper'>
                        <img style='background-color: #ffffff; padding: 10px; border-radius: 5px;' class='logo' src='" . $_ENV['LOGOUT_IMG_URL'] . "' alt='Universidad Tecnológica de Tamaulipas Norte'>
                    </div>
                    <div class='logo-separator'></div>
                    <div class='logo-wrapper'>
                        <img class='logo' src='" . $_ENV['LOGOCEIT_IMG_URL'] . "' alt='CEIT'>
                    </div>
                </div>
                <h1>Centro de Información Tecnológica</h1>
                <p>Universidad Tecnológica de Tamaulipas Norte</p>
            </div>
                    
                    <div class='alert-banner'>
                        {$iconoEstado} {$mensajeUrgencia} {$iconoEstado}
                    </div>
                    
                    <div class='content'>
                        <div class='greeting'>
                            ¡Hola, <strong>{$usuario}</strong>!
                            <span class='user-badge'>{$etiquetaUsuario}</span>
                        </div>
                        
                        <p>Te enviamos este recordatorio sobre el préstamo de libro que tienes activo en nuestro sistema.</p>";

        if ($diasRestantes < 0) {
            $mail->Body .= "
                        <div class='countdown'>
                            ⚠️ PRÉSTAMO VENCIDO HACE " . abs($diasRestantes) . " DÍA(S) ⚠️
                        </div>
                        
                        <div class='important-note'>
                            <strong>¡ATENCIÓN!</strong> Tu préstamo está vencido. Es importante que devuelvas el libro lo antes posible para evitar restricciones en futuros préstamos.
                        </div>";
        } elseif ($diasRestantes == 0) {
            $mail->Body .= "
                        <div class='countdown'>
                            ⏰ ¡VENCE HOY! ⏰
                        </div>";
        } else {
            $mail->Body .= "
                        <div class='countdown'>
                            📅 Vence en {$diasRestantes} día(s)
                        </div>";
        }

        $mail->Body .= "
                        <div class='book-details'>
                            <h3>📖 Detalles del Préstamo:</h3>
                            <div class='detail-item'>
                                <span class='detail-label'>📚 Título:</span>
                                <strong>{$libro['titulo']}</strong>
                            </div>
                            <div class='detail-item'>
                                <span class='detail-label'>🔢 Código:</span>
                                <strong>{$libro['codigo']}</strong>
                            </div>
                            <div class='detail-item'>
                                <span class='detail-label'>📅 Fecha de Devolución:</span>
                                <strong>{$fechaDevolucion}</strong>
                            </div>
                            <div class='detail-item'>
                                <span class='detail-label'>👤 Recordatorio enviado por:</span>
                                {$nombreAdmin}
                            </div>
                        </div>
                        
                        <div class='action-section'>
                            <div class='action-title'>📍 ¿Qué hacer ahora?</div>
                            <p><strong>1.</strong> Dirígete al Centro de Información Tecnológica</p>
                            <p><strong>2.</strong> Entrega el libro en la oficina de préstamos</p>
                            <p><strong>3.</strong> Si necesitas más tiempo, consulta sobre renovaciones con el encargado que esté disponible</p>
                        </div>
                        
                        <div class='contact-info'>
                            <strong>📞 Información de Contacto:</strong><br>
                            Centro de Información Tecnológica<br>
                            Universidad Tecnológica de Tamaulipas Norte<br>
                            <em>Para consultas, acércate directamente a nuestras instalaciones</em>
                        </div>
                        
                        <div class='important-note'>
                            <strong>💡 Recuerda:</strong> Devolver los libros a tiempo nos ayuda a mantener un buen servicio para toda la comunidad universitaria. Así mismo te evitas mal historial con la universidad.
                        </div>
                    </div>
                    
                    <div class='footer'>
                        <p><strong>Centro de Información Tecnológica</strong></p>
                        <p>© " . date('Y') . " | Universidad Tecnológica de Tamaulipas Norte</p>
                        <p><em>Este es un mensaje automático del sistema de biblioteca</em></p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return "Error de Mailer: {$mail->ErrorInfo}";
    }
}

// LÓGICA PRINCIPAL
$data = json_decode(file_get_contents("php://input"), true);
$prestamoId = $data['id'] ?? null;

if (!$prestamoId) {
    echo json_encode(['success' => false, 'message' => 'ID de préstamo no proporcionado.']);
    exit;
}

$db_mysql = conectarDB();
$db_sql = conectarDB3(); 

// 1. Obtener datos del préstamo (ahora incluye tipo_usuario)
$query_prestamo = "SELECT Estudiantes_id, Libros_id, fecha_devolucion, tipo_usuario FROM prestamos WHERE id = ? AND status = '1'";
$stmt_prestamo = $db_mysql->prepare($query_prestamo);
$stmt_prestamo->bind_param('i', $prestamoId);
$stmt_prestamo->execute();
$resultado = $stmt_prestamo->get_result();

if ($resultado->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Préstamo no encontrado o ya fue devuelto.']);
    exit;
}

$prestamo = $resultado->fetch_assoc();
$estudiante_id = $prestamo['Estudiantes_id'];
$libro_id = $prestamo['Libros_id'];
$tipo_usuario = $prestamo['tipo_usuario']; 

// CORRECCIÓN PRINCIPAL: Usar zona horaria de México y cálculo simplificado
$fecha_devolucion = new DateTime($prestamo['fecha_devolucion'], new DateTimeZone('America/Mexico_City'));
$fecha_actual = new DateTime('now', new DateTimeZone('America/Mexico_City'));

// Establecer ambas fechas a medianoche para comparación de solo fecha
$fecha_devolucion->setTime(0, 0, 0);
$fecha_actual->setTime(0, 0, 0);

// Calcular diferencia de días de forma simple
$diferencia_segundos = $fecha_devolucion->getTimestamp() - $fecha_actual->getTimestamp();
$dias_restantes = intval($diferencia_segundos / (24 * 60 * 60));

// DEBUG: Log para verificar cálculos
error_log("=== DEBUG ZONA HORARIA ===");
error_log("Fecha devolución original: " . $prestamo['fecha_devolucion']);
error_log("Fecha devolución procesada: " . $fecha_devolucion->format('Y-m-d H:i:s T'));
error_log("Fecha actual: " . $fecha_actual->format('Y-m-d H:i:s T'));
error_log("Diferencia en segundos: " . $diferencia_segundos);
error_log("Días restantes calculados: " . $dias_restantes);
error_log("Tipo de usuario: " . $tipo_usuario);
error_log("========================");

// 2. Obtener datos del usuario según el tipo
if ($tipo_usuario === 'alumno') {
    // Consulta para ALUMNOS
    $query_usuario = "SELECT [IdAlumno]
          ,[Nombre]
          ,[ApellidoPaterno]
          ,[ApellidoMaterno]
          ,[Matricula]
          ,[CorreoElectronico]
      FROM [GestionUsuarios].[dbo].[Alumnos] 
      WHERE IdAlumno = ?";
} else {
    // Consulta para DOCENTES
    $query_usuario = "SELECT [IdDocente] as IdAlumno
          ,[Nombre]
          ,[ApellidoPaterno]
          ,[ApellidoMaterno]
          ,[NumeroEmpleado]
          ,[Matricula]
          ,[CorreoElectronico]
      FROM [GestionUsuarios].[dbo].[Docentes] 
      WHERE IdDocente = ?";
}

$params_usuario = [$estudiante_id];
$resultado_sqlsrv = sqlsrv_query($db_sql, $query_usuario, $params_usuario);

if ($resultado_sqlsrv === false) {
    echo json_encode([
        'success' => false, 
        'message' => 'Error al consultar SQL Server',
        'error_detail' => print_r(sqlsrv_errors(), true)
    ]);
    exit;
}

if (!sqlsrv_has_rows($resultado_sqlsrv)) {
    echo json_encode([
        'success' => false, 
        'message' => ucfirst($tipo_usuario) . ' no encontrado en SQL Server'
    ]);
    exit;
}

$usuario_data = sqlsrv_fetch_array($resultado_sqlsrv, SQLSRV_FETCH_ASSOC);

// 3. Obtener datos del libro
$query_libro = "SELECT titulo, codigo FROM libros WHERE id = ?";
$stmt_libro = $db_mysql->prepare($query_libro);
$stmt_libro->bind_param('i', $libro_id);
$stmt_libro->execute();
$resultado_libro = $stmt_libro->get_result();

if ($resultado_libro->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Libro no encontrado.']);
    exit;
}
$libro = $resultado_libro->fetch_assoc();

// Preparar datos para el correo
$nombre_usuario = ucwords(strtolower(trim(
    $usuario_data['Nombre'] . ' ' . 
    $usuario_data['ApellidoPaterno'] . ' ' . 
    ($usuario_data['ApellidoMaterno'] ?? '')
)));
$email_usuario = $usuario_data['CorreoElectronico'];
$fecha_devolucion_formateada = $fecha_devolucion->format('d-m-Y');

// Enviar recordatorio
$resultado_correo = enviarRecordatorioDevolucion(
    $email_usuario,
    $nombre_usuario,
    $libro,
    $fecha_devolucion_formateada,
    $dias_restantes,
    $nombreAdministrador,
    $tipo_usuario 
);

// Respuesta con información detallada
if ($resultado_correo === true) {
    $estado = "";
    if ($dias_restantes < 0) {
        $estado = "VENCIDO (hace " . abs($dias_restantes) . " días)";
    } elseif ($dias_restantes == 0) {
        $estado = "VENCE HOY";
    } elseif ($dias_restantes == 1) {
        $estado = "VENCE MAÑANA";
    } else {
        $estado = "Vence en {$dias_restantes} días";
    }

    echo json_encode([
        'success' => true,
        'message' => "Recordatorio enviado exitosamente a {$nombre_usuario}.",
        'tipo_usuario' => ucfirst($tipo_usuario),
        'dias_restantes' => $dias_restantes,
        'estado' => $estado,
        'usuario' => $nombre_usuario,
        'email' => $email_usuario,
        'fecha_devolucion' => $fecha_devolucion_formateada,
        'debug_info' => [
            'fecha_devolucion_original' => $prestamo['fecha_devolucion'],
            'fecha_actual_servidor' => $fecha_actual->format('Y-m-d H:i:s T'),
            'diferencia_segundos' => $diferencia_segundos
        ]
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'El recordatorio no se pudo enviar: ' . $resultado_correo
    ]);
}

// Cerrar conexiones
$stmt_prestamo->close();
$stmt_libro->close();
mysqli_close($db_mysql);
sqlsrv_close($db_sql);