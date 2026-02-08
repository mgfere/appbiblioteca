<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require '../vendor/autoload.php';
require '../includes/funciones.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$auth = adminAutenticado();

if (!$auth) {
  header('Location: login.php');
  exit;
}
$id_admin = isset($_SESSION['adminId']) ? $_SESSION['adminId'] : 0;

// Obtener el nombre del administrador de la sesión
$nombreAdministrador = isset($_SESSION['nombre']) ? $_SESSION['nombre'] : 'Usuario';
// Verificar el rol del administrador
$rolAdministrador = (int) $_SESSION['rol'];

$idAdministrador = isset($_SESSION['id']) ? $_SESSION['id'] : null;
require '../includes/config/database.php';
// Conexión a base de datos
$db = conectarDB();


//* Obtener el límite de registros por página (por defecto 20)
$limite = isset($_GET['limite']) ? (int) $_GET['limite'] : 20;

// Validar que el límite esté entre los valores permitidos
$limitesPermitidos = [5, 10, 15, 20, 25, 30, 40, 50];
if (!in_array($limite, $limitesPermitidos) && $limite != $totalReservaciones) {
  $limite = 20;
}

//* Determinar la página actual
$pagina = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;

//* Calcular el offset para la consulta SQL
$offset = ($pagina - 1) * $limite;


$queryOldReservations = "SELECT 
                         r.id AS reserva_id, 
                         r.Estudiantes_id, 
                         l.id AS Libros_id,  
                         l.titulo, 
                         l.autor, 
                         l.codigo,
                         l.cantidad, 
                         s.nombre_seccion AS seccion_nombre,  
                         u.email, 
                         u.nombre AS usuario_nombre
                       FROM 
                         reservaciones r
                       JOIN 
                         usuarios u ON r.Estudiantes_id = u.id
                       JOIN 
                         libros l ON r.Libros_id = l.id
                       JOIN 
                         secciones s ON l.seccionId = s.id
                       WHERE 
                         r.fecha_reservacion >= CURRENT_DATE
                       ORDER BY r.fecha_reservacion DESC
                       LIMIT $limite OFFSET $offset";
$resultadoOldReservations = mysqli_query($db, $queryOldReservations);
while ($reserva = mysqli_fetch_assoc($resultadoOldReservations)) {
  $reservaId = $reserva['reserva_id'];
  $usuarioId = $reserva['Estudiantes_id'];
  $libroId = $reserva['Libros_id'];
  $librotitulo = $reserva['titulo'];
  $libroautor = $reserva['autor'];
  $librocodigo = $reserva['codigo'];
  $librocantidad = $reserva['cantidad'];
  $libroseccion = $reserva['seccion_nombre'];
  $usuarioEmail = $reserva['email'];
  $usuarioNombre = $reserva['usuario_nombre'];


  // Eliminar la reservación
  $deleteOldReservations = "DELETE FROM reservaciones WHERE id = $reservaId";
  mysqli_query($db, $deleteOldReservations);

  // Cambiar el estado del libro a activo
  $updateBookStatus = "UPDATE libros SET status = 'Activo' WHERE id = $libroId";
  mysqli_query($db, $updateBookStatus);

  // Enviar correo electrónico al usuario
  $mail = new PHPMailer(true);
  try {
    // Configuración del servidor SMTP
    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'];
    $mail->SMTPAuth = $_ENV['SMTP_AUTH'] === 'true';
    $mail->Username = $_ENV['SMTP_USERNAME'];
    $mail->Password = $_ENV['SMTP_PASSWORD'];
    $mail->Port = $_ENV['SMTP_PORT'];
    $mail->setFrom($_ENV['SMTP_USERNAME'], $_ENV['SMTP_USERADMIN']);


    // Activar caracteres UTF-8
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';

    $mail->addAddress($usuarioEmail, $usuarioNombre);

    // Contenido del correo
    $mail->isHTML(true);
    $mail->Subject = '⌛ ¡Su reservación ha expirado! ';
    $mail->Body = "
                  <html>
                  <head>
                      <style>
                          body {
                              font-family: Arial, sans-serif;
                              font-size: 14px;
                          }
                          .libro-imagen {
                              width: 100px;
                              height: 150px;
                              margin: 10px;
                              border: 1px solid #ddd;
                          }
                          
                          .container {
                  display: flex;
                  justify-content: space-between;
                  gap:25px;
                }
                .logo {
                  width: 40%;
                  height: auto;
                }
                      </style>
                  </head>
                  <body>
                  <div class='container'>
                <img class='logo' src=" . $_ENV['LOGOUT_IMG_URL'] . " alt='Universidad Tecnológica de Tamaulipas Norte'>
                <img class='logo' src=" . $_ENV['LOGOCEIT_IMG_URL'] . " alt='CEIT'>
              </div>
              <h2>¡Tu reserva del libro '{$librotitulo}' ha sido eliminada!</h2>
              <p>Hola {$usuarioNombre},</p>
              <p>Te informamos que tu reservación ha sido eliminada por no recoger el libro en el tiempo establecido y el libro vuelve a estar disponible.</p>
              <hr>
              <h3>Detalles de la cancelación:</h3>
              <p><strong>Libro:</strong> {$librotitulo}</p>
              <p><strong>Autor:</strong> {$libroautor}</p>
              <p><strong>Disponible:</strong> {$librocantidad}</p>
              <p><strong>Código:</strong> {$librocodigo}</p>
              <p><strong>Sección:</strong> {$libroseccion}</p>
              <p>Si tienes alguna pregunta o inquietud, no dudes en contactarnos.</p>
              <div style='background-color: #09a787; color: #fff; font-weight: bold; text-align: center;'>
          <p> © " . date('Y') . " | Universidad Tecnológica de Tamaulipas Norte</p>
      </div>
          </body>
          </html>
      ";

    $mail->send();
  } catch (Exception $e) {
    echo "Error al enviar el correo: {$mail->ErrorInfo}";
  }
}

$conn = conectarDB3();

// Consulta para contar el número total de Alumnnos
$countQueryUsuarios = "SELECT COUNT(*) AS total_usuarios FROM Alumnos WHERE Alumnos.Habilitado = 1";
$resultadoCountUsuarios = sqlsrv_query($conn, $countQueryUsuarios);
$totalUsuarios = sqlsrv_fetch_array($resultadoCountUsuarios)['total_usuarios'];

// Consulta para contar el número total de Docentes
$countQueryDocentes = "SELECT COUNT(*) AS total_docentes FROM [GestionUsuarios].[dbo].[Docentes] WHERE docentes.Habilitado = 1";
$resultadoCountDocentes = sqlsrv_query($conn, $countQueryDocentes);
$totalDocentes = sqlsrv_fetch_array($resultadoCountDocentes)['total_docentes'];


// Consulta para contar el número total de reservaciones
$countQueryReservaciones = "SELECT COUNT(*) AS total_reservaciones FROM reservaciones";
$resultadoCountReservaciones = mysqli_query($db, $countQueryReservaciones);
$totalReservaciones = mysqli_fetch_assoc($resultadoCountReservaciones)['total_reservaciones'];

// Consulta para contar el número total de préstamos activos
$countQuerPrestamos = "SELECT COUNT(*) AS total_prestamos FROM prestamos WHERE status = 1";
$resultadoCountPrestamos = mysqli_query($db, $countQuerPrestamos);
$totalPrestamos = mysqli_fetch_assoc($resultadoCountPrestamos)['total_prestamos'];
$rol_admin = $_SESSION['rol'] ?? 0;
$es_admin_master = $_SESSION['es_admin_master'] ?? false;
$matricula_actual = $_SESSION['administrador'] ?? '';
$perfil_temporal = $_SESSION['perfil_temporal'] ?? null;

$reservasQuery = "SELECT COUNT(*)AS total_reservas FROM reservaciones";
;


$resultadoReservas = mysqli_query($db, $reservasQuery);
$totaldeReservas = mysqli_fetch_assoc($resultadoReservas)['total_reservas'];


$totalPaginas = ceil($totalReservaciones / $limite);

incluirTemplate('sidebar');
?>
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
      </span>
      <span>Bienvenido, <?php echo ($nombreAdministrador); ?></span>
      <h2>Panel de reservaciones</h2>
    </div>
    <div class="user--info">
      <div class="search--box">
        <i class="fas fa-search"></i>
        <input type="text" id="buscar" placeholder="Buscar" onkeyup="filtrarReservas()" />
      </div>
      <img src="../public/img/logouttn.png" alt="Foto de perfil" />
    </div>
  </div>
  <div class="card--container">
    <h3 class="main--title">Resumen general</h3>
    <div class="card--wrapper">
      <div class="payment--card">
        <div class="card--header">
          <div class="amount">
            <span class="title"> Alumnos </span>
            <span class="amount--value" id="numUsuarios"><?php echo $totalUsuarios; ?></span>
          </div>
        </div>
      </div>

      <div class="payment--card">
        <div class="card--header">
          <div class="amount">
            <span class="title"> Docentes </span>
            <span class="amount--value" id="numUsuarios"><?php echo $totalDocentes; ?></span>
          </div>
        </div>
      </div>

      <div class="payment--card">
        <div class="card--header">
          <div class="amount">
            <span class="title"> Reservaciones</span>
            <span class="amount--value" id="numReservaciones"><?php echo $totalReservaciones; ?></span>
          </div>
        </div>
      </div>

      <div class="payment--card">
        <div class="card--header">
          <div class="amount">
            <span class="title"> Préstamos Activos</span>
            <span class="amount--value"><?php echo $totalPrestamos; ?></span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="tabular--wrapper">

    <!-- Selector de límite de registros -->
    <div class="limite-selector">
      <label for="limiteReservaciones">Mostrar:</label>
      <select id="limiteReservaciones" onchange="cambiarLimite(this.value)">
        <?php
        // Opciones base
        $opcionesBase = [5, 10, 15, 20, 25, 30, 40, 50];

        // Generar opciones dinámicamente
        foreach ($opcionesBase as $opcion) {
          // Solo mostrar la opción si es menor o igual al total
          if ($opcion <= $totalReservaciones) {
            $selected = ($limite == $opcion) ? 'selected' : '';
            echo "<option value=\"{$opcion}\" {$selected}>{$opcion} reservaciones</option>";
          }
        }

        // Agregar opción "Todos" si hay más reservaciones que la última opción
        if ($totalReservaciones > max($opcionesBase)) {
          $selected = ($limite == $totalReservaciones) ? 'selected' : '';
          echo "<option value=\"{$totalReservaciones}\" {$selected}>Todos ({$totalReservaciones})</option>";
        } elseif ($totalReservaciones > 0 && !in_array($totalReservaciones, $opcionesBase)) {
          // Si el total no está en las opciones base pero es menor que 50
          $selected = ($limite == $totalReservaciones) ? 'selected' : '';
          echo "<option value=\"{$totalReservaciones}\" {$selected}>Todos ({$totalReservaciones})</option>";
        }
        ?>
      </select>
      <span class="limite-info">
        Mostrando <?php echo min($offset + 1, $totalReservaciones); ?> -
        <?php echo min($offset + $limite, $totalReservaciones); ?> de <?php echo $totalReservaciones; ?> reservaciones
      </span>
    </div>
    <h3 class="main--title">Reservaciones</h3>
    <div class="table--container">
      <table>
        <thead>
          <tr>
            <th draggable="true">Fecha</th>
            <th draggable="true">Usuario</th>
            <th draggable="true">Matrícula</th>
            <th draggable="true">Carrera</th>
            <th draggable="true">Turno</th>
            <th draggable="true">Código</th>
            <th draggable="true">Disponibles</th>
            <th draggable="true">Acciones</th>
          </tr>
        </thead>
        <tbody id="tablaReservasBody">
        </tbody>
      </table>
    </div>
  </div>

  <div class="paginacion_contenedor">
    <?php if ($pagina > 1): ?>
      <a href="?pagina=<?php echo $pagina - 1; ?>&limite=<?php echo $limite; ?>">&laquo; Anterior</a>
    <?php endif; ?>

    <?php
    $maxLinks = 5;
    $start = max(1, $pagina - floor($maxLinks / 2));
    $end = min($totalPaginas, $start + $maxLinks - 1);

    if ($start > 1) {
      echo '<a href="?pagina=1&limite=' . $limite . '">1</a>';
      if ($start > 2) {
        echo '<span>...</span>';
      }
    }

    for ($i = $start; $i <= $end; $i++): ?>
      <a href="?pagina=<?php echo $i; ?>&limite=<?php echo $limite; ?>"
        class="<?php if ($i == $pagina)
          echo 'active'; ?>">
        <?php echo $i; ?>
      </a>
    <?php endfor; ?>

    <?php
    if ($end < $totalPaginas) {
      if ($end < $totalPaginas - 1) {
        echo '<span>...</span>';
      }
      echo '<a href="?pagina=' . $totalPaginas . '&limite=' . $limite . '">' . $totalPaginas . '</a>';
    }
    ?>

    <?php if ($pagina < $totalPaginas): ?>
      <a href="?pagina=<?php echo $pagina + 1; ?>&limite=<?php echo $limite; ?>">Siguiente &raquo;</a>
    <?php endif; ?>
  </div>
</div>

<script src="/appceit/public/js/bundle.js"></script>
<?php
incluirTemplate('footer');
?>
<script>

  // Función para cambiar el límite de registros por página
  function cambiarLimite(limite) {
    // Obtener todos los parámetros actuales de la URL
    const urlParams = new URLSearchParams(window.location.search);

    // Actualizar o agregar el parámetro limite
    urlParams.set('limite', limite);

    // Resetear a página 1 cuando se cambia el límite
    urlParams.set('pagina', '1');

    // Construir la nueva URL
    const nuevaURL = window.location.pathname + '?' + urlParams.toString();

    // Redirigir a la nueva URL
    window.location.href = nuevaURL;
  }
  class DraggableTableColumns {
    constructor() {
      this.tableElement = document.querySelector('table');
      if (!this.tableElement) {
        console.error("No se encontró el elemento <table>.");
        return;
      }

      this.selectedColumn = null; // Columna que se está arrastrando (<th>)
      this.hoveredColumn = null; // Columna sobre la que se arrastra (<th>)

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
<script>
  document.addEventListener('DOMContentLoaded', function () {
    let refreshInterval; // Variable para almacenar el intervalo

    function cargarReservas() {
      fetch('https://biblioteca.uttn.app/admin/obtener_reservas.php')
        .then(response => {
          if (!response.ok) {
            throw new Error('Network response was not ok ' + response.statusText);
          }
          return response.json();
        })
        .then(data => {
          if (Array.isArray(data)) {
            const tablaReservasBody = document.getElementById('tablaReservasBody');
            tablaReservasBody.innerHTML = ''; // Limpiar antes de agregar nuevos resultados

            // Crear la fila para "No hay reservaciones" si la tabla está vacía
            if (data.length === 0) {
              const filaVacia = document.createElement('tr');
              filaVacia.id = 'noReservationsRow'; // Un ID específico para esta fila
              filaVacia.innerHTML = `
                                <td colspan="8" style="text-align: center;">No hay reservaciones</td>
                            `;
              tablaReservasBody.appendChild(filaVacia);
            } else {
              data.forEach(reserva => {
                const fecha = new Date(reserva.fecha);
                const fechaFormateada = fecha.toLocaleDateString('es-ES', {
                  year: 'numeric',
                  month: '2-digit',
                  day: '2-digit'
                });

                // Se agrega 1 día a la fecha para mostrar el día correcto
                const fechaCorregida = new Date(fecha.getTime() + 24 * 60 * 60 * 1000);
                const fechaFormateadaCorregida = fechaCorregida.toLocaleDateString('es-ES', {
                  year: 'numeric',
                  month: '2-digit',
                  day: '2-digit'
                });

                const fila = document.createElement('tr');
                fila.innerHTML = `
                                    <td>${fechaFormateadaCorregida}</td>
                                    <td>${reserva.estudiante}</td>
                                    <td>${reserva.matricula}</td>
                                    <td>${reserva.carrera}</td>
                                    <td>${reserva.turno || 'No especificado'}</td>
                                    <td>
                                        <button style="background-color: ${reserva.color_libro}" class="reservacion--libro"></button>
                                        ${reserva.codigo}
                                    </td>
                                    <td>${reserva.cantidad}</td>
                                    <td>
                                        <div class="botones--accion--container">
                                            <button title="Autorizar" type="submit" class="btnAceptado" id=${reserva.id} onclick="autorizarPrestamo(${reserva.id})">Entregado
                                            </button>
                                        </div>
                                    </td>
                                `;
                tablaReservasBody.appendChild(fila);
              });
            }
            // Después de cargar o no reservas, aplicar el filtro si hay texto en la búsqueda
            if (document.getElementById('buscar').value !== '') {
              filtrarReservas();
            }
          } else {
            console.error('Error al cargar las reservas:', data.error);
          }
        })
        .catch(error => console.error('Error al cargar las reservas:', error));
    }

    function setRefreshInterval(interval) {
      clearInterval(refreshInterval); // Detiene el intervalo anterior
      refreshInterval = setInterval(cargarReservas, interval); // Inicia un nuevo intervalo
    }

    // Seleccionando el buscador
    const searchInput = document.getElementById('buscar');

    searchInput.addEventListener('input', function () {
      // Llama a filtrarReservas cada vez que hay un cambio en el input
      filtrarReservas();

      // Ajusta el intervalo de carga solo si el input está vacío o no
      if (searchInput.value === '') {
        setRefreshInterval(1000); // 1 segundo si el input está vacío
      } else {
        setRefreshInterval(5000); // 5 segundos si el usuario está buscando
      }
    });

    // Inicia la carga de reservas con intervalo de 1 segundo
    setRefreshInterval(1000);
    cargarReservas();
  });



  function filtrarReservas() {
    const input = document.getElementById('buscar');
    const filter = input.value.toUpperCase();
    const tableBody = document.getElementById('tablaReservasBody'); // Referencia al tbody
    const tr = tableBody.getElementsByTagName('tr');
    let visibleRows = 0;

    // Ocultar la fila "No hay reservaciones" si existe
    const noReservationsRow = document.getElementById('noReservationsRow');
    if (noReservationsRow) {
      noReservationsRow.style.display = 'none';
    }

    // Eliminar la fila de "No hay resultados" si ya existe antes de filtrar
    let noResultRow = document.getElementById('noResultRow');
    if (noResultRow) {
      noResultRow.remove();
    }

    for (let i = 0; i < tr.length; i++) {
      const tdCodigo = tr[i].getElementsByTagName('td')[5]; // La columna de 'Código' (índice actualizado)
      const tdUsuario = tr[i].getElementsByTagName('td')[1]; // La columna de 'Usuario'
      const tdMatricula = tr[i].getElementsByTagName('td')[2]; // La columna de 'Matrícula'
      const tdCarrera = tr[i].getElementsByTagName('td')[3]; // La columna de 'Carrera'
      const tdTurno = tr[i].getElementsByTagName('td')[4]; // La columna de 'Turno' (nueva)


      if (tdCodigo || tdUsuario || tdMatricula || tdCarrera || tdTurno) {
        const txtValueCodigo = tdCodigo ? (tdCodigo.textContent || tdCodigo.innerText) : '';
        const txtValueUsuario = tdUsuario ? (tdUsuario.textContent || tdUsuario.innerText) : '';
        const txtValueMatricula = tdMatricula ? (tdMatricula.textContent || tdMatricula.innerText) : '';
        const txtValueCarrera = tdCarrera ? (tdCarrera.textContent || tdCarrera.innerText) : '';
        const txtValueTurno = tdTurno ? (tdTurno.textContent || tdTurno.innerText) : '';


        if (txtValueCodigo.toUpperCase().indexOf(filter) > -1 ||
          txtValueUsuario.toUpperCase().indexOf(filter) > -1 ||
          txtValueMatricula.toUpperCase().indexOf(filter) > -1 ||
          txtValueCarrera.toUpperCase().indexOf(filter) > -1 ||
          txtValueTurno.toUpperCase().indexOf(filter) > -1
        ) {
          tr[i].style.display = '';
          visibleRows++;
        } else {
          tr[i].style.display = 'none';
        }
      }
    }

    // Si no hay filas visibles después del filtro, mostrar la fila de "No hay resultados"
    if (visibleRows === 0) {
      noResultRow = document.createElement('tr');
      noResultRow.id = 'noResultRow'; // Asignar un ID para poder buscarla y removerla luego
      noResultRow.innerHTML = `
                <td colspan="8" style="text-align: center;">No hay resultados</td>
            `;
      tableBody.appendChild(noResultRow);
    }
  }

  setInterval(refreshReservations, 1000);

  function refreshReservations() {
    fetch('https://biblioteca.uttn.app/admin/obtenerNumReservas.php')
      .then(response => response.json())
      .then(data => {
        document.getElementById('numReservaciones').textContent = data.total_reservaciones;
      })
      .catch(error => console.error('Error al refrescar reservaciones:', error));
  }

  setInterval(refreshUsuarios, 1000);

  function refreshUsuarios() {
    fetch('https://biblioteca.uttn.app/admin/obtenerNumUsuarios.php')
      .then(response => response.json())
      .then(data => {
        document.getElementById('numUsuarios').textContent = data.total_usuarios;
      })
      .catch(error => console.error('Error al refrescar usuarios:', error));
  }

  function autorizarPrestamo(id) {
    console.log('Intentando autorizar préstamo con ID:', id);

    // Mostrar loader
    Swal.fire({
      title: 'Procesando...',
      text: 'Autorizando préstamo',
      allowOutsideClick: false,
      didOpen: () => {
        Swal.showLoading();
      }
    });

    fetch('https://biblioteca.uttn.app/admin/autorizar_prestamo.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        id: id
      })
    })
      .then(response => {
        console.log('Status:', response.status);
        console.log('Content-Type:', response.headers.get('content-type'));

        // SIEMPRE obtener el texto primero para ver qué devuelve
        return response.text().then(text => {
          console.log('==== RESPUESTA COMPLETA DEL SERVIDOR ====');
          console.log(text);
          console.log('==== FIN DE RESPUESTA ====');

          // Intentar parsear como JSON
          try {
            const data = JSON.parse(text);
            return { success: response.ok, data: data };
          } catch (e) {
            console.error('Error parseando JSON:', e);
            throw new Error(`Respuesta no válida del servidor. Primera línea: ${text.substring(0, 200)}`);
          }
        });
      })
      .then(result => {
        const data = result.data;
        console.log('Datos parseados:', data);

        if (result.success && data.success) {
          Swal.fire({
            title: "¡Libro entregado!",
            text: data.message,
            icon: "success"
          }).then(() => {
            window.location.reload();
          });
        } else {
          Swal.fire({
            title: "Error",
            text: data.error || 'Error desconocido',
            icon: "error"
          });
        }
      })
      .catch(error => {
        console.error('Error completo:', error);
        Swal.fire({
          title: "Error de conexión",
          text: error.message,
          icon: "error"
        });
      });
  }
</script>