<?php
require '../includes/funciones.php';

$auth = adminAutenticado();

if (!$auth) {
    header('Location: login.php');
    exit;
}

// Información del administrador desde la sesión
$nombreAdministrador = $_SESSION['nombre'] ?? 'Usuario';
$rolAdministrador = (int) $_SESSION['rol'];

// Conexión a la base de datos
require '../includes/config/database.php';
$db = conectarDB();

// --- Consulta para obtener ÚNICAMENTE usuarios inactivos (estatus = 0) ---
$query = "SELECT 
    usuarios.id,
    usuarios.nombre, 
    usuarios.apellido, 
    usuarios.matricula, 
    usuarios.email,
    usuarios.turno,
    COALESCE(carreras.nombre_carrera, 'Sin carrera') as nombre_carrera,
    COALESCE(especialidades.nombre_especialidad, 'Sin especialidad') as nombre_especialidad
FROM 
    usuarios
LEFT JOIN carreras ON usuarios.carreraId = carreras.id_carrera
LEFT JOIN especialidades ON usuarios.especialidadId = especialidades.id_especialidad
WHERE 
    usuarios.estatus = 0
ORDER BY 
    usuarios.apellido ASC";

$resultadoUsuarios = mysqli_query($db, $query);

// Consulta para contar el total de usuarios inactivos
$countQuery = "SELECT COUNT(*) AS total_inactivos FROM usuarios WHERE estatus = 0";
$resultadoCount = mysqli_query($db, $countQuery);
$totalInactivos = mysqli_fetch_assoc($resultadoCount)['total_inactivos'];

// Mensajes de éxito o error desde la URL
$resultado = $_GET['resultado'] ?? null;

incluirTemplate('sidebar');
?>

<link rel="stylesheet" href="../public/css/panellibros.css">
<style>
    /* Estilos para los botones de acción */
    .btn-reactivar {
        background-color: #28a745;
        color: white;
        padding: 8px 12px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .btn-reactivar:hover {
        background-color: #218838;
    }
</style>

<div class="container main--content">
    <div class="header--wrapper">
        <div class="header--title">
            <span style="display: flex; border: 2.3px solid #09a787; padding: 2px; margin-bottom: 10px; border-radius: 5px; color: #09a787; width: 230px; text-transform: uppercase">
                <?php echo ($rolAdministrador === '1') ? 'Administrador general' : 'Administrador'; ?>
            </span>
            <span>Bienvenido, <?php echo htmlspecialchars($nombreAdministrador); ?></span>
            <h2>Panel de Usuarios Inactivos</h2>
        </div>
        <div class="user--info">
            <img src="../public/img/logouttn.png" alt="Logo" />
        </div>
    </div>

    <div class="card--container">
        <div class="card--wrapper">
            <div class="payment--card" style="background-color: #fce4e4;">
                <div class="card--header">
                    <div class="amount">
                        <span class="title">Total de usuarios inactivos</span>
                        <span class="amount--value" style="color: #c82333;"><?php echo $totalInactivos; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tabular--wrapper">
        <div class="tabular--botones">
             <a href="panel-usuarios.php" class="btnAceptado">
                <i class="fas fa-arrow-left"></i> Volver a Usuarios Activos
            </a>
        </div>
        
        <?php if ($resultado === '1'): ?>
            <p class="alerta exito fade-out">Usuario reactivado correctamente.</p>
        <?php elseif ($resultado === '0'): ?>
            <p class="alerta error fade-out">Error al reactivar el usuario. Inténtelo de nuevo.</p>
        <?php endif; ?>

        <div class="table--container">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>Matrícula</th>
                        <th>Carrera</th>
                        <th>Correo electrónico</th>
                        <th>Turno</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (mysqli_num_rows($resultadoUsuarios) > 0) :
                        $contador = 1;
                        while ($usuario = mysqli_fetch_assoc($resultadoUsuarios)) : ?>
                            <tr>
                                <td><?php echo $contador++; ?></td>
                                <td><?php echo htmlspecialchars($usuario['apellido'] . " " . $usuario['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['matricula']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['nombre_carrera']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                <td><?php echo htmlspecialchars($usuario['turno']); ?></td>
                                <td>
                                    <?php if ($rolAdministrador == 1): // Solo el admin general puede reactivar ?>
                                    <button class="btn-reactivar" onclick="confirmarReactivacion(<?php echo $usuario['id']; ?>)">
                                        <i class="fas fa-user-check"></i> Reactivar
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile;
                    else : ?>
                        <tr>
                            <td colspan="7">No hay usuarios inactivos.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    function confirmarReactivacion(id) {
        Swal.fire({
            title: '¿Reactivar este usuario?',
            text: "El usuario podrá acceder nuevamente al sistema.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, reactivar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                // Redirigir al script que procesa la reactivación
                window.location.href = 'reactivar_usuario.php?id=' + id;
            }
        });
    }

    // Script para auto-ocultar mensajes
    setTimeout(function() {
        document.querySelectorAll('.fade-out').forEach(function(element) {
            element.style.opacity = '0';
            setTimeout(() => element.style.display = 'none', 500);
        });
    }, 3000);
</script>

<?php
mysqli_close($db);
incluirTemplate('footer');
?>