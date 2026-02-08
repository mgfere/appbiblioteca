<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

setlocale(LC_TIME, 'es_MX.UTF-8', 'spanish');


require '../vendor/autoload.php';
require '../includes/funciones.php';

$auth = adminAutenticado();

if (!$auth) {
    header('Location: login.php');
    exit;
}

$nombreAdministrador = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario';
$rolAdministrador = (int) isset($_SESSION['rol']) ? $_SESSION['rol'] : null;
$idAdministrador = isset($_SESSION['id']) ? $_SESSION['id'] : null;



require '../includes/config/database.php';
$db = conectarDB();
$conn_sqlsrv = conectarDB2();
$conn = conectarDB3();


$meses = array("Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");

$currentYear = date('Y');
$fechaInicioDefault = $currentYear . '-01-01';
$fechaFinDefault = date('Y-m-d');

// Initialize arrays for data, ensuring they are always filled, even if no DB results
// These will be used for the *initial* chart render before any filter is applied
$registros_por_mes = array_fill(0, 12, 0);
$prestamos_por_mes = array_fill(0, 12, 0);
$prestamos_presenciales_por_mes = array_fill(0, 12, 0);
$usuarios_externos_por_mes = array_fill(0, 12, 0);

$prestamosPorSeccionInicial = [];
$prestamosPresencialesPorSeccionInicial = [];

// --- PHP Queries for INITIAL LOAD (using $fechaInicioDefault and $fechaFinDefault) ---
// Note: While these queries run initially, the Excel button will be hidden until 'Consultar' is clicked.
// These serve to populate the charts with default data.
/*
// Consulta para USUARIOS REGISTRADOS (internos, tabla 'usuarios')
$sqlUsuarios = "SELECT DATE_FORMAT(registrado, '%c') AS mes, COUNT(*) AS total_usuarios
                FROM usuarios
                WHERE registrado BETWEEN '{$fechaInicioDefault}' AND '{$fechaFinDefault} 23:59:59'
                GROUP BY DATE_FORMAT(registrado, '%m-%Y')
                ORDER BY DATE_FORMAT(registrado, '%Y-%m') ASC";
$resultadoUsuarios = mysqli_query($db, $sqlUsuarios);
if (!$resultadoUsuarios) { error_log("Error en consulta de usuarios (inicial): " . mysqli_error($db)); }
while ($fila = mysqli_fetch_assoc($resultadoUsuarios)) {
    $mes = intval($fila['mes']) - 1;
    if ($mes >= 0 && $mes < 12) {
        $registros_por_mes[$mes] = (int)$fila['total_usuarios'];
    }
}
    */

// Consulta para PRÉSTAMOS (los de la tabla 'prestamos')
$sqlPrestamos = "SELECT DATE_FORMAT(fecha_prestamo, '%c') AS mes, COUNT(*) AS total_prestamos
                  FROM prestamos
                  WHERE fecha_prestamo BETWEEN '{$fechaInicioDefault}' AND '{$fechaFinDefault} 23:59:59'
                  AND status = 1
                  GROUP BY DATE_FORMAT(fecha_prestamo, '%m-%Y')
                  ORDER BY DATE_FORMAT(fecha_prestamo, '%Y-%m') ASC";
$resultadoPrestamos = mysqli_query($db, $sqlPrestamos);
if (!$resultadoPrestamos) {
    error_log("Error en consulta de préstamos (inicial): " . mysqli_error($db));
}
while ($fila = mysqli_fetch_assoc($resultadoPrestamos)) {
    $mes = intval($fila['mes']) - 1;
    if ($mes >= 0 && $mes < 12) {
        $prestamos_por_mes[$mes] = (int) $fila['total_prestamos'];
    }
}

// Consulta para PRÉSTAMOS PRESENCIALES (tabla 'prestamospresencial')
$sqlPrestamosPresenciales = "SELECT DATE_FORMAT(fechaPrestamo, '%c') AS mes, COUNT(*) AS total_presenciales
                             FROM prestamospresencial
                             WHERE fechaPrestamo BETWEEN '{$fechaInicioDefault}' AND '{$fechaFinDefault} 23:59:59'
                                AND estatus = 1
                             GROUP BY DATE_FORMAT(fechaPrestamo, '%m-%Y')
                             ORDER BY DATE_FORMAT(fechaPrestamo, '%Y-%m') ASC";
$resultadoPrestamosPresenciales = mysqli_query($db, $sqlPrestamosPresenciales);
if (!$resultadoPrestamosPresenciales) {
    error_log("Error en consulta de préstamos presenciales (inicial): " . mysqli_error($db));
}
while ($fila = mysqli_fetch_assoc($resultadoPrestamosPresenciales)) {
    $mes = intval($fila['mes']) - 1;
    if ($mes >= 0 && $mes < 12) {
        $prestamos_presenciales_por_mes[$mes] = (int) $fila['total_presenciales'];
    }
}

/*
// Consulta para USUARIOS EXTERNOS REGISTRADOS (tabla 'usuariosexternos')
$sqlUsuariosExternos = "SELECT DATE_FORMAT(registrado, '%c') AS mes, COUNT(*) AS total_externos
                        FROM usuariosexternos
                        WHERE registrado BETWEEN '{$fechaInicioDefault}' AND '{$fechaFinDefault} 23:59:59'
                        GROUP BY DATE_FORMAT(registrado, '%m-%Y')
                        ORDER BY DATE_FORMAT(registrado, '%Y-%m') ASC";
$resultadoUsuariosExternos = mysqli_query($db, $sqlUsuariosExternos);
if (!$resultadoUsuariosExternos) { error_log("Error en consulta de usuarios externos (inicial): " . mysqli_error($db)); }
while ($fila = mysqli_fetch_assoc($resultadoUsuariosExternos)) {
    $mes = intval($fila['mes']) - 1;
    if ($mes >= 0 && $mes < 12) {
        $usuarios_externos_por_mes[$mes] = (int)$fila['total_externos'];
    }
}
*/
// Consultas para "Préstamos por Sección" (tabla 'prestamos')
$sqlPrestamosSeccionInicial = "SELECT s.nombre_seccion AS seccion, COUNT(p.id) AS total
                               FROM prestamos p
                               JOIN libros l ON p.Libros_id = l.id
                               JOIN secciones s ON l.seccionId = s.id
                               WHERE p.fecha_prestamo BETWEEN '{$fechaInicioDefault}' AND '{$fechaFinDefault} 23:59:59'
                               AND p.status = 1
                               GROUP BY s.nombre_seccion";
$resultadoPrestamosSeccionInicial = mysqli_query($db, $sqlPrestamosSeccionInicial);
if (!$resultadoPrestamosSeccionInicial) {
    error_log("Error en consulta de préstamos por sección (inicial): " . mysqli_error($db));
}
while ($fila = mysqli_fetch_assoc($resultadoPrestamosSeccionInicial)) {
    $prestamosPorSeccionInicial[] = $fila;
}

// Consulta para "Préstamos Presenciales por Sección" (tabla 'prestamospresencial')
$sqlPrestamosPresencialesSeccionInicial = "SELECT s.nombre_seccion AS seccion, COUNT(pp.id) AS total
                                           FROM prestamospresencial pp
                                           JOIN secciones s ON pp.seccionId = s.id
                                           WHERE pp.fechaPrestamo BETWEEN '{$fechaInicioDefault}' AND '{$fechaFinDefault} 23:59:59'
                                             AND pp.estatus = 1
                                           GROUP BY s.nombre_seccion";
$resultadoPrestamosPresencialesSeccionInicial = mysqli_query($db, $sqlPrestamosPresencialesSeccionInicial);
if (!$resultadoPrestamosPresencialesSeccionInicial) {
    error_log("Error en consulta de préstamos presenciales por sección (inicial): " . mysqli_error($db));
}
while ($fila = mysqli_fetch_assoc($resultadoPrestamosPresencialesSeccionInicial)) {
    $prestamosPresencialesPorSeccionInicial[] = $fila;
}
?>

<?php incluirTemplate('sidebar'); ?>

<link rel="stylesheet" href="../public/css/estadisticas.css">
<script src="../public/js/estadisticas.js"></script>
<div class="container main--content">
    <div class="header--wrapper">
        <div class="header--title">
            <span
                style="display: flex; border: 2.3px solid #09a787; padding: 2px; margin-bottom: 10px; border-radius: 5px; color: #09a787; width: 230px; text-transform: uppercase">
                <?php if ($rolAdministrador === 1) {
                    echo "Máster";
                } else if ($rolAdministrador == 2) {
                    echo "Administrador General";
                } else {
                    echo "Administrador";
                }
                ; ?>
            </span>
            <span>Bienvenido, <?php echo ($nombreAdministrador); ?></span>
            <h2>Panel de estadísticas-prestamos</h2>
        </div>
        <div class="user--info">
            <img src="../public/img/logouttn.png" alt="Foto de perfil" />
        </div>
    </div>
<div class="filters">
    <form id="filterForm">
        <label for="fechaInicio">Fecha inicio:</label>
        <input type="date" name="fechaInicio" id="fechaInicio" max="<?php echo date('Y-m-d') ?>"
            value="<?php echo $fechaInicioDefault; ?>">

        <label for="fechaFin">Fecha fin:</label>
        <input type="date" max="<?php echo date('Y-m-d') ?>" name="fechaFin" id="fechaFin"
            value="<?php echo date('Y-m-d') ?>">

        <label for="carrera" style="margin-left: 20px;">
            <i class="fas fa-graduation-cap"></i> Carrera
        </label>
        <select name="carrera" id="carrera">
            <option value="">Todas las carreras</option>
            <?php
            $sqlCarrerasSQLSrv = "SELECT IdCarrera, Nombre FROM [GestionUsuarios].[dbo].[Carreras] ORDER BY Nombre";
            $resultadoCarreras = sqlsrv_query($conn, $sqlCarrerasSQLSrv);

            if ($resultadoCarreras) {
                while ($filaCarrera = sqlsrv_fetch_array($resultadoCarreras, SQLSRV_FETCH_ASSOC)) {
                    $idCarrera = htmlspecialchars($filaCarrera['IdCarrera']);
                    $nombreCarrera = htmlspecialchars($filaCarrera['Nombre']);
                    echo "<option value='{$idCarrera}'>{$nombreCarrera}</option>";
                }
            }
            ?>
        </select>

        <label for="turno" style="margin-left: 10px;">
            <i class="fas fa-clock"></i> Turno
        </label>
        <select name="turno" id="turno">
            <option value="">Todos los turnos</option>
            <?php
            $sqlTurnosSQLSrv = "SELECT IdTurno, Nombre FROM [Tutorias].[dbo].[Turnoes] ORDER BY Nombre";
            $resultadoTurnos = sqlsrv_query($conn_sqlsrv, $sqlTurnosSQLSrv);

            if ($resultadoTurnos) {
                while ($filaTurno = sqlsrv_fetch_array($resultadoTurnos, SQLSRV_FETCH_ASSOC)) {
                    $idTurno = htmlspecialchars($filaTurno['IdTurno']);
                    $nombreTurno = htmlspecialchars($filaTurno['Nombre']);
                    echo "<option value='{$idTurno}'>{$nombreTurno}</option>";
                }
            }
            ?>
        </select>

        <button type="submit" style="margin-left: 20px;">Consultar</button>
        <button type="button" id="btnLimpiarFiltros" style="margin-left: 10px; background-color: #6c757d;">
            <i class="fas fa-eraser"></i> Limpiar
        </button>
    </form>

    <div id="noDataMessage" style="color: #dc3545; font-weight: bold; margin-top: 10px; display: none;"></div>
    <a id="btnExcel" href="#"
        style="display: none; background-color: #09a787; color: #fcfcfc; padding: 10px 10px; border: none; border-radius: 5px; cursor: pointer; font-size: 12px; transition: background-color .3s ease; margin-top: 10px;"
        class="button"><i class="fas fa-file-excel"></i> Exportar Excel</a>
</div>


<!-- TERCERA FILA: Gráficos de préstamos -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-top: 20px;">
    <div style="min-width: 400px; max-height: 350px; overflow: auto;">
        <canvas id="prestamos" height="300"></canvas>
    </div>
    <div style="min-width: 400px; max-height: 350px; overflow: auto;">
        <canvas id="prestamosPresenciales" height="300"></canvas>
    </div>
</div>

<!-- CUARTA FILA: Resúmenes informativos -->
<div class="info-summaries">
    <div class="summary-box" id="infoSeccionesPrestamos"></div>
    <div class="summary-box" id="infoSeccionesPresenciales"></div>
    <div class="summary-box" id="infoCarreras"></div>
    <div class="summary-box" id="infoTurnos"></div>
</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>

document.getElementById('btnLimpiarFiltros').addEventListener('click', function() {
    // Resetear el formulario a valores por defecto
    document.getElementById('fechaInicio').value = '<?php echo $fechaInicioDefault; ?>';
    document.getElementById('fechaFin').value = '<?php echo date('Y-m-d'); ?>';
    document.getElementById('carrera').value = '';
    document.getElementById('turno').value = '';
    
    document.getElementById('filterForm').dispatchEvent(new Event('submit'));
});

    var ctxPrestamos = document.getElementById('prestamos').getContext('2d');
    var ctxPrestamosPresenciales = document.getElementById('prestamosPresenciales').getContext('2d');

    var miGraficoPrestamos, miGraficoPrestamosPresenciales;

    const noDataMessageDiv = document.getElementById('noDataMessage');
    const btnExcel = document.getElementById('btnExcel');
    const meses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

    function hasData(data) {
        const hasChartData =
            (data.prestamos && data.prestamos.some(val => val > 0)) ||
            (data.prestamosPresenciales && data.prestamosPresenciales.some(val => val > 0));

        const hasSummaryData =
            (data.prestamosPorSeccion && data.prestamosPorSeccion.length > 0) ||
            (data.prestamosPresencialesPorSeccion && data.prestamosPresencialesPorSeccion.length > 0) ||
            (data.prestamosPorCarrera && data.prestamosPorCarrera.length > 0) ||
            (data.prestamosPorTurno && data.prestamosPorTurno.length > 0);

        return hasChartData || hasSummaryData;
    }
    function actualizarTodaLaPagina(data) {

        miGraficoPrestamos.data.datasets[0].data = data.prestamos;
        miGraficoPrestamos.update();
        miGraficoPrestamosPresenciales.data.datasets[0].data = data.prestamosPresenciales;
        miGraficoPrestamosPresenciales.update();

        const infoUsuariosCarreraDiv = document.getElementById('infoUsuariosCarrera');


        const infoSeccionesDiv = document.getElementById('infoSeccionesPrestamos');
        let htmlSecciones = '<h5>Préstamos por Sección (Internos)</h5>';
        if (data.prestamosPorSeccion && data.prestamosPorSeccion.length > 0) {
            htmlSecciones += data.prestamosPorSeccion.map(item => `<b>${item.seccion}:</b> ${item.total}`).join('<br>');
        } else {
            htmlSecciones += '<i>Sin datos.</i>';
        }
        infoSeccionesDiv.innerHTML = htmlSecciones;

        const infoPresencialesDiv = document.getElementById('infoSeccionesPresenciales');
        let htmlPresenciales = '<h5>Préstamos por Sección (Externos)</h5>';
        if (data.prestamosPresencialesPorSeccion && data.prestamosPresencialesPorSeccion.length > 0) {
            htmlPresenciales += data.prestamosPresencialesPorSeccion.map(item => `<b>${item.seccion}:</b> ${item.total}`).join('<br>');
        } else {
            htmlPresenciales += '<i>Sin datos.</i>';
        }
        infoPresencialesDiv.innerHTML = htmlPresenciales;

        const infoCarrerasDiv = document.getElementById('infoCarreras');
        let htmlCarreras = '<h5>Préstamos por Carrera</h5>';
        if (data.prestamosPorCarrera && data.prestamosPorCarrera.length > 0) {
            htmlCarreras += data.prestamosPorCarrera.map(item => `<b>${item.carrera}:</b> ${item.total}`).join('<br>');
        } else {
            htmlCarreras += '<i>Sin datos.</i>';
        }
        infoCarrerasDiv.innerHTML = htmlCarreras;

        const infoTurnosDiv = document.getElementById('infoTurnos');
        let htmlTurnos = '<h5>Préstamos por Turno</h5>';
        if (data.prestamosPorTurno && data.prestamosPorTurno.length > 0) {
            htmlTurnos += data.prestamosPorTurno.map(item => `<b>${item.turno}:</b> ${item.total}`).join('<br>');
        } else {
            htmlTurnos += '<i>Sin datos.</i>';
        }
        infoTurnosDiv.innerHTML = htmlTurnos;

        // --- 3. Control del botón de Excel y mensaje de "no hay datos" ---
        if (hasData(data)) {
            btnExcel.style.display = 'inline-block';
            noDataMessageDiv.style.display = 'none';
        } else {
            btnExcel.style.display = 'none';
            noDataMessageDiv.style.display = 'block';
            noDataMessageDiv.textContent = 'No hay datos disponibles para los filtros seleccionados.';
        }
    }

    // --- Función para la carga inicial de las gráficas ---
    function cargarDatosIniciales() {
        console.log('Loading initial data...');

        const canvasPrestamos = document.getElementById('prestamos');
        const canvasPresenciales = document.getElementById('prestamosPresenciales');

        if (!canvasPrestamos || !canvasPresenciales) {
            console.log('Canvas not ready, retrying...');
            setTimeout(cargarDatosIniciales, 100);
            return;
        }

        if (typeof Chart === 'undefined') {
            console.log('Chart.js not loaded, retrying...');
            setTimeout(cargarDatosIniciales, 100);
            return;
        }

        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1,
                        callback: function (value) {
                            if (Number.isInteger(value)) {
                                return value;
                            }
                        }
                    }
                }
            },
            animation: {
                duration: 800,
                easing: 'easeOutQuart'
            }
        };

        setTimeout(() => {
            miGraficoPrestamos = new Chart(ctxPrestamos, {
                type: 'bar',
                data: {
                    labels: meses,
                    datasets: [{
                        label: 'PRÉSTAMOS REALIZADOS',
                        data: <?php echo json_encode($prestamos_por_mes); ?>,
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    }]
                },
                options: commonOptions
            });

            miGraficoPrestamosPresenciales = new Chart(ctxPrestamosPresenciales, {
                type: 'bar',
                data: {
                    labels: meses,
                    datasets: [{
                        label: 'PRÉSTAMOS PRESENCIALES',
                        data: <?php echo json_encode($prestamos_presenciales_por_mes); ?>,
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                },
                options: commonOptions
            });

            actualizarTodaLaPagina({
                prestamos: <?php echo json_encode($prestamos_por_mes); ?>,
                prestamosPresenciales: <?php echo json_encode($prestamos_presenciales_por_mes); ?>,
                prestamosPorSeccion: <?php echo json_encode($prestamosPorSeccionInicial); ?>,
                prestamosPresencialesPorSeccion: <?php echo json_encode($prestamosPresencialesPorSeccionInicial); ?>,
                prestamosPorCarrera: [],
                prestamosPorTurno: []
            });

            btnExcel.style.display = 'none';
            noDataMessageDiv.style.display = 'none';

            console.log('Charts loaded successfully');
        }, 50);
    }

document.getElementById('filterForm').addEventListener('submit', function (event) {
    event.preventDefault();
    var formData = new FormData(this);
    var fechaInicio = document.getElementById('fechaInicio').value;
    var fechaFin = document.getElementById('fechaFin').value;

    if (new Date(fechaInicio) > new Date(fechaFin)) {
        alert('La fecha de inicio no puede ser posterior a la fecha fin.');
        return;
    }

    var request = new XMLHttpRequest();
    request.open('POST', 'procesar_filtro.php');

    request.onload = function () {
        console.log('Status:', request.status); 
        console.log('Response:', request.responseText); 
        
        if (request.status === 200) {
            try {
                var data = JSON.parse(request.responseText);
                console.log('Parsed data:', data);
                
                actualizarTodaLaPagina(data);

                let params = new URLSearchParams(formData).toString();
                document.getElementById('btnExcel').href = 'exportar_excel.php?' + params;

            } catch (e) {
                console.error("Error al parsear JSON:", e);
                console.error("Response text:", request.responseText);
                noDataMessageDiv.textContent = 'Ocurrió un error al procesar la respuesta del servidor.';
                noDataMessageDiv.style.display = 'block';
            }
        } else {
            console.error("Error AJAX status:", request.status);
            noDataMessageDiv.textContent = 'Ocurrió un error al cargar los datos. Inténtalo de nuevo.';
            noDataMessageDiv.style.display = 'block';
        }
    };

    request.onerror = function () {
        console.error("Error de red");
        noDataMessageDiv.textContent = 'Error de conexión. Verifica tu red.';
        noDataMessageDiv.style.display = 'block';
    };

    request.send(formData);
});
    document.addEventListener('DOMContentLoaded', cargarDatosIniciales);
</script>
<?php
if (isset($db)) {
    mysqli_close($db);
}
if (isset($conn_sqlsrv)) {
    sqlsrv_close($conn_sqlsrv);
}
?>
<?php incluirTemplate('footer'); ?>