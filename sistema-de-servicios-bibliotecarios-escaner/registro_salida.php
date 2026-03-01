<?php
require 'database/Database.php';
require 'database/DatabaseAPI.php';
$dbAPI = new DatabaseAPI();
$message = '';
$search_result = [];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search_matricula'])) {
    $search_matricula = strtoupper(trim($_GET['search_matricula']));
    if (!empty($search_matricula)) {
        try {
            // First check if user exists locally
            $usuarioExistente = $dbAPI->usuarioExistenteRevisar($search_matricula);

            // If user doesn't exist locally, check SQL Server to avoid fake searches
            if (!$usuarioExistente) {
                require_once 'database/Database.php';
                $conn = conectarDB3();
                if ($conn) {
                    $userData = null;
                    if (strlen($search_matricula) <= 4) {
                        $sql = "SELECT TOP 1 Nombre, ApellidoPaterno, ApellidoMaterno FROM Docentes WHERE NumeroEmpleado = ? AND Habilitado = 1";
                        $stmt = sqlsrv_query($conn, $sql, array($search_matricula));
                        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                            $userData = true; // User is valid
                        }
                    } else {
                        $sql = "SELECT TOP 1 Nombre FROM Alumnos WHERE Matricula = ? AND Habilitado = 1";
                        $stmt = sqlsrv_query($conn, $sql, array($search_matricula));
                        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                            $userData = true; // User is valid
                        }
                    }
                    sqlsrv_close($conn);

                    if (!$userData) {
                        $message = "Usuario no encontrado en base de datos escolar.";
                        $search_result = [];
                    }
                }
            }

            // Only run query if we didn't flag them as fake above
            if (empty($message)) {
                $search_result = $dbAPI->obtenerRegistroPorMatricula($search_matricula);
                if (empty($search_result)) {
                    $message = "No se encontraron registros activos o pendientes con la matrícula ingresada.";
                } else {
                    $active_records = array_filter($search_result, function ($r) {
                        return empty($r['hora_salida']) || $r['hora_salida'] == '0000-00-00 00:00:00';
                    });
                    if (empty($active_records)) {
                        $message = "No hay registros activos para esta matrícula en este momento.";
                        $search_result = [];
                    } else {
                        $search_result = $active_records;
                    }
                }
            }
        } catch (Exception $e) {
            $message = "Error al buscar el registro: " . $e->getMessage();
        }
    } else {
        $message = "Ingrese su matrícula completa para realizar la búsqueda.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro de Salida</title>
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="output.css">
    <link rel="icon" href="img/favicon.ico" type="image/x-icon">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: #fff;
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

        /* ─── Outer card ─── */
        .outer-card {
            width: 100%;
            max-width: 960px;
            background: #f0f0f0;
            border: 1px solid #d4d4d4;
            border-radius: 14px;
            padding: 24px 20px 28px;
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
        }

        /* ── Mobile (≤ 640px) ── */
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

            #reader-wrap {
                width: min(220px, 90vw);
                height: min(220px, 90vw);
            }

            #reader {
                width: min(220px, 90vw);
                min-height: min(220px, 90vw);
            }

            .page-title {
                font-size: 1.15rem;
                margin-top: 8px;
            }
        }

        @media (max-width: 360px) {
            .outer-card {
                padding: 14px 10px 18px;
                border-radius: 10px;
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

        /* QR overlay */
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

        /* ─── Manual column ─── */
        .col-manual {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 28px 20px;
        }

        /* Search box */
        .search-wrap {
            width: 100%;
            max-width: 310px;
        }

        .search-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .search-row input {
            flex: 1;
            padding: 9px 16px;
            font-size: .84rem;
            border: 1px solid #ccc;
            border-radius: 999px;
            outline: none;
            font-family: inherit;
            transition: border-color .2s, box-shadow .2s;
        }

        .search-row input:focus {
            border-color: #09a787;
            box-shadow: 0 0 0 3px rgba(9, 167, 135, .15);
        }

        .btn-search {
            padding: 9px 16px;
            background: #09a787;
            border: none;
            border-radius: 999px;
            cursor: pointer;
            transition: background .2s;
            display: flex;
            align-items: center;
        }

        .btn-search:hover {
            background: #077f6a;
        }

        .btn-search img {
            width: 22px;
            height: 22px;
            filter: brightness(0) invert(1);
        }

        /* Message */
        .msg-error {
            margin-top: 12px;
            background: #fee2e2;
            color: #b91c1c;
            border-radius: 8px;
            padding: 8px 14px;
            font-size: .8rem;
            font-weight: 600;
            text-align: center;
            width: 100%;
            max-width: 310px;
        }

        /* Results table */
        .results-wrap {
            margin-top: 16px;
            width: 100%;
            max-width: 310px;
            overflow-x: auto;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            font-size: .78rem;
        }

        .results-table thead {
            background: #1f2937;
            color: #fff;
        }

        .results-table th,
        .results-table td {
            padding: 8px 10px;
            text-align: center;
            white-space: nowrap;
        }

        .results-table tbody tr {
            border-bottom: 1px solid #e5e7eb;
        }

        .results-table tbody tr:hover {
            background: #f9fafb;
        }

        .btn-salida {
            padding: 5px 12px;
            background: #09a787;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: .75rem;
            font-weight: 700;
            cursor: pointer;
            transition: background .2s;
        }

        .btn-salida:hover {
            background: #077f6a;
        }

        /* SweetAlert green confirm */
        div:where(.swal2-container) button:where(.swal2-styled).swal2-confirm {
            background-color: #09a787 !important;
        }

        /* Forzar visibilidad del botón Cancelar */
        div:where(.swal2-container) button:where(.swal2-styled).swal2-cancel {
            background-color: #d33 !important;
            display: inline-block !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
    </style>
</head>

<body class="flex flex-col min-h-screen">
    <?php include 'header_registros.php'; ?>

    <div class="page-body flex-1">
        <h1 class="page-title">Registro de Salida</h1>

        <!-- ░░ OUTER CARD ░░ -->
        <div class="outer-card">

            <!-- ░░ WHITE INNER PANEL ░░ -->
            <div class="inner-panel">
                <div class="inner-grid">

                    <!-- ── SCANNER ── -->
                    <div class="col-scanner">
                        <h2>Salida por Credencial Digital</h2>
                        <div id="reader-wrap">
                            <div id="reader"></div>
                        </div>
                        <p id="scanner-status">Iniciando Cámara...</p>
                    </div>

                    <!-- ── MANUAL ── -->
                    <div class="col-manual">
                        <h2>Salida Manual</h2>

                        <div class="search-wrap">
                            <form method="GET" action="">
                                <div class="search-row">
                                    <input type="search" name="search_matricula" id="search-input"
                                        placeholder="Busca tu matrícula" maxlength="10"
                                        value="<?php echo isset($search_matricula) ? htmlspecialchars($search_matricula) : ''; ?>"
                                        required>
                                    <button type="submit" class="btn-search" title="Buscar">
                                        <img src="img/buscar.png" alt="buscar">
                                    </button>
                                </div>
                            </form>

                            <!-- Error message -->
                            <?php if (!empty($message) && empty($search_result)): ?>
                                <div class="msg-error"><?php echo htmlspecialchars($message); ?></div>
                            <?php endif; ?>

                            <!-- Results table -->
                            <?php if (!empty($search_result)): ?>
                                <div class="results-wrap">
                                    <table class="results-table">
                                        <thead>
                                            <tr>
                                                <th>Acción</th>
                                                <th>Matrícula</th>
                                                <th>Nombre</th>
                                                <th>Servicio</th>
                                                <th>Entrada</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($search_result as $row): ?>
                                                <tr>
                                                    <td>
                                                        <?php if (empty($row['hora_salida']) || $row['hora_salida'] == '0000-00-00 00:00:00'): ?>
                                                            <button type="button" class="btn-salida"
                                                                onclick="RegistrarSalida(<?php echo $row['id_registro']; ?>)">
                                                                Salida
                                                            </button>
                                                        <?php else: ?>
                                                            <span style="color:#6b7280;font-size:.72rem;">Registrada</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($row['matricula']); ?></td>
                                                    <td><?php echo htmlspecialchars($row['nameUser']); ?></td>
                                                    <td><?php echo htmlspecialchars($dbAPI->obtenerNombreServicio($row['id_servicio'])); ?>
                                                    </td>
                                                    <td><?php echo date('d/m H:i', strtotime($row['hora_entrada'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div><!-- /inner panel -->

        </div><!-- /outer card -->
    </div>

    <?php include 'footer.php'; ?>

    <script>
        /* ══════════════════════════════════
           1. QR SCANNER (salida automática)
        ══════════════════════════════════ */
        $(function () {
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
                busy = true;
                scanner.pause();
                $status.text('Procesando salida...').css({ color: '#ca8a04', animation: 'none' });

                $.ajax({
                    url: 'procesar_salida_escaner.php',
                    method: 'POST',
                    data: { matricula: text.trim() },
                    dataType: 'json',
                    success(r) {
                        toast(r.success, r.message);
                        if (r.success) {
                            successSound.play().catch(() => { });
                            $status.text('¡Salida Registrada! Redirigiendo...').css('color', '#16a34a');
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

            /* ══════════════════════════════════
               2. TOAST HELPER
            ══════════════════════════════════ */
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

        /* ══════════════════════════════════
           3. SALIDA MANUAL (registro)
        ══════════════════════════════════ */
        function RegistrarSalida(registroId) {
            const successSound = new Audio('public/audio/apple_pay_sound.mp3');
            Swal.fire({
                title: '¿Registrar la salida?',
                text: 'Esta acción no se puede deshacer',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#09a787',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, registrar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        type: 'POST',
                        url: 'logica_salida.php',
                        data: { marcarSalida: true, registroId },
                        dataType: 'json',
                        success(data) {
                            if (data.success) {
                                successSound.play().catch(() => { });
                                Swal.fire({
                                    title: 'Salida registrada',
                                    text: 'La salida ha sido registrada correctamente',
                                    icon: 'success',
                                    timer: 3000,
                                    confirmButtonColor: '#09a787'
                                }).then(() => {
                                    localStorage.setItem('redirectToIndex', 'true');
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error', text: data.error || 'Error desconocido',
                                    icon: 'error', confirmButtonColor: '#d33'
                                });
                            }
                        },
                        error() {
                            Swal.fire({
                                title: 'Error de conexión',
                                text: 'No se pudo conectar con el servidor',
                                icon: 'error', confirmButtonColor: '#d33'
                            });
                        }
                    });
                }
            });
        }

        /* Redirect after reload */
        document.addEventListener('DOMContentLoaded', function () {
            if (localStorage.getItem('redirectToIndex') === 'true') {
                localStorage.removeItem('redirectToIndex');
                setTimeout(() => { window.location.href = 'index.php'; }, 1000);
            }

            /* Clear results when search is emptied */
            const inp = document.getElementById('search-input');
            if (inp) {
                inp.addEventListener('input', function () {
                    if (this.value === '') document.querySelector('form').submit();
                });
            }
        });
    </script>
</body>

</html>