<?php
require 'includes/funciones.php';
$auth = usuarioAutenticado();

if (!$auth) {
    header('Location: principal.php');
    exit;
}

// Obtener el nombre del usuario de la sesión
$nombreUsuario = isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : 'Usuario';

// Convertir el nombre a minúsculas y luego aplicar ucfirst() a la primera letra
$nombreUsuario = ucwords(strtolower($nombreUsuario));

$matricula = isset($_SESSION['usuario_matricula']) ? $_SESSION['usuario_matricula'] : '';
$idusuario = isset($_SESSION['usuario_id']) ? $_SESSION['usuario_id'] : 'no hay';

//* Importar la conexión 
require 'includes/config/database.php';
$db = conectarDB();

// Consulta para obtener las reservaciones del usuario
$sql = "SELECT r.*, l.titulo, l.codigo, l.autor, l.imagen 
        FROM reservaciones r 
        INNER JOIN libros l ON r.Libros_id = l.id 
        WHERE r.Estudiantes_id = ?";
$stmt = $db->prepare($sql);
$stmt->bind_param("i", $idusuario);
$stmt->execute();
$result = $stmt->get_result();

// Consulta para obtener los prestamos del usuario
$sqlPrestamos = "SELECT p.*, l.titulo, l.codigo, l.autor, l.imagen
        FROM prestamos p 
        INNER JOIN libros l ON p.Libros_id = l.id 
        WHERE p.Estudiantes_id = ? and p.status = 1"; 
$stmtPrestamos = $db->prepare($sqlPrestamos);
$stmtPrestamos->bind_param("i", $idusuario);
$stmtPrestamos->execute();
$resultadosPrestamos = $stmtPrestamos->get_result();

// Consulta para contar el número de reservaciones del usuario
$sqlCountReservaciones = "SELECT COUNT(*) as totalReservaciones FROM reservaciones WHERE Estudiantes_id = ?";
$stmtCountReservaciones = $db->prepare($sqlCountReservaciones);
$stmtCountReservaciones->bind_param("i", $idusuario);
$stmtCountReservaciones->execute();
$resultCountReservaciones = $stmtCountReservaciones->get_result();
$totalReservaciones = $resultCountReservaciones->fetch_assoc()['totalReservaciones'];

// Consulta para contar el número de préstamos del usuario con estado 1
$sqlCountPrestamos = "SELECT COUNT(*) as totalPrestamos FROM prestamos WHERE Estudiantes_id = ? AND status = 1";
$stmtCountPrestamos = $db->prepare($sqlCountPrestamos);
$stmtCountPrestamos->bind_param("i", $idusuario);
$stmtCountPrestamos->execute();
$resultCountPrestamos = $stmtCountPrestamos->get_result();
$totalPrestamos = $resultCountPrestamos->fetch_assoc()['totalPrestamos'];

incluirTemplate('header-user');
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Mis Reservaciones</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.4.10/dist/sweetalert2.all.min.js"></script>
</head>

<body>
    <div class="container">
        <h1 style="color: #1ab192; margin-bottom: 10px;">Mis Reservaciones</h1>
        <p style="line-height: 1.6; color: #333;">
            ¡Hola <strong style="color: #1ab192;"><?php echo $nombreUsuario ?></strong>! Bienvenido a tu panel de reservaciones. Aquí puedes consultar todas las reservas de libros que has realizado. Si necesitas <strong style="color: #e20e0e;">cancelar</strong> alguna, puedes hacerlo fácilmente desde este apartado.
        </p>
        <p style="margin-top: 10px; color: #555;">
            <strong style="color: #1ab192;">Recuerda:</strong> Puedes tener hasta <strong>2 préstamos</strong> de libros activos al mismo tiempo.
        </p>
        <div class="contadores-usuarios" style="display: flex; margin-top: 30px; gap: 25px;">
            <div style="background: #f7f7f7; padding: 15px 25px; box-shadow: 0 1px 4px rgba(26,177,146,0.07);">
                <p style="margin: 0; color: #1ab192;"><strong>Reservaciones:</strong></p>
                <span style="font-size: 1.5em; color: #1ab192;"><?php echo $totalReservaciones; ?></span>
            </div>
            <div style="background: #f7f7f7; padding: 15px 25px; box-shadow: 0 1px 4px rgba(26,177,146,0.07);">
                <p style="margin: 0; color: #1ab192;"><strong>Préstamos:</strong></p>
                <span style="font-size: 1.5em; color: #1ab192;"><?php echo $totalPrestamos; ?></span>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="libros_contenedor" id="libros_contenedor">
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="libro-item">
                    <div class="libro-img">
                        <div class="libro-info">
                            <h3>FECHA DE RESERVACIÓN:</h3>
                            <p><?php echo date('d-m-Y', strtotime($row['fecha_reservacion'])); ?></p>
                        </div> <br>
                        <img src="imagenes/<?php echo $row['imagen']; ?>" alt="<?php echo $row['titulo']; ?>" />
                    </div>
                    <div class="libro-info">
                        <p>#<?php echo $row['codigo']; ?></p>
                        <h3><?php echo $row['titulo']; ?></h3>
                        <p><?php echo $row['autor']; ?></p>
                    </div>
                    <form action="eliminar_reservacion.php" method="POST" class="delete-form">
                        <input type="hidden" name="id_reservacion" value="<?php echo $row['id']; ?>">
                        <input type="submit" value="Cancelar" style="background-color: #e20e0e; padding: 8px; color: white; margin: 15px auto; display: block; border: none; outline: none; cursor: pointer;">
                    </form>
                </div>
            <?php endwhile; ?>
        </div>

        <div class="container" style="margin-top: 40px;">
            <h2 style="color: #1ab192; margin-bottom: 10px;">Mis Préstamos Activos</h2>
            <div class="libros_contenedor" id="libros_contenedor_prestamos">
                <?php while ($row = $resultadosPrestamos->fetch_assoc()): ?>
                    <div class="libro-item">
                        <div class="libro-img">
                            <div class="libro-info">
                                <h3>FECHA DE PRÉSTAMO:</h3>
                                <p><?php echo date('d-m-Y', strtotime($row['fecha_prestamo'])); ?></p>  
                            </div> <br>
                            <img src="imagenes/<?php echo $row['imagen']; ?>" alt="<?php echo $row['titulo']; ?>" />
                        </div>
                        <div class="libro-info">
                            <p>#<?php echo $row['codigo']; ?></p>
                            <h3><?php echo $row['titulo']; ?></h3>
                            <p><?php echo $row['autor']; ?></p>
                            <p class="fecha-devolucion" data-fecha="<?php echo date('Y-m-d', strtotime($row['fecha_devolucion'])); ?>">
                                Fecha devolución: <?php echo date('d-m-Y', strtotime($row['fecha_devolucion'])); ?>
                            </p>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>



    <script>
        // Script para mandar a llamar la alerta de confirmación
        document.addEventListener('DOMContentLoaded', function() {
            const deleteForms = document.querySelectorAll('.delete-form');
            deleteForms.forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    event.preventDefault();
                    Swal.fire({
                        title: '¿Estás seguro?',
                        text: '¡Atención! Esta acción es permanente. No podrás recuperar tu reservación.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, cancelar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });

            // 1. Obtener la fecha de hoy en formato AAAA-MM-DD
            const hoy = new Date();
            const anio = hoy.getFullYear();
            const mes = String(hoy.getMonth() + 1).padStart(2, '0');
            const dia = String(hoy.getDate()).padStart(2, '0');
            const fechaActualFormateada = `${anio}-${mes}-${dia}`;

            // 2. Crear fecha de mañana para alertas preventivas
            const manana = new Date(hoy);
            manana.setDate(hoy.getDate() + 1);
            const anioManana = manana.getFullYear();
            const mesManana = String(manana.getMonth() + 1).padStart(2, '0');
            const diaManana = String(manana.getDate()).padStart(2, '0');
            const fechaMananaFormateada = `${anioManana}-${mesManana}-${diaManana}`;

            // 3. Buscar todas las celdas que tienen la fecha de devolución
            const celdasFecha = document.querySelectorAll('.fecha-devolucion');

            // 4. Recorrer cada celda y aplicar el estilo correspondiente
            celdasFecha.forEach(function(celda) {
                const fechaDevolucion = celda.dataset.fecha;
                
                // Resetear estilos
                celda.style.color = '';
                celda.style.fontWeight = '';
                celda.style.backgroundColor = '';

                if (fechaDevolucion < fechaActualFormateada) {
                    // PRÉSTAMOS VENCIDOS (fechas pasadas)
                    celda.style.color = '#dc3545'; // Rojo más fuerte
                    celda.style.fontWeight = 'bold';
                    celda.style.backgroundColor = '#f8d7da'; // Fondo rojo claro
                    celda.title = 'Préstamo VENCIDO';
                    
                } else if (fechaDevolucion === fechaActualFormateada) {
                    // VENCE HOY (fecha actual)
                    celda.style.color = '#fd7e14'; // Naranja
                    celda.style.fontWeight = 'bold';
                    celda.style.backgroundColor = '#fff3cd'; // Fondo amarillo claro
                    celda.title = 'Préstamo vence HOY';
                    
                } else if (fechaDevolucion === fechaMananaFormateada) {
                    // VENCE MAÑANA (alerta preventiva)
                    celda.style.color = '#ffc107'; // Amarillo
                    celda.style.fontWeight = 'bold';
                    celda.title = 'Préstamo vence mañana';
                    
                } else {
                    // PRÉSTAMOS CON TIEMPO (fechas futuras)
                    celda.style.color = '#28a745'; // Verde
                    celda.style.fontWeight = 'normal';
                    celda.title = 'Préstamo activo - sin problemas';
                }
            });
        });
    </script>
</body>

</html>

<?php
incluirTemplate('footer');

// Cerrar statements y conexión
$stmt->close();
$stmtPrestamos->close();
$stmtCountReservaciones->close();
$stmtCountPrestamos->close();
$db->close();
?>