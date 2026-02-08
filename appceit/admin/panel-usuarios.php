<?php
require '../includes/funciones.php';
$auth = adminAutenticado();

if (!$auth) {
  header('Location: login.php');
  exit;
}

$nombreAdministrador = $_SESSION['nombre'] ?? 'Usuario';
$rolAdministrador = (int) $_SESSION['rol'] ?? null;
$rol = (int) $_SESSION['rol'];
require '../includes/config/database.php';
$db_mysql = conectarDB();
$conn_sqlsrv = conectarDB3();

$mensaje = $_SESSION['mensaje'] ?? null;
unset($_SESSION['mensaje']);


$carreraFiltro = $_GET['carrera'] ?? null;
$filtroEstatus = $_GET['filtro'] ?? 'todos';

//* Obtener el límite de registros por página (por defecto 20)
$limite = isset($_GET['limite']) ? (int) $_GET['limite'] : 20;

// Validar que el límite esté entre los valores permitidos (ajustado para grandes volúmenes)
$limitesPermitidos = [10, 20, 30, 50, 100, 200, 500, 1000, 2000];
if (!in_array($limite, $limitesPermitidos) && $limite != $totalUsuarios) {
    $limite = 20;
}

$pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
$por_pagina = $limite;
$offset = ($pagina - 1) * $por_pagina;

$mapa_estatus = [];
$resultado_estatus = mysqli_query($db_mysql, "SELECT IdUsuario, estatus FROM control_acceso WHERE TipoUsuario = 'alumno'");
while ($row = mysqli_fetch_assoc($resultado_estatus)) {
  $mapa_estatus[$row['IdUsuario']] = (int)$row['estatus'];
}

$sortColumn = $_GET['sort'] ?? 'Nombre';
$sortDirection = $_GET['dir'] ?? 'asc';

$allowedColumns = [
    'id' => 'a.IdAlumno',
    'nombre' => 'NombreCompleto',
    'matricula' => 'a.Matricula',
    'carrera' => 'c.Nombre',
    'cuatrimestre' => 'a.Cuatrimestre',
    'correo electronico' => 'a.CorreoElectronico'
];

$dbSortColumn = $allowedColumns[$sortColumn] ?? 'NombreCompleto';
$dbSortDirection = in_array(strtoupper($sortDirection), ['ASC', 'DESC']) ? strtoupper($sortDirection) : 'ASC';

$query_sqlsrv = "SELECT 
                    a.IdAlumno as IdUsuario,
                    a.Nombre as Nom, 
                    a.ApellidoPaterno as Paterno, 
                    a.ApellidoMaterno as Materno, 
                    CONCAT(a.Nombre, ' ', a.ApellidoPaterno, ' ', a.ApellidoMaterno) as NombreCompleto,
                    a.Matricula as UserName, 
                    a.CorreoElectronico as Email, 
                    a.IdCarrera,
                    a.Cuatrimestre,
                    a.Habilitado as Estado,
                    c.Nombre as CarreraNom
                 FROM [GestionUsuarios].[dbo].[Alumnos] a
                 LEFT JOIN [GestionUsuarios].[dbo].[Carreras] c ON a.IdCarrera = c.IdCarrera
                 WHERE a.Habilitado = 1";

$params_sqlsrv = [];
$conditions_sqlsrv = [];

if ($filtroEstatus !== 'todos') {
    if ($filtroEstatus === 'activos') {
        $conditions_sqlsrv[] = "a.Habilitado = 1";
    } elseif ($filtroEstatus === 'inactivos') {
        $conditions_sqlsrv[] = "a.Habilitado = 0";
    }
} else {
    $conditions_sqlsrv[] = "a.Habilitado = 1";
}

if ($carreraFiltro) {
  $conditions_sqlsrv[] = "c.Nombre LIKE ?";
  $params_sqlsrv[] = '%' . $carreraFiltro . '%';
}

if (!empty($conditions_sqlsrv)) {
  $query_sqlsrv .= " AND " . implode(" AND ", $conditions_sqlsrv);
}

$query_sqlsrv .= " ORDER BY $dbSortColumn $dbSortDirection";

$query_sqlsrv .= " 
OFFSET " . $offset . " ROWS 
FETCH NEXT " . $limite . " ROWS ONLY";

$resultado_sqlsrv = sqlsrv_query($conn_sqlsrv, $query_sqlsrv, $params_sqlsrv);
if ($resultado_sqlsrv === false)
  die("Error en consulta SQL Server: " . print_r(sqlsrv_errors(), true));

$query_count = "SELECT COUNT(*) as total
                FROM [GestionUsuarios].[dbo].[Alumnos] a
                LEFT JOIN [GestionUsuarios].[dbo].[Carreras] c ON a.IdCarrera = c.IdCarrera
                WHERE 1=1";

$params_count = [];

// Aplicar mismo filtro de estatus para el conteo
if ($filtroEstatus !== 'todos') {
    if ($filtroEstatus === 'activos') {
        $query_count .= " AND a.Habilitado = 1";
    } elseif ($filtroEstatus === 'inactivos') {
        $query_count .= " AND a.Habilitado = 0";
    }
} else {
    $query_count .= " AND a.Habilitado = 1";
}

if ($carreraFiltro) {
  $query_count .= " AND c.Nombre LIKE ?";
  $params_count[] = '%' . $carreraFiltro . '%';
}

$resultado_count = sqlsrv_query($conn_sqlsrv, $query_count, $params_count);
if ($resultado_count) {
  $row = sqlsrv_fetch_array($resultado_count, SQLSRV_FETCH_ASSOC);
  $totalUsuarios = $row['total'];
} else {
  $totalUsuarios = 0;
}

$totalPaginas = ceil($totalUsuarios / $por_pagina);

$queryCarrerasSQL = "SELECT DISTINCT IdCarrera, Nombre FROM [GestionUsuarios].[dbo].[Carreras] ORDER BY Nombre";
$resultadoCarreras = sqlsrv_query($conn_sqlsrv, $queryCarrerasSQL);

$mensaje_resultado_id = $_GET["resultado"] ?? null;

// Función para generar enlaces de ordenamiento
function getSortLink($columnName, $friendlyName, $currentSort, $currentDir) {
    $allowedColumns = [
        'id' => 'ID',
        'nombre' => 'Nombre',
        'matricula' => 'Matrícula',
        'carrera' => 'Carrera',
        'cuatrimestre' => 'Cuatrimestre',
        'correo electronico' => 'Correo electrónico'
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

  .filtros-container {
    display: flex;
    gap: 15px;
    align-items: center;
    margin: 15px 0;
    flex-wrap: wrap;
  }

  .filtro-select {
    padding: 8px 12px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 14px;
    background-color: white;
    min-width: 150px;
  }

  @media (max-width: 768px) {
    .modal-content {
      width: 95%;
      margin: 10% auto;
      padding: 15px;
    }
    
    .filtros-container {
      flex-direction: column;
      align-items: flex-start;
    }
    
    .filtro-select {
      width: 100%;
    }
  }
</style>

<div class="container main--content">
  <div class="header--wrapper">
    <div class="header--title">
      <span style="display: flex; border: 2.3px solid #09a787; padding: 2px; margin-bottom: 10px; border-radius: 5px; color: #09a787; width: 230px; text-transform: uppercase">
        <?php if ($rolAdministrador === 1) {
          echo 'Máster';
        } elseif($rolAdministrador === 2) {
          echo 'Administrador general';
        }else{
          echo 'Administrador';
        } ?>
      </span>
      <span>Bienvenido, <?php echo ($nombreAdministrador); ?></span>
      <h2>Panel de Usuarios-Alumnos</h2>
      <div class="card--container">
        <h3 class="main--title">Datos actuales</h3>
        <div class="card--wrapper">
          <div class="payment--card">
            <div class="card--header">
              <div class="amount">
                <span class="title"> Total de Alumnos </span>
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
        <input type="text" id="buscar-usuarios" placeholder="Buscar usuarios" />
      </div>
      <img src="../public/img/logouttn.png" alt="Foto de perfil" />
    </div>
  </div>

  <!-- Filtros -->
  <div class="filtros-container">
    <select id="filtro-estatus" class="filtro-select">
      <option value="todos" <?php echo $filtroEstatus === 'todos' ? 'selected' : ''; ?>>Todos los usuarios</option>
      <option value="activos" <?php echo $filtroEstatus === 'activos' ? 'selected' : ''; ?>>Usuarios activos</option>
      <option value="inactivos" <?php echo $filtroEstatus === 'inactivos' ? 'selected' : ''; ?>>Usuarios inactivos</option>
    </select>
    
    <?php if ($resultadoCarreras): ?>
    <select id="filtro-carrera" class="filtro-select">
      <option value="">Todas las carreras</option>
      <?php 
      // Reiniciar el puntero del resultado de carreras
      sqlsrv_free_stmt($resultadoCarreras);
      $resultadoCarreras = sqlsrv_query($conn_sqlsrv, $queryCarrerasSQL);
      while ($carrera = sqlsrv_fetch_array($resultadoCarreras, SQLSRV_FETCH_ASSOC)): ?>
        <option value="<?php echo htmlspecialchars($carrera['Nombre']); ?>" 
                <?php echo ($carreraFiltro === $carrera['Nombre']) ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars($carrera['Nombre']); ?>
        </option>
      <?php endwhile; ?>
    </select>
    <?php endif; ?>
  </div>
  
  <div class="tabular--wrapper" style="padding: 0 !important;">
    <?php if ($rolAdministrador === 1 || $rolAdministrador === 2 || $rolAdministrador === 3): ?>
      <div class="tabular--botones">
        <a title="Exportar PDF" id="btnPDF" href="Reporte de usuarios.php?filtro=<?php echo $filtroEstatus; ?><?php echo $carreraFiltro ? '&carrera=' . urlencode($carreraFiltro) : ''; ?>" target="_blank">
          <i class="fas fa-file-pdf"></i> Exportar PDF
        </a>
        <a title="Exportar Excel" id="btnExcel" href="Reporte de usuarios excel.php?filtro=<?php echo $filtroEstatus; ?><?php echo $carreraFiltro ? '&carrera=' . urlencode($carreraFiltro) : ''; ?>" target="_blank">
          <i class="fas fa-file-excel"></i> Exportar Excel
        </a>
      </div>

      <?php if ($mensaje && is_array($mensaje)): ?>
        <p class="alerta <?php echo htmlspecialchars($mensaje['tipo']); ?> fade-out">
          <?php echo htmlspecialchars($mensaje['texto']); ?>
        </p>
      <?php endif; ?>
      <?php if (!empty($errores)): ?>
        <div class="alerta error">
          <strong>Errores encontrados:</strong>
          <ul>
            <?php foreach ($errores as $error): ?>
              <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    <?php endif; ?>
    
    <!-- Selector de límite de registros -->
<!-- Selector de límite de registros -->
<div class="limite-selector">
    <label for="limiteUsuarios">Mostrar:</label>
    <select id="limiteUsuarios" onchange="cambiarLimite(this.value)">
        <?php
        // Opciones base para grandes volúmenes de datos
        $opcionesBase = [10, 20, 30, 50, 100, 200, 500, 1000, 2000];
        
        // Generar opciones dinámicamente
        foreach ($opcionesBase as $opcion) {
            // Solo mostrar la opción si es menor o igual al total
            if ($opcion <= $totalUsuarios) {
                $selected = ($limite == $opcion) ? 'selected' : '';
                echo "<option value=\"{$opcion}\" {$selected}>{$opcion} usuarios</option>";
            }
        }
        
        // Agregar opción "Todos" si hay más usuarios que la última opción
        if ($totalUsuarios > max($opcionesBase)) {
            $selected = ($limite == $totalUsuarios) ? 'selected' : '';
            echo "<option value=\"{$totalUsuarios}\" {$selected}>Todos ({$totalUsuarios})</option>";
        } elseif ($totalUsuarios > 0 && !in_array($totalUsuarios, $opcionesBase)) {
            // Si el total no está en las opciones base pero es menor que 2000
            $selected = ($limite == $totalUsuarios) ? 'selected' : '';
            echo "<option value=\"{$totalUsuarios}\" {$selected}>Todos ({$totalUsuarios})</option>";
        }
        ?>
    </select>
    <span class="limite-info">
        Mostrando <?php echo number_format(min($offset + 1, $totalUsuarios)); ?> -
        <?php echo number_format(min($offset + $limite, $totalUsuarios)); ?> de <?php echo number_format($totalUsuarios); ?> usuarios
    </span>
</div>

    <div class="table--container">
      <table>
        <thead>
         <thead>
    <tr>
        <th draggable="true"><?php echo getSortLink('id', 'ID', $sortColumn, $sortDirection); ?></th>
        <th draggable="true"><?php echo getSortLink('nombre', 'Nombre', $sortColumn, $sortDirection); ?></th>
        <th draggable="true"><?php echo getSortLink('matricula', 'Matrícula', $sortColumn, $sortDirection); ?></th>
        <th draggable="true"><?php echo getSortLink('carrera', 'Carrera', $sortColumn, $sortDirection); ?></th>
        <th draggable="true"><?php echo getSortLink('cuatrimestre', 'Cuatrimestre', $sortColumn, $sortDirection); ?></th>
        <th draggable="true"><?php echo getSortLink('correo electronico', 'Correo electrónico', $sortColumn, $sortDirection); ?></th>
        <th draggable="true">Acciones</th>
    </tr>
</thead>
        </thead>
        <tbody id="usuarios-tbody">
          <?php
          $contadorFilas = ($pagina - 1) * 20 + 1;
          if ($resultado_sqlsrv) {
            while ($usuario = sqlsrv_fetch_array($resultado_sqlsrv, SQLSRV_FETCH_ASSOC)) {
              $estatus_sistema = $mapa_estatus[$usuario['IdUsuario']] ?? 1;
              $estatus_gestion = $usuario['Estado'];
              $nombre_completo = ucwords(strtolower(trim(
                $usuario['Nom'] . " " . $usuario['Paterno'] . " " . $usuario['Materno']
              )));
              
              // Determinar el estilo de la fila basado en ambos estatus
              $estilo_fila = '';
              if ($estatus_sistema === 0 || $estatus_gestion === 0) {
                $estilo_fila = 'background-color: #ffebee; opacity: 0.7;';
              }
              ?>
              <tr style="<?php echo $estilo_fila; ?>">
                <td class="textosm"><?php echo $contadorFilas; ?></td>
                <td class="textosm"><?php echo htmlspecialchars($nombre_completo); ?></td>
                <td class="textosm"><?php echo htmlspecialchars($usuario['UserName']); ?></td>
                <td class="textosm"><?php echo htmlspecialchars($usuario['CarreraNom'] ?? 'N/A'); ?></td>
                <td class="textosm"><?php echo htmlspecialchars($usuario['Cuatrimestre'] ?? 'N/A'); ?></td>
                <td class="textosm">
                  <?php if (!empty($usuario['Email'])): ?>
                    <a target="_blank" href="mailto:<?php echo htmlspecialchars($usuario['Email']); ?>"><?php echo htmlspecialchars($usuario['Email']); ?></a>
                  <?php else: ?>
                    No disponible
                  <?php endif; ?>
                </td>
              
                <td>
                  <div class="botones--accion--container">
                    <?php if ($rolAdministrador === 1 || $rolAdministrador === 2 || $rolAdministrador === 3): ?>
                      <?php if ($estatus_sistema === 1): ?>
                        <a href="#" class="btn-delete" onclick="confirmarEstatus(event, <?php echo $usuario['IdUsuario']; ?>, 0)" title="Desactivar Usuario">
                          <i class="fas fa-user-slash"></i>
                        </a>
                        <a href="escanear-qr-usuario-interno.php?usuarioId=<?php echo $usuario['IdUsuario']; ?>">
                          <button title="Prestamo" class="btnAceptado">Préstamo</button>
                        </a>
                      <?php else: ?>
                        <a href="#" class="btn-edit" onclick="confirmarEstatus(event, <?php echo $usuario['IdUsuario']; ?>, 1)" title="Activar Usuario">
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
              <td colspan="9">No hay resultados para la búsqueda o filtros aplicados.</td>
            </tr>
          <?php } ?>
        </tbody>
      </table>
    </div>
<div class="paginacion_contenedor">
    <?php if ($pagina > 1): ?>
        <a href="?pagina=<?php echo $pagina - 1; ?>&limite=<?php echo $limite; ?><?php echo $carreraFiltro ? "&carrera=" . urlencode($carreraFiltro) : ''; ?><?php echo $filtroEstatus !== 'todos' ? "&filtro=" . $filtroEstatus : ''; ?><?php echo "&sort=" . $sortColumn . "&dir=" . $sortDirection; ?>">&laquo; Anterior</a>
    <?php endif; ?>

    <?php
    $maxLinks = 5;
    $start = max(1, $pagina - floor($maxLinks / 2));
    $end = min($totalPaginas, $start + $maxLinks - 1);

    if ($start > 1) {
        echo '<a href="?pagina=1&limite=' . $limite . ($carreraFiltro ? "&carrera=" . urlencode($carreraFiltro) : "") . ($filtroEstatus !== 'todos' ? "&filtro=" . $filtroEstatus : "") . "&sort=" . $sortColumn . "&dir=" . $sortDirection . '">1</a>';
        if ($start > 2) {
            echo '<span>...</span>';
        }
    }

    for ($i = $start; $i <= $end; $i++): ?>
        <a href="?pagina=<?php echo $i; ?>&limite=<?php echo $limite; ?><?php echo $carreraFiltro ? "&carrera=" . urlencode($carreraFiltro) : ''; ?><?php echo $filtroEstatus !== 'todos' ? "&filtro=" . $filtroEstatus : ''; ?><?php echo "&sort=" . $sortColumn . "&dir=" . $sortDirection; ?>" class="<?php if ($i == $pagina) echo 'active'; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>

    <?php
    if ($end < $totalPaginas) {
        if ($end < $totalPaginas - 1) {
            echo '<span>...</span>';
        }
        echo '<a href="?pagina=' . $totalPaginas . '&limite=' . $limite . ($carreraFiltro ? "&carrera=" . urlencode($carreraFiltro) : "") . ($filtroEstatus !== 'todos' ? "&filtro=" . $filtroEstatus : "") . "&sort=" . $sortColumn . "&dir=" . $sortDirection . '">' . $totalPaginas . '</a>';
    }
    ?>

    <?php if ($pagina < $totalPaginas): ?>
        <a href="?pagina=<?php echo $pagina + 1; ?>&limite=<?php echo $limite; ?><?php echo $carreraFiltro ? "&carrera=" . urlencode($carreraFiltro) : ''; ?><?php echo $filtroEstatus !== 'todos' ? "&filtro=" . $filtroEstatus : ''; ?><?php echo "&sort=" . $sortColumn . "&dir=" . $sortDirection; ?>">Siguiente &raquo;</a>
    <?php endif; ?>
</div>
  </div>
</div>


  <script>
    // ============================================
// SCRIPT COMPLETO Y OPTIMIZADO PARA PANEL DE USUARIOS
// Reemplaza TODOS los scripts existentes con este código
// ============================================

$(document).ready(function() {
    // Variables globales para mantener el estado de los filtros
    let currentQuery = '';
    let currentPage = 1;
    let currentFilter = $('#filtro-estatus').val() || 'todos';
    let currentCarrera = $('#filtro-carrera').val() || '';
    let currentLimit = <?php echo $limite; ?>;

    // ============================================
    // FUNCIÓN PRINCIPAL DE BÚSQUEDA CON FILTROS
    // ============================================
    function buscarConAjax(query, pagina) {
        const tbody = $('#usuarios-tbody');
        const filtroEstatus = $('#filtro-estatus').val();
        const filtroCarrera = $('#filtro-carrera').val();
        
        $.ajax({
            url: 'buscar_usuarios.php',
            method: 'GET',
            data: { 
                query: query, 
                pagina: pagina,
                filtro: filtroEstatus,
                carrera: filtroCarrera,
                limite: currentLimit
            },
            dataType: 'json',
            beforeSend: function () {
                tbody.html('<tr><td colspan="7" style="text-align:center;padding:20px;"><i class="fas fa-spinner fa-spin"></i> Buscando...</td></tr>');
            },
            success: function (response) {
                if (response && response.html) {
                    tbody.html(response.html);
                    
                    if (response.paginacion) {
                        $('.paginacion_contenedor').html(response.paginacion).show();
                    } else {
                        $('.paginacion_contenedor').hide();
                    }
                    
                    // Actualizar contador
                    if (response.total !== undefined) {
                        $('.limite-info').html(
                            `Mostrando ${response.desde || 1} - ${response.hasta || response.total} de ${response.total} usuarios`
                        );
                    }
                } else {
                    tbody.html('<tr><td colspan="7">Error en la respuesta del servidor.</td></tr>');
                }
            },
            error: function (xhr, status, error) {
                console.error('Error en búsqueda:', error);
                tbody.html('<tr><td colspan="7">Error en la búsqueda. Por favor, intente nuevamente.</td></tr>');
                $('.paginacion_contenedor').hide();
            }
        });
    }

    // ============================================
    // EVENTO DE BÚSQUEDA CON DEBOUNCE
    // ============================================
    let searchTimeout;
    $('#buscar-usuarios').on('input', function () {
        clearTimeout(searchTimeout);
        const query = $(this).val().trim();
        currentQuery = query;

        if (query === '') {
            // Si no hay búsqueda, recargar página con filtros actuales
            aplicarFiltros();
            return;
        }
        
        if (query.length < 2) {
            $('#usuarios-tbody').html('<tr><td colspan="7">Ingrese al menos 2 caracteres</td></tr>');
            $('.paginacion_contenedor').html('');
            return;
        }

        searchTimeout = setTimeout(() => {
            buscarConAjax(query, 1);
        }, 300);
    });

    // ============================================
    // CAMBIO DE FILTROS (MANTIENE BÚSQUEDA)
    // ============================================
    $('#filtro-estatus, #filtro-carrera').on('change', function() {
        currentFilter = $('#filtro-estatus').val();
        currentCarrera = $('#filtro-carrera').val();
        currentPage = 1;
        
        // Si hay búsqueda activa, aplicar filtros a la búsqueda
        if (currentQuery.length >= 2) {
            buscarConAjax(currentQuery, 1);
        } else {
            // Si no hay búsqueda, aplicar filtros normales
            aplicarFiltros();
        }
    });

    // ============================================
    // FUNCIÓN PARA APLICAR FILTROS (SIN BÚSQUEDA)
    // ============================================
    function aplicarFiltros() {
        const filtroEstatus = $('#filtro-estatus').val();
        const filtroCarrera = $('#filtro-carrera').val();
        const urlParams = new URLSearchParams(window.location.search);
        
        // Mantener el límite actual
        urlParams.set('limite', currentLimit);
        urlParams.set('pagina', '1');
        
        // Aplicar filtro de estatus
        if (filtroEstatus !== 'todos') {
            urlParams.set('filtro', filtroEstatus);
        } else {
            urlParams.delete('filtro');
        }
        
        // Aplicar filtro de carrera
        if (filtroCarrera) {
            urlParams.set('carrera', filtroCarrera);
        } else {
            urlParams.delete('carrera');
        }
        
        // Mantener ordenamiento si existe
        if (urlParams.has('sort')) {
            urlParams.set('sort', urlParams.get('sort'));
        }
        if (urlParams.has('dir')) {
            urlParams.set('dir', urlParams.get('dir'));
        }
        
        window.location.href = window.location.pathname + '?' + urlParams.toString();
    }

    // ============================================
    // CAMBIO DE LÍMITE
    // ============================================
    window.cambiarLimite = function(limite) {
        limite = parseInt(limite);
        currentLimit = limite;
        
        if (limite > 1000) {
            Swal.fire({
                title: '⚠️ Advertencia',
                html: `Estás a punto de cargar <strong>${limite}</strong> registros.<br>Esto puede tardar unos momentos.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#09a787',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, continuar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Cargando datos...',
                        text: 'Por favor espere',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        willOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    aplicarCambioLimite(limite);
                }
            });
        } else {
            aplicarCambioLimite(limite);
        }
    };

    function aplicarCambioLimite(limite) {
        const urlParams = new URLSearchParams(window.location.search);
        urlParams.set('limite', limite);
        urlParams.set('pagina', '1');
        window.location.href = window.location.pathname + '?' + urlParams.toString();
    }

    // ============================================
    // PAGINACIÓN EN BÚSQUEDA
    // ============================================
    $('.paginacion_contenedor').on('click', 'a', function (e) {
        e.preventDefault();
        const pagina = $(this).data('pagina');
        
        // Si no tiene data-pagina, obtener de href
        if (!pagina) {
            const href = $(this).attr('href');
            if (href && href !== '#') {
                window.location.href = href;
                return;
            }
        }
        
        // Si hay búsqueda activa, paginar la búsqueda
        if (currentQuery.length >= 2 && pagina) {
            currentPage = pagina;
            buscarConAjax(currentQuery, pagina);
        } else if (pagina) {
            // Navegación normal con filtros
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('pagina', pagina);
            window.location.href = window.location.pathname + '?' + urlParams.toString();
        }
    });

    // ============================================
    // CONFIRMACIÓN DE CAMBIO DE ESTADO
    // ============================================
    window.confirmarEstatus = function(event, id, nuevoEstatus) {
        event.preventDefault();
        const accion = nuevoEstatus === 0 ? 'desactivar' : 'activar';
        const titulo = `¿Deseas ${accion} a este usuario?`;
        const texto = nuevoEstatus === 0 ? "El usuario no podrá iniciar sesión." : "El usuario podrá iniciar sesión nuevamente.";
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
                    url: 'actualizar_estatus_usuario.php',
                    method: 'POST',
                    data: { id: id, estatus: nuevoEstatus },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            Swal.fire('¡Éxito!', response.message, 'success').then(() => {
                                // Si hay búsqueda activa, actualizar búsqueda
                                if (currentQuery.length >= 2) {
                                    buscarConAjax(currentQuery, currentPage);
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

    // ============================================
    // FADE OUT DE ALERTAS
    // ============================================
    setTimeout(function () {
        document.querySelectorAll('.fade-out').forEach(function (element) {
            element.style.opacity = '0';
            element.style.transition = 'opacity 1.5s ease-out';
            setTimeout(function () {
                element.style.display = 'none';
            }, 500);
        });
    }, 1000);

    // ============================================
    // INICIALIZACIÓN
    // ============================================
    console.log('Panel de usuarios cargado correctamente');
    console.log('Filtro actual:', currentFilter);
    console.log('Carrera actual:', currentCarrera);
});

// ============================================
// CLASE PARA COLUMNAS ARRASTRABLES
// ============================================
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
        // Limpieza si es necesario
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

// Inicializar columnas arrastrables
new DraggableTableColumns();
  </script>

<?php
sqlsrv_close($conn_sqlsrv);
mysqli_close($db_mysql);
incluirTemplate('footer');
?>