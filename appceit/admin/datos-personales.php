<?php
require '../includes/funciones.php';
require '../includes/config/database.php';

// NO vuelvas a llamar session_start() si ya está en funciones.php

$auth = adminAutenticado();
if (!$auth) {
    header('Location: login.php');
    exit;
}

$db = conectarDB();

$errores = [
    'matricula' => '',
    'nombre' => '',
    'general' => '',
];

$matricula = '';
$nombre = '';
$bookId = '';
$bookIdFormateado = '';
$libroValido = false;

// Obtener datos del administrador de la sesión para mostrar en el contenedor
$nombreAdministrador = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario';
$rolAdministrador = isset($_SESSION['rol']) ? $_SESSION['rol'] : null;

// Recibir bookId desde GET o POST (POST tiene prioridad)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bookId = trim(filter_input(INPUT_POST, 'bookId', FILTER_SANITIZE_SPECIAL_CHARS));
} else {
    $bookId = trim(filter_input(INPUT_GET, 'bookId', FILTER_SANITIZE_SPECIAL_CHARS));
}

if (!$bookId) {
    $errores['general'] = 'No se ha proporcionado un ID de libro válido. Por favor, escanee o ingrese el ID nuevamente.';
} else {
    // Formatear el ID igual que en validar
    $bookIdFormateado = preg_replace('/[^0-9a-zA-Z]/', '', $bookId);
    $codigoNumerico = preg_replace('/[^0-9]/', '', $bookIdFormateado);
    $codigoNumerico = str_pad($codigoNumerico, 6, '0', STR_PAD_LEFT);
    $sufijo = strtoupper(substr($bookIdFormateado, -1));
    if (preg_match('/[A-Z]/', $sufijo)) {
        $bookIdFormateado = $codigoNumerico . '-' . $sufijo;
    } else {
        $bookIdFormateado = $codigoNumerico;
    }

    // Escapar para consulta SQL
    $bookIdSql = mysqli_real_escape_string($db, $bookIdFormateado);

    // Verificar que el libro exista y esté activo con cantidad > 0
    $query = "SELECT id, status, cantidad FROM libros WHERE id = '$bookIdSql'";
    $resultado = mysqli_query($db, $query);

    if ($resultado && mysqli_num_rows($resultado) > 0) {
        $libro = mysqli_fetch_assoc($resultado);

        if ($libro['status'] === 'Inactivo') {
            $errores['general'] = 'El libro con el código ' . htmlspecialchars($bookIdFormateado) . ' está inactivo.';
        } elseif ($libro['cantidad'] <= 0) {
            $errores['general'] = 'El libro con el código ' . htmlspecialchars($bookIdFormateado) . ' no tiene existencias disponibles.';
        } else {
            $libroValido = true;
            $bookIdFormateado = $libro['id']; // usar id real para el formulario
        }
    } else {
        $errores['general'] = 'El libro con el código ' . htmlspecialchars($bookIdFormateado) . ' no fue encontrado.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matricula = trim(filter_input(INPUT_POST, 'matricula', FILTER_SANITIZE_STRING));
    $nombre = trim(filter_input(INPUT_POST, 'nombre', FILTER_SANITIZE_STRING));

    if (!$matricula) {
        $errores['matricula'] = 'La matrícula es obligatoria.';
    }
    if (!$nombre) {
        $errores['nombre'] = 'El nombre es obligatorio.';
    }
    if (!$bookIdFormateado || !$libroValido) {
        $errores['general'] = 'ID de libro inválido.';
    }

    if (!array_filter($errores)) {
        // Buscar usuario externo por matricula
        $stmt = $db->prepare("SELECT nombreCompleto FROM usuariosexternos WHERE identificacion = ?");
        $stmt->bind_param("s", $matricula);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($resultado->num_rows > 0) {
            $usuarioExistente = $resultado->fetch_assoc();
            $nombre = $usuarioExistente['nombreCompleto'];
        } else {
            // Insertar nuevo usuario externo
            $stmtInsert = $db->prepare("INSERT INTO usuariosexternos (nombreCompleto, identificacion, registrado) VALUES (UPPER(?), UPPER(?), NOW())");
            $stmtInsert->bind_param("ss", $nombre, $matricula);
            if (!$stmtInsert->execute()) {
                $errores['general'] = 'Error al registrar el usuario. Intente nuevamente.';
            }
            $stmtInsert->close();
        }
        $stmt->close();

        if (!array_filter($errores)) {
            // Redirigir a registrar-prestamo-presencial.php enviando datos en query string
            $params = [
                'bookId' => $bookIdFormateado,
                'matricula' => $matricula,
                'nombre' => $nombre,
            ];
            $queryString = http_build_query($params);
            header('Location: registrar-prestamo.php?' . $queryString);
            exit;
        }
    }
}

incluirTemplate('sidebar-formularios');
?>

<link rel="stylesheet" href="../public/css/bundle.css">

<div class="container main--content">

    <div class="header--wrapper">
        <div class="header--title">
            <span style="display: flex; border: 2.3px solid #09a787; padding: 2px; margin-bottom: 10px; border-radius: 5px; color: #09a787; width: 230px; text-transform: uppercase">
                <?php if ($rolAdministrador === '1') {
                    echo 'Administrador general';
                } else {
                    echo 'Administrador';
                } ?>
            </span>
            <span>Bienvenido, <?php echo htmlspecialchars($nombreAdministrador); ?></span>
            <h2>Escanear Libro para Préstamo Presencial</h2>
        </div>
        <div class="user--info">
            <div class="search--box">
                <i class="fas fa-search"></i>
                <input type="text" id="buscar" placeholder="Buscar" disabled />
            </div>
            <img src="../public/img/logouttn.png" alt="Foto de perfil" />
        </div>
    </div>
    <div class="tabular--wrapper">
        <div class="tabular--botones">
            <a href="escanear-qr.php">
                <button title="Volver" class="btnAgregar">
                    <i class="fas fa-arrow-left"></i> Volver a Escanear
                </button>
            </a>
        </div>
        <div class="table--container">
            <form class="book-form" method="POST" action="datos-personales.php">
                <h1>Datos del Usuario</h1>

                <?php if ($errores['general']): ?>
                    <div class="alerta error"><?= htmlspecialchars($errores['general']); ?></div>
                <?php endif; ?>

                <input type="hidden" name="bookId" value="<?= htmlspecialchars($bookIdFormateado); ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="matricula">Matrícula o Identificación:</label>
                        <input type="text" id="matricula" name="matricula" value="<?= htmlspecialchars($matricula); ?>">
                        <?php if ($errores['matricula']): ?>
                            <div class="alerta error"><?= htmlspecialchars($errores['matricula']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="nombre">Nombre Completo:</label>
                        <input type="text" id="nombre" name="nombre" value="<?= htmlspecialchars($nombre); ?>">
                        <?php if ($errores['nombre']): ?>
                            <div class="alerta error"><?= htmlspecialchars($errores['nombre']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <button type="submit" class="btnAceptado">
                    <i class="fas fa-arrow-right" style="margin-right: 5px;"></i>Continuar al Préstamo
                </button>
            </form>
        </div>
    </div>
</div>

<?php
mysqli_close($db);
incluirTemplate('footer');
?>
