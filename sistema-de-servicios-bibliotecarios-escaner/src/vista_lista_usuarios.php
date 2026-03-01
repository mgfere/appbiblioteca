`<?php
    require_once '../database/Database.php';
    require_once '../database/DatabaseAPI.php';

    session_start();

    //RECUPERA EL USUARIO DE LA BASE DE DATOS
    if (!isset($_SESSION['user'])) {
         header("Location: login_admin.php");
         exit();
    }
    


    function getNextDirection($column, $currentSort, $currentDir)
    {
        $sortableColumns = ['matricula', 'nombre', 'tipo', 'carrera'];

        if (!in_array($column, $sortableColumns)) return 'asc';
        if ($currentSort !== $column) return 'asc';
        if ($currentDir === 'asc') return 'desc';
        if ($currentDir === 'desc') return 'reset';
        return 'asc';
    }


    $startTime = isset($_REQUEST['start_time']) ? date('Y-m-d', strtotime($_REQUEST['start_time'])) : null;
    $endTime = isset($_REQUEST['end_time']) ? date('Y-m-d', strtotime($_REQUEST['end_time'])) : null;
    $searchTerm = isset($_REQUEST['search_term']) ? $_REQUEST['search_term'] : null;


    try {
        $db = new DatabaseAPI();
        $sortColumn = isset($_GET['sort']) ? $_GET['sort'] : null;
        $sortDirection = isset($_GET['dir']) ? $_GET['dir'] : 'asc';

        $result = $db->mostrarListaDeUsuarios();



        if ($sortColumn !== null && isset($result[0][$sortColumn])) {
            usort($result, function ($a, $b) use ($sortColumn, $sortDirection) {
                $valA = $a[$sortColumn];
                $valB = $b[$sortColumn];
                return $sortDirection === 'asc' ? strcoll($valA, $valB) : strcoll($valB, $valA);
            });
        }    // PAGINACIÓN CORREGIDA
        $itemsPorPagina = 10;
        $totalRegistros = count($result);
        $totalPaginas = ceil($totalRegistros / $itemsPorPagina);
        $paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
        $paginaActual = max(1, min($totalPaginas, $paginaActual)); // por seguridad
        $inicio = ($paginaActual - 1) * $itemsPorPagina;
        $datosPaginados = array_slice($result, $inicio, $itemsPorPagina);
    } catch (PDOException $e) {
        die("Error en la consulta de servicios: " . $e->getMessage());
    }

    ?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vista de Administrador</title>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="stylesheet" href="../output.css">
    <script src="https://unpkg.com/xlsx@0.16.9/dist/xlsx.full.min.js"></script>
    <script src="https://unpkg.com/file-saverjs@latest/FileSaver.min.js"></script>
    <script src="https://unpkg.com/tableexport@latest/dist/js/tableexport.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <link rel="icon" href="../img/favicon.ico" type="image/x-icon">

</head>

<body>
    <main>
        <header>
            <div class="header-bar">
                <div class="flex justify-center">
                    <a href="./index_admin.php"><img src="../img/Image.jpeg" alt="Logo" id="logo"></a>
                </div>
            </div>
        </header>
  <div class="container mx-auto py-8">
    <br><br>
    <h1 class="text-2xl font-semibold text-center mb-4">Importar Información desde CSV</h1>
    <div class="flex justify-center">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 mt-5">
            <!-- Formulario: Alumnos -->
            <div class="bg-white p-4 shadow-lg rounded-lg">
            <a href="../archivoCSV/alumnosTemplate.csv" download class="flex justify-center">
                <button style="padding: 10px 20px; background-color: #09a787; color: white; border: none; cursor: pointer;">
                Descargar Plantilla Alumno
                </button>
            </a>
            <br>
                <h2 class="text-lg font-bold text-[#09a787] mb-3 text-center">Importar Alumnos</h2>
                <form enctype="multipart/form-data" method="POST" action="alumnosInsertarPorCsv.php" id="formularioAlumnos" class="flex flex-col items-center gap-4">
                    <input type="hidden" name="tipo" value="alumnos">
                    <input type="file" id="archivoAlumnos" name="archivo" accept=".csv" class="bg-white rounded px-3 py-2 border border-gray-300 w-full" required>
                    <button type="submit" class="w-full text-white font-bold py-2 rounded mt-2"
                        style="background-color: #09a787; transition: transform 0.2s ease-in-out;"
                        onmouseover="this.style.transform='scale(1.01)'"
                        onmouseout="this.style.transform='scale(1)'">
                        Subir
                    </button>
                    <div id="mensajeResultado" class="text-center mt-2 hidden"></div>
                </form>
            </div>

            <!-- Formulario: Profesores -->
            <div class="bg-white p-4 shadow-lg rounded-lg">
                <a href="../archivoCSV/profesoresTemplate.csv" download class="flex justify-center">
                <button style="padding: 10px 20px; background-color: #09a787; color: white; border: none; cursor: pointer;">
                Descargar Plantilla Profesor
                </button>
                </a>
                <br>
                <h2 class="text-lg font-bold text-[#09a787] mb-3 text-center">Importar Profesores</h2>
                <form enctype="multipart/form-data" method="POST" action="profesoresImportarPorCsv.php" id="formularioProfesores" class="flex flex-col items-center gap-4">
                    <input type="hidden" name="tipo" value="profesores">
                    <input type="file" name="archivo" accept=".csv" class="bg-white rounded px-3 py-2 border border-gray-300 w-full" required>
                    <button type="submit" class="w-full text-white font-bold py-2 rounded mt-2"
                        style="background-color: #09a787; transition: transform 0.2s ease-in-out;"
                        onmouseover="this.style.transform='scale(1.01)'"
                        onmouseout="this.style.transform='scale(1)'">
                        Subir
                    </button>
                    <div id="mensajeResultadoProfesores" class="text-center mt-2 hidden"></div>
                </form>
            </div>
        </div>
    </div>
</div>

            <br><br><br>
            <?php if (!empty($result)) : ?>
                <div class="bg-white mt-8 shadow-lg rounded-lg overflow-x-auto">
                    <table id="tabla" class="min-w-full">
                        <thead>
                            <tr class="bg-gray-800 text-white">

                                <!-- MATRÍCULA ordenable -->
                                <th class="py-3 px-4 text-center" data-title="Matrícula">
                                    <?php
                                    $nextDir = getNextDirection('matricula', $sortColumn, $sortDirection);
                                    $resetUrl = "vista_admin.php";

                                    // Construir parámetros del filtro actuales
                                    $params = [
                                        'start_time' => $startTime,
                                        'end_time' => $endTime,
                                        'search_term' => $searchTerm
                                    ];

                                    // Si es reset, ir a la vista sin ordenamiento pero con filtros
                                    if ($nextDir === 'reset') {
                                        $resetQuery = http_build_query($params);
                                        echo '<a href="?' . $resetQuery . '" style="color: white; text-decoration: none;">Matrícula↓</a>';
                                    } else {
                                        $queryString = http_build_query(array_merge($params, ['sort' => 'matricula', 'dir' => $nextDir]));
                                        echo '<a href="?' . $queryString . '" style="color: white; text-decoration: none;">';
                                        echo 'Matrícula' . ($sortColumn === 'matricula' ? ($sortDirection === 'asc' ? '↑' : '↓') : '');
                                        echo '</a>';
                                    }
                                    ?>
                                </th>

                                <!-- NOMBRE ordenable -->
                                <th class="py-3 px-4 text-center" data-title="Nombre">
                                    <?php
                                    $nextDir = getNextDirection('nombre', $sortColumn, $sortDirection);
                                    $params = [
                                        'start_time' => $startTime,
                                        'end_time' => $endTime,
                                        'search_term' => $searchTerm
                                    ];
                                    ?>
                                    <?php if ($nextDir === 'reset'): ?>
                                        <a href="?<?= http_build_query($params) ?>" style="color: white; text-decoration: none;">Nombre↓</a>
                                    <?php else: ?>
                                        <a href="?<?= http_build_query(array_merge($params, ['sort' => 'nombre', 'dir' => $nextDir])) ?>" style="color: white; text-decoration: none;">
                                            Nombre<?= $sortColumn === 'nombre' ? ($sortDirection === 'asc' ? '↑' : '↓') : '' ?>
                                        </a>
                                    <?php endif; ?>
                                </th>

                                <th class="py-3 px-4 text-center" data-title="Tipo">
                                    <?php
                                    $nextDir = getNextDirection('tipo', $sortColumn, $sortDirection);
                                    ?>
                                    <?php if ($nextDir === 'reset'): ?>
                                        <a href="?<?= http_build_query($params) ?>" style="color: white; text-decoration: none;">Tipo↓</a>
                                    <?php else: ?>
                                        <a href="?<?= http_build_query(array_merge($params, ['sort' => 'tipo', 'dir' => $nextDir])) ?>" style="color: white; text-decoration: none;">
                                            Tipo<?= $sortColumn === 'tipo' ? ($sortDirection === 'asc' ? '↑' : '↓') : '' ?>
                                        </a>
                                    <?php endif; ?>
                                </th>

                                <!-- CARRERA ordenable -->
                                <th class="py-3 px-4 text-center" data-title="Carrera">
                                    <?php
                                    $nextDir = getNextDirection('carrera', $sortColumn, $sortDirection);
                                    ?>
                                    <?php if ($nextDir === 'reset'): ?>
                                        <a href="?<?= http_build_query($params) ?>" style="color: white; text-decoration: none;">Carrera↓</a>
                                    <?php else: ?>
                                        <a href="?<?= http_build_query(array_merge($params, ['sort' => 'carrera', 'dir' => $nextDir])) ?>" style="color: white; text-decoration: none;">
                                            Carrera<?= $sortColumn === 'carrera' ? ($sortDirection === 'asc' ? '↑' : '↓') : '' ?>
                                        </a>
                                    <?php endif; ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($datosPaginados as $row) { ?>
                                <tr class="text-gray-700">
                                    <td class="py-2 px-4 text-center"><?= $row['matricula']; ?></td>
                                    <td class="py-2 px-4 text-center"><?= $row['nombre']; ?></td>
                                    <td class="py-2 px-4 text-center"><?= $row['tipo'] === 'Alumno' ? 'Alumno' : 'Maestro'; ?></td>
                                    <td class="py-2 px-4 text-center"><?= $row['carrera'] ?? 'N/A'; ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php
            // Paginación con botón [1] y "..." si se esta al final
            $botonesVisibles = 5;
            $mitad = floor($botonesVisibles / 2);

            $inicio = max(1, $paginaActual - $mitad);
            $fin = $inicio + $botonesVisibles - 1;

            if ($fin >= $totalPaginas) {
                $fin = $totalPaginas;
                $inicio = max(1, $fin - $botonesVisibles + 1);
            }

            $sortColumn = $_GET['sort'] ?? null;
            $sortDirection = $_GET['dir'] ?? null;

            // Esto se usará para añadir a cada link de paginación
            $params = [
                'start_time' => $startTime,
                'end_time' => $endTime,
                'search_term' => $searchTerm,
                'sort' => $sortColumn,
                'dir' => $sortDirection
            ];
            $queryExtras = http_build_query(array_filter($params)); // elimina nulos
            ?>

            <!-- paginacion -->
            <?php if ($totalPaginas > 1): ?>
                <div class="flex justify-center mt-8 mb-8">
                    <nav style="display: flex; gap: 6px;" aria-label="Paginación">

                        <!-- Botón ANTERIOR -->
                        <?php if ($paginaActual > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($params, ['pagina' => $paginaActual - 1])); ?>"
                                style="padding: 6px 12px; background-color: #09a787; color: white; font-weight: bold; border-radius: 6px; text-decoration: none;">
                                «
                            </a>
                        <?php else: ?>
                            <span style="padding: 6px 12px; background-color: #e0e0e0; color: #999; font-weight: bold; border-radius: 6px;">
                                «
                            </span>
                        <?php endif; ?>

                        <!-- Mostrar [1] al final si se esta muy lejos del principio -->
                        <?php if ($inicio > 2): ?>
                            <a href="?<?php echo http_build_query(array_merge($params, ['pagina' => 1])); ?>"
                                style="padding: 6px 12px; border-radius: 6px; font-weight: bold; text-decoration: none;
                    <?php echo ($paginaActual == 1)
                                ? 'background-color: #09a787; color: white; border: 1px solid #09a787;'
                                : 'background-color: white; color: #333; border: 1px solid #ccc;'; ?>">
                                1
                            </a>
                            <span style="padding: 6px 12px; color: #999;">...</span>
                        <?php endif; ?>

                        <!-- Botones numerados dinámicos -->
                        <?php for ($i = $inicio; $i <= $fin; $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($params, ['pagina' => $i])); ?>"
                                style="padding: 6px 12px; border-radius: 6px; font-weight: bold; text-decoration: none;
                    <?php echo ($i == $paginaActual)
                                ? 'background-color: #09a787; color: white; border: 1px solid #09a787;'
                                : 'background-color: white; color: #333; border: 1px solid #ccc;'; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <!-- Mostrar "..." si faltan páginas después -->
                        <?php if ($fin < $totalPaginas - 1): ?>
                            <span style="padding: 6px 12px; color: #999;">...</span>
                        <?php endif; ?>

                        <!-- Última página -->
                        <?php if ($fin < $totalPaginas): ?>
                            <a href="?<?php echo http_build_query(array_merge($params, ['pagina' => $totalPaginas])); ?>"
                                style="padding: 6px 12px; border-radius: 6px; font-weight: bold; text-decoration: none;
                        <?php echo ($paginaActual == $totalPaginas)
                                ? 'background-color: #09a787; color: white; border: 1px solid #09a787;'
                                : 'background-color: white; color: #333; border: 1px solid #ccc;'; ?>">
                                <?php echo $totalPaginas; ?>
                            </a>
                        <?php endif; ?>


                        <!-- Botón SIGUIENTE -->
                        <?php if ($paginaActual < $totalPaginas): ?>
                            <a href="?<?php echo http_build_query(array_merge($params, ['pagina' => $paginaActual + 1])); ?>"
                                style="padding: 6px 12px; background-color: #09a787; color: white; font-weight: bold; border-radius: 6px; text-decoration: none;">
                                »
                            </a>
                        <?php else: ?>
                            <span style="padding: 6px 12px; background-color: #e0e0e0; color: #999; font-weight: bold; border-radius: 6px;">
                                »
                            </span>
                        <?php endif; ?>

                    </nav>
                </div>
            <?php endif; ?>
        </div>

    </main>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/chartjs-plugin-datalabels/2.2.0/chartjs-plugin-datalabels.min.js" integrity="sha512-JPcRR8yFa8mmCsfrw4TNte1ZvF1e3+1SdGMslZvmrzDYxS69J7J49vkFL8u6u8PlPJK+H3voElBtUCzaXj+6ig==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <?php
    include 'footer.php';
    ?>
    <script>
        var inputMatricula = document.querySelector('#buscador');
        inputMatricula.addEventListener('input', borrarMatricula);

        function borrarMatricula() {
            // Verificar si el contenido está vacío
            if (inputMatricula.value === '') {
                // Envía el formulario de búsqueda
                document.getElementById("buscador").value = "";
                val('');
            }
        }

        const $btnExportar = document.querySelector("#btnExportar"),
            $tabla = document.querySelector("#tabla");

        $btnExportar.addEventListener("click", function() {
            const datos = [];
            const headers = [];
            const bodyRows = [];

            // Tomar los headers de la tabla
            const $headers = $tabla.querySelectorAll("thead th");
            $headers.forEach((th) => headers.push(th.dataset.title));

            // Tomar las filas de las tablas
            const resultData = <?php echo json_encode($result); ?>;
            resultData.forEach(row => {
                bodyRows.push([
                    row['matricula'],
                    row['nombre'],
                    row['tipo'],
                    row['carrera']
                ]);
            });

            // Agregar los datos a Excel
            const groupedData = <?php echo json_encode($groupedData); ?>;
            Object.entries(groupedData).forEach(([servicio, cantidad]) => {
                bodyRows.push([servicio, cantidad]);
            });

            // Combinar los headers y filas
            datos.push(headers);
            datos.push(...bodyRows);

            // Exportar en Excel
            const worksheet = XLSX.utils.aoa_to_sheet(datos);
            const workbook = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(workbook, worksheet, "Reporte de registros");
            const wbout = XLSX.write(workbook, {
                bookType: "xlsx",
                type: "array"
            });
            saveAs(new Blob([wbout], {
                type: "application/octet-stream"
            }), "Reporte_de_registros.xlsx");
        });

        document.querySelector("#btnExportarPDF").addEventListener("click", function() {
            const {
                jsPDF
            } = window.jspdf;
            const doc = new jsPDF();

            const img = new Image();
            img.src = '../img/UTTN_princ.png';

            img.onload = function() {
                doc.addImage(img, 'PNG', 10, 10, 40, 20); // Logo

                const headers = [];
                const bodyRows = [];

                const $headers = document.querySelectorAll("#tabla thead th");
                $headers.forEach((th) => headers.push(th.dataset.title));

                const resultData = <?php echo json_encode($result); ?>;
                resultData.forEach(row => {
                    bodyRows.push([
                        row['matricula'],
                        row['nombre'],
                        row['tipo'],
                        row['nombre_especialidad'],
                        row['nombre_servicio'],
                        new Date(row['hora_entrada']).toLocaleString(),
                        new Date(row['hora_salida']).toLocaleString()
                    ]);
                });

                // Mostrar la fecha en la esquina superior derecha
                const fecha = new Date();
                const meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                    'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'
                ];
                const fechaTexto = `Reynosa, Tamaulipas a ${fecha.getDate()} de ${meses[fecha.getMonth()]} de ${fecha.getFullYear()}`;

                // Tamaño de fuente y posición (ajusta X si deseas moverla más al centro)
                doc.setFontSize(10);
                doc.text(fechaTexto, 125, 17); // x, y → 150 = cerca de la esquina derecha


                const groupedData = <?php echo json_encode($groupedData); ?>;
                const carrerasGroupedData = <?php echo json_encode($carrerasGroupedData); ?>;

                let currentY = 50;

                // Título y tabla principal
                doc.setFontSize(14);
                doc.text("Registros de Entrada y Salida:", 14, currentY);

                doc.autoTable({
                    head: [headers],
                    body: bodyRows,
                    startY: currentY + 5,
                    styles: {
                        fontSize: 10
                    },
                    headStyles: {
                        fillColor: [0, 0, 0],
                        textColor: [255, 255, 255]
                    },
                });

                // Aumentar Y tras la tabla principal
                currentY = doc.lastAutoTable.finalY + 15;

                // Título y tabla de servicios
                doc.setFontSize(14);
                doc.text("Servicios Utilizados:", 14, currentY);

                const serviciosTable = [];
                Object.entries(groupedData).forEach(([servicio, cantidad]) => {
                    serviciosTable.push([servicio, cantidad]);
                });

                doc.autoTable({
                    head: [
                        ['Servicio', 'Cantidad']
                    ],
                    body: serviciosTable,
                    startY: currentY + 5,
                    styles: {
                        fontSize: 10
                    },
                    headStyles: {
                        fillColor: [0, 0, 0],
                        textColor: [255, 255, 255]
                    },
                });

                // Aumentar Y tras tabla de servicios
                currentY = doc.lastAutoTable.finalY + 15;

                // Título y tabla de carreras
                doc.setFontSize(14);
                doc.text("Carreras en Registros:", 14, currentY);

                const carrerasTable = [];
                Object.entries(carrerasGroupedData).forEach(([carrera, cantidad]) => {
                    carrerasTable.push([carrera, cantidad]);
                });

                doc.autoTable({
                    head: [
                        ['Carrera', 'Cantidad']
                    ],
                    body: carrerasTable,
                    startY: currentY + 5,
                    styles: {
                        fontSize: 10
                    },
                    headStyles: {
                        fillColor: [0, 0, 0],
                        textColor: [255, 255, 255]
                    },
                });

                doc.save("Reporte_de_registros.pdf");
            };
        });


        document.addEventListener('DOMContentLoaded', function() {
            //Sleccionar los elementos

            function mostrarAlerta(mensaje, referencia) {
                limpiarAlerta(referencia);
                const error = document.createElement('P');
                error.textContent = mensaje;
                error.classList.add('bg-white', 'text-red-500', 'p-2', 'text-center');
                referencia.appendChild(error);
            }

            function limpiarAlerta(referencia) {
                const alerta = referencia.querySelector('.bg-red-600');
                if (alerta) {
                    alerta.remove();
                }
                console.log('desde limpiar alerta');
            }
        });

        // Obtener los datos agrupados para la gráfica de barras
        const serviciosData = <?php echo json_encode($groupedData); ?>;
        const serviciosLabels = Object.keys(serviciosData);
        const serviciosValues = Object.values(serviciosData);

        // Crear un elemento canvas para la gráfica de barras
        const canvas = document.createElement('canvas');
        canvas.id = 'serviciosChart';
        canvas.width = 400;
        canvas.height = 400;
        const chartContainer = document.querySelector('.chart-container'); // Asegúrate de que este selector sea correcto
        chartContainer.appendChild(canvas);

        // Crear la gráfica de barras
        new Chart(canvas, {
            type: 'bar',
            data: {
                labels: serviciosLabels,
                datasets: [{
                    label: 'Servicios',
                    data: serviciosValues,
                    backgroundColor: [
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(63, 215, 125, 0.92)',
                        'rgba(128, 0, 0, 0.7)',
                        'rgba(0, 128, 0, 0.7)',
                        'rgba(0, 0, 128, 0.7)',
                        'rgba(255, 165, 0, 0.8)',
                        'rgba(0, 255, 255, 0.8)',
                        'rgba(255, 0, 255, 0.8)',
                        'rgba(128, 128, 0, 0.9)',
                        'rgba(0, 128, 128, 0.9)',
                        'rgba(128, 0, 128, 0.9)',
                        'rgba(0, 0, 0, 0.6)',
                        'rgba(255, 0, 0, 0.8)'
                    ]
                }],
            },
            options: {
                plugins: {
                    legend: {
                        labels: {
                            // Set labels to an empty array to hide the legend
                            display: false,
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Cantidad de Alumnos',
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Servicios',
                        }
                    }
                }
            },
            datasets: [{
                data: [], // Set an empty array to remove the dataset
            }],
        });


        // Crear un elemento canvas para la gráfica de pastel de carreras
        const carrerasCanvas = document.createElement('canvas');
        carrerasCanvas.id = 'carrerasChart';
        carrerasCanvas.width = 400;
        carrerasCanvas.height = 400;
        const carrerasChartContainer = document.querySelector('.carreras-chart-container'); // Agrega una clase a un contenedor adecuado en tu HTML
        carrerasChartContainer.appendChild(carrerasCanvas);

        // Obtener los datos agrupados para la gráfica de pastel de carreras
        const carrerasData = <?php echo json_encode($carrerasGroupedData); ?>;
        const carrerasLabels = Object.keys(carrerasData);
        const carrerasValues = Object.values(carrerasData);

        // Crear la gráfica de pastel de carreras
        new Chart(carrerasCanvas, {
            type: 'pie',
            data: {
                labels: carrerasLabels,
                datasets: [{
                    label: 'Cantidad de Carreras Utilizadas',
                    data: carrerasValues,
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.8)',
                        'rgba(54, 162, 235, 0.8)',
                        'rgba(255, 206, 86, 0.8)',
                        'rgba(63, 215, 125, 0.92)',
                        'rgba(128, 0, 0, 0.7)',
                        'rgba(0, 128, 0, 0.7)',
                        'rgba(0, 0, 128, 0.7)',
                        'rgba(255, 165, 0, 0.8)',
                        'rgba(0, 255, 255, 0.8)',
                        'rgba(255, 0, 255, 0.8)',
                        'rgba(128, 128, 0, 0.9)',
                        'rgba(0, 128, 128, 0.9)',
                        'rgba(128, 0, 128, 0.9)',
                        'rgba(0, 0, 0, 0.6)',
                        'rgba(255, 0, 0, 0.8)',
                        'rgba(0, 255, 0, 0.8)',
                        'rgba(0, 0, 255, 0.8)',
                        'rgba(255, 255, 0, 0.9)',
                        'rgba(255, 0, 255, 0.7)',
                        'rgba(0, 255, 255, 0.7)',
                        'rgba(128, 0, 0, 0.6)',
                        'rgba(0, 128, 0, 0.6)',
                        'rgba(0, 0, 128, 0.6)',
                        'rgba(255, 165, 0, 0.7)',
                        'rgba(0, 255, 255, 0.6)',
                        'rgba(255, 0, 255, 0.6)',
                        'rgba(128, 128, 0, 0.8)',
                        'rgba(0, 128, 128, 0.8)',
                        'rgba(128, 0, 128, 0.8)'
                    ],
                }],
            },
            plugins: [ChartDataLabels], // Include the plugin
            options: {
                plugins: {
                    datalabels: {
                        formatter: (value, context) => {
                            let sum = context.dataset.data.reduce((a, b) => a + b, 0);
                            let percentage = (value * 100 / sum).toFixed(2);
                            return percentage + '%';
                        },
                        color: '#fff', // Label color
                    },
                },
            },
        });

        // Funciones modalArchivo
        function abrirModalArchivo() {
            document.getElementById('modalArchivo').style.display = 'flex';
        }

        function cerrarModalArchivo() {
            document.getElementById('modalArchivo').style.display = 'none';
        }
    </script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.29/jspdf.plugin.autotable.min.js"></script>


    <script>
        const startInput = document.getElementById('start_time');
        const endInput = document.getElementById('end_time');

        function syncEndDateLimit() {
            if (startInput.value) {
                endInput.min = startInput.value;
            } else {
                endInput.removeAttribute('min');
            }
        }

        startInput.addEventListener('change', syncEndDateLimit);
        window.addEventListener('load', syncEndDateLimit); // aplica si ya hay valor al cargar
    </script>

    <script>
        document.getElementById('formularioAlumnos').addEventListener('submit', function(e) {
            e.preventDefault();

            const form = this;
            const formData = new FormData(form);
            const mensajeResultado = document.getElementById('mensajeResultado');
            const botonSubmit = form.querySelector('button[type="submit"]');

            // Resetear mensajes y mostrar loading
            mensajeResultado.classList.add('hidden');
            mensajeResultado.innerHTML = '';
            botonSubmit.disabled = true;
            botonSubmit.innerHTML = 'Procesando...';

            fetch(form.action, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        mensajeResultado.innerHTML = `<div class="text-red-500">${data.error}</div>`;
                    } else {
                        mensajeResultado.innerHTML = `<div class="text-green-500">${data.message}</div>`;
                        form.reset();
                    }
                    mensajeResultado.classList.remove('hidden');
                })
                .catch(error => {
                    mensajeResultado.innerHTML = `<div class="text-red-500">Error al procesar la solicitud</div>`;
                    mensajeResultado.classList.remove('hidden');
                })
                .finally(() => {
                    botonSubmit.disabled = false;
                    botonSubmit.innerHTML = 'Subir';
                });
        });


</script>        
<script>
  document.getElementById('formularioProfesores').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const form = this;
    const formData = new FormData(form);
    const mensajeResultado = document.getElementById('mensajeResultadoProfesores');
    const botonSubmit = form.querySelector('button[type="submit"]');
    
    // Limpiar resultados anteriores
    mensajeResultado.innerHTML = '';
    mensajeResultado.classList.add('hidden', 'space-y-2');
    
    // Mostrar estado de carga
    botonSubmit.disabled = true;
    botonSubmit.innerHTML = `
        <span class="inline-block animate-spin"></span> Procesando...
    `;
    
    try {
        const response = await fetch(form.action, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Error en el servidor');
        }
        
        if (data.success) {
            let html = `
                <div class="p-4 bg-green-100 text-green-800 rounded-lg">
                    <strong>${data.message}</strong>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <span>Total registros: ${data.total}</span>
                        <span>Insertados: ${data.inserted}</span>
                        <span>Omitidos: ${data.skipped}</span>
                        <span>Errores: ${data.errors}</span>
                    </div>
                </div>
            `;
            
            if (data.errors > 0 && data.error_details) {
                html += `
                    <div class="p-4 bg-yellow-50 text-yellow-800 rounded-lg">
                        <details>
                            <summary class="font-bold cursor-pointer">Ver detalles de errores (${data.errors})</summary>
                            <div class="mt-2 max-h-40 overflow-y-auto text-sm">
                                ${data.error_details.map(e => `<div class="py-1 border-b border-yellow-200">• ${e}</div>`).join('')}
                            </div>
                        </details>
                    </div>
                `;
            }
            
            mensajeResultado.innerHTML = html;
        } else {
            throw new Error(data.error || 'Error desconocido');
        }
    } catch (error) {
        mensajeResultado.innerHTML = `
            <div class="p-4 bg-red-100 text-red-800 rounded-lg">
                <strong>Error al procesar el archivo</strong>
                <div class="mt-1">${error.message}</div>
            </div>
        `;
        
        console.error('Error en importación:', error);
    } finally {
        mensajeResultado.classList.remove('hidden');
        botonSubmit.disabled = false;
        botonSubmit.innerHTML = 'Subir archivo';
    }
});
    </script>

</body>

</html>