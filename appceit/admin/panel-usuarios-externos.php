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

// Verificar el rol del administrador
$idAdministrador = isset($_SESSION['id']) ? $_SESSION['id'] : null;

// Conexión a la base de datos
require '../includes/config/database.php';
$db = conectarDB();

// Determinar la página actual
$pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;

// Calcular el offset para la consulta SQL
$offset = ($pagina - 1) * 20;

$sortColumn = $_GET['sort'] ?? 'Nombre';
$sortDirection = $_GET['dir'] ?? 'asc';

$allowedColumns = [
    'id' => 'u.id',
    'nombre' => 'u.nombreCompleto',
    'identificacion' => 'u.identificacion',
    'correo electronico' => 'u.email',
    'celular' => 'u.celular',
    'domicilio' => 'Domicilio',

];

$dbSortColumn = $allowedColumns[$sortColumn] ?? 'nombreCompleto';
$dbSortDirection = in_array(strtoupper($sortDirection), ['ASC', 'DESC']) ? strtoupper($sortDirection) : 'ASC';

// Construir la consulta de datos con paginado
$query = "SELECT id, 
nombreCompleto, 
email, 
identificacion, 
celular, 
calle, 
colonia, 
CP, 
ciudad, 
registrado,
CONCAT(u.calle, ' ', u.colonia, ' ', u.CP, ' ', u.ciudad) as Domicilio
FROM usuariosexternos u 
ORDER BY $dbSortColumn $dbSortDirection
LIMIT 20 OFFSET $offset";

// Ejecutar la consulta de usuarios
$resultadoUsuarios = mysqli_query($db, $query);

// Consulta para contar el número total de usuarios
$countQuery = "SELECT COUNT(*) AS total_usuarios FROM usuariosexternos";
$resultadoCount = mysqli_query($db, $countQuery);
$totalUsuarios = mysqli_fetch_assoc($resultadoCount)['total_usuarios'];

// Calcular el número total de páginas
$totalPaginas = ceil($totalUsuarios / 20);

if ($_SERVER['REQUEST_METHOD'] === "POST") {
  $id = $_POST['id'];
  $id = filter_var($id, FILTER_VALIDATE_INT);

  if ($id) {
    // Elimina el usuario
    $query = "DELETE FROM usuarios WHERE id = {$id}";

    $resultado = mysqli_query($db, $query);

    if ($resultado) {
      header('Location: panel-usuarios.php?resultado=1');
    }
  }
}

function getSortLink($columnName, $friendlyName, $currentSort, $currentDir) {
    $allowedColumns = [
    'id' => 'u.id',
    'nombre' => 'u.nombreCompleto',
    'identificacion' => 'u.identificacion',
    'correo electronico' => 'u.email',
    'celular' => 'u.celular',
    'domicilio' => 'Domicilio',
    ];
    
    if (!isset($allowedColumns[$columnName])) {
        return $friendlyName;
    }
    
    $params = $_GET;
    $newDirection = 'asc';
    
    if ($currentSort === $columnName) {
        $newDirection = $currentDir === 'asc' ? 'desc' : 'asc';
    }
    
    $params['sort'] = $columnName;
    $params['dir'] = $newDirection;
    
    // Mantener otros parámetros existentes
    if (isset($params['pagina'])) unset($params['pagina']);
    
    $queryString = http_build_query($params);
    $arrow = '';
    
    if ($currentSort === $columnName) {
        $arrow = $currentDir === 'asc' ? ' ↑' : ' ↓';
    }
    
    return '<a href="?' . $queryString . '" style="color: white; text-decoration: none; display: block; padding: 8px;">' . 
           $friendlyName . $arrow . '</a>';
}


incluirTemplate('sidebar');
?>
<!-- Contenido Principal -->
<link rel="stylesheet" href="../public/css/panellibros.css">

<div class="container main--content">
  <div class="header--wrapper">
    <div class="header--title">
      <span
        style="display: flex; border: 2.3px solid #09a787; padding: 2px; margin-bottom: 10px; border-radius: 5px; color: #09a787; width: 230px; text-transform: uppercase">
        <?php if ($rolAdministrador === 1) {
          echo 'Máster';
        } elseif ($rolAdministrador === 2) {
          echo 'Administrador general';
        } else {
          echo 'Administrador';
        } ?>
      </span>
      <span>Bienvenido, <?php echo ($nombreAdministrador); ?></span>
      <h2>Panel de usuarios externos</h2>
    </div>
    <div class="user--info">
      <div class="search--box">
        <i class="fas fa-search"></i>
        <input type="text" id="buscar" placeholder="Buscar" />
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
            <span class="title"> Total de usuarios </span>
            <span class="amount--value"><?php echo $totalUsuarios; ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="tabular--wrapper">
    <h3 class="main--title">Usuarios externos</h3>
    <div class="tabular--botones">
      <?php if ($rolAdministrador == 1): ?>
        <a title="Exportar PDF" id="btnPDF" href="Reporte de usuarios externos.php" target="_blank"><i
            class="fas fa-file-pdf"></i> Exportar PDF</a>
        <a title="Exportar Excel" id="btnExcel" href="Reporte de usuarios externos excel.php" target="_blank"><i
            class="fas fa-file-excel"></i> Exportar Excel</a>
      <?php endif; ?>
    </div>
    <div class="table--container">
      <table>
        <thead>
          <tr>
            <th draggable="true"><?php echo getSortLink('id', 'ID', $sortColumn, $sortDirection); ?></th>
            <th draggable="true"><?php echo getSortLink('nombre', 'Nombre', $sortColumn, $sortDirection); ?></th>
            <th draggable="true"><?php echo getSortLink('identificacion', 'Identificación', $sortColumn, $sortDirection); ?></th>
            <th draggable="true"><?php echo getSortLink('correo electronico', 'Correo electrónico', $sortColumn, $sortDirection); ?></th>
            <th draggable="true"><?php echo getSortLink('celular', 'Celular', $sortColumn, $sortDirection); ?></th>
            <th draggable="true"><?php echo getSortLink('domicilio', 'Domicilio', $sortColumn, $sortDirection); ?></th>

            <th draggable="true">Acciones</th>
          </tr>
        </thead>
        <tbody id="usuarios-tbody">
          <?php if (mysqli_num_rows($resultadoUsuarios) > 0): ?>
            <?php $i = $offset + 1;
            while ($usuario = mysqli_fetch_assoc($resultadoUsuarios)): ?>
              <tr>
                <td class="textosm"><?php echo $i; ?></td>
                <td class="textosm nombretable"><?php echo ($usuario['nombreCompleto']); ?></td>
                <td class="textosm"><?php echo $usuario['identificacion'] ?></td>
                <td class="textosm"><a target="_blank"
                    href="mailto:<?php echo $usuario['email']; ?>"><?php echo $usuario['email']; ?></a></td>
                <td class="textosm"><?php echo ($usuario['celular']); ?></td>
                <td class="domiciliotable textodomicilio">
                  <?php echo "Calle: " . $usuario['calle'] . " " . "Col: " . $usuario['colonia'] . " " . "CP: #" . $usuario['CP'] . " " . "Ciudad: " . $usuario['ciudad']; ?>
                </td>
                <td>
                  <div class="botones--accion--container">
                    <a href="escanear-qr-usuarios-externos.php?id=<?php echo $usuario['id']; ?>">
                      <button title="Prestamo" class="btnAceptado">Préstamo</button>
                    </a>
                  </div>
                </td>
              </tr>
              <?php $i++;
            endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="7">No hay resultados</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Paginación -->
    <div class="paginacion_contenedor">
      <?php if ($pagina > 1): ?>
        <a href="?pagina=<?php echo $pagina - 1; ?>">&laquo; Anterior</a>
      <?php endif; ?>

      <?php
      // Número máximo de enlaces de página que se mostrarán
      $maxLinks = 5;

      // Calcula el rango de páginas a mostrar
      $start = max(1, $pagina - floor($maxLinks / 2));
      $end = min($totalPaginas, $start + $maxLinks - 1);

      if ($start > 1) {
        echo '<a href="?pagina=1">1</a>';
        if ($start > 2) {
          echo '<span>...</span>';
        }
      }

      for ($i = $start; $i <= $end; $i++): ?>
        <a href="?pagina=<?php echo $i; ?>" class="<?php if ($i == $pagina)
             echo 'active'; ?>"><?php echo $i; ?></a>
      <?php endfor; ?>

      <?php
      if ($end < $totalPaginas) {
        if ($end < $totalPaginas - 1) {
          echo '<span>...</span>';
        }
        echo '<a href="?pagina=' . $totalPaginas . '">' . $totalPaginas . '</a>';
      }
      ?>

      <?php if ($pagina < $totalPaginas): ?>
        <a href="?pagina=<?php echo $pagina + 1; ?>">Siguiente &raquo;</a>
      <?php endif; ?>
    </div>
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
  </script>

  <!-- Script para la búsqueda -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    $(document).ready(function () {
      $('#buscar').on('input', function () {
        var query = $(this).val();
        $.ajax({
          url: 'buscar_usuarios_externos.php',
          method: 'GET',
          data: {
            query: query
          },
          success: function (data) {
            $('#usuarios-tbody').html(data);
          }
        });
      });
    });
  </script>

  <?php
  mysqli_close($db);

  incluirTemplate('footer');
  ?>