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

$idAdministrador = isset($_SESSION['id']) ? $_SESSION['id'] : null;

// Conexión a la base de datos
require '../includes/config/database.php';
$db = conectarDB();

// Muestra un mensaje condicional
$resultado = $_GET["resultado"] ?? null;

$countQueryPrestamos = "SELECT COUNT(*) AS total_prestamos FROM prestamospresencial WHERE estatus = '1' ORDER BY fechaPrestamo DESC";
$resultadoCountPrestamos = mysqli_query($db, $countQueryPrestamos);
$totalPrestamos = mysqli_fetch_assoc($resultadoCountPrestamos)['total_prestamos'];

// Consulta para obtener los datos de los préstamos junto con el color de la sección del libro
$consultaPrestamos = "
  SELECT pp.*, s.color
  FROM prestamospresencial pp
  INNER JOIN libros l ON pp.codigoLibro = l.codigo
  INNER JOIN secciones s ON l.seccionId = s.id
  WHERE pp.estatus = 1 AND pp.seccionId = s.id
  ORDER BY pp.fechaPrestamo DESC
";

$resultadoPrestamos = mysqli_query($db, $consultaPrestamos);

// Verificar si la consulta tuvo éxito
if (!$resultadoPrestamos) {
  die('Consulta fallida: ' . mysqli_error($db));
}

incluirTemplate('sidebar');
?>
<link rel="stylesheet" href="../public/css/panellibros.css">
<div class="container main--content">
  <div class="header--wrapper">
    <div class="header--title">
      <span style="display: flex; border: 2.3px solid #09a787; padding: 2px; margin-bottom: 10px; border-radius: 5px; color: #09a787; width: 230px; text-transform: uppercase">
        <?php if ($rolAdministrador === '1') {
          echo 'Administrador general';
        } else {
          echo 'Administrador';
        } ?>
      </span>
      <span>Bienvenido, <?php echo ($nombreAdministrador); ?></span>
      <h2>Panel de préstamos externos</h2>
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
            <span class="title"> Préstamos activos </span>
            <span class="amount--value" id="numPrestamos"><?php echo $totalPrestamos; ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="tabular--wrapper">
    <h3 class="main--title">Préstamos externos</h3>
    <div class="tabular--botones">
<a href="escanear-qr.php">
  <button title="Registrar Prestamo" class="btnAgregar">
    <i class="fas fa-plus"></i> Registrar préstamo
  </button>
</a>
      <?php if ($rolAdministrador == 1): ?>
        <a title="Exportar PDF" id="btnPDF" href="Reporte de prestamos presenciales.php" target="_blank"><i class="fas fa-file-pdf"></i> Exportar PDF</a>
        <a title="Exportar Excel" id="btnExcel" href="Reporte de prestamos presenciales excel.php" target="_blank"><i class="fas fa-file-pdf"></i> Exportar PDF</a>
      <?php endif; ?>
    </div>
    <?php if (intval($resultado) === 1): ?>
      <p class="alerta exito fade-out">Libro entregado correctamente</p>
    <?php elseif (intval($resultado) === 2): ?>
      <p class="alerta exito fade-out">Libro devuelto correctamente</p>
    <?php endif; ?>
    <div class="table--container">
      <table>
        <thead>
          <tr>
            <th draggable="true">Fecha de préstamo</th>
            <th draggable="true">Fecha de devolución</th>
            <th draggable="true">Estatus</th>
            <th draggable="true">Código</th>
            <th draggable="true">Disponibles</th>
            <th draggable="true">Usuario</th>
            <th draggable="true">Correo electrónico</th>
            <?php if ($rolAdministrador == 1): ?>
              <th>Entregado por</th>
            <?php endif; ?>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody id="tablaPrestamos">
          <?php if (mysqli_num_rows($resultadoPrestamos) > 0) : ?>
            <?php while ($prestamo = mysqli_fetch_assoc($resultadoPrestamos)) : ?>
              <tr>
                <td class="textosm"><?php echo date('d/m/Y', strtotime($prestamo['fechaPrestamo'])); ?></td>
                <td class="textosm"><?php echo date('d/m/Y', strtotime($prestamo['fechaDevolucion'])); ?></td>

                <td class="textosm">
                  <?php
                  if ($prestamo['estatus'] === '1') {
                    echo "Préstamo";
                  } elseif ($prestamo['estatus'] === '2') {
                    echo "Devuelto";
                  }
                  ?>
                </td>

                <td class="textosm">
                  <button style="background-color: <?php echo htmlspecialchars($prestamo['color']); ?>" class="reservacion--libro"></button>
                  <?php echo htmlspecialchars($prestamo['codigoLibro']); ?>
                </td>
                <td><?php echo htmlspecialchars($prestamo['cantidad']); ?></td>
                <td class="textosm"><?php echo htmlspecialchars($prestamo['nombreCompleto']); ?></td>
                <td class="textosm"><a target="_blank" href="mailto:<?php echo htmlspecialchars($prestamo['email']); ?>?subject=📚 ¡No olvides devolver tu libro!"><?php echo htmlspecialchars($prestamo['email']); ?></a>
                </td>
                <?php if ($rolAdministrador == 1): ?>
                  <td><?php echo htmlspecialchars($prestamo['entregado']); ?></td>
                <?php endif; ?>
                <td>
                  <div class="botones--accion--container">
                    <button title="Devuelto" type="button" class="btnAceptado" value="<?php echo $prestamo['id']; ?>">Devuelto</button>
                  </div>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else : ?>
            <tr>
              <td colspan="8">No hay préstamos activos</td>
            </tr>
          <?php endif; ?>
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
  $(document).ready(function() {
    $('.btnAceptado').on('click', function() {
      var prestamoId = $(this).val(); // Obtener el valor del atributo 'value'

      $.ajax({
        url: 'devolver_prestamo_presencial.php',
        method: 'POST',
        data: {
          prestamoId: prestamoId
        },
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            Swal.fire({
              title: "¡Libro devuelto!",
              icon: "success"
            }).then(() => {
              window.location = "panel-prestamos-presenciales.php?resultado=2"; // Redirige al usuario al panel de prestamos
            });
            // location.reload(); // Recargar la página para actualizar los datos
          } else {
            alert('Hubo un error al devolver el préstamo: ' + response.message);
          }
        },
        error: function(xhr, status, error) {
          console.log(xhr.responseText); // Mostrar la respuesta completa del servidor en la consola
          alert('Error en la solicitud AJAX: ' + error); // Mostrar un mensaje de error genérico
        }
      });
    });
  });
$(document).ready(function() {
    $('#buscar').on('input', function() {
        var query = $(this).val();
        // Asegúrate de que $rolAdministrador está disponible en este script PHP principal
        // y se pasa al JavaScript.
        var rolAdminActual = <?php echo json_encode($rolAdministrador); ?>; // <--- ESTO ES CLAVE

        $.ajax({
            url: 'buscar_prestamos_externos.php',
            method: 'GET',
            data: {
                query: query,
                rolAdmin: rolAdminActual // <--- PASAR EL ROL AQUI
            },
            success: function(data) {
                $('#tablaPrestamos').html(data);
            }
        });
    });
});
</script>

<?php
// Cerrar la conexión a base de datos
mysqli_close($db);

incluirTemplate('footer');
?>