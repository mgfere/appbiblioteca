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
require_once 'validaciones.php';

// Incluir PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

require '../../vendor/autoload.php';

// Cargar el archivo .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Obtener parámetros de la URL
$id_usuario = isset($_GET['id_usuario']) ? intval($_GET['id_usuario']) : null;
$bookId = isset($_GET['bookId']) ? $_GET['bookId'] : '';

// Variables para el libro
$libroId = null;
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

// Arreglo con mensajes de errores
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
    if (ctype_digit($identificador)) {
        // Buscar por ID
        $query = "SELECT l.*, s.nombre_seccion FROM libros l JOIN secciones s ON l.seccionId = s.id WHERE l.id = ?";
        $stmt = mysqli_prepare($db, $query);
        mysqli_stmt_bind_param($stmt, 'i', $identificador);
    } else {
        // Buscar por código
        $query = "SELECT l.*, s.nombre_seccion FROM libros l JOIN secciones s ON l.seccionId = s.id WHERE l.codigo = ?";
        $stmt = mysqli_prepare($db, $query);
        mysqli_stmt_bind_param($stmt, 's', $identificador);
    }
    
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
if (!empty($bookId)) {
    $libro = buscarLibro($db, $bookId);
    
    if ($libro) {
        // IMPORTANTE: Guardar el ID del libro
        $libroId = $libro['id'];
        
        // MANTENER el código original del libro (no formatearlo aquí)
        $codigoLibro = $libro['codigo']; // Código original de la base de datos
        
        $seccionId = $libro['seccionId'];
        $tituloLibro = $libro['titulo'];
        $cantidadDisponible = $libro['cantidad'];
        
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

     $errores['celular'] = generarErroresTelefono($celular);
    if (empty($errores['celular'])) {
        $celular = formatearTelefonoMexicano($celular); // Formatear para BD
    }
    
    // Validar email mejorado
    $errores['email'] = generarErroresEmail($email);
    if (empty($errores['email'])) {
        $email = validarEmailMejorado($email); // Limpiar y formatear
    }
    
    // Resto de validaciones...
    if (empty($nombreCompleto)) {
        $errores['nombreCompleto'] = "El nombre completo es obligatorio";
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

    // Validar que el libro existe y está disponible usando ID
    if ($libroId > 0) {
    $queryValidarLibro = "SELECT id, codigo, titulo, status, cantidad, seccionId FROM libros WHERE id = ?";
    $stmtValidarLibro = mysqli_prepare($db, $queryValidarLibro);
    mysqli_stmt_bind_param($stmtValidarLibro, 'i', $libroId);
    mysqli_stmt_execute($stmtValidarLibro);
    $resultadoValidarLibro = mysqli_stmt_get_result($stmtValidarLibro);

    if ($libroValidado = mysqli_fetch_assoc($resultadoValidarLibro)) {
        // NO sobrescribir las variables ya establecidas
        // $codigoLibro = $libroValidado['codigo']; // COMENTAR ESTA LÍNEA
        // $tituloLibro = $libroValidado['titulo'];   // COMENTAR ESTA LÍNEA
        $seccionIdLibro = $libroValidado['seccionId'];
        
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


    // Si no hay errores, proceder con el registro
    if (!array_filter($errores)) {
        // Iniciar transacción
        mysqli_begin_transaction($db);
        
        try {
            // Verificar si el usuario ya existe
            $query_verificar_usuario = "SELECT id, identificacion FROM usuariosexternos WHERE identificacion = ?";
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

<link rel="stylesheet" href="../../public/css/panellibros.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A==" crossorigin="anonymous" referrerpolicy="no-referrer" />

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Contenido Principal -->
<div class="container main--content">
    <div class="tabular--wrapper">
        <div class="tabular--botones">
            <a href="../panel-prestamos-presenciales.php">
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
    <input type="email" 
           id="email" 
           name="email" 
           value="<?php echo htmlspecialchars($email); ?>" 
           placeholder="usuario@ejemplo.com"
           maxlength="254"
           required>
    <?php if ($errores['email']): ?>
        <div class="alerta error"><?php echo $errores['email']; ?></div>
    <?php endif; ?>
</div>
                    
                    <div class="form-group campo-telefono">
    <label for="celular">Celular:</label>
    <input type="tel" 
           id="celular" 
           name="celular" 
           value="<?php echo htmlspecialchars(formatearTelefonoParaMostrar($celular)); ?>" 
           placeholder="899-123-4567"
           pattern="[0-9]{3}-[0-9]{3}-[0-9]{4}"
           maxlength="12"
           required>
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
                
                <?php if ($errores['general']): ?>
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
// Funciones de validación JavaScript (definir FUERA del DOMContentLoaded)
function formatearTelefonoInput(input) {
    let valor = input.value.replace(/[^0-9]/g, '');
    
    if (valor.length > 10) {
        valor = valor.substring(0, 10);
    }
    
    let formateado = '';
    if (valor.length >= 6) {
        formateado = valor.substring(0, 3) + '-' + valor.substring(3, 6) + '-' + valor.substring(6);
    } else if (valor.length >= 3) {
        formateado = valor.substring(0, 3) + '-' + valor.substring(3);
    } else {
        formateado = valor;
    }
    
    input.value = formateado;
}

function validarTelefonoJS(telefono) {
    const telefonoLimpio = telefono.replace(/[^0-9]/g, '');
    return telefonoLimpio.length === 10 && !['0', '1'].includes(telefonoLimpio[0]);
}

function validarEmailJS(email) {
    const regex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
    return regex.test(email);
}

// TODO EL CÓDIGO DENTRO DE UN SOLO DOMContentLoaded
document.addEventListener('DOMContentLoaded', function() {
    // Debug: Verificar que los datos lleguen correctamente
    const libroId = document.querySelector('input[name="libroId"]')?.value;
    const codigoLibro = document.getElementById('codigoLibro')?.value;
    const tituloLibro = document.querySelector('input[readonly]')?.value;
    
    console.log('Datos del libro cargados:');
    console.log('Libro ID:', libroId);
    console.log('Código:', codigoLibro);
    console.log('Título:', tituloLibro);

    // ========================================
    // CONFIGURACIÓN DE CAMPOS DE TELÉFONO Y EMAIL
    // ========================================
    
    // Aplicar formateo automático de teléfono
    const celularInput = document.getElementById('celular');
    if (celularInput) {
        celularInput.addEventListener('input', function() {
            formatearTelefonoInput(this);
        });
        
        celularInput.addEventListener('blur', function() {
            if (this.value && !validarTelefonoJS(this.value)) {
                this.classList.add('invalido');
                this.classList.remove('valido');
            } else if (this.value) {
                this.classList.add('valido');
                this.classList.remove('invalido');
            }
        });
    }
    
    // Aplicar validación automática de email
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('input', function() {
            this.value = this.value.toLowerCase();
        });
        
        emailInput.addEventListener('blur', function() {
            if (this.value && !validarEmailJS(this.value)) {
                this.classList.add('invalido');
                this.classList.remove('valido');
            } else if (this.value) {
                this.classList.add('valido');
                this.classList.remove('invalido');
            }
        });
    }

    // ========================================
    // VALIDACIÓN DEL FORMULARIO
    // ========================================
    
    const form = document.querySelector('.book-form');
    
    form.addEventListener('submit', function(e) {
        let hasErrors = false;
        
        // Validar ID del libro
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
        
        // Validar teléfono
        const celular = document.getElementById('celular').value;
        if (celular && !validarTelefonoJS(celular)) {
            hasErrors = true;
            Swal.fire({
                title: 'Teléfono inválido',
                text: 'Por favor ingresa un número de teléfono mexicano válido (10 dígitos)',
                icon: 'error',
                confirmButtonColor: '#09a787'
            });
            e.preventDefault();
            return false;
        }
        
        // Validar email
        const email = document.getElementById('email').value;
        if (email && !validarEmailJS(email)) {
            hasErrors = true;
            Swal.fire({
                title: 'Email inválido',
                text: 'Por favor ingresa un correo electrónico válido',
                icon: 'error',
                confirmButtonColor: '#09a787'
            });
            e.preventDefault();
            return false;
        }
        
        // Validar campos requeridos
        const requiredFields = form.querySelectorAll('input[required], select[required]');
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

    // ========================================
    // VALIDACIÓN DE FECHAS EN TIEMPO REAL
    // ========================================
    
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

    // ========================================
    // ESTABLECER FECHAS POR DEFECTO
    // ========================================
    
    // Establecer fecha de préstamo actual por defecto
    const inputDiaActual = document.getElementById('fechaPrestamo');
    if (inputDiaActual && !inputDiaActual.value) {
        const fechaActual = new Date();
        const año = fechaActual.getFullYear();
        const mes = ('0' + (fechaActual.getMonth() + 1)).slice(-2);
        const dia = ('0' + fechaActual.getDate()).slice(-2);
        inputDiaActual.value = `${año}-${mes}-${dia}`;
    }
    
    // Establecer fecha de devolución por defecto (3 días después)
    if (fechaDevolucionInput && !fechaDevolucionInput.value) {
        const fechaDevolucion = new Date();
        fechaDevolucion.setDate(fechaDevolucion.getDate() + 3);
        const añoD = fechaDevolucion.getFullYear();
        const mesD = ('0' + (fechaDevolucion.getMonth() + 1)).slice(-2);
        const diaD = ('0' + fechaDevolucion.getDate()).slice(-2);
        fechaDevolucionInput.value = `${añoD}-${mesD}-${diaD}`;
    }
});
</script>

<!-- CSS para los indicadores visuales -->
<style>
.campo-telefono {
    position: relative;
}

.campo-telefono input {
    padding-right: 30px;
}

.campo-telefono::after {
    content: "🇲🇽";
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    pointer-events: none;
}

.alerta.error {
    color: #dc3545;
    font-size: 12px;
    margin-top: 5px;
    display: block;
}

/* Indicador visual para campos válidos */
input.valido {
    border-color: #28a745 !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='m2.3 6.73.86.86L6.6 4.12 5.74 3.26c-1.25 1.25-2.08 2.08-3.44 3.47zm-1.94-1.94L1.22 6 4 3.22l-.86-.86c-1.28 1.28-2.22 2.22-2.78 2.78z'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}

/* Indicador visual para campos inválidos */
input.invalido {
    border-color: #dc3545 !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath d='M5.8 8.2l2.4-2.4M8.2 8.2L5.8 5.8'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}
</style>
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
    const submitBtn = form.querySelector('button[type="submit"]');
    
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
    
    // Establecer fecha de préstamo actual por defecto
    const inputDiaActual = document.getElementById('fechaPrestamo');
    const fechaActual = new Date();
    const año = fechaActual.getFullYear();
    const mes = ('0' + (fechaActual.getMonth() + 1)).slice(-2);
    const dia = ('0' + fechaActual.getDate()).slice(-2);
    inputDiaActual.value = `${año}-${mes}-${dia}`;
});


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