<?php
require '../includes/funciones.php';

$auth = adminAutenticado();

if (!$auth) {
    header('Location: login.php');
    exit;
}

// Obtener el nombre del administrador de la sesión
$nombreAdministrador = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario';

// Verificar el rol del administrador
$rolAdministrador = (int) $_SESSION['rol'];

$idAdministrador = isset($_SESSION['rol']) ? $_SESSION['rol'] : null;



//* Importar la conexión
require '../includes/config/database.php';
$db = conectarDB();


$pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;

//* Calcular el offset para la consulta SQL
$offset = ($pagina - 1) * 20;

//* Obtener la sección seleccionada
$seccionId = $_GET['seccion'] ?? null;

//* Obtener el estado seleccionado
$statusId = $_GET['status'] ?? null;

//* Obtener el límite de registros por página (por defecto 20)
$limite = isset($_GET['limite']) ? (int) $_GET['limite'] : 20;

// Validar que el límite esté entre los valores permitidos
$limitesPermitidos = [10, 20, 30, 50, 100, 200, 500];
if (!in_array($limite, $limitesPermitidos) && $limite != $totalLibros) {
    $limite = 20;
}

//* Determinar la página actual
$pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;

//* Calcular el offset para la consulta SQL
$offset = ($pagina - 1) * $limite;

//* Escribir la consulta con JOIN
$query = "SELECT libros.*, secciones.nombre_seccion AS seccion_nombre, secciones.color AS seccion_color FROM libros JOIN secciones ON libros.seccionId = secciones.id ";

$conditions = [];

if ($seccionId) {
    $conditions[] = "libros.seccionId = " . intval($seccionId);
}

if ($statusId) {
    $conditions[] = "libros.status = '" . $statusId . "'";
}

if (count($conditions) > 0) {
    $query .= " WHERE " . implode(" AND ", $conditions);
}

$query .= " ORDER BY libros.codigo ASC LIMIT $limite OFFSET $offset";

//* Consultar BD
$resultadoQuery = mysqli_query($db, $query);
//* Consulta para contar el número total de libros
$countQuery = "SELECT COUNT(*) AS total_libros FROM libros";

if ($seccionId || $statusId) {
    $countQuery .= " WHERE ";
    $conditions = [];
    if ($seccionId) {
        $conditions[] = "seccionId = " . intval($seccionId);
    }
    if ($statusId) {
        $conditions[] = "status = '" . $statusId . "'";
    }
    $countQuery .= implode(" AND ", $conditions);
}

$resultadoCount = mysqli_query($db, $countQuery);
$totalLibros = mysqli_fetch_assoc($resultadoCount)['total_libros'];

//* Consulta para obtener la suma de títulos
$titulosQuery = "SELECT SUM(titulos) AS total_titulos FROM libros";

if ($seccionId || $statusId) {
    $titulosQuery .= " WHERE ";
    $conditions = [];
    if ($seccionId) {
        $conditions[] = "seccionId = " . intval($seccionId);
    }
    if ($statusId) {
        $conditions[] = "status = '" . $statusId . "'";
    }
    $titulosQuery .= implode(" AND ", $conditions);
}

$resultadoTitulos = mysqli_query($db, $titulosQuery);
$totalTitulos = mysqli_fetch_assoc($resultadoTitulos)['total_titulos'];

//* Consulta para obtener la suma de ejemplares
$ejemplaresQuery = "SELECT SUM(ejemplares) AS total_ejemplares FROM libros";

if ($seccionId || $statusId) {
    $ejemplaresQuery .= " WHERE ";
    $conditions = [];
    if ($seccionId) {
        $conditions[] = "seccionId = " . intval($seccionId);
    }
    if ($statusId) {
        $conditions[] = "status = '" . $statusId . "'";
    }
    $ejemplaresQuery .= implode(" AND ", $conditions);
}

$resultadoEjemplares = mysqli_query($db, $ejemplaresQuery);
$totalEjemplares = mysqli_fetch_assoc($resultadoEjemplares)['total_ejemplares'];

//* Calcular el número total de páginas
$totalPaginas = ceil($totalLibros / $limite);
//? Consulta para obtener las secciones
$consultaSecciones = "SELECT * FROM secciones";
$resultadoSecciones = mysqli_query($db, $consultaSecciones);

//? Consulta para obtener los estados
$consultaEstados = "SELECT DISTINCT status FROM libros";
$resultadoEstados = mysqli_query($db, $consultaEstados);

// Muestra un mensaje condicional
$resultado = $_GET["resultado"] ?? null;

if ($_SERVER['REQUEST_METHOD'] === "POST") {
    $id = $_POST['id'];
    $id = filter_var($id, FILTER_VALIDATE_INT);

    if ($id) {

        // Elimina el archivo
        $query = "SELECT imagen, ImagenQR, ImagenCodigoDeBarras FROM libros WHERE id = {$id}";

        $resultado = mysqli_query($db, $query);

        $libro = mysqli_fetch_assoc($resultado);

        // Eliminar imagen de portada
        if (!empty($libro['imagen']) && file_exists('../imagenes/' . $libro['imagen'])) {
            unlink('../imagenes/' . $libro['imagen']);
        }
        // Eliminar imagen QR
        if (!empty($libro['ImagenQR']) && file_exists('../imagenes/' . $libro['ImagenQR'])) {
            unlink('../imagenes/' . $libro['ImagenQR']);
        }
        // Eliminar imagen de Código de Barras
        if (!empty($libro['ImagenCodigoDeBarras']) && file_exists('../imagenes/' . $libro['ImagenCodigoDeBarras'])) {
            unlink('../imagenes/' . $libro['ImagenCodigoDeBarras']);
        }


        // Elimina el libro de la BD
        $query = "DELETE FROM libros WHERE id = {$id}";

        $resultado = mysqli_query($db, $query);

        if ($resultado) {
            header('Location: panel-libros.php?resultado=3');
        }
    }
}
//* Obtener el límite de registros por página (por defecto 20)
$limite = isset($_GET['limite']) ? (int) $_GET['limite'] : 20;

// Validar que el límite esté entre los valores permitidos o sea el total
$limitesPermitidos = [10, 20, 30, 50, 100, 200, 500, $totalLibros];
if (!in_array($limite, $limitesPermitidos)) {
    $limite = 20;
}

// Si el límite es mayor al total, ajustarlo al total
if ($limite > $totalLibros && $totalLibros > 0) {
    $limite = $totalLibros;
}
incluirTemplate('sidebar');
?>
<style>
    @media (min-width: 1200px) {
        .tabular--wrapper .table--container {
            overflow-x: visible !important;
        }
    }
</style>
<link rel="stylesheet" href="../public/css/panellibros.css">
<div class="container main--content">
    <div class="header--wrapper">
        <div class="header--title">
            <span
                style="display: flex; border: 2.3px solid #09a787; padding: 2px; margin-bottom: 10px; border-radius: 5px; width: 230px; text-transform: uppercase; color: #09a787;">
                <?php if ($rolAdministrador === 1) {
                    echo 'Máster';
                } elseif ($rolAdministrador === 2) {
                    echo 'Administrador general';
                } else {
                    echo 'Administrador';
                } ?>
            </span>
            <span>Bienvenido, <?php echo ($nombreAdministrador); ?></span>
            <h2>Panel de libros</h2>
        </div>
        <div class="user--info">
            <div class="search--box">
                <i class="fas fa-search"></i>
                <input type="text" id="buscar" placeholder="Buscar" oninput="buscarLibros()" />
            </div>
            <img src="../public/img/logouttn.png" alt="Foto de perfil" />
        </div>
    </div>
    <div class="card--container">
        <h3 class="main--title">Datos actuales</h3>
        <div class="card--wrapper">

            <div class="payment--card">
                <div class="card--header">
                    <div class="amount">
                        <span class="title"> Total de títulos </span>
                        <span class="amount--value"><?php echo $totalTitulos ?></span>
                    </div>
                </div>
            </div>
            <div class="payment--card">
                <div class="card--header">
                    <div class="amount">
                        <span class="title"> Total de ejemplares </span>
                        <span class="amount--value"><?php echo $totalEjemplares ?> </span>
                    </div>
                </div>
            </div>
            <div class="payment--card" style="width: 410px;">
                <div class="card--header">
                    <div class="amount">
                        <span class="title"> Secciones </span>
                        <form method="GET" action="">
                            <select name="seccion" id="selectSeccion" onchange="this.form.submit()">
                                <option value="">Todas las secciones</option>
                                <?php while ($seccion = mysqli_fetch_assoc($resultadoSecciones)): ?>
                                    <option value="<?php echo $seccion['id']; ?>" <?php if ($seccion['id'] == $seccionId)
                                           echo 'selected'; ?>>
                                        <?php echo $seccion['nombre_seccion']; ?>
                                    </option>
                                <?php endwhile ?>
                            </select>
                        </form>
                    </div>
                </div>
            </div>
            <div class="payment--card" style="width: 200px;">
                <div class="card--header">
                    <div class="amount">
                        <span class="title"> Estatus </span>
                        <form method="GET" action="">
                            <select name="status" id="selectSeccion" style="width: 170px;"
                                onchange="this.form.submit()">
                                <option value="">Todos los estados</option>
                                <?php while ($estado = mysqli_fetch_assoc($resultadoEstados)): ?>
                                    <option value="<?php echo $estado['status']; ?>" <?php if ($estado['status'] == $statusId)
                                           echo 'selected'; ?>>
                                        <?php echo $estado['status']; ?>
                                    </option>
                                <?php endwhile ?>
                            </select>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<!-- Selector de límite de registros --><!-- Selector de límite de registros -->
<div class="limite-selector">
    <label for="limitePrestamos">Mostrar:</label>
    <select id="limitePrestamos" onchange="cambiarLimite(this.value)">
        <?php
        // Opciones base
        $opcionesBase = [10, 20, 30, 50, 100, 200, 500];
        
        // Generar opciones dinámicamente
        foreach ($opcionesBase as $opcion) {
            // Solo mostrar la opción si es menor o igual al total de libros
            if ($opcion <= $totalLibros) {
                $selected = ($limite == $opcion) ? 'selected' : '';
                echo "<option value=\"{$opcion}\" {$selected}>{$opcion} libros</option>";
            }
        }
        
        // Agregar opción "Todos" si el total es mayor que la última opción base
        if ($totalLibros > max($opcionesBase)) {
            $selected = ($limite == $totalLibros) ? 'selected' : '';
            echo "<option value=\"{$totalLibros}\" {$selected}>Todos ({$totalLibros})</option>";
        } elseif ($totalLibros > 0 && !in_array($totalLibros, $opcionesBase)) {
            // Si el total no está en las opciones base pero es menor que 500, agregarlo
            $selected = ($limite == $totalLibros) ? 'selected' : '';
            echo "<option value=\"{$totalLibros}\" {$selected}>Todos ({$totalLibros})</option>";
        }
        ?>
    </select>
    <span class="limite-info">
        Mostrando <?php echo min($offset + 1, $totalLibros); ?> - <?php echo min($offset + $limite, $totalLibros); ?> de <?php echo $totalLibros; ?> libros
    </span>
</div>
    <div class="tabular--wrapper">
        <h3 class="main--title">Libros</h3>
        <div class="tabular--botones">
            <a href="./libros/crear-libro.php">
                <button title="Añadir libro" class="btnAgregar">
                    <i class="fas fa-plus"></i> Registrar libro
                </button>
            </a>
            <a title="Exportar PDF" id="btnPDF" href="Reporte de libros.php"><i class="fas fa-file-pdf"></i> Exportar
                PDF</a>
            <a title="Exportar Excel" href="Inventario bibliografico.php" id="btnExcel"><i
                    class="fas fa-file-excel"></i> Exportar Excel</a>
            <button title="inventariar" id="inventariar" class="btnAgregar" style="background:#515151;"><i
                    class="fas fa-boxes"></i> Inventariar</button>
            <button title="habilitar" id="habilitar" class="btnAgregar" style="background:#515151;">
                <i class="fas fa-clipboard-check"></i>
                Habilitar
            </button>
            <a href="crear-qr.php">
                <button title="habilitar" id="habilitar" class="btnAgregar" style="background: #000 !important;">
                    <i class="fas fa-qrcode"></i>
                    Generar Qr
                </button>
            </a>

            <!--  <a href="crear-barcode.php" style="display: none;"></a>
                <button title="habilitar" id="habilitar" class="btnAgregar" style="background: #000 !important; display: none; "></button>
                    <i class="fas fa-barcode"></i>
                    Generar Codigo De Barras
                </button>
            </a> -->

        </div>
        <?php if (intval($resultado) === 1): ?>
            <p class="alerta exito fade-out">Libro agregado correctamente</p>
        <?php elseif (intval($resultado) === 2): ?>
            <p class="alerta exito fade-out">Libro actualizado correctamente</p>
        <?php elseif (intval($resultado) === 3): ?>
            <p class="alerta exito fade-out">Libro eliminado correctamente</p>
        <?php endif; ?>
        <div class="table--container">
            <table id="results" style="overflow-x: auto">
                <thead>
                    <tr>
                        <th draggable="true">#</th>
                        <th draggable="true">Titulo</th>
                        <th draggable="true">Sección</th>
                        <th draggable="true">Código</th>
                        <th draggable="true">Disponibles</th>
                        <th draggable="true">Títulos</th>
                        <th draggable="true">Ejemplares</th>
                        <th draggable="true">QR generado</th>
                        <th draggable="true">Imagen</th>
                        <th draggable="true">Reserva</th>
                        <th draggable="true">Estatus</th>
                        <th draggable="true">Acciones</th>
                    </tr>
                </thead>
                <tbody id="resultados">
                    <?php if (mysqli_num_rows($resultadoQuery) > 0): ?>
                        <?php $numeroFila = 1; ?>
                        <?php while ($libro = mysqli_fetch_assoc($resultadoQuery)): ?>
                            <tr>
                                <td style="font-size:14px;"><?php echo $numeroFila++; ?></td>
                                <td style="font-size:14px;"><?php echo $libro['titulo']; ?></td>
                                <td style="font-size:14px;"><?php echo $libro['seccion_nombre']; ?></td>
                                <td style="font-size:14px;">
                                    <button style="background-color: <?php echo $libro['seccion_color'] ?>"
                                        class="reservacion--libro"></button>
                                    <?php echo $libro['codigo']; ?>
                                </td>
                                <td style="font-size:14px;" class="libro--cantidad"><?php echo $libro['cantidad']; ?></td>
                                <td style="font-size:14px;" class="libro--cantidad ">
                                    <?php if ($libro['titulos'] === "0") {
                                        echo '';
                                    } else {
                                        echo $libro['titulos'];
                                    } ?>
                                </td>
                                <td style="font-size:14px;" class="libro--cantidad ">
                                    <?php if ($libro['ejemplares'] === "0") {
                                        echo '';
                                    } else {
                                        echo $libro['ejemplares'];
                                    } ?>
                                </td>
                                <td>
                                    <?php if (!empty($libro['ImagenQR'])): ?>
                                        <a href="../includes/DescargarPDF.php?img=<?php echo urlencode($libro['ImagenQR']); ?>&type=qr"
                                            title="Descargar QR como PDF de <?php echo htmlspecialchars($libro['titulo']); ?>">
                                            <img src="../imagenes/<?php echo htmlspecialchars($libro['ImagenQR']); ?>"
                                                alt="QR de <?php echo htmlspecialchars($libro['titulo']); ?>"
                                                style="width: 60px; height: 60px;" />
                                        </a>
                                    <?php else: ?>
                                        <span style="color: #888; font-size: 13px;">Aún no tiene QR</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <img src="../imagenes/<?php echo $libro['imagen']; ?>"
                                        alt="Portada de <?php echo $libro['titulo']; ?>"
                                        title="Portada de <?php echo $libro['titulo']; ?>" />
                                </td>
                                <td style="font-size:14px;" class="libro--cantidad"><?php echo $libro['reserva']; ?></td>
                                <td style="font-size:14px;" class="libro--cantidad"><?php echo $libro['status']; ?></td>
                                <td>
                                    <div class="botones--accion--container">
                                        <a href="./libros/actualizar-libro.php?id=<?php echo $libro['id']; ?>">
                                            <button title="Editar" style="font-size:14px;" class="btnAceptado">Editar</button>
                                        </a>
                                        <form id="eliminar-form-<?php echo $libro['id']; ?>" method="POST">
                                            <input type="hidden" name="id" value="<?php echo $libro['id']; ?>">
                                            <button type="button" style="font-size:14px;" title="Eliminar" class="btnCancelar"
                                                onclick="confirmarEliminacion(<?php echo $libro['id']; ?>, '<?php echo addslashes($libro['titulo']); ?>')">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="13">No hay resultados</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

 <div class="paginacion_contenedor">
    <?php if ($pagina > 1): ?>
        <a href="?pagina=<?php echo $pagina - 1; ?>&limite=<?php echo $limite; ?><?php
            if ($seccionId) echo "&seccion=" . $seccionId;
            if ($statusId) echo "&status=" . $statusId;
        ?>">&laquo; Anterior</a>
    <?php endif; ?>

    <?php
    $maxLinks = 5;
    $start = max(1, $pagina - floor($maxLinks / 2));
    $end = min($totalPaginas, $start + $maxLinks - 1);

    if ($start > 1) {
        echo '<a href="?pagina=1&limite=' . $limite .
            ($seccionId ? "&seccion=" . $seccionId : "") .
            ($statusId ? "&status=" . $statusId : "") . '">1</a>';
        if ($start > 2) {
            echo '<span>...</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++): ?>
        <a href="?pagina=<?php echo $i; ?>&limite=<?php echo $limite; ?><?php
            if ($seccionId) echo "&seccion=" . $seccionId;
            if ($statusId) echo "&status=" . $statusId;
        ?>" class="<?php if ($i == $pagina) echo 'active'; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>

    <?php
    if ($end < $totalPaginas) {
        if ($end < $totalPaginas - 1) {
            echo '<span>...</span>';
        }
        echo '<a href="?pagina=' . $totalPaginas . '&limite=' . $limite .
            ($seccionId ? "&seccion=" . $seccionId : "") .
            ($statusId ? "&status=" . $statusId : "") . '">' . $totalPaginas . '</a>';
    }
    ?>

    <?php if ($pagina < $totalPaginas): ?>
        <a href="?pagina=<?php echo $pagina + 1; ?>&limite=<?php echo $limite; ?><?php
            if ($seccionId) echo "&seccion=" . $seccionId;
            if ($statusId) echo "&status=" . $statusId;
        ?>">Siguiente &raquo;</a>
    <?php endif; ?>
</div>
    <script>
        class DraggableTableColumns {
            constructor() {
                this.tableElement = document.querySelector('table');
                if (!this.tableElement) {
                    console.error("No se encontró el elemento <table>.");
                    return;
                }

                this.selectedColumn = null; // Columna que se está arrastrando (<th>)
                this.hoveredColumn = null;  // Columna sobre la que se arrastra (<th>)

                this.addDraggingEvents();
            }

            addDraggingEvents() {
                this.tableElement.querySelectorAll("thead th").forEach(th => {
                    th.addEventListener("dragstart", (e) => this.dragStart(e));
                    th.addEventListener("dragover", (e) => this.dragOver(e));
                    th.addEventListener("dragleave", (e) => this.dragLeave(e));
                    th.addEventListener("drop", (e) => this.drop(e));
                    th.addEventListener("dragend", (e) => this.dragEnd(e)); // Limpiar estilos al finalizar
                });
            }

            dragStart(e) {
                this.selectedColumn = e.currentTarget;
                this.selectedColumn.classList.add("drag-selected"); // Añadir clase visual
                e.dataTransfer.effectAllowed = "move";
                // Para Firefox, se necesita establecer algún dato para que el arrastre funcione
                e.dataTransfer.setData("text/plain", "");
                console.log("Drag Start:", this.selectedColumn.textContent);
            }

            dragOver(e) {
                e.preventDefault(); // Permite que el drop ocurra
                if (e.currentTarget === this.selectedColumn) return; // No hacer nada si es la misma columna

                this.clearDragHoverStyles(); // Limpia estilos de todas las columnas
                this.hoveredColumn = e.currentTarget;
                this.hoveredColumn.classList.add("drag-hovered"); // Añade estilo a la columna sobre la que se pasa
                console.log("Drag Over:", this.hoveredColumn.textContent);
            }

            dragLeave(e) {
                // Se puede limpiar aquí si la columna arrastrada sale del área de una columna
                // Pero `dragOver` ya maneja la limpieza y aplicación en el siguiente `th`
            }

            drop(e) {
                e.preventDefault(); // Previene el comportamiento por defecto del drop
                console.log("Drop on:", e.currentTarget.textContent);

                if (this.selectedColumn && this.hoveredColumn && this.selectedColumn !== this.hoveredColumn) {
                    this.moveColumn();
                }
                this.clearDragStyles(); // Limpia todos los estilos al soltar
            }

            dragEnd(e) {
                this.clearDragStyles(); // Asegura la limpieza al finalizar el arrastre
                console.log("Drag End");
            }

            moveColumn() {
                const headRow = this.tableElement.querySelector("thead tr");
                const tbody = this.tableElement.querySelector("tbody");

                if (!headRow || !tbody) return;

                // Obtener el índice de la columna seleccionada y la columna sobre la que se soltó
                const selectedIndex = Array.from(headRow.children).indexOf(this.selectedColumn);
                const hoveredIndex = Array.from(headRow.children).indexOf(this.hoveredColumn);

                if (selectedIndex === -1 || hoveredIndex === -1) return;

                // Mover los encabezados (<th>)
                if (selectedIndex < hoveredIndex) {
                    headRow.insertBefore(this.selectedColumn, this.hoveredColumn.nextSibling);
                } else {
                    headRow.insertBefore(this.selectedColumn, this.hoveredColumn);
                }

                // Mover las celdas (<td>) en cada fila del tbody
                Array.from(tbody.rows).forEach(row => {
                    const cells = Array.from(row.children);
                    const selectedCell = cells[selectedIndex];
                    const hoveredCell = cells[hoveredIndex];

                    if (selectedCell && hoveredCell) {
                        if (selectedIndex < hoveredIndex) {
                            row.insertBefore(selectedCell, hoveredCell.nextSibling);
                        } else {
                            row.insertBefore(selectedCell, hoveredCell);
                        }
                    }
                });

                // Restablecer referencias después del movimiento
                this.selectedColumn = null;
                this.hoveredColumn = null;
            }

            clearDragHoverStyles() {
                this.tableElement.querySelectorAll("thead th").forEach(th => th.classList.remove("drag-hovered"));
            }

            clearDragStyles() {
                this.tableElement.querySelectorAll("thead th").forEach(th => {
                    th.classList.remove("drag-selected");
                    th.classList.remove("drag-hovered");
                });
            }
        }

        // Instancia de la clase para activar la funcionalidad
        new DraggableTableColumns();

            function cambiarLimite(limite) {
        const urlParams = new URLSearchParams(window.location.search);

        urlParams.set('limite', limite);

        urlParams.set('pagina', '1');

        const nuevaURL = window.location.pathname + '?' + urlParams.toString();

        window.location.href = nuevaURL;
    }

        
    </script>

    <script>
        function sincronizarLibros() {
            fetch('getLibros.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Error en la respuesta de la API');
                    }
                    return response.json();
                })
                .then(libros => {

                    //Log para verificar los datos obtenidos
                    //console.log('Datos obtenidos:', libros);


                    if (!window.indexedDB) {
                        console.log("Tu navegador no soporta IndexedDB");
                        return;
                    }

                    let request = window.indexedDB.open("biblioteca", 1);

                    request.onerror = function (event) {
                        console.log("Error al abrir IndexedDB:", event);
                    };

                    request.onupgradeneeded = function (event) {
                        let db = event.target.result;
                        let objectStore = db.createObjectStore("libros", {
                            keyPath: "id"
                        });
                        objectStore.createIndex("titulo", "titulo", {
                            unique: false
                        });
                        objectStore.createIndex("codigo", "codigo", {
                            unique: false
                        });
                    };

                    request.onsuccess = function (event) {
                        let db = event.target.result;
                        let transaction = db.transaction(["libros"], "readwrite");
                        let objectStore = transaction.objectStore("libros");

                        objectStore.clear();

                        libros.forEach(libro => {
                            let addRequest = objectStore.put(libro);
                            addRequest.onsuccess = function (event) {
                                /* 
                                Log que muestra los libros en la terminal para comprobar si se han agregado correctamente
                                console.log('Libro agregado:', libro);
                                */
                            };
                            addRequest.onerror = function (event) {
                                console.log('Error al agregar libro:', event.target.error);
                            };
                        });

                        transaction.oncomplete = function () {
                            console.log("Libros sincronizados con IndexedDB");
                        };
                    };
                })
                .catch(error => console.log('Error fetching libros:', error));
        }

        sincronizarLibros();

        function buscarLibros() {
            let input = document.getElementById('buscar').value.toLowerCase();

            // Verifica si el campo de búsqueda está vacío y se recarga la pagina si se cumple
            if (input === '') {
                location.reload();
                return;
            }

            let request = window.indexedDB.open("biblioteca", 1);

            request.onsuccess = function (event) {
                let db = event.target.result;
                let transaction = db.transaction(["libros"], "readonly");
                let objectStore = transaction.objectStore("libros");
                let tituloIndex = objectStore.index("titulo");

                let results = [];

                tituloIndex.openCursor().onsuccess = function (event) {
                    let cursor = event.target.result;
                    if (cursor) {
                        if (cursor.value.titulo.toLowerCase().includes(input) || cursor.value.codigo.toLowerCase().includes(input)) {
                            results.push(cursor.value);
                        }
                        cursor.continue();
                    } else {
                        mostrarResultados(results);
                    }
                };

                tituloIndex.openCursor().onerror = function (event) {
                    console.log("Error al buscar libros:", event.target.error);
                };
            };

            request.onerror = function (event) {
                console.log("Error al abrir IndexedDB:", event.target.error);
            };
        }
        function buscarLibros() {
            let input = document.getElementById('buscar').value.toLowerCase();

            // Verifica si el campo de búsqueda está vacío y se recarga la pagina si se cumple
            if (input === '') {
                location.reload();
                return;
            }

            let request = window.indexedDB.open("biblioteca", 1);

            request.onsuccess = function (event) {
                let db = event.target.result;
                let transaction = db.transaction(["libros"], "readonly");
                let objectStore = transaction.objectStore("libros");
                let tituloIndex = objectStore.index("titulo");

                let results = [];
                const MAX_RESULTS = 20; // Define el límite de resultados aquí

                tituloIndex.openCursor().onsuccess = function (event) {
                    let cursor = event.target.result;
                    if (cursor) {
                        if (cursor.value.titulo.toLowerCase().includes(input) || cursor.value.codigo.toLowerCase().includes(input)) {
                            results.push(cursor.value);
                        }
                        // Si ya tenemos el número máximo de resultados, ¡detenemos el cursor!
                        if (results.length >= MAX_RESULTS) {
                            mostrarResultados(results); // Muestra los resultados obtenidos hasta ahora
                            return; // Detiene la ejecución del onsuccess del cursor
                        }
                        cursor.continue(); // Continúa al siguiente elemento si no hemos alcanzado el límite
                    } else {
                        // Si el cursor ha terminado y no hemos alcanzado el límite, muestra los resultados
                        mostrarResultados(results);
                    }
                };

                tituloIndex.openCursor().onerror = function (event) {
                    console.log("Error al buscar libros:", event.target.error);
                };
            };

            request.onerror = function (event) {
                console.log("Error al abrir IndexedDB:", event.target.error);
            };
        }

        function mostrarResultados(libros) {
            let tbody = document.getElementById('resultados');
            tbody.innerHTML = '';

            if (libros.length > 0) {
                let numeroFila = 1;
                libros.forEach(libro => {
                    let tr = document.createElement('tr');

                    tr.innerHTML = `
                <td style="font-size:14px;">${numeroFila++}</td>
                <td style="font-size:14px;">${libro.titulo}</td>
                <td style="font-size:14px;">${libro.seccion_nombre}</td>
                <td style="font-size:14px;">
                    <button style="background-color: ${libro.seccion_color}" class="reservacion--libro"></button>
                    ${libro.codigo}
                </td>
                <td style="font-size:14px;" class="libro--cantidad">${libro.cantidad}</td>
                <td style="font-size:14px;" class="libro--cantidad">${libro.titulos === "0" ? '' : libro.titulos}</td>
                <td style="font-size:14px;" class="libro--cantidad">${libro.ejemplares === "0" ? '' : libro.ejemplares}</td>
                
                <td>
                    ${libro.ImagenQR && libro.ImagenQR !== '0' ? // Check if exists and not '0'
                            `<img src="../imagenes/${libro.ImagenQR}" alt="QR de ${libro.titulo}" title="QR de ${libro.titulo}" style="width: 60px; height: 60px;" />` :
                            `<span style="color: #888; font-size: 13px;">Aún no tiene QR</span>`}
                </td>
                
                

                <td style="font-size:14px;">
                    <img src="../imagenes/${libro.imagen}" alt="Portada de ${libro.titulo}" title="Portada de ${libro.titulo}" />
                </td>
                <td style="font-size:14px;" class="libro--cantidad">${libro.reserva}</td>
                <td style="font-size:14px;" class="libro--cantidad">${libro.status}</td>
                <td>
                    <div class="botones--accion--container">
                        <a href="./libros/actualizar-libro.php?id=${libro.id}">
                            <button title="Editar" style="font-size:14px;" class="btnAceptado">Editar</button>
                        </a>
                        <form id="eliminar-form-${libro.id}" method="POST">
                            <input type="hidden" name="id" value="${libro.id}">
                            <button type="button" style="font-size:14px;" title="Eliminar" class="btnCancelar" onclick="confirmarEliminacion(${libro.id}, '${libro.titulo.replace(/'/g, "\\'")}')">Eliminar</button>
                        </form>
                    </div>
                </td>
            `;
                    tbody.appendChild(tr);
                });
            } else {
                // Corrected colspan to 13, matching the number of <th> elements in your HTML
                tbody.innerHTML = '<tr><td colspan="13">No hay resultados</td></tr>';
            }
        }
    </script>

    <script>
        document.getElementById('inventariar').addEventListener('click', function () {
            Swal.fire({
                title: "¿Estás seguro?",
                text: "Esto cambiará todos los libros activos a inactivos.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, cambiar"
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('actualizar_estado_libros.php', {
                        method: 'POST',
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire(
                                    '¡Actualizado!',
                                    'El estado de los libros ha sido actualizado.',
                                    'success'
                                ).then(() => {
                                    location.reload(); // Recargar la página para ver los cambios
                                });
                            } else {
                                Swal.fire(
                                    'Error!',
                                    'Hubo un problema al actualizar el estado de los libros.',
                                    'error'
                                );
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire(
                                'Error!',
                                'Hubo un problema al actualizar el estado de los libros.',
                                'error'
                            );
                        });
                }
            });
        });

        document.getElementById('habilitar').addEventListener('click', function () {
            Swal.fire({
                title: "¿Estás seguro?",
                text: "Esto cambiará todos los libros inactivos a activos.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "Sí, cambiar"
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('reactivar_libros.php', {
                        method: 'POST',
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire(
                                    '¡Actualizado!',
                                    'El estado de los libros ha sido actualizado.',
                                    'success'
                                ).then(() => {
                                    location.reload(); // Recargar la página para ver los cambios
                                });
                            } else {
                                Swal.fire(
                                    'Error!',
                                    'Hubo un problema al actualizar el estado de los libros.',
                                    'error'
                                );
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire(
                                'Error!',
                                'Hubo un problema al actualizar el estado de los libros.',
                                'error'
                            );
                        });
                }
            });
        });
    </script>

    <script>
        function confirmarEliminacion(id, titulo) {
            Swal.fire({
                title: `¿Estás seguro de eliminar el libro "${titulo}"?`,
                text: "¡No podrás revertir esto!",
                icon: "warning",
                showCancelButton: true,
                confirmButtonColor: "#3085d6",
                cancelButtonColor: "#d33",
                confirmButtonText: "¡Sí, elimínalo!"
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('eliminar-form-' + id).submit();
                }
            });
        }
    </script>

    <?php
    //? Cerrar la conexión de la base de datos
    mysqli_close($db);

    incluirTemplate('footer');
    ?>