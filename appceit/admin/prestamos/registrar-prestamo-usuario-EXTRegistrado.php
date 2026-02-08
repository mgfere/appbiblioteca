<?php
require '../../includes/funciones.php';
$auth = adminAutenticado();
if (!$auth) {
    header('Location: ../login.php');
    exit;
}

// Obtener el nombre del administrador de la sesión
$nombreAdministrador = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario';

// Base de datos
require '../../includes/config/database.php';
$db = conectarDB();
date_default_timezone_set('America/Mexico_City');

// Incluir PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

require '../../vendor/autoload.php';

// Cargar el archivo .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Variables para el libro
$bookId = isset($_GET['bookId']) ? intval($_GET['bookId']) : null;
$id_usuario = isset($_GET['id']) ? intval($_GET['id']) : null;$libroId = null;
$codigoLibro = '';
$seccionId = '';
$tituloLibro = '';
$cantidadDisponible = 0;

// Variables para el usuario externo
$nombreCompleto = "";
$identificacion = "";
$email = "";
$celular = "";
$calle = "";
$colonia = "";
$CP = "";
$ciudad = "";

// Arreglo con mensajes de errores (INICIALIZAR PRIMERO)
$errores = [
    'nombreCompleto' => '',
    'identificacion' => '',
    'email' => '',
    'celular' => '',
    'calle' => '',
    'colonia' => '',
    'CP' => '',
    'ciudad' => '',
    'codigoLibro' => '',
    'seccionId' => '',
    'cantidad' => '',
    'fechaPrestamo' => '',
    'fechaDevolucion' => '',
        'prestamoActivo' => '', // <-- 1. Añadido campo de error para préstamo activo
    'general' => ''
];

// Función para buscar usuario externo de forma segura
function buscarUsuarioExterno($db, $id) {
    $query = "SELECT * FROM usuariosexternos WHERE id = ?";
    $stmt = mysqli_prepare($db, $query);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $usuario = mysqli_fetch_assoc($resultado);
    mysqli_stmt_close($stmt);
    return $usuario;
}

// Función para buscar libro de forma segura
function buscarLibro($db, $identificador) {
    $query = "SELECT l.*, s.nombre_seccion FROM libros l JOIN secciones s ON l.seccionId = s.id WHERE l.id = ?";
    $stmt = mysqli_prepare($db, $query);
    mysqli_stmt_bind_param($stmt, 'i', $identificador);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $libro = mysqli_fetch_assoc($resultado);
    mysqli_stmt_close($stmt);
    return $libro;
}

// Si hay un ID de usuario, obtener sus datos
if ($id_usuario) {
    $usuario = buscarUsuarioExterno($db, $id_usuario);
    if ($usuario) {
        $nombreCompleto = $usuario['nombreCompleto'];
        $identificacion = $usuario['identificacion'];
        $email = $usuario['email'];
        $celular = $usuario['celular'];
        $calle = $usuario['calle'];
        $colonia = $usuario['colonia'];
        $CP = $usuario['CP'];
        $ciudad = $usuario['ciudad'];
    }
}

// Si hay bookId, buscar el libro
if ($bookId) {
    $libro = buscarLibro($db, $bookId);
    
    if ($libro) {
        // IMPORTANTE: Guardar el ID del libro
        $libroId = $libro['id'];
        
        // MANTENER el código original del libro (no formatearlo)
        $codigoLibro = $libro['codigo'];
        
        $seccionId = $libro['seccionId'];
        $tituloLibro = $libro['titulo'];
        $cantidadDisponible = $libro['cantidad'];
        
        // Debug log
        error_log("Libro encontrado - ID: $libroId, Status: " . $libro['status'] . ", Cantidad: $cantidadDisponible");
        
        // Validar estado del libro
        if ($libro['status'] === 'Inactivo') {
            $errores['codigoLibro'] = 'El libro está inactivo y no puede prestarse.';
        }
        if ($cantidadDisponible <= 0) {
            $errores['cantidad'] = 'No hay ejemplares disponibles de este libro.';
        }
    } else {
        $errores['codigoLibro'] = 'El libro no existe en el sistema.';
    }
}

// Consulta para obtener las secciones
$consultaSecciones = "SELECT * FROM secciones ORDER BY nombre_seccion";
$resultadoSecciones = mysqli_query($db, $consultaSecciones);

// Establecer zona horaria de México
date_default_timezone_set('America/Mexico_City');

// Valores por defecto para fechas
$fechaPrestamo = date('Y-m-d');
$fechaDevolucion = date('Y-m-d', strtotime('+3 days'));

// Procesar el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Sanitizar datos de entrada
    $nombreCompleto = mysqli_real_escape_string($db, trim($_POST["nombreCompleto"] ?? ''));
    $identificacion = mysqli_real_escape_string($db, trim($_POST["identificacion"] ?? ''));
    $email = mysqli_real_escape_string($db, trim($_POST["email"] ?? ''));
    $celular = mysqli_real_escape_string($db, trim($_POST["celular"] ?? ''));
    $calle = mysqli_real_escape_string($db, trim($_POST["calle"] ?? ''));
    $colonia = mysqli_real_escape_string($db, trim($_POST["colonia"] ?? ''));
    $CP = mysqli_real_escape_string($db, trim($_POST["CP"] ?? ''));
    $ciudad = mysqli_real_escape_string($db, trim($_POST["ciudad"] ?? ''));
    $libroId = intval($_POST["libroId"] ?? 0);
    $seccionId = intval($_POST["seccionId"] ?? 0);
    $cantidad = intval($_POST["cantidad"] ?? 1);
    $fechaPrestamo = mysqli_real_escape_string($db, $_POST["fechaPrestamo"] ?? '');
    $fechaDevolucion = mysqli_real_escape_string($db, $_POST["fechaDevolucion"] ?? '');

    // Validaciones básicas
    if (empty($nombreCompleto)) {
        $errores['nombreCompleto'] = "El nombre completo es obligatorio";
    }
    if (empty($identificacion)) {
        $errores['identificacion'] = "La identificación es obligatoria";
    }
    if (empty($email)) {
        $errores['email'] = "El correo electrónico es obligatorio";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores['email'] = "El formato del correo no es válido";
    }
    if (empty($celular)) {
        $errores['celular'] = "El celular es obligatorio";
    }
    if (empty($calle)) {
        $errores['calle'] = "La calle es obligatoria";
    }
    if (empty($colonia)) {
        $errores['colonia'] = "La colonia es obligatoria";
    }
    if (empty($CP)) {
        $errores['CP'] = "El código postal es obligatorio";
    }
    if (empty($ciudad)) {
        $errores['ciudad'] = "La ciudad es obligatoria";
    }
    if ($libroId <= 0) {
        $errores['codigoLibro'] = "Debe seleccionar un libro válido";
    }
    if ($seccionId <= 0) {
        $errores['seccionId'] = "Debe seleccionar una sección válida";
    }
    if ($cantidad <= 0) {
        $errores['cantidad'] = "La cantidad debe ser mayor a 0";
    }
    if (empty($fechaPrestamo)) {
        $errores['fechaPrestamo'] = "La fecha de préstamo es obligatoria";
    }
    if (empty($fechaDevolucion)) {
        $errores['fechaDevolucion'] = "La fecha de devolución es obligatoria";
    }

    // Validar fechas
    if (!empty($fechaPrestamo) && !empty($fechaDevolucion)) {
        $fechaP = new DateTime($fechaPrestamo);
        $fechaD = new DateTime($fechaDevolucion);
        $hoy = new DateTime();
        
        if ($fechaP < $hoy->setTime(0, 0, 0)) {
            $errores['fechaPrestamo'] = "La fecha de préstamo no puede ser anterior a hoy";
        }
        if ($fechaD <= $fechaP) {
            $errores['fechaDevolucion'] = "La fecha de devolución debe ser posterior a la de préstamo";
        }
        
        $diasDiferencia = $fechaD->diff($fechaP)->days;
        if ($diasDiferencia > 3) {
            $errores['fechaDevolucion'] = "El período máximo de préstamo es 3 días";
        }
    }
    

    // Validar que el libro existe y está disponible usando ID
    if ($libroId > 0) {
        $queryValidarLibro = "SELECT id, codigo, titulo, status, cantidad, seccionId FROM libros WHERE id = ?";
        $stmtValidarLibro = mysqli_prepare($db, $queryValidarLibro);
        mysqli_stmt_bind_param($stmtValidarLibro, 'i', $libroId);
        mysqli_stmt_execute($stmtValidarLibro);
        $resultadoValidarLibro = mysqli_stmt_get_result($stmtValidarLibro);

        if ($libroValidado = mysqli_fetch_assoc($resultadoValidarLibro)) {
            // Actualizar variables con datos correctos del libro
            $codigoLibro = $libroValidado['codigo'];
            $tituloLibro = $libroValidado['titulo'];
            $seccionIdLibro = $libroValidado['seccionId'];
            
            // Debug
            error_log("Validando libro - ID: $libroId, Status: " . $libroValidado['status'] . ", Cantidad: " . $libroValidado['cantidad']);
            
            // Verificar que la sección coincida
            if ($seccionIdLibro != $seccionId) {
                $errores['seccionId'] = 'La sección seleccionada no coincide con la del libro.';
            }
            
            if ($libroValidado['status'] === 'Inactivo') {
                $errores['codigoLibro'] = 'El libro está inactivo y no puede prestarse.';
            }
            if ($libroValidado['cantidad'] < $cantidad) {
                $errores['cantidad'] = 'No hay suficientes ejemplares disponibles.';
            }
        } else {
            $errores['codigoLibro'] = 'El libro no existe.';
        }
        mysqli_stmt_close($stmtValidarLibro);
    }
     if (!empty($identificacion)) {
        $queryActivos = "SELECT COUNT(*) as total FROM prestamospresencial WHERE identificacion = ? AND estatus = 1";
        $stmtActivos = mysqli_prepare($db, $queryActivos);
        mysqli_stmt_bind_param($stmtActivos, 's', $identificacion);
        mysqli_stmt_execute($stmtActivos);
        $resultadoActivos = mysqli_stmt_get_result($stmtActivos);
        $conteo = mysqli_fetch_assoc($resultadoActivos)['total'];
        mysqli_stmt_close($stmtActivos);

        if ($conteo > 0) {
            $errores['prestamoActivo'] = "Este usuario ya tiene un préstamo activo. No puede solicitar otro hasta que lo devuelva.";
        }
    }

    // Si no hay errores, proceder con el registro
    if (!array_filter($errores)) {
        // Iniciar transacción
        mysqli_begin_transaction($db);
        
        try {
            // Verificar si el usuario ya existe
            $query_verificar_usuario = "SELECT id FROM usuariosexternos WHERE identificacion = ?";
            $stmt_verificar_usuario = mysqli_prepare($db, $query_verificar_usuario);
            mysqli_stmt_bind_param($stmt_verificar_usuario, 's', $identificacion);
            mysqli_stmt_execute($stmt_verificar_usuario);
            $resultado_verificar_usuario = mysqli_stmt_get_result($stmt_verificar_usuario);
            
            // Si el usuario no existe, insertarlo
            if (mysqli_num_rows($resultado_verificar_usuario) == 0) {
                $query_usuario_externo = "INSERT INTO usuariosexternos (nombreCompleto, identificacion, email, calle, colonia, CP, ciudad, celular, registrado) 
                                        VALUES (UPPER(?), UPPER(?), LOWER(?), UPPER(?), UPPER(?), ?, UPPER(?), ?, NOW())";
                $stmt_usuario_externo = mysqli_prepare($db, $query_usuario_externo);
                mysqli_stmt_bind_param($stmt_usuario_externo, 'ssssssss', $nombreCompleto, $identificacion, $email, $calle, $colonia, $CP, $ciudad, $celular);
                
                if (!mysqli_stmt_execute($stmt_usuario_externo)) {
                    throw new Exception("Error al registrar el usuario: " . mysqli_error($db));
                }
                mysqli_stmt_close($stmt_usuario_externo);
            }
            mysqli_stmt_close($stmt_verificar_usuario);

            // Insertar el préstamo
            $query_prestamo = "INSERT INTO prestamospresencial (nombreCompleto, identificacion, email, celular, calle, colonia, CP, ciudad, codigoLibro, cantidad, fechaPrestamo, fechaDevolucion, estatus, seccionId, entregado) 
                             VALUES (UPPER(?), UPPER(?), LOWER(?), ?, UPPER(?), UPPER(?), ?, UPPER(?), UPPER(?), ?, ?, ?, 1, ?, ?)";
            $stmt_prestamo = mysqli_prepare($db, $query_prestamo);
            mysqli_stmt_bind_param($stmt_prestamo, 'sssssssssissis', $nombreCompleto, $identificacion, $email, $celular, $calle, $colonia, $CP, $ciudad, $codigoLibro, $cantidad, $fechaPrestamo, $fechaDevolucion, $seccionId, $nombreAdministrador);
            
            if (!mysqli_stmt_execute($stmt_prestamo)) {
                throw new Exception("Error al registrar el préstamo: " . mysqli_error($db));
            }
            mysqli_stmt_close($stmt_prestamo);

            // Actualizar cantidad de libros
            $queryActualizarLibro = "UPDATE libros SET cantidad = cantidad - ? WHERE id = ?";
            $stmtActualizarLibro = mysqli_prepare($db, $queryActualizarLibro);
            mysqli_stmt_bind_param($stmtActualizarLibro, 'ii', $cantidad, $libroId);
            
            if (!mysqli_stmt_execute($stmtActualizarLibro)) {
                throw new Exception("Error al actualizar la cantidad del libro: " . mysqli_error($db));
            }
            mysqli_stmt_close($stmtActualizarLibro);

            // Verificar si el libro debe marcarse como inactivo
            $queryVerificarCantidad = "SELECT cantidad FROM libros WHERE id = ?";
            $stmtVerificarCantidad = mysqli_prepare($db, $queryVerificarCantidad);
            mysqli_stmt_bind_param($stmtVerificarCantidad, 'i', $libroId);
            mysqli_stmt_execute($stmtVerificarCantidad);
            $resultadoCantidad = mysqli_stmt_get_result($stmtVerificarCantidad);
            $libroActualizado = mysqli_fetch_assoc($resultadoCantidad);

            if ($libroActualizado['cantidad'] <= 0) {
                $queryActualizarEstatus = "UPDATE libros SET status = 'Inactivo' WHERE id = ?";
                $stmtActualizarEstatus = mysqli_prepare($db, $queryActualizarEstatus);
                mysqli_stmt_bind_param($stmtActualizarEstatus, 'i', $libroId);
                mysqli_stmt_execute($stmtActualizarEstatus);
                mysqli_stmt_close($stmtActualizarEstatus);
            }
            mysqli_stmt_close($stmtVerificarCantidad);

            // Confirmar transacción
            mysqli_commit($db);

            // Enviar correo de confirmación
            try {
                $mail = new PHPMailer(true);
                
                // Obtener datos completos del libro para el correo
                $queryLibroCorreo = "SELECT l.titulo, l.imagen, s.nombre_seccion 
                                    FROM libros l 
                                    INNER JOIN secciones s ON l.seccionId = s.id 
                                    WHERE l.id = ?";
                $stmtLibroCorreo = mysqli_prepare($db, $queryLibroCorreo);
                mysqli_stmt_bind_param($stmtLibroCorreo, 'i', $libroId);
                mysqli_stmt_execute($stmtLibroCorreo);
                $resultadoLibroCorreo = mysqli_stmt_get_result($stmtLibroCorreo);
                $libroCorreo = mysqli_fetch_assoc($resultadoLibroCorreo);
                mysqli_stmt_close($stmtLibroCorreo);

                $tituloLibroCorreo = $libroCorreo['titulo'];
                $seccionLibro = $libroCorreo['nombre_seccion'];
                $fechaPrestamoFormateada = date_format(date_create($fechaPrestamo), 'd-m-Y');
                $fechaDevolucionFormateada = date_format(date_create($fechaDevolucion), 'd-m-Y');

                // Configuración SMTP
                $mail->isSMTP();
                $mail->Host = $_ENV['SMTP_HOST'];
                $mail->SMTPAuth = $_ENV['SMTP_AUTH'] === 'true';
                $mail->Username = $_ENV['SMTP_USERNAME'];
                $mail->Password = $_ENV['SMTP_PASSWORD'];
                $mail->Port = $_ENV['SMTP_PORT'];
                $mail->setFrom($_ENV['SMTP_USERNAME'], $_ENV['SMTP_USERADMIN']);
                $mail->CharSet = 'UTF-8';
                $mail->Encoding = 'base64';

                // Configuración del correo
                $mail->addAddress($email, $nombreCompleto);
                $mail->isHTML(true);
                $mail->Subject = '✅ ¡Entregado! - Préstamo de libro';
                $mail->Body = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
                        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
                        <style>
                            body { 
                                font-family: Arial, sans-serif; 
                                font-size: 14px; 
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
                            .content {
                                background-color: #f8f9fa;
                                padding: 20px;
                                border-radius: 8px;
                                margin: 20px 0;
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
                            .warning {
                                background-color: #fff3cd;
                                border: 1px solid #ffeaa7;
                                color: #856404;
                                padding: 15px;
                                border-radius: 5px;
                                margin: 15px 0;
                            }
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <img class='logo' src='{$_ENV['LOGOUT_IMG_URL']}' alt='Universidad Tecnológica de Tamaulipas Norte'>
                                <img class='logo' src='{$_ENV['LOGOCEIT_IMG_URL']}' alt='CEIT'>
                            </div>
                            
                            <h2 style='color: #09a787;'>¡Libro entregado exitosamente!</h2>
                            <p>Hola, <strong>$nombreCompleto</strong></p>
                            <p>Tu libro ha sido entregado correctamente. A continuación encontrarás los detalles del préstamo:</p>
                            
                            <div class='content'>
                                <h3 style='color: #09a787; margin-top: 0;'>Detalles del préstamo:</h3>
                                <p><strong>Código:</strong> $codigoLibro</p>
                                <p><strong>Título:</strong> $tituloLibroCorreo</p>
                                <p><strong>Sección:</strong> $seccionLibro</p>
                                <p><strong>Cantidad:</strong> $cantidad</p>
                                <p><strong>Fecha de préstamo:</strong> $fechaPrestamoFormateada</p>
                                <p><strong>Fecha de devolución:</strong> $fechaDevolucionFormateada</p>
                                <p><strong>Entregado por:</strong> $nombreAdministrador</p>
                            </div>
                            
                            <div class='warning'>
                                <strong>⚠️ Recordatorio importante:</strong><br>
                                Recuerda devolver el libro en la fecha indicada para evitar sanciones.
                                En caso de pérdida o daño, se aplicarán las políticas correspondientes.
                            </div>
                            
                            <div class='footer'>
                                <p>© " . date('Y') . " | Universidad Tecnológica de Tamaulipas Norte</p>
                                <p>Sistema de Gestión Bibliotecaria</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";

                $mail->send();
                header('Location: ../panel-prestamos-presenciales.php?resultado=1');
                exit;

            } catch (Exception $e) {
                // El préstamo se registró pero falló el correo
                header('Location: ../panel-prestamos-presenciales.php?correo=fallo');
                exit;
            }

        } catch (Exception $e) {
            // Revertir transacción en caso de error
            mysqli_rollback($db);
            $errores['general'] = "Error al procesar el préstamo: " . $e->getMessage();
        }
    }
}

incluirTemplate('sidebar-formularios');
?>

<!-- Contenido Principal -->
<link rel="stylesheet" href="../../public/css/panellibros.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container main--content">
    <div class="tabular--wrapper">
        <div class="tabular--botones">
            <a href="../panel-usuarios-externos.php">
                <button title="Volver" class="btnAgregar">
                    <i class="fas fa-arrow-left"></i> Volver
                </button>
            </a>
        </div>
        <div class="table--container">
            <form class="book-form" method="POST" enctype="multipart/form-data">
                <h1>Registro de préstamo</h1>
                <?php if (!empty($errores['prestamoActivo'])): ?>
                    <div class="alerta error" style="margin-bottom: 1.5rem; text-align: center;">
                        <?php echo $errores['prestamoActivo']; ?>
                    </div>
                <?php endif; ?>
                <!-- Campo hidden para el ID del libro -->
                <input type="hidden" name="libroId" value="<?php echo htmlspecialchars($libroId ?? 0); ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombreCompleto">Nombre completo:</label>
                        <input type="text" id="nombreCompleto" name="nombreCompleto" 
                               value="<?php echo htmlspecialchars($nombreCompleto); ?>" required>
                        <?php if ($errores['nombreCompleto']): ?>
                            <div class="alerta error"><?php echo $errores['nombreCompleto']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="identificacion">Identificación (INE):</label>
                        <input type="text" id="identificacion" name="identificacion" 
                               value="<?php echo htmlspecialchars($identificacion); ?>" required>
                        <?php if ($errores['identificacion']): ?>
                            <div class="alerta error"><?php echo $errores['identificacion']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Correo electrónico:</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($email); ?>" required>
                        <?php if ($errores['email']): ?>
                            <div class="alerta error"><?php echo $errores['email']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="celular">Celular:</label>
                        <input type="text" id="celular" name="celular" 
                               value="<?php echo htmlspecialchars($celular); ?>" required>
                        <?php if ($errores['celular']): ?>
                            <div class="alerta error"><?php echo $errores['celular']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="calle">Calle:</label>
                        <input type="text" id="calle" name="calle" 
                               value="<?php echo htmlspecialchars($calle); ?>" required>
                        <?php if ($errores['calle']): ?>
                            <div class="alerta error"><?php echo $errores['calle']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="colonia">Colonia:</label>
                        <input type="text" id="colonia" name="colonia" 
                               value="<?php echo htmlspecialchars($colonia); ?>" required>
                        <?php if ($errores['colonia']): ?>
                            <div class="alerta error"><?php echo $errores['colonia']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="CP">Código Postal:</label>
                        <input type="text" id="CP" name="CP" 
                               value="<?php echo htmlspecialchars($CP); ?>" required>
                        <?php if ($errores['CP']): ?>
                            <div class="alerta error"><?php echo $errores['CP']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="ciudad">Ciudad:</label>
                        <input type="text" id="ciudad" name="ciudad" 
                               value="<?php echo htmlspecialchars($ciudad); ?>" required>
                        <?php if ($errores['ciudad']): ?>
                            <div class="alerta error"><?php echo $errores['ciudad']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="codigoLibro">Código de libro:</label>
                        <input type="text" id="codigoLibro" name="codigoLibro" 
                               value="<?php echo htmlspecialchars($codigoLibro); ?>" readonly>
                        <?php if ($errores['codigoLibro']): ?>
                            <div class="alerta error"><?php echo $errores['codigoLibro']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label>Título del libro:</label>
                        <input type="text" value="<?php echo htmlspecialchars($tituloLibro); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="seccion">Sección:</label>
                        <select id="seccion" name="seccionId" required 
                                <?php echo (isset($seccionId) && $seccionId) ? 'disabled' : ''; ?>>
                            <option value="">-- Seleccionar sección --</option>
                            <?php 
                            mysqli_data_seek($resultadoSecciones, 0);
                            while ($seccion = mysqli_fetch_assoc($resultadoSecciones)): 
                            ?>
                                <option value="<?php echo $seccion['id']; ?>" 
                                    <?php echo (isset($seccionId) && $seccionId == $seccion['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($seccion['nombre_seccion']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        
                        <!-- Si la sección está predefinida, enviarla como hidden -->
                        <?php if (isset($seccionId) && $seccionId): ?>
                            <input type="hidden" name="seccionId" value="<?php echo $seccionId; ?>">
                        <?php endif; ?>
                        
                        <?php if ($errores['seccionId']): ?>
                            <div class="alerta error"><?php echo $errores['seccionId']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="cantidad">Cantidad:</label>
                        <input type="number" id="cantidad" name="cantidad" 
                               value="1" min="1" max="<?php echo $cantidadDisponible; ?>" 
                               <?php echo ($cantidadDisponible <= 0) ? 'disabled' : ''; ?>>
                        <small style="color: #666;">Disponibles: <?php echo $cantidadDisponible; ?></small>
                        <?php if ($errores['cantidad']): ?>
                            <div class="alerta error"><?php echo $errores['cantidad']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="fechaPrestamo">Fecha de préstamo:</label>
                        <input type="date" id="fechaPrestamo" name="fechaPrestamo" 
                               value="<?php echo htmlspecialchars($fechaPrestamo); ?>" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               max="<?php echo date('Y-m-d'); ?>" required>
                        <?php if ($errores['fechaPrestamo']): ?>
                            <div class="alerta error"><?php echo $errores['fechaPrestamo']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="fechaDevolucion">Fecha de devolución:</label>
                        <input type="date" id="fechaDevolucion" name="fechaDevolucion" 
                               value="<?php echo htmlspecialchars($fechaDevolucion); ?>" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               max="<?php echo date('Y-m-d', strtotime('+3 days')); ?>" required>
                        <?php if ($errores['fechaDevolucion']): ?>
                            <div class="alerta error"><?php echo $errores['fechaDevolucion']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (isset($errores['general']) && $errores['general']): ?>
                    <div class="alerta error"><?php echo $errores['general']; ?></div>
                <?php endif; ?>
                
                <button type="submit" class="btnAceptado" 
                        <?php echo (array_filter($errores) || $cantidadDisponible <= 0) ? 'disabled' : ''; ?>>
                    <i class="fas fa-save" style="margin-right: 5px;"></i>Registrar
                </button>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Debug: Verificar que los datos lleguen correctamente
    const libroId = document.querySelector('input[name="libroId"]')?.value;
    const codigoLibro = document.getElementById('codigoLibro')?.value;
    const tituloLibro = document.querySelector('input[readonly]')?.value;
    
    console.log('Datos del libro cargados:');
    console.log('Libro ID:', libroId);
    console.log('Código:', codigoLibro);
    console.log('Título:', tituloLibro);
    
    // Validación del formulario antes del envío
    const form = document.querySelector('.book-form');
    
    form.addEventListener('submit', function(e) {
        const currentLibroId = document.querySelector('input[name="libroId"]').value;
        
        if (!currentLibroId || currentLibroId === '0') {
            e.preventDefault();
            Swal.fire({
                title: 'Error',
                text: 'ID de libro no válido. Por favor, regresa al escáner.',
                icon: 'error',
                confirmButtonColor: '#09a787'
            });
            return false;
        }
        
        // Validar campos requeridos
        const requiredFields = form.querySelectorAll('input[required], select[required]');
        let hasErrors = false;
        
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                hasErrors = true;
                field.style.borderColor = '#dc3545';
            } else {
                field.style.borderColor = '';
            }
        });
        
        if (hasErrors) {
            e.preventDefault();
            Swal.fire({
                title: 'Campos incompletos',
                text: 'Por favor complete todos los campos obligatorios',
                icon: 'warning',
                confirmButtonColor: '#09a787'
            });
            return false;
        }
        
        console.log('Enviando formulario con libro ID:', currentLibroId);
    });
    
    // Validación de fechas en tiempo real
    const fechaPrestamoInput = document.getElementById('fechaPrestamo');
    const fechaDevolucionInput = document.getElementById('fechaDevolucion');
    
    fechaPrestamoInput.addEventListener('change', function() {
        const fechaPrestamo = new Date(this.value);
        if (fechaPrestamo) {
            // Sugerir fecha de devolución (3 días después)
            const fechaSugerida = new Date(fechaPrestamo);
            fechaSugerida.setDate(fechaSugerida.getDate() + 3);
            
            // Verificar que no exceda el máximo permitido (3 días)
            const fechaMaxima = new Date(fechaPrestamo);
            fechaMaxima.setDate(fechaMaxima.getDate() + 3);
            
            if (!fechaDevolucionInput.value) {
                fechaDevolucionInput.value = fechaSugerida.toISOString().split('T')[0];
            }
            
            fechaDevolucionInput.max = fechaMaxima.toISOString().split('T')[0];
        }
    });
    
    fechaDevolucionInput.addEventListener('change', function() {
        const fechaPrestamo = new Date(fechaPrestamoInput.value);
        const fechaDevolucion = new Date(this.value);
        
        if (fechaPrestamo && fechaDevolucion) {
            if (fechaDevolucion <= fechaPrestamo) {
                Swal.fire({
                    title: 'Fecha inválida',
                    text: 'La fecha de devolución debe ser posterior a la fecha de préstamo',
                    icon: 'warning',
                    confirmButtonColor: '#09a787'
                });
                this.value = '';
                return;
            }
            
            const diasDiferencia = Math.ceil((fechaDevolucion - fechaPrestamo) / (1000 * 60 * 60 * 24));
            if (diasDiferencia > 3) {
                Swal.fire({
                    title: 'Período muy largo',
                    text: 'El período máximo de préstamo es 3 días',
                    icon: 'warning',
                    confirmButtonColor: '#09a787'
                });
                this.value = '';
                return;
            }
        }
    });
    
    // Establecer fecha de préstamo actual por defecto (zona horaria local)
    const inputDiaActual = document.getElementById('fechaPrestamo');
    const fechaActual = new Date();
    
    // Ajustar a zona horaria de México
    const offsetMexico = -6; // UTC-6 (CST) o UTC-5 (CDT) según la época del año
    const fechaLocal = new Date(fechaActual.getTime() + (offsetMexico * 60 * 60 * 1000));
    
    // O más simple: usar la fecha local del navegador
    const año = fechaActual.getFullYear();
    const mes = ('0' + (fechaActual.getMonth() + 1)).slice(-2);
    const dia = ('0' + fechaActual.getDate()).slice(-2);
    inputDiaActual.value = `${año}-${mes}-${dia}`;
    
    // También establecer la fecha de devolución por defecto (3 días después)
    const inputFechaDevolucion = document.getElementById('fechaDevolucion');
    const fechaDevolucion = new Date(fechaActual);
    fechaDevolucion.setDate(fechaDevolucion.getDate() + 3);
    const añoD = fechaDevolucion.getFullYear();
    const mesD = ('0' + (fechaDevolucion.getMonth() + 1)).slice(-2);
    const diaD = ('0' + fechaDevolucion.getDate()).slice(-2);
    inputFechaDevolucion.value = `${añoD}-${mesD}-${diaD}`;
    
    console.log('Fecha establecida en formulario:', inputDiaActual.value);
});
</script>

<!-- Mensaje de éxito -->
<?php if (isset($_GET['resultado']) && $_GET['resultado'] == 1): ?>
<script>
Swal.fire({
    icon: 'success',
    title: '¡Correo enviado!',
    text: 'El préstamo fue registrado y el correo se ha enviado correctamente.',
    confirmButtonColor: '#09a787'
});
</script>
<?php endif; ?>

<!-- Mensaje de fallo en correo -->
<?php if (isset($_GET['correo']) && $_GET['correo'] == 'fallo'): ?>
<script>
Swal.fire({
    icon: 'warning',
    title: 'Préstamo registrado',
    text: 'El préstamo se registró correctamente, pero hubo un problema al enviar el correo.',
    confirmButtonColor: '#09a787'
});
</script>
<?php endif; ?>

<?php
mysqli_close($db);
incluirTemplate('footer');
?>