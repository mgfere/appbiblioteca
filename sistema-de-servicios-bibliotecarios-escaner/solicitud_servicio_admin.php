<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: login_admin.php');
    exit();
}
$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
require_once 'database/DatabaseAPI.php';

$carreras = [];
$servicios = [];
$message = [];
$dbAPI = new DatabaseAPI();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $matricula = strtoupper(trim($_POST['matricula'] ?? ''));
    $id_servicio = $_POST['id_servicio'] ?? '';

    if (empty($matricula) || empty($id_servicio)) {
        $message['error'] = 'Faltan datos (matrícula o servicio)';
    } else {
        $usuarioExistente = $dbAPI->usuarioExistenteRevisar($matricula);
        if ($usuarioExistente) {
            // Register service immediately if they exist locally
            $result = $dbAPI->insertarRegistroSolicitud($matricula, $id_servicio);
            $result === true ? $message['exito'] = 'Se ha registrado la solicitud exitosamente' : $message['error'] = $result;
        } else {
            // Does NOT exist locally. We MUST check SQL Server to prevent fake users.
            require_once 'database/Database.php'; // ensure connection is loaded
            $conn = conectarDB3();
            if ($conn) {
                $userData = null;
                // Teachers logic
                if (strlen($matricula) <= 4) {
                    $sql = "SELECT TOP 1 Nombre, ApellidoPaterno, ApellidoMaterno FROM Docentes WHERE NumeroEmpleado = ? AND Habilitado = 1";
                    $stmt = sqlsrv_query($conn, $sql, array($matricula));
                    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        $userData = [
                            'nameUser' => trim($row['Nombre'] . ' ' . $row['ApellidoPaterno'] . ' ' . $row['ApellidoMaterno']),
                            'userType' => 'Profesor',
                            'id_carrera' => null,
                            'id_especialidad' => null
                        ];
                    }
                    if ($stmt)
                        sqlsrv_free_stmt($stmt);
                }
                // Students logic
                else {
                    $sql = "SELECT TOP 1 A.Nombre, A.ApellidoPaterno, A.ApellidoMaterno, A.IdCarrera, GC.IdArea AS IdEspecialidad FROM Alumnos A LEFT JOIN GruposCuatrimestres GC ON A.IdGrupoCuatrimestre = GC.IdGrupoCuatrimestre WHERE A.Matricula = ? AND A.Habilitado = 1";
                    $stmt = sqlsrv_query($conn, $sql, array($matricula));
                    if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                        $userData = [
                            'nameUser' => trim($row['Nombre'] . ' ' . $row['ApellidoPaterno'] . ' ' . $row['ApellidoMaterno']),
                            'userType' => 'Alumno',
                            'id_carrera' => isset($row['IdCarrera']) ? $row['IdCarrera'] : null,
                            'id_especialidad' => isset($row['IdEspecialidad']) ? $row['IdEspecialidad'] : null
                        ];
                    }
                    if ($stmt)
                        sqlsrv_free_stmt($stmt);
                }
                sqlsrv_close($conn);

                if ($userData) {
                    // Save validated user locally and record entry
                    $result = $dbAPI->insertingTeachersAndStudents($matricula, $userData['nameUser'], $userData['id_especialidad'], $userData['id_carrera'], $id_servicio, $userData['userType']);
                    $result === true ? $message['exito'] = 'Se ha registrado la solicitud exitosamente' : $message['error'] = $result;
                } else {
                    $message['error'] = 'Usuario no encontrado en base de datos escolar.';
                }
            } else {
                $message['error'] = 'Error de conexión con el servidor escolar.';
            }
        }
    }
}

try {
    $carreras = $dbAPI->obtenerCarrerasSQLServer();
    $servicios = $dbAPI->obtenerServicios();
} catch (Exception $e) {
    error_log("Error cargando datos: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Solicitud de Servicio</title>
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="output.css">
    <link rel="icon" href="img/favicon.ico" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        /* ─── Base ─── */
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #ffffff;
            color: #1a1a1a;
            min-height: 100vh;
        }

        /* ─── Page wrapper ─── */
        .page-body {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 50px 16px 60px;
        }

        .page-title {
            font-size: 1.35rem;
            font-weight: 700;
            margin: 0 0 20px;
            letter-spacing: .01em;
        }

        /* ─── Outer grey card (matches mockup exactly) ─── */
        .outer-card {
            width: 100%;
            max-width: 960px;
            background: #f0f0f0;
            border: 1px solid #d4d4d4;
            border-radius: 14px;
            padding: 24px 20px 28px;
        }

        /* ─── Pills row ─── */
        .pills-label {
            text-align: center;
            font-size: 1.35rem;
            font-weight: 700;
            font-family: 'Segoe UI', system-ui, sans-serif;
            color: #1a1a1a;
            margin: 0 0 12px;
        }

        .pills-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .service-pill {
            padding: 7px 20px;
            border-radius: 999px;
            border: none;
            background: #5ba992;
            color: #fff;
            font-size: .78rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s, transform .15s, box-shadow .2s;
            line-height: 1.3;
            text-align: center;
        }

        .service-pill:hover {
            background: #3d9079;
        }

        .service-pill.active {
            background: #09a787;
            box-shadow: 0 4px 12px rgba(9, 167, 135, .38);
            transform: scale(1.06);
        }

        /* ─── White inner panel ─── */
        .inner-panel {
            background: #fff;
            border: 1px solid #c5c8c5;
            border-radius: 10px;
            overflow: hidden;
        }

        .inner-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
        }

        /* ── Tablet (≤ 768px) ── */
        @media (max-width: 768px) {
            .page-body {
                padding: 50px 12px 50px;
            }

            .outer-card {
                padding: 18px 14px 22px;
                border-radius: 12px;
            }

            .service-pill {
                padding: 6px 15px;
                font-size: .74rem;
            }
        }

        /* ── Mobile (≤ 640px): stack columns ── */
        @media (max-width: 640px) {
            .inner-grid {
                grid-template-columns: 1fr;
            }

            .col-scanner {
                border-right: none !important;
                border-bottom: 1px solid #e2e5e2;
                padding: 22px 16px;
            }

            .col-manual {
                padding: 22px 16px;
            }

            /* Scanner box fills available width on mobile */
            #reader-wrap {
                width: min(220px, 90vw);
                height: min(220px, 90vw);
            }

            #reader {
                width: min(220px, 90vw);
                min-height: min(220px, 90vw);
            }

            /* Form card fills width on mobile */
            .form-card {
                max-width: 100%;
            }

            .page-title {
                font-size: 1.15rem;
                margin-top: 8px;
            }

            .pills-label {
                font-size: .7rem;
            }

            .pills-row {
                gap: 8px;
            }

            .service-pill {
                padding: 6px 14px;
                font-size: .72rem;
            }
        }

        /* ── Very small phones (≤ 360px) ── */
        @media (max-width: 360px) {
            .outer-card {
                padding: 14px 10px 18px;
                border-radius: 10px;
            }

            .service-pill {
                padding: 5px 11px;
                font-size: .68rem;
            }
        }


        /* ─── Scanner column ─── */
        .col-scanner {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 28px 20px;
            border-right: 1px solid #e2e5e2;
        }




        .col-scanner h2,
        .col-manual h2 {
            font-size: 1rem;
            font-weight: 700;
            font-family: 'Segoe UI', system-ui, sans-serif;
            text-align: center;
            margin: 0 0 16px;
            line-height: 1.5;
            color: #1a1a1a;
            min-height: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #reader-wrap {
            position: relative;
            width: 220px;
            height: 220px;
            background: #555;
            border: 1px solid #999;
            border-radius: 4px;
            overflow: hidden;
            flex-shrink: 0;
        }

        #reader {
            width: 220px;
            min-height: 220px;
        }

        #scanner-status {
            margin-top: 14px;
            font-size: .72rem;
            font-weight: 700;
            letter-spacing: .08em;
            color: #09a787;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: .45;
            }
        }

        /* ─── QR overlay corners ─── */
        #reader__dashboard_section {
            padding: 0 !important;
            margin: 0 !important;
            border: none !important;
            height: 0 !important;
            overflow: hidden !important;
            opacity: 0 !important;
        }

        #qr-shaded-region {
            display: none !important;
        }

        .custom-qr-target {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 150px;
            height: 150px;
            box-shadow: 0 0 0 4000px rgba(0, 0, 0, .45);
            pointer-events: none;
            z-index: 10;
        }

        .sc {
            position: absolute;
            pointer-events: none;
            z-index: 20;
            width: 20px;
            height: 20px;
            border-color: #09a787;
            border-style: solid;
        }

        .sc-tl {
            top: 18px;
            left: 18px;
            border-width: 4px 0 0 4px;
            border-top-left-radius: 8px;
        }

        .sc-tr {
            top: 18px;
            right: 18px;
            border-width: 4px 4px 0 0;
            border-top-right-radius: 8px;
        }

        .sc-bl {
            bottom: 18px;
            left: 18px;
            border-width: 0 0 4px 4px;
            border-bottom-left-radius: 8px;
        }

        .sc-br {
            bottom: 18px;
            right: 18px;
            border-width: 0 4px 4px 0;
            border-bottom-right-radius: 8px;
        }

        /* ─── Manual entry column ─── */
        .col-manual {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 28px 20px;
        }

        .col-manual h2 {
            font-size: .85rem;
            font-weight: 700;
            margin: 0 0 18px;
            text-align: center;
            color: #222;
        }

        /* ─── Manual form card ─── */
        .form-card {
            width: 100%;
            max-width: 300px;
            background: #f0f0f0;
            border: 1px solid #d4d4d4;
            border-radius: 10px;
            padding: 20px 18px;
        }

        .form-card label {
            display: block;
            font-size: .75rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 8px;
        }

        .form-card input[type="text"],
        .form-card select {
            width: 100%;
            padding: 8px 14px;
            font-size: .82rem;
            border: 1px solid #ccc;
            border-radius: 999px;
            background: #fff;
            color: #222;
            outline: none;
            transition: border-color .2s, box-shadow .2s;
            margin-bottom: 0;
        }

        .form-card input[type="text"]:focus,
        .form-card select:focus {
            border-color: #09a787;
            box-shadow: 0 0 0 3px rgba(9, 167, 135, .15);
        }

        .form-card select {
            border-radius: 8px;
        }

        .field-wrap {
            margin-bottom: 14px;
        }

        .field-wrap.hidden {
            display: none;
        }

        .btn-save {
            display: block;
            width: 100%;
            margin-top: 18px;
            padding: 10px;
            background: #5ba992;
            color: #fff;
            font-size: .82rem;
            font-weight: 700;
            border: none;
            border-radius: 999px;
            cursor: pointer;
            transition: background .2s, transform .15s;
        }

        .btn-save:hover {
            background: #09a787;
            transform: scale(1.02);
        }

        /* ─── SweetAlert green ─── */
        div:where(.swal2-container) button:where(.swal2-styled).swal2-confirm {
            background-color: #09a787 !important;
        }
    </style>
</head>

<body class="flex flex-col min-h-screen">
    <?php include 'header.php'; ?>

    <div class="page-body flex-1">
        <h1 class="page-title">Solicitud de Servicio</h1>

        <!-- ░░ OUTER GREY CARD ░░ -->
        <div class="outer-card">

            <!-- SERVICE PILLS -->
            <p class="pills-label">Selecciona un servicio</p>
            <div class="pills-row">
                <?php if (!empty($servicios)): ?>
                    <?php foreach ($servicios as $s): ?>
                        <button type="button" class="service-pill" data-id="<?php echo (int) $s['id_servicio']; ?>">
                            <?php echo htmlspecialchars($s['nombre_servicio']); ?>
                        </button>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p style="color:#b00;font-size:.8rem;">No hay servicios disponibles.</p>
                <?php endif; ?>
            </div>

            <!-- Shared hidden: synced by pill click -->
            <input type="hidden" id="selected_service_id">

            <!-- ░░ WHITE INNER PANEL ░░ -->
            <div class="inner-panel">
                <div class="inner-grid">

                    <!-- ── SCANNER ── -->
                    <div class="col-scanner">
                        <h2>Entrada por Credencial Digital</h2>
                        <div id="reader-wrap">
                            <div id="reader"></div>
                        </div>
                        <p id="scanner-status">Iniciando Cámara...</p>
                    </div>

                    <!-- ── MANUAL ENTRY ── -->
                    <div class="col-manual">
                        <h2>Entrada Manual</h2>

                        <form id="formulario" method="POST" action="" class="form-card">
                            <input type="hidden" name="id_servicio" id="servicio_form_hidden">
                            <input type="hidden" name="userType" id="userType">

                            <!-- Matricula -->
                            <div class="field-wrap">
                                <label for="matriculaInput">Matricula/Numero de Empleado</label>
                                <input type="text" name="matricula" id="matriculaInput"
                                    placeholder="Matricula o Numero de Empleado" required maxlength="10"
                                    autocomplete="off">
                            </div>

                            <!-- Nombre (auto-hidden) -->
                            <div class="field-wrap hidden" id="field-nombre">
                                <label for="nombre">Nombre Completo</label>
                                <input type="text" name="nombre" id="nombre" placeholder="(Autocompletado)" readonly
                                    style="background:#fff; cursor:not-allowed;">
                            </div>

                            <!-- Tipo (auto-hidden) -->
                            <div class="field-wrap hidden" id="field-tipo">
                                <label>Tipo</label>
                                <span id="tipo-badge" style="display:inline-block; padding:5px 16px; background:#fff; border:1px solid #ccc;
                                           border-radius:999px; font-size:.8rem; font-weight:600; color:#333;">
                                </span>
                            </div>

                            <!-- Carrera (auto-hidden) -->
                            <div class="field-wrap hidden" id="carreraAlum">
                                <label for="id_carrera">Carrera</label>
                                <select name="id_carrera" id="id_carrera">
                                    <option value="" disabled selected>Seleccione una carrera</option>
                                    <?php foreach ($carreras as $c): ?>
                                        <option value="<?php echo $c['id_carrera']; ?>">
                                            <?php echo htmlspecialchars($c['nombre_carrera']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Especialidad (auto-hidden) -->
                            <div class="field-wrap hidden" id="especialidadAlum">
                                <label for="id_especialidad">Especialidad</label>
                                <select name="id_especialidad" id="id_especialidad">
                                    <option value="" disabled selected>Seleccione una especialidad</option>
                                </select>
                            </div>

                            <button type="submit" class="btn-save">Guardar</button>
                        </form>
                    </div>

                </div>
            </div><!-- /white inner panel -->

        </div><!-- /outer grey card -->
    </div>

    <?php include 'footer.php'; ?>

    <!-- ─── POST feedback ─── -->
    <?php if (isset($message['exito'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                new Audio('public/audio/apple_pay_sound.mp3').play().catch(() => { });
                Swal.fire({
                    position: 'top-end', icon: 'success',
                    title: <?php echo json_encode($message['exito']); ?>,
                    showConfirmButton: false, timer: 3000, timerProgressBar: true,
                    toast: true, background: '#f0f9f0', iconColor: '#28a745'
                }).then(() => { window.location.href = 'index.php'; });
            });
        </script>
    <?php endif; ?>

    <?php if (isset($message['error'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                Swal.fire({
                    icon: 'error', title: 'Error',
                    text: <?php echo json_encode($message['error']); ?>,
                    confirmButtonColor: '#09a787'
                });
            });
        </script>
    <?php endif; ?>

    <!-- ─── Main JS ─── -->
    <script>
        $(function () {

            /* ═══════════════════════════════════
               1. SERVICE PILLS
            ═══════════════════════════════════ */
            $('.service-pill').on('click', function () {
                $('.service-pill').removeClass('active');
                $(this).addClass('active');
                const id = $(this).data('id');
                $('#selected_service_id').val(id);
                $('#servicio_form_hidden').val(id);
            });

            /* ═══════════════════════════════════
               2. MANUAL FORM  (autocomplete)
            ═══════════════════════════════════ */
            function show(selector) { $(selector).removeClass('hidden').show(); }
            function hide(selector) { $(selector).addClass('hidden').hide(); }

            function loadEspecialidades(carreraId, presel) {
                $.ajax({
                    url: 'getEspecialidades.php', method: 'POST',
                    data: { carreraId, selectedId: presel || '' },
                    success: html => {
                        $('#id_especialidad').html(html);
                        if (presel) $('#id_especialidad').val(presel);
                    }
                });
            }

            function fillUser(data) {
                $('#nombre').val(data.nameUser);
                $('#userType').val(data.userType);
                show('#field-nombre');
                // Mostrar la etiqueta de Tipo
                $('#tipo-badge').text(data.userType);
                show('#field-tipo');
                if (data.userType === 'Profesor' || data.userType === 'Administrativo') {
                    hide('#carreraAlum'); hide('#especialidadAlum');
                    $('#id_carrera, #id_especialidad').val('').prop('required', false);
                } else {
                    show('#carreraAlum'); $('#id_carrera').prop('required', true);
                    if (data.id_carrera) {
                        $('#id_carrera').val(data.id_carrera);
                        show('#especialidadAlum');
                        loadEspecialidades(data.id_carrera, data.id_especialidad);
                    }
                }
            }

            $('#matriculaInput').on('blur', function () {
                const mat = $(this).val().trim();
                if (mat.length <= 4 || mat.length === 10) {
                    $.ajax({
                        url: 'autocompletar_info.php', type: 'POST',
                        data: { matricula: mat }, dataType: 'json',
                        success: r => {
                            if (r.success) {
                                fillUser(r.userData);
                            } else {
                                const tipo = mat.length <= 4 ? 'Profesor' : 'Alumno';
                                $('#userType').val(tipo);
                                hide('#field-nombre');
                                hide('#field-tipo');
                                tipo === 'Alumno' ? show('#carreraAlum, #especialidadAlum')
                                    : (hide('#carreraAlum'), hide('#especialidadAlum'));
                            }
                        }
                    });
                } else {
                    hide('#field-nombre'); hide('#field-tipo'); hide('#carreraAlum'); hide('#especialidadAlum');
                }
            });

            $('#id_carrera').on('change', function () { loadEspecialidades($(this).val()); });

            $('#formulario').on('submit', function (e) {
                if (!$('#servicio_form_hidden').val()) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'warning', title: 'Falta el servicio',
                        text: 'Haz clic en uno de los botones verdes de arriba primero.',
                        confirmButtonColor: '#09a787'
                    });
                }
            });

            /* ═══════════════════════════════════
               3. QR SCANNER
            ═══════════════════════════════════ */
            const $status = $('#scanner-status');
            const successSound = new Audio('public/audio/apple_pay_sound.mp3');
            let busy = false;

            const scanner = new Html5QrcodeScanner('reader', {
                fps: 60,
                qrbox: { width: 180, height: 180 },
                aspectRatio: 1.0,
                supportedScanTypes: [Html5QrcodeScanType.SCAN_TYPE_CAMERA]
            }, false);

            function onScan(text) {
                if (busy) return;
                const serviceId = $('#selected_service_id').val();
                if (!serviceId) {
                    Swal.fire({
                        icon: 'warning', title: 'Falta el servicio',
                        text: 'Selecciona un servicio con los botones de arriba primero.',
                        confirmButtonColor: '#09a787'
                    });
                    return;
                }
                busy = true;
                scanner.pause();
                $status.text('Procesando...').css({ color: '#ca8a04', animation: 'none' });

                $.ajax({
                    url: 'procesar_solicitud_escaner.php', method: 'POST',
                    data: { matricula: text.trim(), id_servicio: serviceId },
                    dataType: 'json',
                    success(r) {
                        toast(r.success, r.message);
                        if (r.success) {
                            successSound.play().catch(() => { });
                            $status.text('¡Registrado! Redirigiendo...').css('color', '#16a34a');
                            setTimeout(() => { window.location.href = 'index.php'; }, 3000);
                        } else {
                            resume();
                        }
                    },
                    error() { toast(false, 'Error de conexión'); resume(); }
                });
            }

            function resume() {
                setTimeout(() => {
                    busy = false;
                    $status.text('Cámara Activa · Escanea aquí')
                        .css({ color: '#09a787', animation: 'pulse 2s ease-in-out infinite' });
                    scanner.resume();
                }, 1000);
            }

            scanner.render(onScan, () => { });

            // Inject corner overlay after camera starts
            setTimeout(() => {
                $status.text('Cámara Activa · Escanea aquí');
                if (!$('#reader-wrap .custom-qr-target').length) {
                    $('#reader-wrap').append(
                        '<div class="custom-qr-target"></div>' +
                        '<div class="sc sc-tl"></div>' +
                        '<div class="sc sc-tr"></div>' +
                        '<div class="sc sc-bl"></div>' +
                        '<div class="sc sc-br"></div>'
                    );
                }
            }, 1500);

            /* ═══════════════════════════════════
               4. TOAST HELPER
            ═══════════════════════════════════ */
            function toast(ok, msg) {
                Swal.fire({
                    position: 'top-end', icon: ok ? 'success' : 'error',
                    title: ok ? msg : 'Error', text: ok ? '' : msg,
                    showConfirmButton: false, timer: 3000, timerProgressBar: true,
                    toast: true, background: ok ? '#f0f9f0' : '#fff5f5',
                    iconColor: ok ? '#28a745' : '#d33',
                    customClass: { popup: 'rounded-xl' }
                });
            }
        });
    </script>
</body>

</html>