<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

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

// Si es petición AJAX para especialidades
if (isset($_GET['id_carrera'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $idCarrera = intval($_GET['id_carrera']);
    $sql = "SELECT id_especialidad, nombre_especialidad FROM especialidades WHERE id_carrera = ?";
    $stmt = mysqli_prepare($db, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $idCarrera);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $especialidades = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $especialidades[] = $row;
    }
    mysqli_stmt_close($stmt);
    
    echo json_encode($especialidades);
    exit;
}

// Consulta para obtener las carreras
$consultaCarreras = "SELECT id_carrera, nombre_carrera FROM carreras ORDER BY nombre_carrera";
$resultadoCarreras = mysqli_query($db, $consultaCarreras);

$consultaTurnos = "SELECT DISTINCT turno FROM usuarios WHERE turno IS NOT NULL AND turno != '' ORDER BY turno";
$resultadoTurnos = mysqli_query($db, $consultaTurnos);

$consultaSecciones = "SELECT * FROM secciones ORDER BY nombre_seccion";
$resultadoSecciones = mysqli_query($db, $consultaSecciones);

require '../../vendor/autoload.php';
require 'validaciones.php';


// Cargar el archivo .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Obtener el ID del libro desde la URL
$bookId = isset($_GET['bookId']) ? $_GET['bookId'] : '';
$libroId = null; // NUEVO: Variable para almacenar el ID del libro
$codigoLibro = '';
$seccionId = '';
$tituloLibro = '';
$cantidadDisponible = 0;

// Arreglo con mensajes de errores (MOVER ANTES de usarlo)
$errores = [
    'nombreCompleto' => '',
    'matricula' => '',
    'carreraId' => '',
    'especialidadId' => '',
    'turno' => '',
    'email' => '',
    'cantidad' => '',
    'fecha_prestamo' => '',
    'fecha_devolucion' => '',
    'codigoLibro' => '',
    'seccionId' => '',
    'prestamoActivo' => '',
    'general' => ''
];

// Función para buscar libro de forma segura
function buscarLibroPorId($db, $identificador) {
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

// Si hay ID de libro, obtener sus datos
if (!empty($bookId)) {
    $libro = buscarLibroPorId($db, $bookId);
    
    if ($libro) {
        // IMPORTANTE: Guardar el ID del libro
        $libroId = $libro['id'];
        
        $codigoSinFormato = $libro['codigo'];
        $codigoLibro = preg_replace('/[^0-9a-zA-Z]/', '', $codigoSinFormato);
        $codigoNumerico = preg_replace('/[^0-9]/', '', $codigoLibro);
        $codigoNumerico = str_pad($codigoNumerico, 6, '0', STR_PAD_LEFT);
        $sufijo = strtoupper(substr($codigoLibro, -1));
        $codigoLibro = preg_match('/[A-Z]/', $sufijo) ? $codigoNumerico . '-' . $sufijo : $codigoNumerico;
        
        $seccionId = $libro['seccionId'];
        $tituloLibro = $libro['titulo'];
        $cantidadDisponible = $libro['cantidad'];
        
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

// Variables del formulario
$nombreCompleto = '';
$matricula = '';
$email = '';
$carreraId = '';
$especialidadId = '';
$turno = '';
$cantidad = '1';
$fechaPrestamo = date('Y-m-d');
$fechaDevolucion = '';

// Procesar el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombreCompleto = mysqli_real_escape_string($db, trim($_POST["nombreCompleto"] ?? ''));
    $matricula = mysqli_real_escape_string($db, trim($_POST["matricula"] ?? ''));
    $email = mysqli_real_escape_string($db, trim($_POST["email"] ?? ''));
    $carreraId = intval($_POST["carreraId"] ?? 0);  // Cambio: ahora es int
    $especialidadId = intval($_POST["especialidadId"] ?? 0);  // Cambio: ahora es int
$turno = mysqli_real_escape_string($db, trim($_POST["turno"] ?? ''));
    $libroId = intval($_POST["libroId"] ?? 0);
    $seccionId = intval($_POST["seccionId"] ?? 0);
    $cantidad = intval($_POST["cantidad"] ?? 1);
    $fechaPrestamo = mysqli_real_escape_string($db, $_POST["fecha_prestamo"] ?? '');
    $fechaDevolucion = mysqli_real_escape_string($db, $_POST["fecha_devolucion"] ?? '');

    // Separar nombreCompleto en nombre y apellido
    $nombreApellido = explode(' ', $nombreCompleto, 2);
    $nombre = $nombreApellido[0] ?? '';
    $apellido = $nombreApellido[1] ?? '';

    // Validaciones básicas
    if (empty($nombreCompleto)) {
        $errores['nombreCompleto'] = "El nombre completo es obligatorio";
    }
    if (empty($matricula)) {
        $errores['matricula'] = "La matrícula es obligatoria";
    }
    if (empty($email)) {
        $errores['email'] = "El correo electrónico es obligatorio";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores['email'] = "El formato del correo no es válido";
    }
    if ($carreraId <= 0) {  // Cambio: validar ID
        $errores['carreraId'] = "La carrera es obligatoria";
    }
    if ($especialidadId <= 0) {  // Cambio: validar ID
        $errores['especialidadId'] = "La especialidad es obligatoria";
    }
    if (empty($turno)) {
        $errores['turno'] = "El turno es obligatorio";
    }
    if ($libroId <= 0) {
        $errores['codigoLibro'] = "Debe seleccionar un libro válido";
    }
    if ($seccionId <= 0) {
        $errores['seccionId'] = "La sección del libro es obligatoria";
    }
    if ($cantidad <= 0) {
        $errores['cantidad'] = "La cantidad debe ser mayor a 0";
    }
    if (empty($fechaPrestamo)) {
        $errores['fecha_prestamo'] = "La fecha de préstamo es obligatoria";
    }
    if (empty($fechaDevolucion)) {
        $errores['fecha_devolucion'] = "La fecha de devolución es obligatoria";
    }

    // Validar fechas
    if (!empty($fechaPrestamo) && !empty($fechaDevolucion)) {
        $fechaP = new DateTime($fechaPrestamo);
        $fechaD = new DateTime($fechaDevolucion);
        $hoy = new DateTime();
        
        if ($fechaP < $hoy->setTime(0, 0, 0)) {
            $errores['fecha_prestamo'] = "La fecha de préstamo no puede ser anterior a hoy";
        }
        if ($fechaD <= $fechaP) {
            $errores['fecha_devolucion'] = "La fecha de devolución debe ser posterior a la de préstamo";
        }
        
        $diasDiferencia = $fechaD->diff($fechaP)->days;
        if ($diasDiferencia > 3) {
            $errores['fecha_devolucion'] = "El período máximo de préstamo es 3 días";
        }
    }

    // NUEVA validación del libro usando ID (más confiable)
    if ($libroId > 0) {
        $queryVerificarLibro = "SELECT id, codigo, titulo, status, cantidad, seccionId FROM libros WHERE id = ?";
        $stmtVerificarLibro = mysqli_prepare($db, $queryVerificarLibro);
        mysqli_stmt_bind_param($stmtVerificarLibro, 'i', $libroId);
        mysqli_stmt_execute($stmtVerificarLibro);
        $resultadoVerificarLibro = mysqli_stmt_get_result($stmtVerificarLibro);

        if ($libroValidado = mysqli_fetch_assoc($resultadoVerificarLibro)) {
            // Actualizar variables con datos correctos del libro
            $codigoLibro = $libroValidado['codigo'];
            $tituloLibro = $libroValidado['titulo'];
            $seccionIdLibro = $libroValidado['seccionId'];
            
            // Verificar que la sección coincida
            if ($seccionIdLibro != $seccionId) {
                $errores['seccionId'] = 'La sección seleccionada no coincide con la del libro.';
            }
            
            if ($libroValidado['status'] === 'Inactivo') {
                $errores['codigoLibro'] = 'El libro está inactivo.';
            }
            if ($libroValidado['cantidad'] < $cantidad) {
                $errores['cantidad'] = 'No hay suficientes ejemplares disponibles.';
            }
        } else {
            $errores['codigoLibro'] = 'El libro no existe.';
        }
        mysqli_stmt_close($stmtVerificarLibro);
    }

    // Verificar si el usuario existe y tiene préstamos activos
    $queryVerificarUsuario = "SELECT id FROM usuarios WHERE matricula = ?";
    $stmtVerificarUsuario = mysqli_prepare($db, $queryVerificarUsuario);
    mysqli_stmt_bind_param($stmtVerificarUsuario, 's', $matricula);
    mysqli_stmt_execute($stmtVerificarUsuario);
    $resultadoVerificarUsuario = mysqli_stmt_get_result($stmtVerificarUsuario);
    $usuario = mysqli_fetch_assoc($resultadoVerificarUsuario);
    $usuarioId = $usuario ? $usuario['id'] : null;
    mysqli_stmt_close($stmtVerificarUsuario);

    if ($usuarioId) {
        // Verificar préstamos activos
        $queryPrestamosActivos = "SELECT COUNT(*) as cantidad_prestamos_activos 
                                 FROM prestamos 
                                 WHERE status = 1 AND Estudiantes_id = ?";
        $stmtPrestamosActivos = mysqli_prepare($db, $queryPrestamosActivos);
        mysqli_stmt_bind_param($stmtPrestamosActivos, 'i', $usuarioId);
        mysqli_stmt_execute($stmtPrestamosActivos);
        $resultadoPrestamosActivos = mysqli_stmt_get_result($stmtPrestamosActivos);
        $prestamosActivos = mysqli_fetch_assoc($resultadoPrestamosActivos);

        if ($prestamosActivos['cantidad_prestamos_activos'] >= 2) {
            $errores['prestamoActivo'] = "El estudiante ya tiene 2 préstamos activos, no puede solicitar otro.";
        }
        mysqli_stmt_close($stmtPrestamosActivos);
    }

    // Si no hay errores, proceder con el registro
    if (!array_filter($errores)) {
        // Iniciar transacción
        mysqli_begin_transaction($db);
        
        try {
            // Insertar o actualizar usuario
            if (!$usuarioId) {
    $queryInsertarUsuario = "INSERT INTO usuarios (nombre, apellido, email, matricula, carreraId, especialidadId, turno, estatus, registrado) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())";
    $stmtInsertarUsuario = mysqli_prepare($db, $queryInsertarUsuario);
    mysqli_stmt_bind_param($stmtInsertarUsuario, 'ssssiis', $nombre, $apellido, $email, $matricula, $carreraId, $especialidadId, $turno);
    
    if (!mysqli_stmt_execute($stmtInsertarUsuario)) {
        throw new Exception("Error al registrar el usuario: " . mysqli_error($db));
    }
    $usuarioId = mysqli_insert_id($db);
    mysqli_stmt_close($stmtInsertarUsuario);
                } else {
    // Si el usuario ya existe, podrías actualizarlo con los nuevos datos
    $queryActualizarUsuario = "UPDATE usuarios SET 
                               nombre = ?, 
                               apellido = ?, 
                               email = ?, 
                               carreraId = ?, 
                               especialidadId = ?, 
                               turno = ? 
                               WHERE id = ?";
    $stmtActualizarUsuario = mysqli_prepare($db, $queryActualizarUsuario);
    mysqli_stmt_bind_param($stmtActualizarUsuario, 'sssiisi', $nombre, $apellido, $email, $carreraId, $especialidadId, $turno, $usuarioId);
    mysqli_stmt_execute($stmtActualizarUsuario);
    mysqli_stmt_close($stmtActualizarUsuario);
            }
            

            // Insertar préstamo
            $query = "INSERT INTO prestamos (fecha_prestamo, fecha_devolucion, status, Libros_id, Estudiantes_id, cantidad, entregado) 
                      VALUES (?, ?, 1, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($db, $query);
            mysqli_stmt_bind_param($stmt, 'ssiiss', $fechaPrestamo, $fechaDevolucion, $libroId, $usuarioId, $cantidad, $nombreAdministrador);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error al registrar el préstamo: " . mysqli_error($db));
            }
            mysqli_stmt_close($stmt);

            // Actualizar cantidad de libros
            $queryActualizarLibro = "UPDATE libros SET cantidad = cantidad - ? WHERE id = ?";
            $stmtActualizarLibro = mysqli_prepare($db, $queryActualizarLibro);
            mysqli_stmt_bind_param($stmtActualizarLibro, 'ii', $cantidad, $libroId);
            
            if (!mysqli_stmt_execute($stmtActualizarLibro)) {
                throw new Exception("Error al actualizar la cantidad del libro: " . mysqli_error($db));
            }
            mysqli_stmt_close($stmtActualizarLibro);

            // Cambiar estado del libro a inactivo si la cantidad es 0
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

            // Enviar correo
            try {
                $mail = new PHPMailer(true);
                
                $queryLibro = "SELECT l.titulo, l.imagen, s.nombre_seccion 
                              FROM libros l 
                              INNER JOIN secciones s ON l.seccionId = s.id 
                              WHERE l.id = ?";
                $stmtLibro = mysqli_prepare($db, $queryLibro);
                mysqli_stmt_bind_param($stmtLibro, 'i', $libroId);
                mysqli_stmt_execute($stmtLibro);
                $resultadoLibro = mysqli_stmt_get_result($stmtLibro);
                $libroCorreo = mysqli_fetch_assoc($resultadoLibro);
                $tituloLibroCorreo = $libroCorreo['titulo'];
                $seccionLibro = $libroCorreo['nombre_seccion'];
                $fechaPrestamoFormateada = date_format(date_create($fechaPrestamo), 'd-m-Y');
                $fechaDevolucionFormateada = date_format(date_create($fechaDevolucion), 'd-m-Y');
                mysqli_stmt_close($stmtLibro);

                $mail->isSMTP();
                $mail->Host = $_ENV['SMTP_HOST'];
                $mail->SMTPAuth = $_ENV['SMTP_AUTH'] === 'true';
                $mail->Username = $_ENV['SMTP_USERNAME'];
                $mail->Password = $_ENV['SMTP_PASSWORD'];
                $mail->Port = $_ENV['SMTP_PORT'];
                $mail->setFrom($_ENV['SMTP_USERNAME'], $_ENV['SMTP_USERADMIN']);
                $mail->CharSet = 'UTF-8';
                $mail->Encoding = 'base64';
                $mail->addAddress($email, $nombreCompleto);
                $mail->isHTML(true);
                $mail->Subject = '✅ ¡Entregado! - Préstamo de libro';
                $mail->Body = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset='UTF-8'>
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
                        </style>
                    </head>
                    <body>
                        <div class='container'>
                            <div class='header'>
                                <img class='logo' src='{$_ENV['LOGOUT_IMG_URL']}' alt='Universidad Tecnológica de Tamaulipas Norte'>
                                <img class='logo' src='{$_ENV['LOGOCEIT_IMG_URL']}' alt='CEIT'>
                            </div>
                            <h2 style='color: #09a787;'>¡Libro entregado!</h2>
                            <p>Hola, <strong>$nombreCompleto</strong></p>
                            <p>Tu libro a préstamo ha sido entregado. A continuación, encontrarás los detalles del préstamo:</p>
                            <div class='content'>
                                <h3 style='color: #09a787; margin-top: 0;'>Detalles del préstamo:</h3>
                                <p><strong>Código:</strong> $codigoLibro</p>
                                <p><strong>Cantidad:</strong> $cantidad</p>
                                <p><strong>Título:</strong> $tituloLibroCorreo</p>
                                <p><strong>Sección:</strong> $seccionLibro</p>
                                <p><strong>Fecha de préstamo:</strong> $fechaPrestamoFormateada</p>
                                <p><strong>Fecha de devolución:</strong> $fechaDevolucionFormateada</p>
                                <p><strong>Entregado por:</strong> $nombreAdministrador</p>
                            </div>
                            <div class='footer'>
                                <p>© " . date('Y') . " | Universidad Tecnológica de Tamaulipas Norte</p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";
                $mail->send();
                header('Location: ../panel-prestamos.php?resultado=1');
                exit;
                
            } catch (Exception $e) {
                header('Location: ../panel-prestamos.php?correo=fallo');
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
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container main--content">
    <div class="tabular--wrapper">
        <div class="tabular--botones">
            <a href="../panel-prestamos.php">
                <button title="Volver" class="btnAgregar">
                    <i class="fas fa-arrow-left"></i> Volver
                </button>
            </a>
        </div>
        <div class="table--container">
            <form class="book-form" method="POST" enctype="multipart/form-data">
                <h1>Registro de préstamo</h1>
                
                <!-- Campo hidden para el ID del libro -->
                <input type="hidden" name="libroId" value="<?php echo htmlspecialchars($libroId ?? 0); ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="matricula">Matrícula:</label>
                        <input type="text" id="matricula" name="matricula" 
                               value="<?php echo htmlspecialchars($matricula); ?>" required>
                        <?php if ($errores['matricula']): ?>
                            <div class="alerta error"><?php echo $errores['matricula']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="nombreCompleto">Nombre completo:</label>
                        <input type="text" id="nombreCompleto" name="nombreCompleto" 
                               value="<?php echo htmlspecialchars($nombreCompleto); ?>" required>
                        <?php if ($errores['nombreCompleto']): ?>
                            <div class="alerta error"><?php echo $errores['nombreCompleto']; ?></div>
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
                    

                    
<div class="form-group">
    <label for="carreraId">Carrera:</label>
    <select id="carreraId" name="carreraId" required>
        <option value="">-- Seleccionar carrera --</option>
        <?php 
        mysqli_data_seek($resultadoCarreras, 0);
        while ($carrera = mysqli_fetch_assoc($resultadoCarreras)): 
        ?>
            <option value="<?php echo $carrera['id_carrera']; ?>" 
                <?php echo ($carreraId == $carrera['id_carrera']) ? 'selected' : ''; ?>>
<?php echo htmlspecialchars($carrera['nombre_carrera'] ?? ''); ?>
            </option>
        <?php endwhile; ?>
    </select>
    <?php if ($errores['carreraId']): ?>
        <div class="alerta error"><?php echo $errores['carreraId']; ?></div>
    <?php endif; ?>
</div>

<div class="form-group">
    <label for="especialidadId">Área:</label>
    <select id="especialidadId" name="especialidadId" required>
        <option value="">-- Seleccionar especialidad --</option>
        <!-- Las opciones se cargarán con AJAX basado en la carrera seleccionada -->
    </select>
    <?php if ($errores['especialidadId']): ?>
        <div class="alerta error"><?php echo $errores['especialidadId']; ?></div>
    <?php endif; ?>
</div>

<div class="form-group">
    <label for="turno">Turno:</label>
    <select id="turno" name="turno" required>
        <option value="">-- Seleccionar turno --</option>
        <option value="Matutino" <?php echo ($turno == 'Matutino') ? 'selected' : ''; ?>>Matutino</option>
        <option value="Vespertino" <?php echo ($turno == 'Vespertino') ? 'selected' : ''; ?>>Vespertino</option>
    </select>
    <?php if ($errores['turno']): ?>
        <div class="alerta error"><?php echo $errores['turno']; ?></div>
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
<?php echo htmlspecialchars($seccion['nombre_seccion'] ?? ''); ?>
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
                        <label for="fecha_prestamo">Fecha de préstamo:</label>
                        <input type="date" id="fecha_prestamo" name="fecha_prestamo" 
                               value="<?php echo htmlspecialchars($fechaPrestamo); ?>" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               max="<?php echo date('Y-m-d'); ?>" required>
                        <?php if ($errores['fecha_prestamo']): ?>
                            <div class="alerta error"><?php echo $errores['fecha_prestamo']; ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="fecha_devolucion">Fecha de devolución:</label>
                        <input type="date" id="fecha_devolucion" name="fecha_devolucion" 
                               value="<?php echo htmlspecialchars($fechaDevolucion); ?>" 
                               min="<?php echo date('Y-m-d'); ?>" 
                               max="<?php echo date('Y-m-d', strtotime('+3 days')); ?>" required>
                        <?php if ($errores['fecha_devolucion']): ?>
                            <div class="alerta error"><?php echo $errores['fecha_devolucion']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($errores['prestamoActivo']): ?>
                    <div class="alerta error"><?php echo $errores['prestamoActivo']; ?></div>
                <?php endif; ?>
                
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
    const carreraSelect = document.getElementById('carreraId');
    const especialidadSelect = document.getElementById('especialidadId');
    
    if (carreraSelect && especialidadSelect) {
        carreraSelect.addEventListener('change', function() {
            const idCarrera = this.value;
            
            // Limpiar especialidades
            especialidadSelect.innerHTML = '<option value="">-- Seleccionar especialidad --</option>';
            
            if (idCarrera) {
                // Realizar petición AJAX
                fetch(window.location.pathname + '?id_carrera=' + idCarrera)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Error en la respuesta del servidor');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.length > 0) {
                            data.forEach(function(esp) {
                                const option = document.createElement('option');
                                option.value = esp.id_especialidad;
                                option.textContent = esp.nombre_especialidad;
                                especialidadSelect.appendChild(option);
                            });
                        } else {
                            const option = document.createElement('option');
                            option.value = '';
                            option.textContent = 'No hay especialidades disponibles';
                            especialidadSelect.appendChild(option);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        const option = document.createElement('option');
                        option.value = '';
                        option.textContent = 'Error al cargar especialidades';
                        especialidadSelect.appendChild(option);
                    });
            }
        });
    }

    // Actualizar la búsqueda de estudiante para manejar IDs
    const matriculaInput = document.getElementById('matricula');
    if (matriculaInput) {
        matriculaInput.addEventListener('blur', function() {
            const matricula = this.value.trim();
            if (matricula) {
                $.ajax({
                    url: 'buscar-estudiante-ajax.php',
                    method: 'POST',
                    data: { matricula: matricula },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            document.getElementById('nombreCompleto').value = response.nombreCompleto || '';
                            document.getElementById('email').value = response.email || '';
                            
                            // Manejar carrera
                            if (response.carreraId) {
                                document.getElementById('carreraId').value = response.carreraId;
                                // Disparar evento change para cargar especialidades
                                carreraSelect.dispatchEvent(new Event('change'));
                                
                                // Esperar un poco y seleccionar la especialidad
                                setTimeout(() => {
                                    if (response.especialidadId) {
                                        document.getElementById('especialidadId').value = response.especialidadId;
                                    }
                                }, 500);
                            }
                            
                            // Manejar turno si viene en la respuesta
                            if (response.turno) {
                                document.getElementById('turno').value = response.turno;
                            }
                        } else {
                            Swal.fire({
                                title: 'Estudiante no encontrado',
                                text: 'No se encontró un estudiante con esa matrícula. Por favor, completa los datos manualmente.',
                                icon: 'info',
                                confirmButtonColor: '#09a787'
                            });
                            // Limpiar campos
                            document.getElementById('nombreCompleto').value = '';
                            document.getElementById('email').value = '';
                            document.getElementById('carreraId').value = '';
                            document.getElementById('especialidadId').value = '';
                            document.getElementById('turno').value = '';
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error('Error AJAX:', textStatus, errorThrown, jqXHR.responseText);
                        Swal.fire({
                            title: 'Error',
                            text: 'Ocurrió un error al buscar el estudiante. Intenta de nuevo.',
                            icon: 'error',
                            confirmButtonColor: '#09a787'
                        });
                    }
                });
            }
        });
    }
    
    console.log('Datos del libro cargados:');
    console.log('Libro ID:', libroId);
    console.log('Código:', codigoLibro);
    console.log('Título:', tituloLibro);

    // ========================================
    // CONFIGURACIÓN DE CAMPOS DE TELÉFONO Y EMAIL
    // ========================================
    
    // Aplicar formateo automático de teléfono
    
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
    
const fechaPrestamoInput = document.getElementById('fecha_prestamo');
const fechaDevolucionInput = document.getElementById('fecha_devolucion');
    
   if (fechaPrestamoInput) {
    fechaPrestamoInput.addEventListener('change', function() {
        const fechaPrestamo = new Date(this.value);
        if (fechaPrestamo) {
            // Sugerir fecha de devolución (7 días después)
            const fechaSugerida = new Date(fechaPrestamo);
            fechaSugerida.setDate(fechaSugerida.getDate() + 3);
            
            // Verificar que no exceda el máximo permitido (15 días)
            const fechaMaxima = new Date(fechaPrestamo);
            fechaMaxima.setDate(fechaMaxima.getDate() + 3);
            
            if (!fechaDevolucionInput.value) {
                fechaDevolucionInput.value = fechaSugerida.toISOString().split('T')[0];
            }
            
            fechaDevolucionInput.max = fechaMaxima.toISOString().split('T')[0];
        }
    });
   }
    
    if (fechaDevolucionInput) {
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
            if (diasDiferencia > 15) {
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
}

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
        inputDiaActual.value = año + '-' + mes + '-' + dia;
    }
    
    // Establecer fecha de devolución por defecto (3 días después)
    if (fechaDevolucionInput && !fechaDevolucionInput.value) {
        const fechaDevolucion = new Date();
        fechaDevolucion.setDate(fechaDevolucion.getDate() + 3);
        const añoD = fechaDevolucion.getFullYear();
        const mesD = ('0' + (fechaDevolucion.getMonth() + 1)).slice(-2);
        const diaD = ('0' + fechaDevolucion.getDate()).slice(-2);
        fechaDevolucionInput.value = añoD + '-' + mesD + '-' + diaD;
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
<script></script>
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
    
    // Búsqueda de estudiante por matrícula
    const matriculaInput = document.getElementById('matricula');
    matriculaInput.addEventListener('blur', function() {
        const matricula = this.value.trim();
        if (matricula) {
            $.ajax({
                url: 'buscar-estudiante-ajax.php',
                method: 'POST',
                data: { matricula: matricula },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        document.getElementById('nombreCompleto').value = response.nombreCompleto || '';
                        document.getElementById('email').value = response.email || '';
                        document.getElementById('carreraId').value = response.carreraId || '';
                        document.getElementById('especialidadId').value = response.especialidadId || '';
                        document.getElementById('turno').value = response.turno || '';
                    } else {
                        Swal.fire({
                            title: 'Estudiante no encontrado',
                            text: 'No se encontró un estudiante con esa matrícula. Por favor, completa los datos manualmente.',
                            icon: 'info',
                            confirmButtonColor: '#09a787'
                        });
                        document.getElementById('nombreCompleto').value = '';
                        document.getElementById('email').value = '';
                        document.getElementById('carreraId').value = '';
                        document.getElementById('especialidadId').value = '';
                        document.getElementById('turno').value = '';
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Error AJAX:', textStatus, errorThrown, jqXHR.responseText);
                    Swal.fire({
                        title: 'Error',
                        text: 'Ocurrió un error al buscar el estudiante. Intenta de nuevo.',
                        icon: 'error',
                        confirmButtonColor: '#09a787'
                    });
                }
            });
        }
    });

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
    const fechaPrestamoInput = document.getElementById('fecha_prestamo');
    const fechaDevolucionInput = document.getElementById('fecha_devolucion');
    
    fechaPrestamoInput.addEventListener('change', function() {
        const fechaPrestamo = new Date(this.value);
        if (fechaPrestamo) {
            // Sugerir fecha de devolución (3 días después)
            const fechaSugerida = new Date(fechaPrestamo);
            fechaSugerida.setDate(fechaSugerida.getDate() + 3);
            
            if (!fechaDevolucionInput.value) {
                fechaDevolucionInput.value = fechaSugerida.toISOString().split('T')[0];
            }
            
            // Límite máximo de 3 días
            const fechaMaxima = new Date(fechaPrestamo);
            fechaMaxima.setDate(fechaMaxima.getDate() + 3);
            fechaDevolucionInput.max = fechaMaxima.toISOString().split('T')[0];
        }
    });

    // Establecer fecha de préstamo actual
    const inputDiaActual = document.getElementById('fecha_prestamo');
    const fechaActual = new Date();
    const año = fechaActual.getFullYear();
    const mes = ('0' + (fechaActual.getMonth() + 1)).slice(-2);
    const dia = ('0' + fechaActual.getDate()).slice(-2);
    inputDiaActual.value = año + '-' + mes + '-' + dia;
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