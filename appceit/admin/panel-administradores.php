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
$rolAdministrador = isset($_SESSION['rol']) ? $_SESSION['rol'] : null;

$idAdministrador = isset($_SESSION['id']) ? $_SESSION['id'] : null;

// Conexión a base de datos
require '../includes/config/database.php';
$db = conectarDB();

$sortColumn = $_GET['sort'] ?? 'Nombre';
$sortDirection = $_GET['dir'] ?? 'asc';

$allowedColumns = [
  'nombre' => 'a.nombre',
  'matricula' => 'a.matricula',
  'tipo de administrador' => 'r.nombreRol',

];

$dbSortColumn = $allowedColumns[$sortColumn] ?? 'nombre';
$dbSortDirection = in_array(strtoupper($sortDirection), ['ASC', 'DESC']) ? strtoupper($sortDirection) : 'ASC';


if ($rolAdministrador ==1) {
  $query = "SELECT id, 
a.nombre,
a.matricula,
r.nombreRol,
a.rol,
a.registrado
FROM administradores a
Left JOIN rol r ON a.rol = r.IdRol
ORDER BY $dbSortColumn $dbSortDirection";
$resultado = mysqli_query($db, $query);
}else {
  $query = "SELECT id, 
a.nombre,
a.matricula,
r.nombreRol,
a.rol,
a.registrado
FROM administradores a 
Left JOIN rol r ON a.rol = r.IdRol
WHERE a.rol between 2 and 3
ORDER BY $dbSortColumn $dbSortDirection";
$resultado = mysqli_query($db, $query);
}

// Muestra un mensaje condicional
$resultadoMensaje = $_GET["resultado"] ?? null;

if ($_SERVER['REQUEST_METHOD'] === "POST") {
  $id = $_POST['id'];
  $id = filter_var($id, FILTER_VALIDATE_INT);

  if ($id) {
    // Elimina el administrador
    $query = "DELETE FROM administradores WHERE id = {$id}";

    $resultado = mysqli_query($db, $query);

    if ($resultado) {
      header('Location: panel-administradores.php?resultado=2');
      exit;
    }
  }
}

function getSortLink($columnName, $friendlyName, $currentSort, $currentDir)
{
  $allowedColumns = [
    'nombre' => 'a.nombre',
    'matricula' => 'a.matricula',
    'tipo de administrador' => 'r.nombreRol',

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

<style>
  th[draggable="true"] {
    cursor: grab;
  }

  th[draggable="true"]:active {
    cursor: grabbing;
  }

  .drag-hovered {
    border-right: 2px solid #09a787;
  }

  .drag-selected {
    opacity: 0.7;
  }
</style>

<link rel="stylesheet" href="../public/css/paneladministrador.css">

<!-- Contenido Principal -->
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
      <span>Bienvenido, <?php echo htmlspecialchars($nombreAdministrador); ?></span>
      <h2>Panel de administradores</h2>
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
    <h3 class="main--title">Administradores</h3>
    <div class="tabular--botones">
      <a href="./administradores/crear-administrador.php">
        <button title="Añadir Administrador" class="btnAgregar">
          <i class="fas fa-plus"></i> Registrar administrador
        </button>
      </a>
    </div>
    <?php if (intval($resultadoMensaje) === 1): ?>
      <p class="alerta exito fade-out">Administrador agregado correctamente</p>
      <script>
        setTimeout(function () {
          window.location.href = "<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>";
        }, 1000);
      </script>
    <?php elseif (intval($resultadoMensaje) === 2): ?>
      <p class="alerta exito fade-out">Administrador eliminado correctamente</p>
      <script>
        setTimeout(function () {
          window.location.href = "<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>";
        }, 1000);
      </script>
    <?php elseif (intval($resultadoMensaje) === 3): ?>
      <p class="alerta exito fade-out">Administrador actualizado correctamente</p>
      <script>
        setTimeout(function () {
          window.location.href = "<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>";
        }, 1000);
      </script>
    <?php endif; ?>
    <div class="table--container">
      <table>
        <thead>
          <tr>
            <th draggable="true">#</th>
            <th draggable="true"><?php echo getSortLink('nombre', 'Nombre', $sortColumn, $sortDirection); ?></th>
            <th draggable="true"><?php echo getSortLink('matricula', 'Matrícula', $sortColumn, $sortDirection); ?></th> 
            <th draggable="true"><?php echo getSortLink('tipo de administrador', 'Tipo de administrador', $sortColumn, $sortDirection); ?></th>
            <th draggable="true">Acciones</th>
          </tr>
        </thead>
        <tbody id="resultadoBusqueda">
          <?php $fila = 1;
          while ($administrador = mysqli_fetch_assoc($resultado)): ?>
            <tr>
              <td><?php echo $fila; ?></td>
              <td><?php echo htmlspecialchars($administrador['nombre']); ?></td>
              <td><?php echo htmlspecialchars($administrador['matricula']); ?></td>
              <td>
                <?php if ($administrador['rol'] == 1) {
                  echo "MÁSTER";
                } else if ($administrador['rol'] == 2) {
                  echo "ADMINISTRADOR DE GENERAL";
                } else {
                  echo "ADMINISTRADOR";
                }
                ; ?>

              </td>
              <td>
                <div class="botones--accion--container">
                  <?php if ($_SESSION['rol'] == 1 || $_SESSION['rol'] == 2 || $_SESSION['id'] == $administrador['id']): ?>
                    <?php
                    // Administrador General no puede editar Masters
                    $puedeEditar = true;
                    if ($_SESSION['rol'] == 2 && $administrador['rol'] == 1) {
                      $puedeEditar = false;
                    }
                    ?>
                    <?php if ($puedeEditar): ?>
                      <a href="./administradores/actualizar-administrador.php?id=<?php echo $administrador['id']; ?>">
                        <button title="Editar" class="btnAceptado">Editar</button>
                      </a>
                    <?php endif; ?>
                  <?php endif; ?>

                  <?php if ($_SESSION['rol'] == 1 || $_SESSION['rol'] == 2): ?>
                    <?php
                    // Solo Master puede eliminar Masters
                    $puedeEliminar = true;
                    if ($administrador['rol'] == 1 && $_SESSION['rol'] != 1) {
                      $puedeEliminar = false;
                    }
                    // No permitir eliminarse a sí mismo
                    if ($_SESSION['id'] == $administrador['id']) {
                      $puedeEliminar = false;
                    }
                    ?>
                    <?php if ($puedeEliminar): ?>
                      <form id="eliminar-form-<?php echo $administrador['id']; ?>" method="POST">
                        <input type="hidden" name="id" value="<?php echo $administrador['id']; ?>">
                        <button type="button" title="Eliminar" class="btnCancelar"
                          onclick="confirmarEliminacion(<?php echo $administrador['id']; ?>, '<?php echo addslashes($administrador['nombre']); ?>')">
                          Eliminar
                        </button>
                      </form>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php $fila++;
          endwhile; ?>
        </tbody>
      </table>
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
      this.hoveredColumn.classList.add("drag-hovered");
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
  $(document).ready(function () {
    $('#buscar').on('input', function () {
      var query = $(this).val();
      $.ajax({
        url: 'buscar_administradores.php',
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
      title: `¿Estás seguro de eliminar al administrador "${titulo}"?`,
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
incluirTemplate('footer');
?>