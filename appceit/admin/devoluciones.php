<?php
require '../includes/funciones.php';
setlocale(LC_TIME, 'es_MX.UTF-8', 'spanish');


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

// Initialize arrays for return data for the initial page load
$devoluciones_internas_por_mes = array_fill(0, 12, 0);
$devoluciones_presenciales_por_mes = array_fill(0, 12, 0);

// Initialize arrays for sectional data
$devolucionesInternasPorSeccionInicial = [];
$devolucionesPresencialesPorSeccionInicial = [];

// --- PHP Queries for INITIAL LOAD (Devoluciones Section) ---

// Consulta para DEVOLUCIONES INTERNAS (tabla 'prestamos' - fecha_devolucion)
$sqlDevolucionesInternasInicial = "SELECT DATE_FORMAT(fecha_devolucion, '%c') AS mes, COUNT(*) AS total_devoluciones
                                   FROM prestamos
                                   WHERE fecha_devolucion BETWEEN '{$fechaInicioDefault}' AND '{$fechaFinDefault} 23:59:59'
                                   AND fecha_devolucion IS NOT NULL 
                                   AND status = 2
                                   GROUP BY DATE_FORMAT(fecha_devolucion, '%m-%Y')
                                   ORDER BY DATE_FORMAT(fecha_devolucion, '%Y-%m') ASC";
$resultadoDevolucionesInternasInicial = mysqli_query($db, $sqlDevolucionesInternasInicial);
if (!$resultadoDevolucionesInternasInicial) {
    error_log("Error en consulta de devoluciones internas (inicial): " . mysqli_error($db));
}
while ($fila = mysqli_fetch_assoc($resultadoDevolucionesInternasInicial)) {
    $mes = intval($fila['mes']) - 1;
    if ($mes >= 0 && $mes < 12) {
        $devoluciones_internas_por_mes[$mes] = (int) $fila['total_devoluciones'];
    }
}

// Consulta para DEVOLUCIONES PRESENCIALES (tabla 'prestamospresencial' - fechaDevolucion)
$sqlDevolucionesPresencialesInicial = "SELECT DATE_FORMAT(fechaDevolucion, '%c') AS mes, COUNT(*) AS total_devoluciones_presenciales
                                       FROM prestamospresencial
                                       WHERE fechaDevolucion BETWEEN '{$fechaInicioDefault}' AND '{$fechaFinDefault} 23:59:59'
                                       AND fechaDevolucion IS NOT NULL
                                        AND estatus = 2
                                       GROUP BY DATE_FORMAT(fechaDevolucion, '%m-%Y')
                                       ORDER BY DATE_FORMAT(fechaDevolucion, '%Y-%m') ASC";
$resultadoDevolucionesPresencialesInicial = mysqli_query($db, $sqlDevolucionesPresencialesInicial);
if (!$resultadoDevolucionesPresencialesInicial) {
    error_log("Error en consulta de devoluciones presenciales (inicial): " . mysqli_error($db));
}
while ($fila = mysqli_fetch_assoc($resultadoDevolucionesPresencialesInicial)) {
    $mes = intval($fila['mes']) - 1;
    if ($mes >= 0 && $mes < 12) {
        $devoluciones_presenciales_por_mes[$mes] = (int) $fila['total_devoluciones_presenciales'];
    }
}

// Consultas para "Devoluciones por Sección" (tabla 'prestamos')
$sqlDevolucionesInternasPorSeccionInicial = "SELECT s.nombre_seccion AS seccion, COUNT(p.id) AS total
                                            FROM prestamos p
                                            JOIN libros l ON p.Libros_id = l.id
                                            JOIN secciones s ON l.seccionId = s.id
                                            WHERE p.fecha_devolucion BETWEEN '{$fechaInicioDefault}' AND '{$fechaFinDefault} 23:59:59'
                                            AND p.fecha_devolucion IS NOT NULL
                                            AND p.status = 2
                                            GROUP BY s.nombre_seccion";
$resultadoDevolucionesInternasPorSeccionInicial = mysqli_query($db, $sqlDevolucionesInternasPorSeccionInicial);
if (!$resultadoDevolucionesInternasPorSeccionInicial) {
    error_log("Error en consulta de devoluciones internas por sección (inicial): " . mysqli_error($db));
}
while ($fila = mysqli_fetch_assoc($resultadoDevolucionesInternasPorSeccionInicial)) {
    $devolucionesInternasPorSeccionInicial[] = $fila;
}

// Consulta para "Devoluciones Presenciales por Sección" (tabla 'prestamospresencial')
$sqlDevolucionesPresencialesPorSeccionInicial = "SELECT s.nombre_seccion AS seccion, COUNT(pp.id) AS total
                                                FROM prestamospresencial pp
                                                JOIN secciones s ON pp.seccionId = s.id
                                                WHERE pp.fechaDevolucion BETWEEN '{$fechaInicioDefault}' AND '{$fechaFinDefault} 23:59:59'
                                                AND pp.fechaDevolucion IS NOT NULL
                                                AND pp.estatus = 2
                                                GROUP BY s.nombre_seccion";
$resultadoDevolucionesPresencialesPorSeccionInicial = mysqli_query($db, $sqlDevolucionesPresencialesPorSeccionInicial);
if (!$resultadoDevolucionesPresencialesPorSeccionInicial) {
    error_log("Error en consulta de devoluciones presenciales por sección (inicial): " . mysqli_error($db));
}
while ($fila = mysqli_fetch_assoc($resultadoDevolucionesPresencialesPorSeccionInicial)) {
    $devolucionesPresencialesPorSeccionInicial[] = $fila;
}

mysqli_close($db);
?>
<style>

</style>

<?php incluirTemplate('sidebar'); // Assuming you have a sidebar template ?>

<link rel="stylesheet" href="../public/css/estadisticas.css">

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
                }; ?>
            </span>
            <span>Bienvenido, <?php echo ($nombreAdministrador); ?></span>
            <h2>Panel de estadísticas-devoluciones</h2>
        </div>
        <div class="user--info">
            <img src="../public/img/logouttn.png" alt="Foto de perfil" />
        </div>
    </div>
    <div class="filters">
        <form id="filterDevolucionesForm">
            <label for="fechaInicio">Fecha inicio:</label>
            <input type="date" name="fechaInicio" id="fechaInicio" max="<?php echo date('Y-m-d') ?>"
                value="<?php echo $fechaInicioDefault; ?>">

            <label for="fechaFin">Fecha fin:</label>
            <input type="date" max="<?php echo date('Y-m-d') ?>" name="fechaFin" id="fechaFin"
                value="<?php echo date('Y-m-d'); ?>">

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
                // Consulta para obtener los turnos desde SQL Server
                $sqlTurnosSQLSrv = "SELECT IdTurno, Nombre FROM [Tutorias].[dbo].[Turnoes] ORDER BY Nombre";
                $resultadoTurnos = sqlsrv_query($conn_sqlsrv, $sqlTurnosSQLSrv);

                if ($resultadoTurnos) {
                    // Itera sobre los resultados y crea las opciones del select
                    while ($filaTurno = sqlsrv_fetch_array($resultadoTurnos, SQLSRV_FETCH_ASSOC)) {
                        // Usamos htmlspecialchars para mayor seguridad
                        $idTurno = htmlspecialchars($filaTurno['IdTurno']);
                        $nombreTurno = htmlspecialchars($filaTurno['Nombre']);
                        echo "<option value='{$idTurno}'>{$nombreTurno}</option>";
                    }
                } else {
                    // Manejo de error si la consulta falla
                    echo "<option value=''>Error al cargar turnos</option>";
                    error_log("Error al consultar turnos de SQL Server: " . print_r(sqlsrv_errors(), true));
                }
                ?>
            </select>

            <button type="submit" style="margin-left: 20px;">Consultar</button>

        <button type="button" id="btnLimpiarFiltros" style="margin-left: 10px; background-color: #6c757d;">
            <i class="fas fa-eraser"></i> Limpiar
        </button>

        </form>

        <div id="noDataMessageDevoluciones" style="color: #dc3545; font-weight: bold; margin-top: 10px; display: none;">
        </div>
        <a id="btnExcelDevoluciones" href="#"
            style="display: none; background-color: #09a787; color: #fcfcfc; padding: 10px 10px; border: none; border-radius: 5px; cursor: pointer; font-size: 12px; transition: background-color .3s ease; margin-top: 10px;"
            class="button"><i class="fas fa-file-excel"></i> Exportar Excel</a>
    </div>

    <div
        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-top: 20px;">
        <div style="min-width: 400px; max-height: 350px; overflow: auto;">
            <canvas id="devolucionesInternas" height="300"></canvas>
        </div>
        <div style="min-width: 400px; max-height: 350px; overflow: auto;">
            <canvas id="devolucionesPresenciales" height="300"></canvas>
        </div>
    </div>

    <div class="info-summaries">
        <div class="summary-box" id="infoDevolucionesCarrera"></div>
        <div class="summary-box" id="infoDevolucionesTurno"></div>
        <div class="summary-box" id="infoSeccionesDevolucionesInternas"></div>
        <div class="summary-box" id="infoSeccionesDevolucionesPresenciales"></div>
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
    
    document.getElementById('filterDevolucionesForm').dispatchEvent(new Event('submit'));
}); 
        // --- Variables globales para las gráficas ---
        var ctxDevolucionesInternas = document.getElementById('devolucionesInternas').getContext('2d');
        var ctxDevolucionesPresenciales = document.getElementById('devolucionesPresenciales').getContext('2d');
        var miGraficoDevolucionesInternas, miGraficoDevolucionesPresenciales;

        // --- Variables globales de la página ---
        const meses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
        const noDataMessageDiv = document.getElementById('noDataMessageDevoluciones');
        const btnExcel = document.getElementById('btnExcelDevoluciones');

        // --- Función para verificar si hay datos para mostrar ---
        function hasData(data) {
            let total = 0;
            total += (data.devolucionesInternas || []).reduce((sum, current) => sum + current, 0);
            total += (data.devolucionesPresenciales || []).reduce((sum, current) => sum + current, 0);
            total += (data.devolucionesPorCarrera || []).length;
            total += (data.devolucionesPorTurno || []).length;
            total += (data.devolucionesInternasPorSeccion || []).length;
            total += (data.devolucionesPresencialesPorSeccion || []).length;
            return total > 0;
        }

        // --- Función principal para actualizar toda la información ---
        function actualizarPagina(data) {
            // --- 1. Actualización de las gráficas ---
            miGraficoDevolucionesInternas.data.datasets[0].data = data.devolucionesInternas;
            miGraficoDevolucionesInternas.update();

            miGraficoDevolucionesPresenciales.data.datasets[0].data = data.devolucionesPresenciales;
            miGraficoDevolucionesPresenciales.update();

            // --- 2. Actualización de los resúmenes de texto ---
            const infoCarrerasDiv = document.getElementById('infoDevolucionesCarrera');
            let htmlCarreras = '<h5>Devoluciones por Carrera (Internos)</h5>';
            if (data.devolucionesPorCarrera && data.devolucionesPorCarrera.length > 0) {
                htmlCarreras += data.devolucionesPorCarrera.map(item => `<b>${item.carrera}:</b> ${item.total}`).join('<br>');
            } else {
                htmlCarreras += '<i>Sin datos.</i>';
            }
            infoCarrerasDiv.innerHTML = htmlCarreras;

            const infoTurnosDiv = document.getElementById('infoDevolucionesTurno');
            let htmlTurnos = '<h5>Devoluciones por Turno (Internos)</h5>';
            if (data.devolucionesPorTurno && data.devolucionesPorTurno.length > 0) {
                htmlTurnos += data.devolucionesPorTurno.map(item => `<b>${item.turno}:</b> ${item.total}`).join('<br>');
            } else {
                htmlTurnos += '<i>Sin datos.</i>';
            }
            infoTurnosDiv.innerHTML = htmlTurnos;

            const infoSeccionesInternasDiv = document.getElementById('infoSeccionesDevolucionesInternas');
            let htmlSeccionesInternas = '<h5>Devoluciones por Sección (Internos)</h5>';
            if (data.devolucionesInternasPorSeccion && data.devolucionesInternasPorSeccion.length > 0) {
                htmlSeccionesInternas += data.devolucionesInternasPorSeccion.map(item => `<b>${item.seccion}:</b> ${item.total}`).join('<br>');
            } else {
                htmlSeccionesInternas += '<i>Sin datos.</i>';
            }
            infoSeccionesInternasDiv.innerHTML = htmlSeccionesInternas;

            const infoSeccionesPresencialesDiv = document.getElementById('infoSeccionesDevolucionesPresenciales');
            let htmlSeccionesPresenciales = '<h5>Devoluciones por Sección (Externos)</h5>';
            if (data.devolucionesPresencialesPorSeccion && data.devolucionesPresencialesPorSeccion.length > 0) {
                htmlSeccionesPresenciales += data.devolucionesPresencialesPorSeccion.map(item => `<b>${item.seccion}:</b> ${item.total}`).join('<br>');
            } else {
                htmlSeccionesPresenciales += '<i>Sin datos.</i>';
            }
            infoSeccionesPresencialesDiv.innerHTML = htmlSeccionesPresenciales;


            // --- 3. Control del botón de Excel y mensaje de "no hay datos" ---
            if (hasData(data)) {
                btnExcel.style.display = 'inline-block';
                noDataMessageDiv.style.display = 'none';
            } else {
                btnExcel.style.display = 'none';
                noDataMessageDiv.style.display = 'block';
            }
        }

        // --- Función para la carga inicial de las gráficas ---
        function cargarDatosIniciales() {
            const commonOptions = {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            };

            miGraficoDevolucionesInternas = new Chart(ctxDevolucionesInternas, {
                type: 'bar',
                data: {
                    labels: meses,
                    datasets: [{
                        label: 'DEVOLUCIONES USUARIOS INTERNOS',
                        data: <?php echo json_encode($devoluciones_internas_por_mes); ?>,
                        backgroundColor: 'rgba(73, 193, 233, 0.2)',
                        borderColor: 'rgba(153, 102, 255, 1)',
                        borderWidth: 1
                    }]
                },
                options: commonOptions
            });

            miGraficoDevolucionesPresenciales = new Chart(ctxDevolucionesPresenciales, {
                type: 'bar',
                data: {
                    labels: meses,
                    datasets: [{
                        label: 'DEVOLUCIONES USUARIOS EXTERNOS',
                        data: <?php echo json_encode($devoluciones_presenciales_por_mes); ?>,
                        backgroundColor: 'rgba(166, 255, 64, 0.2)',
                        borderColor: 'rgba(111, 251, 46, 1)',
                        borderWidth: 1
                    }]
                },
                options: commonOptions
            });

            // Cargar los datos iniciales en los resúmenes de texto
            actualizarPagina({
                devolucionesInternas: <?php echo json_encode($devoluciones_internas_por_mes); ?>,
                devolucionesPresenciales: <?php echo json_encode($devoluciones_presenciales_por_mes); ?>,
                devolucionesInternasPorSeccion: <?php echo json_encode($devolucionesInternasPorSeccionInicial); ?>,
                devolucionesPresencialesPorSeccion: <?php echo json_encode($devolucionesPresencialesPorSeccionInicial); ?>,
                devolucionesPorCarrera: [],
                devolucionesPorTurno: []
            });

            btnExcel.style.display = 'none';
            noDataMessageDiv.style.display = 'none';
        }

        // --- Event Listener para el formulario ---
        document.getElementById('filterDevolucionesForm').addEventListener('submit', function (event) {
            event.preventDefault();
            var formData = new FormData(this);
            var request = new XMLHttpRequest();
            request.open('POST', 'procesar_devoluciones_filtro.php');

            request.onload = function () {
                if (request.status === 200) {
                    try {
                        var data = JSON.parse(request.responseText);
                        actualizarPagina(data);

                        let params = new URLSearchParams(formData).toString();
                        btnExcel.href = 'exportar_excel_devoluciones.php?' + params;

                    } catch (e) {
                        console.error("Error al procesar la respuesta JSON:", e, request.responseText);
                        noDataMessageDiv.textContent = 'Ocurrió un error al procesar la respuesta del servidor.';
                        noDataMessageDiv.style.display = 'block';
                    }
                } else {
                    console.error("Error en la solicitud AJAX: " + request.status);
                    noDataMessageDiv.textContent = 'Ocurrió un error al cargar los datos. Inténtalo de nuevo.';
                    noDataMessageDiv.style.display = 'block';
                }
            };

            request.onerror = function () {
                console.error("Error de red.");
                noDataMessageDiv.textContent = 'Error de conexión. Verifica tu red.';
                noDataMessageDiv.style.display = 'block';
            };

            request.send(formData);
        });

        // --- Carga inicial de la página ---
        document.addEventListener('DOMContentLoaded', cargarDatosIniciales);
    </script>
    <?php incluirTemplate('footer'); // Assuming you have a footer template ?>