<?php
//* Incluir PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

require '../../includes/funciones.php';
$auth = adminAutenticado();
if (!$auth) {
    header('Location: ../login.php');
    exit;
}

if (!isset($_GET['usuarioId'])) {
    header('Location: ../panel-usuarios.php');
    exit;
}

$id = $_GET['usuarioId'];
$id = filter_var($id, FILTER_VALIDATE_INT);

$nombreAdministrador = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario';

// Base de datos
require '../../includes/config/database.php';
$db = conectarDB();
$conn_sqlsrv = conectarDB3(); // ✅ CAMBIADO A conectarDB3()
date_default_timezone_set('America/Mexico_City');

require '../../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Variables para el libro
$bookId = isset($_GET['bookId']) ? intval($_GET['bookId']) : null;
$libroId = null;
$codigoLibro = '';
$seccionId = '';
$tituloLibro = '';
$cantidadDisponible = 0;

$errores = [
    'nombreCompleto' => '',
    'matricula' => '',
    'carreraId' => '',
    'turno' => '',
    'email' => '',
    'cantidad' => '',
    'fecha_prestamo' => '',
    'fecha_devolucion' => '',
    'codigoLibro' => '',
    'seccionId' => '',
    'prestamoActivo' => '',
];

if ($bookId) {
    $queryLibro = "SELECT l.*, s.nombre_seccion 
                FROM libros l 
                JOIN secciones s ON l.seccionId = s.id 
                WHERE l.id = ?";

    $stmtLibro = mysqli_prepare($db, $queryLibro);
    mysqli_stmt_bind_param($stmtLibro, 'i', $bookId);
    mysqli_stmt_execute($stmtLibro);
    $resultadoLibro = mysqli_stmt_get_result($stmtLibro);

    if ($libro = mysqli_fetch_assoc($resultadoLibro)) {
        // Guardar datos del libro
        $libroId = $libro['id'];
        $codigoLibro = $libro['codigo'];
        $seccionId = $libro['seccionId'];
        $tituloLibro = $libro['titulo'];
        $cantidadDisponible = $libro['cantidad'];

        // Validaciones
        if ($libro['status'] === 'Inactivo') {
            $errores['codigoLibro'] = 'El libro está inactivo y no puede prestarse.';
        }

        if ($cantidadDisponible <= 0) {
            $errores['cantidad'] = 'No hay ejemplares disponibles de este libro.';
        }
    } else {
        $errores['codigoLibro'] = 'El libro no existe en el sistema.';
    }
    mysqli_stmt_close($stmtLibro);
}

// --- CONSULTAR USUARIOS DESDE GESTIONUSUARIOS ---
$consultarUsuarios = "SELECT 
                        a.IdAlumno as IdUsuario,
                        a.Matricula,
                        a.Nombre,
                        a.ApellidoPaterno, 
                        a.ApellidoMaterno,
                        a.CorreoElectronico as Email,
                        a.IdCarrera,
                        a.Cuatrimestre,
                        c.Nombre as CarreraNom
                    FROM [GestionUsuarios].[dbo].[Alumnos] a
                    LEFT JOIN [GestionUsuarios].[dbo].[Carreras] c ON a.IdCarrera = c.IdCarrera
                    WHERE a.IdAlumno = ?";

$paramsUsuarios = [$id];
$stmtUsuarios = sqlsrv_query($conn_sqlsrv, $consultarUsuarios, $paramsUsuarios);

if ($stmtUsuarios === false) {
    die("Error en consulta SQL Server: " . print_r(sqlsrv_errors(), true));
}

$usuario = sqlsrv_fetch_array($stmtUsuarios, SQLSRV_FETCH_ASSOC);
sqlsrv_free_stmt($stmtUsuarios);

if (!$usuario) {
    die("Usuario no encontrado en GestionUsuarios");
}

// Consulta para obtener las secciones
$consultaSecciones = "SELECT * FROM secciones ORDER BY nombre_seccion";
$resultadoSecciones = mysqli_query($db, $consultaSecciones);

// Variables del usuario
$nombreCompleto = trim($usuario['Nombre'] . ' ' . $usuario['ApellidoPaterno'] . ' ' . $usuario['ApellidoMaterno']);
$matricula = $usuario['Matricula'];
$email = $usuario['Email'];
$carreraId = $usuario['IdCarrera'];
$nombreCarrera = $usuario['CarreraNom'] ?? 'Sin carrera asignada';
$cuatrimestre = $usuario['Cuatrimestre'] ?? 'N/A';

// Variables para fechas por defecto
$fechaPrestamo = date('Y-m-d');
$fechaDevolucion = date('Y-m-d', strtotime('+3 days'));

// Ejecutar el código después de que el usuario envíe el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Sanitizar datos de entrada
    $nombreCompleto = mysqli_real_escape_string($db, trim($_POST["nombreCompleto"] ?? ''));
    $matricula = mysqli_real_escape_string($db, trim($_POST["matricula"] ?? ''));
    $email = mysqli_real_escape_string($db, trim($_POST["email"] ?? ''));
    $libroId = intval($_POST["libroId"] ?? 0);
    $seccionId = intval($_POST["seccionId"] ?? 0);
    $cantidad = intval($_POST["cantidad"] ?? 1);

    $fechaPrestamo = mysqli_real_escape_string($db, $_POST["fecha_prestamo"] ?? '');
    $fechaDevolucion = mysqli_real_escape_string($db, $_POST["fecha_devolucion"] ?? '');

    // Validaciones básicas
    if (empty($nombreCompleto)) {
        $errores['nombreCompleto'] = "El nombre es obligatorio";
    }
    if (empty($matricula)) {
        $errores['matricula'] = "La matrícula es obligatoria";
    }
    if (empty($email)) {
        $errores['email'] = "El correo electrónico es obligatorio";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errores['email'] = "El formato del correo no es válido";
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
        if ($diasDiferencia > 7) {
            $errores['fecha_devolucion'] = "El período máximo de préstamo es 7 días";
        }
    }

    // Validar que el libro existe y está disponible usando ID
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
                $errores['codigoLibro'] = 'El libro está inactivo y no puede prestarse.';
            }
            if ($libroValidado['cantidad'] < $cantidad) {
                $errores['cantidad'] = 'No hay suficientes ejemplares disponibles.';
            }
        } else {
            $errores['codigoLibro'] = 'El libro no existe.';
        }
        mysqli_stmt_close($stmtVerificarLibro);
    }

    // Verificar si el usuario ya tiene 2 préstamos activos
    $consultaPrestamosActivos = "SELECT COUNT(*) as cantidad_prestamos_activos 
        FROM prestamos 
        WHERE status = 1 
        AND Estudiantes_id = ?";
    $stmtPrestamosActivos = mysqli_prepare($db, $consultaPrestamosActivos);
    mysqli_stmt_bind_param($stmtPrestamosActivos, 'i', $id);
    mysqli_stmt_execute($stmtPrestamosActivos);
    $resultadoPrestamosActivos = mysqli_stmt_get_result($stmtPrestamosActivos);
    $prestamosActivos = mysqli_fetch_assoc($resultadoPrestamosActivos);
    mysqli_stmt_close($stmtPrestamosActivos);

    if ($prestamosActivos['cantidad_prestamos_activos'] >= 2) {
        $errores['prestamoActivo'] = "Ya tienes 2 préstamos activos, no puedes solicitar otro préstamo.";
    }

    // Si no hay errores, proceder con el registro
    if (!array_filter($errores)) {
        // Iniciar transacción
        mysqli_begin_transaction($db);

        try {
            // Insertar el préstamo usando prepared statements
            $query = "INSERT INTO prestamos (fecha_prestamo, fecha_devolucion, status, Libros_id, Estudiantes_id, Matricula, cantidad, entregado) 
                     VALUES (?, ?, 1, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($db, $query);
            // SOLUCIÓN (CORRECTA: 7 TIPOS, 7 VARIABLES)
mysqli_stmt_bind_param($stmt, 'ssiisis', $fechaPrestamo, $fechaDevolucion, $libroId, $id, $matricula, $cantidad, $nombreAdministrador);

            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error al registrar el préstamo: " . mysqli_error($db));
            }
            mysqli_stmt_close($stmt);

            // Actualizar la cantidad de libros
            $queryActualizarLibro = "UPDATE libros SET cantidad = cantidad - ? WHERE id = ?";
            $stmtActualizar = mysqli_prepare($db, $queryActualizarLibro);
            mysqli_stmt_bind_param($stmtActualizar, 'ii', $cantidad, $libroId);

            if (!mysqli_stmt_execute($stmtActualizar)) {
                throw new Exception("Error al actualizar la cantidad del libro: " . mysqli_error($db));
            }
            mysqli_stmt_close($stmtActualizar);

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

            // Enviar correo electrónico de confirmación
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
                                <p><strong>Carrera:</strong> $nombreCarrera</p>
                                <p><strong>Cuatrimestre:</strong> $cuatrimestre</p>
                                <p><strong>Entregado por:</strong> $nombreAdministrador</p>
                            </div>
                            
                            <div class='warning'>
                                <strong>⚠ Recordatorio importante:</strong><br>
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
                header('Location: ../panel-prestamos.php?resultado=1');
                exit;
            } catch (Exception $e) {
                // El préstamo se registró pero falló el correo
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

<!-- Contenido Principal -->
<link rel="stylesheet" href="../../public/css/panellibros.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<div class="container main--content">
    <div class="tabular--wrapper">
        <div class="tabular--botones">
            <a href="../panel-usuarios.php">
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
                        <label for="nombreCompleto">Nombre completo:</label>
                        <input type="text" id="nombreCompleto" name="nombreCompleto"
                            value="<?php echo htmlspecialchars($nombreCompleto); ?>" readonly>
                        <?php if ($errores['nombreCompleto']): ?>
                            <div class="alerta error"><?php echo $errores['nombreCompleto']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="matricula">Matrícula:</label>
                        <input type="text" id="matricula" name="matricula"
                            value="<?php echo htmlspecialchars($matricula); ?>" readonly>
                        <?php if ($errores['matricula']): ?>
                            <div class="alerta error"><?php echo $errores['matricula']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="email">Correo electrónico:</label>
                        <input type="email" id="email" name="email"
                            value="<?php echo htmlspecialchars($email); ?>" readonly>
                        <?php if ($errores['email']): ?>
                            <div class="alerta error"><?php echo $errores['email']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="carrera">Carrera:</label>
                        <input type="text" id="carrera" name="carrera"
                            value="<?php echo htmlspecialchars($nombreCarrera); ?>" readonly>
                        <input type="hidden" name="carreraId" value="<?php echo htmlspecialchars($carreraId ?? ''); ?>">
                        <?php if (isset($errores['carreraId']) && $errores['carreraId']): ?>
                            <div class="alerta error"><?php echo $errores['carreraId']; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="cuatrimestre">Cuatrimestre:</label>
                        <input type="text" id="cuatrimestre" name="cuatrimestre"
                            value="<?php echo htmlspecialchars($cuatrimestre); ?>" readonly>
                    </div>

                    <div class="form-group">
                        <label for="codigoLibro">Código de libro:</label>
                        <input type="text" id="codigoLibro" name="codigoLibro"
                            value="<?php echo htmlspecialchars($codigoLibro); ?>"
                            readonly>
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
                            max="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                        <?php if ($errores['fecha_devolucion']): ?>
                            <div class="alerta error"><?php echo $errores['fecha_devolucion']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($errores['prestamoActivo']): ?>
                    <div class="alerta error"><?php echo $errores['prestamoActivo']; ?></div>
                <?php endif; ?>

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
        const fechaPrestamoInput = document.getElementById('fecha_prestamo');
        const fechaDevolucionInput = document.getElementById('fecha_devolucion');

        fechaPrestamoInput.addEventListener('change', function() {
            const fechaPrestamo = new Date(this.value);
            if (fechaPrestamo) {
                // Sugerir fecha de devolución (7 días después)
                const fechaSugerida = new Date(fechaPrestamo);
                fechaSugerida.setDate(fechaSugerida.getDate() + 7);

                // Verificar que no exceda el máximo permitido (7 días)
                const fechaMaxima = new Date(fechaPrestamo);
                fechaMaxima.setDate(fechaMaxima.getDate() + 7);

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
                if (diasDiferencia > 7) {
                    Swal.fire({
                        title: 'Período muy largo',
                        text: 'El período máximo de préstamo es 7 días',
                        icon: 'warning',
                        confirmButtonColor: '#09a787'
                    });
                    this.value = '';
                    return;
                }
            }
        });
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
sqlsrv_close($conn_sqlsrv);
incluirTemplate('footer');
?>