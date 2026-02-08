<?php
require '../../includes/funciones.php';
$auth = adminAutenticado();

if (!$auth) {
    header('Location: ../login.php');
    exit;
}

// Obtener el nombre del administrador de la sesión
$nombreAdministrador = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario';

//* Validando que el id sea un número
$id = $_GET['id'];
$id = filter_var($id, FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: ../panel-usuarios-externos.php');
}

//* Base de datos
require '../../includes/config/database.php';
$db = conectarDB();

//* Incluir PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

require '../../vendor/autoload.php';

// Cargar el archivo .env
$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

//? Consulta para obtener los datos de los usuarios externos
$consultaUsuariosExternos = "SELECT * FROM usuariosexternos WHERE id = {$id}";
$resultadoUsuariosExternos = mysqli_query($db, $consultaUsuariosExternos);
$usuarioExterno = mysqli_fetch_assoc($resultadoUsuariosExternos);

//? Consulta para obtener las secciones
$consultaSecciones = "SELECT * FROM secciones";
$resultadoSecciones = mysqli_query($db, $consultaSecciones);

//* Arreglo con mensajes de errores
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
    'prestamoActivo' => '',
];

$nombreCompleto = $usuarioExterno['nombreCompleto'];
$identificacion = $usuarioExterno['identificacion'];
$email = $usuarioExterno['email'];
$celular = $usuarioExterno['celular'];
$calle = $usuarioExterno['calle'];
$colonia = $usuarioExterno['colonia'];
$CP = $usuarioExterno['CP'];
$ciudad = $usuarioExterno['ciudad'];
$codigoLibro = "";
$seccionId = "";
$cantidad = "";
$fechaPrestamo = "";
$fechaDevolucion = "";

//* Ejecutar el código después de que el usuario envíe el formulario
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombreCompleto = mysqli_real_escape_string($db, $_POST["nombreCompleto"]);
    $identificacion = mysqli_real_escape_string($db, $_POST["identificacion"]);
    $email = mysqli_real_escape_string($db, $_POST["email"]);
    $celular = mysqli_real_escape_string($db, $_POST["celular"]);
    $calle = mysqli_real_escape_string($db, $_POST["calle"]);
    $colonia = mysqli_real_escape_string($db, $_POST["colonia"]);
    $CP = mysqli_real_escape_string($db, $_POST["CP"]);
    $ciudad = mysqli_real_escape_string($db, $_POST["ciudad"]);
    $codigoLibro = $_POST["codigoLibro"];
    // Elimina caracteres no deseados, permitiendo solo números y letras
    $codigoLibro = preg_replace('/[^0-9a-zA-Z]/', '', $codigoLibro);
    // Extrae la parte numérica
    $codigoNumerico = preg_replace('/[^0-9]/', '', $codigoLibro);
    // Rellena la parte numérica con ceros a la izquierda para que tenga 6 dígitos
    $codigoNumerico = str_pad($codigoNumerico, 6, '0', STR_PAD_LEFT);
    // Extrae el sufijo (letra), asumiendo que es el último carácter de la cadena original
    $sufijo = strtoupper(substr($codigoLibro, -1));
    // Verifica si el sufijo es una letra (A-Z)
    if (preg_match('/[A-Z]/', $sufijo)) {
        // Concatena la parte numérica con el sufijo separado por un guion
        $codigoLibro = $codigoNumerico . '-' . $sufijo;
    } else {
        // Si no hay sufijo válido, solo usa la parte numérica
        $codigoLibro = $codigoNumerico;
    }
    $seccionId = mysqli_real_escape_string($db, $_POST["seccionId"]);
    $cantidad = mysqli_real_escape_string($db, $_POST["cantidad"]);
    $fechaPrestamo = mysqli_real_escape_string($db, $_POST["fechaPrestamo"]);
    $fechaDevolucion = mysqli_real_escape_string($db, $_POST["fechaDevolucion"]);

    if (!$nombreCompleto) {
        $errores['nombreCompleto'] = "El nombre es obligatorio";
    }

    if (!$identificacion) {
        $errores['identificacion'] = "La identificación es obligatoria";
    }

    if (!$email) {
        $errores['email'] = "El correo electrónico es obligatorio";
    }

    if (!$celular) {
        $errores['celular'] = "El número de celular es obligatorio";
    }

    if (!$calle) {
        $errores['calle'] = "Este campo no puede estar vacío";
    }

    if (!$colonia) {
        $errores['colonia'] = "Este campo no puede estar vacío";
    }

    if (!$CP) {
        $errores['CP'] = "Este campo no puede estar vacío";
    }

    if (!$ciudad) {
        $errores['ciudad'] = "Este campo no puede estar vacío";
    }

    if (!$codigoLibro) {
        $errores['codigoLibro'] = "El código del libro es obligatorio";
    }

    if (!$seccionId) {
        $errores['seccionId'] = "La sección del libro es obligatoria";
    }

    if (!$cantidad) {
        $errores['cantidad'] = "Este campo no puede estar vacío";
    }

    if (!$fechaPrestamo) {
        $errores['fechaPrestamo'] = "Este campo no puede estar vacío";
    }

    if (!$fechaDevolucion) {
        $errores['fechaDevolucion'] = "Este campo no puede estar vacío";
    }

    // Validar que el código del libro no exista en la sección o esté inactivo
    $queryVerificarLibro = "SELECT status FROM libros WHERE codigo = '$codigoLibro' AND seccionId = '$seccionId'";
    $resultadoVerificarLibro = mysqli_query($db, $queryVerificarLibro);

    if (mysqli_num_rows($resultadoVerificarLibro) > 0) {
        $libro = mysqli_fetch_assoc($resultadoVerificarLibro);
        if ($libro['status'] === 'Inactivo') {
            $errores['codigoLibro'] = 'El libro está inactivo.';
        }
    } else {
        $errores['codigoLibro'] = 'El libro no existe en la sección especificada.';
    }


    //* Verificar si el usuario ya tiene 2 préstamos activos
    $consultaPrestamosActivos = "SELECT COUNT(*) as cantidad_prestamos_activos FROM prestamospresencial WHERE estatus = 1 AND nombreCompleto = '{$nombreCompleto}'";
    $resultadoPrestamosActivos = mysqli_query($db, $consultaPrestamosActivos);

    if (mysqli_num_rows($resultadoPrestamosActivos) > 0) {
        $prestamosActivos = mysqli_fetch_assoc($resultadoPrestamosActivos);
        if ($prestamosActivos['cantidad_prestamos_activos'] >= 2) {
            $errores['prestamoActivo'] = "Ya tienes 2 préstamos activos, no puedes solicitar otro préstamo";
        }
    }

    //* Revisar que el arreglo de errores esté vacío 
    if (!array_filter($errores)) {
        //* Insertar a la base de datos
        $query = "INSERT INTO prestamospresencial (nombreCompleto, identificacion, email, celular, calle, colonia, CP, ciudad, codigoLibro, cantidad, fechaPrestamo, fechaDevolucion, estatus, seccionId,entregado) VALUES (UPPER('$nombreCompleto'), UPPER('$identificacion'), LOWER('$email'), '$celular', UPPER('$calle'), UPPER('$colonia'), '$CP', UPPER('$ciudad'), UPPER('$codigoLibro'), '$cantidad', '$fechaPrestamo', '$fechaDevolucion', 1, '$seccionId','$nombreAdministrador')";

        $resultado = mysqli_query($db, $query);

        //* Insertar en la tabla de usuarios externos
        $queryUsuarioExterno = "UPDATE usuariosexternos SET nombreCompleto = UPPER('$nombreCompleto'), identificacion = UPPER('$identificacion'), email = LOWER('$email'), calle = UPPER('$calle'), colonia = UPPER('$colonia'), CP = '$CP', ciudad = UPPER('$ciudad'), celular = '$celular' WHERE id = $id";

        $resultado_usuario_externo = mysqli_query($db, $queryUsuarioExterno);


        if ($resultado) {
            //* Actualizar la cantidad de libros en la tabla libros
            $queryActualizarLibro = "UPDATE libros SET cantidad = cantidad - $cantidad WHERE codigo = '$codigoLibro' AND seccionId = '$seccionId'";
            mysqli_query($db, $queryActualizarLibro);

            //* Cambiar el estado del libro a inactivo si la cantidad llega a 0
            $queryVerificarCantidad = "SELECT cantidad FROM libros WHERE codigo = '$codigoLibro' AND seccionId = '$seccionId'";
            $resultadoCantidad = mysqli_query($db, $queryVerificarCantidad);
            $libro = mysqli_fetch_assoc($resultadoCantidad);

            if ($libro['cantidad'] <= 0) {
                $queryActualizarEstatus = "UPDATE libros SET status = 'Inactivo' WHERE codigo = '$codigoLibro' AND seccionId = '$seccionId'";
                mysqli_query($db, $queryActualizarEstatus);
            }

            //* Enviar correo electrónico de confirmación
            $mail = new PHPMailer(true);
            try {

                $queryLibro = "SELECT l.titulo, l.imagen, s.nombre_seccion FROM libros l INNER JOIN secciones s ON l.seccionId = s.id WHERE l.codigo = '$codigoLibro' AND l.seccionId = '$seccionId'";
                $resultadoLibro = mysqli_query($db, $queryLibro);
                $libro = mysqli_fetch_assoc($resultadoLibro);

                $tituloLibro = $libro['titulo'];
                $portadaLibro = $libro['imagen'];
                $seccionLibro = $libro['nombre_seccion'];
                $fechaPrestamoFormateada = date_format(date_create($fechaPrestamo), 'd-m-Y');
                $fechaDevolucionFormateada = date_format(date_create($fechaDevolucion), 'd-m-Y');

                // Configuraciones de la libreria phpmailer
                $mail->isSMTP();
                $mail->Host = $_ENV['SMTP_HOST'];
                $mail->SMTPAuth = $_ENV['SMTP_AUTH'] === 'true';
                $mail->Username = $_ENV['SMTP_USERNAME'];
                $mail->Password = $_ENV['SMTP_PASSWORD'];
                $mail->Port = $_ENV['SMTP_PORT'];
                $mail->setFrom($_ENV['SMTP_USERNAME'], $_ENV['SMTP_USERADMIN']);

                // Activar caracteres UTF-8
                $mail->CharSet = 'UTF-8';
                $mail->Encoding = 'base64';

                // Destinatarios
                $mail->addAddress($email, $nombreCompleto);

                // Contenido del correo
                $mail->isHTML(true);
                $mail->Subject = '¡✅ Entregado!';
                $mail->Body = "
                <html>
                    <head>
                    <style>
                        body {
                        font-family: Arial, sans-serif;
                        font-size: 14px;
                        }
                        .libro-info {
                        margin-bottom: 20px;
                        }
                        .libro-imagen {
                        width: 100px;
                        height: 150px;
                        margin: 10px;
                        border: 1px solid #ddd;
                        }
                        .container {
                            display: flex;
                            justify-content: space-between;
                            gap:25px;
                        }
                        .logo {
                            width: 40%;
                            height: auto;
                        }
                        .footer {
                            background-color: #09a787;
                            color: #fff;
                            font-weight: bold;
                            text-align: center;
                        }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <img class='logo' src=" . $_ENV['LOGOUT_IMG_URL'] . " alt='Universidad Tecnológica de Tamaulipas Norte'>
                        <img class='logo' src='" . $_ENV['LOGOCEIT_IMG_URL'] . "' alt='CEIT'>
                    </div>
                    <h2>¡Libro entregado!</h2>
                    <p>Hola, $nombreCompleto</p>
                    <p>Tu libro a préstamo ha sido entregado. A continuación, encontrará los detalles del préstamo:</p>
                    <hr>
                    <h3>Detalles del préstamo:</h3>
                    <p><strong>Código:</strong> $codigoLibro</p>
                    <p><strong>Disponibles:</strong> $cantidad</p>
                    <p><strong>Título:</strong> $tituloLibro</p>
                    <p><strong>Sección:</strong> $seccionLibro</p>
                    <p><strong>Fecha de préstamo:</strong> $fechaPrestamoFormateada</p>
                    <p><strong>Fecha de devolución:</strong> $fechaDevolucionFormateada</p>
                    <p><strong>Entregado por:</strong> $nombreAdministrador</p>
                    <div class='footer'>
                        <p> © " . date('Y') . " | Universidad Tecnológica de Tamaulipas Norte</p>
                    </div>
                </body>
                </html>";

                $mail->send();
                header('Location: ../panel-prestamos-presenciales.php?resultado=1');
                exit; // Agregar salida para evitar redirecciones múltiples
            } catch (Exception $e) {
                echo "Error al enviar el correo: {$mail->ErrorInfo}";
            }
        } else {
            echo "Error: " . $query . "<br>" . mysqli_error($db);
        }
    }
}

incluirTemplate('sidebar-formularios')
?>

<!-- Contenido Principal -->
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
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombreCompleto">Nombre completo:</label>
                        <input type="text" id="nombreCompleto" name="nombreCompleto" value="<?php echo $nombreCompleto ?>">
                        <?php if ($errores['nombreCompleto']): ?>
                            <div class="alerta error"><?php echo $errores['nombreCompleto']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="identificacion">Identificación:</label>
                        <input type="text" id="identificacion" name="identificacion" value="<?php echo $identificacion ?>">
                        <?php if ($errores['identificacion']): ?>
                            <div class="alerta error"><?php echo $errores['identificacion']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="email">Correo electrónico:</label>
                        <input type="email" id="email" name="email" value="<?php echo $email ?>">
                        <?php if ($errores['email']): ?>
                            <div class="alerta error"><?php echo $errores['email']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="celular">Celular:</label>
                        <input type="text" id="celular" name="celular" value="<?php echo $celular ?>">
                        <?php if ($errores['celular']): ?>
                            <div class="alerta error"><?php echo $errores['celular']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="calle">Calle:</label>
                        <input type="text" id="calle" name="calle" value="<?php echo $calle ?>">
                        <?php if ($errores['calle']): ?>
                            <div class="alerta error"><?php echo $errores['calle']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="colonia">Colonia:</label>
                        <input type="text" id="colonia" name="colonia" value="<?php echo $colonia ?>">
                        <?php if ($errores['colonia']): ?>
                            <div class="alerta error"><?php echo $errores['colonia']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="CP">Código Postal:</label>
                        <input type="text" id="CP" name="CP" value="<?php echo $CP ?>">
                        <?php if ($errores['CP']): ?>
                            <div class="alerta error"><?php echo $errores['CP']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="ciudad">Ciudad:</label>
                        <input type="text" id="ciudad" name="ciudad" value="<?php echo $ciudad ?>">
                        <?php if ($errores['ciudad']): ?>
                            <div class="alerta error"><?php echo $errores['ciudad']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="codigoLibro">Código de libro:</label>
                        <input type="text" id="codigoLibro" name="codigoLibro" value="<?php echo $codigoLibro ?>">
                        <?php if ($errores['codigoLibro']): ?>
                            <div class="alerta error"><?php echo $errores['codigoLibro']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="seccion">Sección</label>
                        <select id="seccion" name="seccionId">
                            <option selected value="">--Seleccionar--</option>
                            <?php while ($seccion = mysqli_fetch_assoc($resultadoSecciones)): ?>
                                <option <?php echo $seccionId === $seccion['id'] ? 'selected' : ''; ?> value="<?php echo $seccion['id']; ?>"><?php echo $seccion['nombre_seccion']; ?></option>
                            <?php endwhile ?>
                        </select>
                        <?php if ($errores['seccionId']): ?>
                            <div class="alerta error"><?php echo $errores['seccionId']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="cantidad">Disponibles:</label>
                        <input type="number" id="cantidad" name="cantidad" readonly value="1">
                        <?php if ($errores['cantidad']): ?>
                            <div class="alerta error"><?php echo $errores['cantidad']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="fechaPrestamo">Fecha de préstamo:</label>
                        <input type="date" min="<?php echo date('Y-m-d') ?>" max="<?php echo date('Y-m-d') ?>" id="fechaPrestamo" name="fechaPrestamo" value="<?php echo $fechaPrestamo ?>">
                        <?php if ($errores['fechaPrestamo']): ?>
                            <div class="alerta error"><?php echo $errores['fechaPrestamo']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="fechaDevolucion">Fecha de devolución:</label>
                        <input type="date" id="fechaDevolucion" name="fechaDevolucion" min="<?php echo date('Y-m-d') ?>" max="<?php echo date('Y-m-d', strtotime('+7 days')) ?>" value="<?php echo $fechaDevolucion ?>">
                        <?php if ($errores['fechaDevolucion']): ?>
                            <div class="alerta error"><?php echo $errores['fechaDevolucion']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($errores['prestamoActivo']): ?>
                    <div class="alerta error"><?php echo $errores['prestamoActivo']; ?></div>
                <?php endif; ?>
                <button type="submit" class="btnAceptado"><i class="fas fa-save" style="margin-right: 5px;"></i>Registrar</button>
            </form>
        </div>
    </div>
</div>

<script>
    // Obtener el elemento input con id "diaActual"
    var inputDiaActual = document.getElementById('fechaPrestamo');

    // Crear una nueva instancia de Date para obtener la fecha actual
    var fechaActual = new Date();

    // Formatear la fecha actual en el formato requerido para el input type="date"
    var año = fechaActual.getFullYear();
    var mes = ('0' + (fechaActual.getMonth() + 1)).slice(-2);
    var dia = ('0' + fechaActual.getDate()).slice(-2);

    // Establecer el valor del campo de fecha actual
    inputDiaActual.value = año + '-' + mes + '-' + dia;
</script>

<?php
incluirTemplate('footer');
?>