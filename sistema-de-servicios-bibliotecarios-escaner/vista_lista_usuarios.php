<?php
require_once 'database/Database.php';
require_once 'database/DatabaseAPI.php';

session_start();

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

   if ($sortColumn !== null && !empty($result)) {
    usort($result, function ($a, $b) use ($sortColumn, $sortDirection) {
        $valA = $a[$sortColumn] ?? '';
        $valB = $b[$sortColumn] ?? '';
        
        // Para carreras, tratar "N/A" como string vacío para ordenamiento consistente
        if ($sortColumn === 'carrera') {
            $valA = ($valA === 'N/A' || $valA === null) ? '' : $valA;
            $valB = ($valB === 'N/A' || $valB === null) ? '' : $valB;
        }
        
        $comparison = strcmp($valA, $valB);
        return $sortDirection === 'asc' ? $comparison : -$comparison;
    });
}

    // PAGINACIÓN
    $itemsPorPagina = 10;
    $totalRegistros = count($result);
    $totalPaginas = ceil($totalRegistros / $itemsPorPagina);
    $paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
    $paginaActual = max(1, min($totalPaginas, $paginaActual));
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
    <title>Lista de Usuarios</title>
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="output.css">
    <script src="https://unpkg.com/xlsx@0.16.9/dist/xlsx.full.min.js"></script>
    <script src="https://unpkg.com/file-saverjs@latest/FileSaver.min.js"></script>
    <link rel="icon" href="img/favicon.ico" type="image/x-icon">
</head>

<body>
    <main>
        <header>
            <div class="header-bar">
                <div class="flex justify-center">
                    <a href="index_admin.php"><img src="img/Image.jpeg" alt="Logo" id="logo"></a>
                </div>
            </div>
        </header>
        
        <div class="container mx-auto py-8">
            <br><br>
            <h1 class="text-2xl font-semibold text-center mb-4">Importar Información desde CSV</h1>
            <div class="flex justify-center">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 mt-5">
                    <div class="bg-white p-4 shadow-lg rounded-lg">
                        <!-- Formulario: Alumnos -->
                        <a href="archivoCSV/alumnosTemplate.csv" download class="flex justify-center">
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
                        <a href="archivoCSV/profesoresTemplate.csv" download class="flex justify-center">
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
            <div class="container mx-auto px-4">
                <div class="bg-white mt-8 shadow-lg rounded-lg overflow-x-auto">
                    <table id="tabla" class="min-w-full">
                        <thead>
                            <tr class="bg-gray-800 text-white">
                                <!-- MATRÍCULA ordenable -->
                                <th class="py-3 px-4 text-center" data-title="Matrícula">
                                    <?php
                                    $nextDir = getNextDirection('matricula', $sortColumn, $sortDirection);
                                    $params = [
                                        'start_time' => $startTime,
                                        'end_time' => $endTime,
                                        'search_term' => $searchTerm
                                    ];
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
                                <tr class="text-gray-700 border-b hover:bg-gray-50">
                                    <td class="py-2 px-4 text-center"><?= htmlspecialchars($row['matricula']); ?></td>
                                    <td class="py-2 px-4 text-center"><?= htmlspecialchars($row['nombre']); ?></td>
                                    <td class="py-2 px-4 text-center"><?= $row['tipo'] === 'Alumno' ? 'Alumno' : 'Maestro'; ?></td>
                                    <td class="py-2 px-4 text-center"><?= htmlspecialchars($row['carrera'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php else: ?>
            <div class="container mx-auto px-4">
                <div class="bg-white mt-8 shadow-lg rounded-lg p-8 text-center">
                    <p class="text-gray-600">No se encontraron usuarios registrados.</p>
                </div>
            </div>
        <?php endif; ?>

        <?php
        // Paginación
        if ($totalPaginas > 1):
            $params = [
                'start_time' => $startTime,
                'end_time' => $endTime,
                'search_term' => $searchTerm,
                'sort' => $sortColumn,
                'dir' => $sortDirection
            ];
            
            $botonesVisibles = 5;
            $mitad = floor($botonesVisibles / 2);
            $inicio = max(1, $paginaActual - $mitad);
            $fin = min($totalPaginas, $inicio + $botonesVisibles - 1);
            $inicio = max(1, $fin - $botonesVisibles + 1);
        ?>
            <div class="flex justify-center mt-8 mb-8">
                <nav style="display: flex; gap: 6px;" aria-label="Paginación">
                    <!-- Botón ANTERIOR -->
                    <?php if ($paginaActual > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($params, ['pagina' => $paginaActual - 1])); ?>"
                            style="padding: 6px 12px; background-color: #09a787; color: white; font-weight: bold; border-radius: 6px; text-decoration: none;">
                            «
                        </a>
                    <?php else: ?>
                        <span style="padding: 6px 12px; background-color: #e0e0e0; color: #999; font-weight: bold; border-radius: 6px;">«</span>
                    <?php endif; ?>

                    <!-- Mostrar [1] al final si se esta muy lejos del principio -->
                    <?php if ($inicio > 2): ?>
                        <a href="?<?php echo http_build_query(array_merge($params, ['pagina' => 1])); ?>"
                            style="padding: 6px 12px; border-radius: 6px; font-weight: bold; text-decoration: none; background-color: white; color: #333; border: 1px solid #ccc;">
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
                            style="padding: 6px 12px; border-radius: 6px; font-weight: bold; text-decoration: none; background-color: white; color: #333; border: 1px solid #ccc;">
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
                        <span style="padding: 6px 12px; background-color: #e0e0e0; color: #999; font-weight: bold; border-radius: 6px;">»</span>
                    <?php endif; ?>
                </nav>
            </div>
        <?php endif; ?>

    </main>

    <?php include 'footer.php'; ?>

    <script>
 

        // Solo mantener los scripts esenciales para los formularios
        document.getElementById('formularioAlumnos')?.addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;
            const formData = new FormData(form);
            const mensajeResultado = document.getElementById('mensajeResultado');
            const botonSubmit = form.querySelector('button[type="submit"]');

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

        document.getElementById('formularioProfesores')?.addEventListener('submit', async function(e) {
            e.preventDefault();
            const form = this;
            const formData = new FormData(form);
            const mensajeResultado = document.getElementById('mensajeResultadoProfesores');
            const botonSubmit = form.querySelector('button[type="submit"]');

            mensajeResultado.innerHTML = '';
            mensajeResultado.classList.add('hidden', 'space-y-2');
            botonSubmit.disabled = true;
            botonSubmit.innerHTML = 'Procesando...';

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