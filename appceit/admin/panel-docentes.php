<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require '../includes/funciones.php';

$nombreAdministrador = $_SESSION['nombre'] ?? 'Usuario';
$rolAdministrador = (int) $_SESSION['rol'] ?? null;
$rol = (int) $_SESSION['rol'];
require '../includes/config/database.php';
$db_mysql = conectarDB();
$conn_sqlsrv = conectarDB3();

$mensaje = $_SESSION['mensaje'] ?? null;
unset($_SESSION['mensaje']);

$pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$filtro = isset($_GET['filtro']) ? $_GET['filtro'] : 'todos';

// PRIMERO: Obtener el total de usuarios para poder validar el límite
$query_count = "SELECT COUNT(*) as total
                FROM [GestionUsuarios].[dbo].[Docentes]
                WHERE Habilitado = 1";

$resultado_count = sqlsrv_query($conn_sqlsrv, $query_count);
if ($resultado_count) {
  $row = sqlsrv_fetch_array($resultado_count, SQLSRV_FETCH_ASSOC);
  $totalUsuarios = $row['total'];
} else {
  $totalUsuarios = 0;
}

// SEGUNDO: Ahora sí validar el límite usando $totalUsuarios
$limite = isset($_GET['limite']) ? (int) $_GET['limite'] : 20;

// Validar que el límite esté entre los valores permitidos
$limitesPermitidos = [10, 20, 30, 50, 100, 200, 500];
if (!in_array($limite, $limitesPermitidos) && $limite != $totalUsuarios) {
    $limite = 20;
}

$por_pagina = $limite;
$offset = ($pagina - 1) * $por_pagina;

// Obtener el mapa de estatus
$mapa_estatus = [];
$resultado_estatus = mysqli_query($db_mysql, "SELECT IdUsuario, estatus FROM control_acceso WHERE TipoUsuario = 'docente'");
while ($row = mysqli_fetch_assoc($resultado_estatus)) {
  $mapa_estatus[$row['IdUsuario']] = (int) $row['estatus'];
}

// Configurar ordenamiento
$sortColumn = $_GET['sort'] ?? 'Nombre';
$sortDirection = $_GET['dir'] ?? 'asc';

$allowedColumns = [
  'id' => 'd.IdDocente',
  'nombre' => 'NombreCompleto',
  'matricula' => 'd.Matricula',
  'numero empleado' => 'd.NumeroEmpleado',
  'correo electronico' => 'd.CorreoElectronico'
];

$dbSortColumn = $allowedColumns[$sortColumn] ?? 'NombreCompleto';
$dbSortDirection = in_array(strtoupper($sortDirection), ['ASC', 'DESC']) ? strtoupper($sortDirection) : 'ASC';

// Consulta principal con paginación
$query_sqlsrv = "SELECT 
                    d.IdDocente as IdUsuario,
                    d.Nombre as Nom, 
                    d.ApellidoPaterno as Paterno, 
                    d.ApellidoMaterno as Materno,
                    CONCAT(d.Nombre, ' ', d.ApellidoPaterno, ' ', d.ApellidoMaterno) as NombreCompleto,
                    d.Matricula as UserName, 
                    d.CorreoElectronico as Email,
                    d.NumeroEmpleado
                 FROM [GestionUsuarios].[dbo].[Docentes] d 
                 WHERE Habilitado = 1
                 ORDER BY $dbSortColumn $dbSortDirection
                 OFFSET " . $offset . " ROWS 
                 FETCH NEXT " . $limite . " ROWS ONLY";

$resultado_sqlsrv = sqlsrv_query($conn_sqlsrv, $query_sqlsrv);
if ($resultado_sqlsrv === false)
  die("Error en consulta SQL Server: " . print_r(sqlsrv_errors(), true));

// Obtener todos los docentes
$todosLosDocentes = [];
if ($resultado_sqlsrv) {
  while ($usuario = sqlsrv_fetch_array($resultado_sqlsrv, SQLSRV_FETCH_ASSOC)) {
    $todosLosDocentes[] = $usuario;
  }
}

// Filtrar docentes según el estatus
$docentesFiltrados = [];
foreach ($todosLosDocentes as $usuario) {
  $estatus_actual = $mapa_estatus[$usuario['IdUsuario']] ?? 1;

  if ($filtro === 'todos') {
    $docentesFiltrados[] = $usuario;
  } elseif ($filtro === 'activos' && $estatus_actual === 1) {
    $docentesFiltrados[] = $usuario;
  } elseif ($filtro === 'inactivos' && $estatus_actual === 0) {
    $docentesFiltrados[] = $usuario;
  }
}

// Calcular total de páginas
$totalPaginas = ceil($totalUsuarios / $por_pagina);

$mensaje_resultado_id = $_GET["resultado"] ?? null;

// Función para generar enlaces de ordenamiento
function getSortLink($columnName, $friendlyName, $currentSort, $currentDir)
{
  $allowedColumns = [
    'id' => 'd.IdDocente',
    'nombre' => 'NombreCompleto',
    'matricula' => 'd.Matricula',
    'numero empleado' => 'd.NumeroEmpleado',
    'correo electronico' => 'd.CorreoElectronico'
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
<!-- Contenido Principal -->
<link rel="stylesheet" href="../public/css/panellibros.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  .modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
  }

  .modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    padding: 20px;
    border: none;
    border-radius: 10px;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    overflow-y: auto;
  }

  .close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
  }

  .close:hover,
  .close:focus {
    color: #000;
    text-decoration: none;
  }

  .form-group {
    margin-bottom: 15px;
  }

  .form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: bold;
    color: #333;
  }

  .form-group input,
  .form-group select {
    width: 100%;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 14px;
    box-sizing: border-box;
  }

  .form-group input:focus,
  .form-group select:focus {
    border-color: #09a787;
    outline: none;
  }

  .btn-modal {
    background-color: #09a787;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    margin-right: 10px;
  }

  .btn-modal:hover {
    background-color: #087565;
  }

  .btn-cancel {
    background-color: #dc3545;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
  }

  .btn-cancel:hover {
    background-color: #c82333;
  }

  .btn-edit {
    background-color: #28a745;
    color: white;
    padding: 5px 10px;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    font-size: 12px;
    margin-right: 5px;
  }

  .btn-edit:hover {
    background-color: #218838;
  }

  .btn-delete {
    background-color: #dc3545;
    color: white;
    padding: 5px 10px;
    border: none;
    border-radius: 3px;
    cursor: pointer;
    font-size: 12px;
  }

  .btn-delete:hover {
    background-color: #c82333;
  }

  .table--container table {
    width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
  }

  .table--container td,
  .table--container th {
    word-wrap: break-word;
  }

  @media (max-width: 768px) {
    .modal-content {
      width: 95%;
      margin: 10% auto;
      padding: 15px;
    }
  }
</style>

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
      <h2>Panel de Usuarios-Docentes</h2>
      <div class="card--container">
        <h3 class="main--title">Datos actuales</h3>
        <div class="card--wrapper">
          <div class="payment--card">
            <div class="card--header">
              <div class="amount">
                <span class="title"> Total de Docentes </span>
                <span class="amount--value"><?php echo $totalUsuarios; ?></span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="user--info">
      <div class="search--box">
        <i class="fas fa-search"></i>
        <input type="text" id="buscar-usuarios" placeholder="Buscar docentes" />
      </div>

      <div style="margin-left: 15px;">
        <select id="filtro-estatus"
          style="padding: 8px 12px; border: 1px solid #ccc; border-radius: 5px; font-size: 14px;">
          <option value="todos" <?php echo (!isset($_GET['filtro']) || $_GET['filtro'] === 'todos') ? 'selected' : ''; ?>>
            Todos</option>
          <option value="activos" <?php echo (isset($_GET['filtro']) && $_GET['filtro'] === 'activos') ? 'selected' : ''; ?>>Activos</option>
          <option value="inactivos" <?php echo (isset($_GET['filtro']) && $_GET['filtro'] === 'inactivos') ? 'selected' : ''; ?>>Inactivos</option>
        </select>
      </div>

      <img src="../public/img/logouttn.png" alt="Foto de perfil" />
    </div>

    <div class="tabular--wrapper" style="padding: 0 !important;">
      <?php if ($rolAdministrador === 1 || $rolAdministrador === 2 || $rolAdministrador === 3): ?>
        <div class="tabular--botones">
          <a title="Exportar PDF" id="btnPDF" href="Reporte de usuarios.php" target="_blank"><i
              class="fas fa-file-pdf"></i> Exportar PDF</a>
          <a title="Exportar Excel" id="btnExcel" href="Reporte de usuarios excel.php" target="_blank"><i
              class="fas fa-file-excel"></i> Exportar Excel</a>
        </div>


        <?php if ($mensaje && is_array($mensaje)): ?>
          <p class="alerta <?php echo htmlspecialchars($mensaje['tipo']); ?> fade-out">
            <?php echo htmlspecialchars($mensaje['texto']); ?>
          </p>
        <?php endif; ?>
      <?php endif; ?>
<!-- Selector de límite de registros -->
<div class="limite-selector">
    <label for="limiteDocentes">Mostrar:</label>
    <select id="limiteDocentes" onchange="cambiarLimite(this.value)">
        <?php
        // Opciones base
        $opcionesBase = [10, 20, 30, 50, 100, 200, 500];
        
        // Generar opciones dinámicamente
        foreach ($opcionesBase as $opcion) {
            // Solo mostrar la opción si es menor o igual al total
            if ($opcion <= $totalUsuarios) {
                $selected = ($limite == $opcion) ? 'selected' : '';
                echo "<option value=\"{$opcion}\" {$selected}>{$opcion} docentes</option>";
            }
        }
        
        // Agregar opción "Todos" si hay más docentes que la última opción
        if ($totalUsuarios > max($opcionesBase)) {
            $selected = ($limite == $totalUsuarios) ? 'selected' : '';
            echo "<option value=\"{$totalUsuarios}\" {$selected}>Todos ({$totalUsuarios})</option>";
        } elseif ($totalUsuarios > 0 && !in_array($totalUsuarios, $opcionesBase)) {
            $selected = ($limite == $totalUsuarios) ? 'selected' : '';
            echo "<option value=\"{$totalUsuarios}\" {$selected}>Todos ({$totalUsuarios})</option>";
        }
        ?>
    </select>
    <span class="limite-info">
        Mostrando <?php echo number_format(min($offset + 1, $totalUsuarios)); ?> -
        <?php echo number_format(min($offset + $limite, $totalUsuarios)); ?> de <?php echo number_format($totalUsuarios); ?> docentes
    </span>
</div>
      <div class="table--container">
        <table>
          <thead>
            <tr>
              <th draggable="true"><?php echo getSortLink('id', 'ID', $sortColumn, $sortDirection); ?></th>
              <th draggable="true"><?php echo getSortLink('nombre', 'Nombre', $sortColumn, $sortDirection); ?></th>
              <th draggable="true"><?php echo getSortLink('matricula', 'Matrícula', $sortColumn, $sortDirection); ?>
              </th>
              <th draggable="true">
                <?php echo getSortLink('numero empleado', 'Número de Empleado', $sortColumn, $sortDirection); ?></th>
              <th draggable="true">
                <?php echo getSortLink('correo electronico', 'Correo electrónico', $sortColumn, $sortDirection); ?></th>
              <th draggable="true">Acciones</th>
            </tr>
          </thead>
          <tbody id="usuarios-tbody">
            <?php
            $contadorFilas = ($pagina - 1) * 20 + 1;
            if (count($docentesFiltrados) > 0) {
              foreach ($docentesFiltrados as $usuario) {
                $estatus_actual = $mapa_estatus[$usuario['IdUsuario']] ?? 1;
                $nombre_completo = ucwords(strtolower(trim(
                  $usuario['Nom'] . " " . $usuario['Paterno'] . " " . $usuario['Materno']
                )));
                ?>
                <tr style="<?php echo ($estatus_actual == 0) ? 'background-color: #ffebee; opacity: 0.7;' : ''; ?>">
                  <td class="textosm"><?php echo $contadorFilas; ?></td>
                  <td class="textosm"><?php echo htmlspecialchars($nombre_completo); ?></td>
                  <td class="textosm"><?php echo htmlspecialchars($usuario['UserName']); ?></td>
                  <td class="textosm"><?php echo htmlspecialchars($usuario['NumeroEmpleado'] ?? 'N/A'); ?></td>
                  <td class="textosm">
                    <?php if (!empty($usuario['Email'])): ?>
                      <a target="_blank"
                        href="mailto:<?php echo htmlspecialchars($usuario['Email']); ?>"><?php echo htmlspecialchars($usuario['Email']); ?></a>
                    <?php else: ?>
                      No disponible
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="botones--accion--container">
                      <?php if ($rolAdministrador === 1 || $rolAdministrador === 2 || $rolAdministrador === 3): ?>
                        <?php if ($estatus_actual === 1): ?>
                          <a href="#" class="btn-delete"
                            onclick="confirmarEstatusDocente(event, <?php echo $usuario['IdUsuario']; ?>, 0)"
                            title="Desactivar Usuario">
                            <i class="fas fa-user-slash"></i>
                          </a>
                          <a href="escanear_qr_docente.php?usuarioId=<?php echo $usuario['IdUsuario']; ?>">
                            <button title="Prestamo" class="btnAceptado">Préstamo</button>
                          </a>
                        <?php else: ?>
                          <a href="#" class="btn-edit"
                            onclick="confirmarEstatusDocente(event, <?php echo $usuario['IdUsuario']; ?>, 1)"
                            title="Activar Usuario">
                            <i class="fas fa-user-check"></i>
                          </a>
                        <?php endif; ?>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
                <?php
                $contadorFilas++;
              }
            } else {
              ?>
              <tr>
                <td colspan="6">No hay docentes
                  <?php echo $filtro === 'activos' ? 'activos' : ($filtro === 'inactivos' ? 'inactivos' : ''); ?> en esta
                  página.</td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>

   <div class="paginacion_contenedor">
    <?php if ($pagina > 1): ?>
        <a href="?pagina=<?php echo $pagina - 1; ?>&limite=<?php echo $limite; ?>&filtro=<?php echo $filtro; ?>&sort=<?php echo $sortColumn; ?>&dir=<?php echo $sortDirection; ?>">&laquo; Anterior</a>
    <?php endif; ?>

    <?php
    $maxLinks = 5;
    $start = max(1, $pagina - floor($maxLinks / 2));
    $end = min($totalPaginas, $start + $maxLinks - 1);

    if ($start > 1) {
        echo '<a href="?pagina=1&limite=' . $limite . '&filtro=' . $filtro . '&sort=' . $sortColumn . '&dir=' . $sortDirection . '">1</a>';
        if ($start > 2) {
            echo '<span>...</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++): ?>
        <a href="?pagina=<?php echo $i; ?>&limite=<?php echo $limite; ?>&filtro=<?php echo $filtro; ?>&sort=<?php echo $sortColumn; ?>&dir=<?php echo $sortDirection; ?>" 
           class="<?php if ($i == $pagina) echo 'active'; ?>">
            <?php echo $i; ?>
        </a>
    <?php endfor; ?>

    <?php
    if ($end < $totalPaginas) {
        if ($end < $totalPaginas - 1) {
            echo '<span>...</span>';
        }
        echo '<a href="?pagina=' . $totalPaginas . '&limite=' . $limite . '&filtro=' . $filtro . '&sort=' . $sortColumn . '&dir=' . $sortDirection . '">' . $totalPaginas . '</a>';
    }
    ?>

    <?php if ($pagina < $totalPaginas): ?>
        <a href="?pagina=<?php echo $pagina + 1; ?>&limite=<?php echo $limite; ?>&filtro=<?php echo $filtro; ?>&sort=<?php echo $sortColumn; ?>&dir=<?php echo $sortDirection; ?>">Siguiente &raquo;</a>
    <?php endif; ?>
</div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    // Búsqueda de docentes con paginación y filtros
    $(document).ready(function () {
      let searchTimeout;
      let currentQuery = ''; 
      let currentPage = 1;
      let currentFilter = 'todos';

function realizarBusqueda(query, pagina = 1, filtro = 'todos') {
    const urlParams = new URLSearchParams(window.location.search);
    const limite = urlParams.get('limite') || 20;
    
    $.ajax({
        url: 'buscar_docente.php',
        method: 'GET',
        data: {
            query: query,
            pagina: pagina,
            filtro: filtro,
            limite: limite
        },
        dataType: 'json',
        success: function (response) {
            $('#usuarios-tbody').html(response.html);
            $('.paginacion_contenedor').html(response.paginacion);

            if (response.total > 0) {
                $('.paginacion_contenedor').show();
            } else {
                $('.paginacion_contenedor').hide();
            }
        },
        error: function () {
            $('#usuarios-tbody').html('<tr><td colspan="6">Error en la búsqueda.</td></tr>');
            $('.paginacion_contenedor').hide();
        }
    });
}

      // Evento de búsqueda
      $('#buscar-usuarios').on('input', function () {
        clearTimeout(searchTimeout);
        const query = $(this).val().trim();
        currentQuery = query;
        currentPage = 1;

        if (query === '') {
          window.location.href = window.location.pathname;
          return;
        }

        if (query.length < 2) {
          $('#usuarios-tbody').html('<tr><td colspan="6">Ingrese al menos 2 caracteres</td></tr>');
          $('.paginacion_contenedor').hide();
          return;
        }

        searchTimeout = setTimeout(() => {
          realizarBusqueda(query, 1, currentFilter);
        }, 300);
      });


      
      // Evento para los filtros
      $(document).on('change', '#filtro-estatus', function () {
        currentFilter = $(this).val();
        currentPage = 1;

        if (currentQuery.length >= 2) {
          realizarBusqueda(currentQuery, 1, currentFilter);
        } else {
          // Si no hay búsqueda, recargar con el filtro en la URL
          window.location.href = window.location.pathname + '?filtro=' + currentFilter;
        }
      });

      // Evento para los enlaces de paginación de búsqueda
      $(document).on('click', '.pagina-busqueda', function (e) {
        e.preventDefault();
        const pagina = $(this).data('pagina');
        currentPage = pagina;
        realizarBusqueda(currentQuery, pagina, currentFilter);
      });

      // Función global para confirmar estatus SIN recargar página
      window.confirmarEstatusDocente = function (event, id, nuevoEstatus) {
        event.preventDefault();
        const accion = nuevoEstatus === 0 ? 'desactivar' : 'activar';
        const titulo = `¿Deseas ${accion} a este docente?`;
        const texto = nuevoEstatus === 0 ? "El docente no podrá iniciar sesión." : "El docente podrá iniciar sesión nuevamente.";
        const botonConfirmar = `Sí, ${accion}`;

        Swal.fire({
          title: titulo,
          text: texto,
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: nuevoEstatus === 0 ? '#d33' : '#28a745',
          cancelButtonColor: '#3085d6',
          confirmButtonText: botonConfirmar,
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) {
            $.ajax({
              url: 'actualizar_estatus_docente.php',
              method: 'POST',
              data: { id: id, estatus: nuevoEstatus },
              dataType: 'json',
              success: function (response) {
                if (response.success) {
                  Swal.fire({
                    title: '¡Éxito!',
                    text: response.message,
                    icon: 'success',
                    timer: 1500,
                    showConfirmButton: false
                  }).then(() => {
                    if (currentQuery.length >= 2) {
                      realizarBusqueda(currentQuery, currentPage, currentFilter);
                    } else {
                      window.location.reload();
                    }
                  });
                } else {
                  Swal.fire('Error', response.message, 'error');
                }
              },
              error: function (jqXHR) {
                const errorMsg = jqXHR.responseJSON ? jqXHR.responseJSON.message : 'No se pudo completar la acción.';
                Swal.fire('Error', errorMsg, 'error');
              }
            });
          }
        });
      };
    });

    // Función para cambiar el límite de registros por página
function cambiarLimite(limite) {
    const urlParams = new URLSearchParams(window.location.search);
    
    urlParams.set('limite', limite);
    
    urlParams.set('pagina', '1');
    
    const nuevaURL = window.location.pathname + '?' + urlParams.toString();
    
    window.location.href = nuevaURL;
}

    // Clase para el drag and drop de columnas
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
      }

      dragOver(e) {
        e.preventDefault();
        if (e.currentTarget === this.selectedColumn) return;

        this.clearDragHoverStyles();
        this.hoveredColumn = e.currentTarget;
        this.hoveredColumn.classList.add("drag-hovered");
      }

      dragLeave(e) {
        // Se puede limpiar aquí si es necesario
      }

      drop(e) {
        e.preventDefault();

        if (this.selectedColumn && this.hoveredColumn && this.selectedColumn !== this.hoveredColumn) {
          this.moveColumn();
        }
        this.clearDragStyles();
      }

      dragEnd(e) {
        this.clearDragStyles();
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

    // Auto-ocultar mensajes
    setTimeout(function () {
      document.querySelectorAll('.fade-out').forEach(function (element) {
        element.style.opacity = '0';
        element.style.transition = 'opacity 1.5s ease-out';
        setTimeout(function () {
          element.style.display = 'none';
        }, 500);
      });
    }, 1000);
  </script>

  <?php
  sqlsrv_close($conn_sqlsrv);
  mysqli_close($db_mysql);
  incluirTemplate('footer');
  ?>
</div>