<?php
require '../includes/funciones.php';

$auth = adminAutenticado();

if (!$auth) {
  header('Location: login.php');
  exit;
}

$nombreAdministrador = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario';

$rolAdministrador = (int) $_SESSION['rol'];

$idAdministrador = isset($_SESSION['id']) ? $_SESSION['id'] : null;

require '../includes/config/database.php';
$db = conectarDB();

$sortColumn = $_GET['sort'] ?? 'nombre_seccion';
$sortDirection = $_GET['dir'] ?? 'asc';

$allowedColumns = [
  'nombre_seccion' => 'nombre_seccion',
  'color' => 's.color',
];

$dbSortColumn = $allowedColumns[$sortColumn] ?? 'nombre_seccion';
$dbSortDirection = in_array(strtoupper($sortDirection), ['ASC', 'DESC']) ? strtoupper($sortDirection) : 'ASC';


$consultaSecciones = "SELECT s.id,
s.nombre_seccion,
s.color
FROM secciones s
ORDER BY $dbSortColumn $dbSortDirection";
$resultadoSecciones = mysqli_query($db, $consultaSecciones);

$resultado = $_GET["resultado"] ?? null;

if ($_SERVER['REQUEST_METHOD'] === "POST") {
  $id = $_POST['id'];
  $id = filter_var($id, FILTER_VALIDATE_INT);

  if ($id) {
    // Elimina la sección
    $query = "DELETE FROM secciones WHERE id = {$id}";

    $resultado = mysqli_query($db, $query);

    if ($resultado) {
      header('Location: panel-secciones.php?resultado=3');
    }
  }
}

function getSortLink($columnName, $friendlyName, $currentSort, $currentDir)
{
  $allowedColumns = [
  'nombre_seccion' => 'nombre_seccion',
  'color' => 's.color',
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
  if (isset($params['pagina']))
    unset($params['pagina']);

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

<link rel="stylesheet" href="../public/css/paneladministrador.css">
<!-- Contenido Principal -->
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
      <h2>Panel de secciones</h2>
    </div>
    <div class="user--info">
      <div class="search--box">
        <i class="fas fa-search"></i>
        <input type="text" id="buscar" placeholder="Buscar" />
      </div>
      <img src="../public/img/logouttn.png" alt="Foto de perfil" />
    </div>
  </div>

  <div class="tabular--wrapper">
    <h3 class="main--title">Secciones</h3>
    <div class="tabular--botones">
      <a href="./secciones/crear-seccion.php">
        <button title="Añadir Sección" class="btnAgregar">
          <i class="fas fa-plus"></i> Registrar sección
        </button>
      </a>
    </div>
    <?php if (intval($resultado) === 1): ?>
      <p class="alerta exito fade-out">Sección agregada correctamente</p>
    <?php elseif (intval($resultado) === 2): ?>
      <p class="alerta exito fade-out">Sección actualizada correctamente</p>
    <?php elseif (intval($resultado) === 3): ?>
      <p class="alerta exito fade-out">Sección eliminada correctamente</p>
    <?php endif; ?>
    <div class="table--container">
      <table>
        <thead>
          <tr>
            <th draggable="true">#</th>
            <th draggable="true"><?php echo getSortLink('nombre_seccion', 'Nombre de la sección', $sortColumn, $sortDirection); ?></th>
            <th draggable="true"><?php echo getSortLink('color', 'Color', $sortColumn, $sortDirection); ?></th>
            <th draggable="true">Acciones</th>
          </tr>
        </thead>
        <tbody id="resultadoBusqueda">
          <?php $fila = 1;
          while ($seccion = mysqli_fetch_assoc($resultadoSecciones)): ?>
            <tr>
              <td><?php echo $fila; ?></td>
              <td><?php echo $seccion['nombre_seccion']; ?></td>
              <td>
                <button title="<?php echo $seccion['nombre_seccion']; ?>"
                  style="background-color: <?php echo $seccion['color']; ?>" class="reservacion--libro"></button>
              </td>
              <td>
                <div class="botones--accion--container">
                  <a href="./secciones/actualizar-seccion.php?id=<?php echo $seccion['id']; ?>">
                    <button title="Editar" class="btnAceptado">Editar</button>
                  </a>
                  <form id="eliminar-form-<?php echo $seccion['id']; ?>" method="POST">
                    <input type="hidden" name="id" value="<?php echo $seccion['id']; ?>">
                    <button type="button" title="Eliminar" class="btnCancelar"
                      onclick="confirmarEliminacion(<?php echo $seccion['id']; ?>, '<?php echo addslashes($seccion['nombre_seccion']); ?>')">Eliminar</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php $fila++;
          endwhile ?>
        </tbody>
      </table>
    </div>
  </div>
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

      this.selectedColumn = null;
      this.hoveredColumn = null;

      this.addDraggingEvents();
    }

    addDraggingEvents() {
      this.tableElement.querySelectorAll("thead th").forEach(th => {
        th.addEventListener("dragstart", (e) => this.dragStart(e));
        th.addEventListener("dragover", (e) => this.dragOver(e));
        th.addEventListener("dragleave", (e) => this.dragLeave(e));
        th.addEventListener("drop", (e) => this.drop(e));
        th.addEventListener("dragend", (e) => this.dragEnd(e)); 
      });
    }

    dragStart(e) {
      this.selectedColumn = e.currentTarget;
      this.selectedColumn.classList.add("drag-selected");
      e.dataTransfer.effectAllowed = "move";
      e.dataTransfer.setData("text/plain", "");
      console.log("Drag Start:", this.selectedColumn.textContent);
    }

    dragOver(e) {
      e.preventDefault();
      if (e.currentTarget === this.selectedColumn) return;
      this.clearDragHoverStyles();
      this.hoveredColumn = e.currentTarget;
      this.hoveredColumn.classList.add("drag-hovered"); la que se pasa
      console.log("Drag Over:", this.hoveredColumn.textContent);
    }

    dragLeave(e) {
    }

    drop(e) {
      e.preventDefault();
      console.log("Drop on:", e.currentTarget.textContent);

      if (this.selectedColumn && this.hoveredColumn && this.selectedColumn !== this.hoveredColumn) {
        this.moveColumn();
      }
      this.clearDragStyles();
    }

    dragEnd(e) {
      this.clearDragStyles();
      console.log("Drag End");
    }

    moveColumn() {
      const headRow = this.tableElement.querySelector("thead tr");
      const tbody = this.tableElement.querySelector("tbody");

      if (!headRow || !tbody) return;

      const selectedIndex = Array.from(headRow.children).indexOf(this.selectedColumn);
      const hoveredIndex = Array.from(headRow.children).indexOf(this.hoveredColumn);

      if (selectedIndex === -1 || hoveredIndex === -1) return;

      if (selectedIndex < hoveredIndex) {
        headRow.insertBefore(this.selectedColumn, this.hoveredColumn.nextSibling);
      } else {
        headRow.insertBefore(this.selectedColumn, this.hoveredColumn);
      }

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

  new DraggableTableColumns();
</script>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
  $(document).ready(function () {
    $('#buscar').on('input', function () {
      var query = $(this).val();
      $.ajax({
        url: 'buscar_secciones.php',
        method: 'GET',
        data: {
          query: query
        },
        success: function (data) {
          $('#resultadoBusqueda').html(data);
        }
      });
    });
  });

  function confirmarEliminacion(id, titulo) {
    Swal.fire({
      title: `¿Estás seguro de eliminar la sección de "${titulo}"?`,
      text: "¡No podrás revertir esto!",
      icon: "warning",
      showCancelButton: true,
      confirmButtonColor: "#3085d6",
      cancelButtonColor: "#d33",
      confirmButtonText: "¡Sí, elimínala!"
    }).then((result) => {
      if (result.isConfirmed) {
        document.getElementById('eliminar-form-' + id).submit();
      }
    });
  }
</script>

<?php
mysqli_close($db);

incluirTemplate('footer');

?>