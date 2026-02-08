<?php
require 'phpmailer.php';
require 'includes/funciones.php';
$auth = usuarioAutenticado();

if (!$auth) {
  header('Location: principal.php');
  exit;
}

// Validando que el id sea un número y este exista
$id = $_GET['id'];
$id = filter_var($id, FILTER_VALIDATE_INT);

if (!$id) {
  header('Location: index.php');
  exit;
}

// Base de datos
require 'includes/config/database.php';
$db = conectarDB();

// Consulta para obtener los datos de los libros
$consultaLibros = "SELECT libros.*, 
                          idiomas.idioma AS idioma_nombre, 
                          secciones.nombre_seccion AS seccion_nombre, 
                          secciones.color AS seccion_color 
                   FROM libros 
                   LEFT JOIN idiomas ON libros.idiomaId = idiomas.id 
                   LEFT JOIN secciones ON libros.seccionId = secciones.id 
                   WHERE libros.id = {$id}";

$resultadoLibros = mysqli_query($db, $consultaLibros);
$libro = mysqli_fetch_assoc($resultadoLibros);

$mensaje = '';

if (isset($_SESSION['usuario_id'])) {
  $usuarioId = $_SESSION['usuario_id'];
  $matricula = $_SESSION['usuario_matricula'];
  $tipo_usuario = $_SESSION['usuario_tipo'] ?? 'alumno';
  $numero_empleado = $_SESSION['numero_empleado'] ?? ''; // Para docentes
} else {
  header('Location: principal.php');
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cantidad = $_POST['cantidad'];

    // 1. Abrir la conexión a SQL Server
    $conn_sqlsrv2 = conectarDB3(); 
    
    $usuario_sqlsrv = null;
    $tipo_encontrado = null;
    
    // 2. Determinar qué tabla buscar según el tipo de usuario en sesión
    if ($tipo_usuario === 'alumno') {
        // Buscar en Alumnos
        $query = "SELECT [IdAlumno], [Nombre], [ApellidoPaterno], [ApellidoMaterno], [CorreoElectronico] 
                 FROM [GestionUsuarios].[dbo].[Alumnos] 
                 WHERE IdAlumno = ? AND Matricula = ?";
        
        $params = [$usuarioId, $matricula];
        $resultado = sqlsrv_query($conn_sqlsrv2, $query, $params);

        if ($resultado !== false && sqlsrv_has_rows($resultado)) {
            $usuario_sqlsrv = sqlsrv_fetch_array($resultado, SQLSRV_FETCH_ASSOC);
            $tipo_encontrado = 'alumno';
        }
    } else if ($tipo_usuario === 'docente') {
        // Buscar en Docentes - usar Matricula para la búsqueda
        $query = "SELECT [IdDocente], [Nombre], [ApellidoPaterno], [ApellidoMaterno], [CorreoElectronico], [NumeroEmpleado] 
                 FROM [GestionUsuarios].[dbo].[Docentes] 
                 WHERE IdDocente = ? AND Matricula = ?";
        
        $params = [$usuarioId, $matricula];
        $resultado = sqlsrv_query($conn_sqlsrv2, $query, $params);

        if ($resultado !== false && sqlsrv_has_rows($resultado)) {
            $usuario_sqlsrv = sqlsrv_fetch_array($resultado, SQLSRV_FETCH_ASSOC);
            $tipo_encontrado = 'docente';
            // Guardar el número de empleado para usarlo después
            $numero_empleado = $usuario_sqlsrv['NumeroEmpleado'];
        }
    }
    
    // 4. Cerrar la conexión a SQL Server
    sqlsrv_close($conn_sqlsrv2); 
    
    // 5. Si no se encontró al usuario en SQL Server
    if (!$usuario_sqlsrv) {
        $mensaje = "<p class='alerta error'>Error: No se pudo verificar la información del usuario. Tipo: " . $tipo_usuario . ", ID: " . $usuarioId . ", Matrícula: " . $matricula . "</p>";
    } else {
        // Asignar datos del usuario
        $correoElectronico = $usuario_sqlsrv['CorreoElectronico'];
        $usuario = [
            'nombre' => trim($usuario_sqlsrv['Nombre'] . ' ' . $usuario_sqlsrv['ApellidoPaterno'] . ' ' . $usuario_sqlsrv['ApellidoMaterno']),
            'email' => $usuario_sqlsrv['CorreoElectronico']
        ];

        // Validaciones de reserva
        $consultaReservasTotales = "SELECT COUNT(*) as cantidad_reservas_totales FROM reservaciones WHERE Estudiantes_id = ?";
        $stmt_reservas = mysqli_prepare($db, $consultaReservasTotales);
        mysqli_stmt_bind_param($stmt_reservas, "i", $usuarioId);
        mysqli_stmt_execute($stmt_reservas);
        $resultadoReservasTotales = mysqli_stmt_get_result($stmt_reservas);
        $reservasTotales = mysqli_fetch_assoc($resultadoReservasTotales);

        $consultaEstadoLibros = "SELECT status FROM libros WHERE id = ?";
        $stmt_estado = mysqli_prepare($db, $consultaEstadoLibros);
        mysqli_stmt_bind_param($stmt_estado, "i", $id);
        mysqli_stmt_execute($stmt_estado);
        $resultadoEstado = mysqli_stmt_get_result($stmt_estado);
        $estadoLibro = mysqli_fetch_assoc($resultadoEstado);
        
        if ($estadoLibro['status'] === 'Inactivo') {
            $mensaje = "<p class='alerta error'>Este libro no está disponible para reservación. El inventariado esta en curso, espera hasta que el estatus cambie.</p>";
            echo "<meta http-equiv='refresh' content='3;url=index.php'>";
        } else {
            if ($reservasTotales['cantidad_reservas_totales'] >= 2) {
                $mensaje = "<p class='alerta error'>Ya has alcanzado el límite de 2 reservas</p>";
            } else {
                $consultaLibroReservado = "SELECT Estudiantes_id FROM reservaciones WHERE Libros_id = ? LIMIT 1";
                $stmt_libro_reservado = mysqli_prepare($db, $consultaLibroReservado);
                mysqli_stmt_bind_param($stmt_libro_reservado, "i", $id);
                mysqli_stmt_execute($stmt_libro_reservado);
                $resultadoLibroReservado = mysqli_stmt_get_result($stmt_libro_reservado);
                $libroReservado = mysqli_fetch_assoc($resultadoLibroReservado);

                if ($libroReservado) {
                    if ($libroReservado['Estudiantes_id'] == $usuarioId) {
                        $mensaje = "<p class='alerta error'>Ya has reservado este libro anteriormente.</p>";
                    } else {
                        $mensaje = "<p class='alerta error'>Este libro ya está reservado por otro usuario.</p>";
                    }
                } else {
                    $consultaPrestamosActivos = "SELECT COUNT(*) as cantidad_prestamos_activos FROM prestamos WHERE Estudiantes_id = ? AND status = 1";
                    $stmt_prestamos = mysqli_prepare($db, $consultaPrestamosActivos);
                    mysqli_stmt_bind_param($stmt_prestamos, "i", $usuarioId);
                    mysqli_stmt_execute($stmt_prestamos);
                    $resultadoPrestamosActivos = mysqli_stmt_get_result($stmt_prestamos);
                    $prestamosActivos = mysqli_fetch_assoc($resultadoPrestamosActivos);

                    if ($prestamosActivos['cantidad_prestamos_activos'] >= 2) {
                        $mensaje = "<p class='alerta error'>Ya tienes 2 préstamos activos, no puedes realizar otra reservación.</p>";
                    } else {
                        // INSERTAR RESERVA CON TIPO DE USUARIO Y MATRÍCULA/NÚMERO EMPLEADO
                        // Para docentes, guardamos el número de empleado en el campo Matricula
                        $matricula_a_guardar = ($tipo_encontrado === 'docente') ? $numero_empleado : $matricula;
                        
                        $query = "INSERT INTO reservaciones (fecha_reservacion, Libros_id, Estudiantes_id, Matricula, cantidad, estado, creado, tipo_usuario) VALUES (NOW(), ?, ?, ?, ?, 'activa', NOW(), ?)";
                        $stmt = mysqli_prepare($db, $query);
                        mysqli_stmt_bind_param($stmt, "iiiss", $id, $usuarioId, $matricula_a_guardar, $cantidad, $tipo_encontrado);
                        $resultado = mysqli_stmt_execute($stmt);

                        if ($resultado) {
                            $mensaje = "<p class='alerta exito'>Reservación realizada con éxito, revisa tu correo electrónico <strong>$correoElectronico</strong> para más información.</p>";
                            $queryStatus = "UPDATE libros SET status = 'Inactivo' WHERE id = ?";
                            $stmt_status = mysqli_prepare($db, $queryStatus);
                            mysqli_stmt_bind_param($stmt_status, "i", $id);
                            mysqli_stmt_execute($stmt_status);
                            enviarCorreoReserva($usuario, $libro, $cantidad, $libro['seccion_nombre']);
                            echo "<meta http-equiv='refresh' content='3;url=index.php'>";
                        } else {
                            $mensaje = "<p class='alerta error'>Error al realizar la reserva: " . mysqli_error($db) . "</p>";
                        }
                    }
                }
            }          
        }
    }
}

// El resto del código HTML se mantiene igual...
incluirTemplate('header-user');
?>

<!-- ... El resto del código HTML permanece igual ... -->


<div class="container">
  <div class="paginacion_contenedor" style="justify-content: start">
    <a title="Volver a la página principal" href="index.php">&laquo</a>
  </div>
  <h1 class="titulo_detalle" style="color: #1ab192">Detalles del Libro</h1>
  <div class="detalles_contenedor">
    <div class="detalle_imagen">
      <img src="imagenes/<?php echo $libro['imagen']; ?>" alt="<?php echo $libro['titulo']; ?>" title="<?php echo $libro['titulo']; ?>" />
    </div>
    <div class="detalle_info">
      <h1><?php echo $libro['titulo']; ?></h1>
      <div class="detalle_estado">
        <p class="estado_texto" style="text-transform:uppercase">
          <?php
          echo $libro['status']
          ?>
        </p>
        <p id="isbn">CÓDIGO: # <?php echo $libro['codigo']; ?></p>
      </div>
      <hr />
      <?php echo $mensaje; ?>
      <div class="detalle_input">
        <form method="POST">
          <label for="cantidad" id="cantidadLabel" style="color: #1ab192; font-weight: bold; margin-right: 10px;">Disponibles:</label>
          <input style="border: none" type="text" name="cantidad" id="cantidad" require value="1" readonly min="0">
          <button type="submit" id="reservar">Reservar</button>
        </form>
      </div>
      <p id="mensaje"></p>
      <div class="detalle_seccion">
        <h4 style="color: #1ab192; font-weight: bold; margin-bottom: 8px;">Sección: <span style="color: initial; font-weight: normal;"> <?php echo $libro['seccion_nombre']; ?></span></h4>
        <div class="detalle_color">
          <h4 style="color: #1ab192; font-weight: bold; margin-bottom: 8px;">Color de la sección:</h4>
          <button style="background-color: <?php echo $libro['seccion_color']; ?>" id="seccion_color"></button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Caracteristicas del libro, reseñas e instrucciones de reservaciónes -->
<div class="container">
  <div class="mas-informacion" id="mas-informacion">
    <div class="tabs">
      </div>
    <div class="tab tab--active" id="caracteristicas">
      <h3 class="tab__titulo">Características</h3>
      <table>
        <tr>
          <th>Atributo</th>
          <th>Valor</th>
        </tr>
        <tr>
          <td>Título</td>
          <td><?php echo $libro['titulo']; ?></td>
        </tr>
        <tr>
          <td>Autor</td>
          <td><?php echo $libro['autor']; ?></td>
        </tr>
        <tr>
          <td>ISBN</td>
          <td><?php echo $libro['isbn']; ?></td>
        </tr>
        <tr>
          <td>Edición</td>
          <td><?php echo $libro['edicion']; ?></td>
        </tr>
        <tr>
          <td>Volumen</td>
          <td><?php echo $libro['volumen']; ?></td>
        </tr>
        <tr>
          <td>Idioma</td>
          <td style="text-transform: uppercase"><?php echo $libro['idioma_nombre']; ?></td>
        </tr>
        <tr>
          <td>Editorial</td>
          <td><?php echo $libro['editorial']; ?></td>
        </tr>
      </table>
    </div>
  </div>
</div>

<script>
  // Obtener la fecha actual
  var fecha = new Date();

  // Formatear la fecha actual
  var opciones = {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  };
  var fechaFormateada = fecha.toLocaleDateString('es-ES', opciones);

  // Calcular las fechas de viernes anterior, domingo próximo y lunes próximo
  // 0 = Domingo, 1 = Lunes, ..., 5 = Viernes, 6 = Sábado
  var diaSemana = fecha.getDay();
  var viernesAnterior = new Date(fecha);
  var domingoProximo = new Date(fecha);
  var lunesProximo = new Date(fecha);

  // Ajustar las fechas
  viernesAnterior.setDate(fecha.getDate() - ((diaSemana + 2) % 7)); // Retroceder al viernes anterior
  domingoProximo.setDate(fecha.getDate() + (7 - diaSemana) % 7); // Avanzar al próximo domingo
  lunesProximo.setDate(fecha.getDate() + ((8 - diaSemana) % 7)); // Avanzar al próximo lunes

  // Obtener los días, meses y años correspondientes
  var diaViernesAnterior = viernesAnterior.getDate();
  var mesViernesAnterior = viernesAnterior.toLocaleDateString('es-ES', {
    month: 'long'
  });
  var añoViernesAnterior = viernesAnterior.getFullYear();
  var diaDomingoProximo = domingoProximo.getDate();
  var mesDomingoProximo = domingoProximo.toLocaleDateString('es-ES', {
    month: 'long'
  });
  var añoDomingoProximo = domingoProximo.getFullYear();
  var diaLunesProximo = lunesProximo.getDate();
  var mesLunesProximo = lunesProximo.toLocaleDateString('es-ES', {
    month: 'long'
  });
  var añoLunesProximo = lunesProximo.getFullYear();

  // Verificar si el día está entre Sábado (6) y Domingo (0)
  if (diaSemana === 6 || diaSemana === 0) {
    // Deshabilitar los botones
    let btnReservar = document.getElementById('reservar');
    let selectCantidad = document.getElementById('cantidad');
    let cantidadLabel = document.getElementById('cantidadLabel');

    btnReservar.style.display = "none";
    selectCantidad.style.display = "none";
    cantidadLabel.style.display = "none";

    // Mostrar mensaje con la fecha actual y los días, meses y años de viernes, domingo y lunes
    document.getElementById('mensaje').innerText = `Las reservaciones están deshabilitadas durante el fin de semana. Se reanudarán el lunes ${diaLunesProximo} de ${mesLunesProximo} de ${añoLunesProximo}.`;
  }

  let cantidadDisponible = document.getElementById('cantidadDisponible');

  if (cantidadDisponible.innerText === "0") {
    let btnReservar = document.getElementById('reservar');
    let selectCantidad = document.getElementById('cantidad');
    let tituloCantidad = document.getElementById('tituloCantidad');
    let cantidadLabel = document.getElementById('cantidadLabel');

    btnReservar.style.display = "none";
    selectCantidad.style.display = "none";
    tituloCantidad.style.display = "none";
    cantidadLabel.style.display = "none";

    // Mostrar mensaje con que el libro no está dispobible
    document.getElementById('mensaje').innerText = `Actualmente no hay cantidad disponible para este libro.`;
  }
</script>

<footer>
  <div class="content-slim" style="margin-top: 50px;">
    <p> &copy; <?php echo date('Y'); ?> | Universidad Tecnológica de Tamaulipas Norte</p>
  </div>
  </div>
</footer>

<?php
//* Cerrar la conexión de la base de datos
mysqli_close($db);

incluirTemplate('footer');
?>