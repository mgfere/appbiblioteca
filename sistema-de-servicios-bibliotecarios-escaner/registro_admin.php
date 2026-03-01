<?php
session_start();

require_once 'database/Database.php';
require_once 'database/DatabaseAPI.php';

$NombreUsuario = $_SESSION['user'] ?? null;
$RolUsuario = $_SESSION['rol'] ?? 0; 

if (!$NombreUsuario) {
    header('Location: login_admin.php');
    exit;
}

$db = new DatabaseAPI();
$exito = '';
$message = [];

$listaRoles = $db->obtenerRoles(); 
$usuarios = $db->obtenerTodosLosUsuarios($RolUsuario);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] == 'create') {
        $user = $_POST['user'] ?? '';
        $password = $_POST['password'] ?? '';
        $matricula = $_POST['matricula'] ?? '';
        $correo = $_POST['correo'] ?? '';
        
        $rolNuevo = 2; 
        if ($RolUsuario == 1 && isset($_POST['rol'])) {
            $rolNuevo = $_POST['rol'];
        }

        if ($db->verificarUsuarioExistente($user)) {
            $message['usuario_existente'] = 'El usuario ya existe.';
        } else {
            if ($db->registrarAdministrador($user, $password, $matricula, $correo, $rolNuevo)) {
                $exito = 'Usuario creado exitosamente.';
            } else {
                $message['generales'] = 'Error al crear el usuario.';
            }
        }
    }

    if ($_POST['action'] == 'edit') {
        $id = $_POST['id'] ?? '';
        $currentPassword = $_POST['current_password'] ?? '';
        $user = $_POST['user'] ?? '';
        $password = $_POST['password'] ?? '';
        $matricula = $_POST['matricula'] ?? '';
        $correo = $_POST['correo'] ?? '';
        
        $rolEdit = $_POST['rol_actual_hidden'] ?? 2;
        if ($RolUsuario == 1 && isset($_POST['rol'])) {
            $rolEdit = $_POST['rol'];
        }

        if ($db->verificarContraseñaActual($id, $currentPassword)) {
            if ($db->editarUsuario($id, $user, $password, $matricula, $correo, $rolEdit)) {
                $exito = 'Usuario actualizado correctamente.';
            } else {
                $message['generales'] = 'Error al actualizar el usuario.';
            }
        } else {
            $message['generales'] = 'La contraseña actual es incorrecta.';
        }
    }

    if ($_POST['action'] == 'delete') {
        $id = $_POST['id'] ?? '';
        $currentPassword = $_POST['password'] ?? '';

        if ($db->verificarContraseñaActual($id, $currentPassword)) {
            if ($db->eliminarUsuario($id)) {
                $exito = 'Usuario eliminado correctamente.';
            } else {
                $message['generales'] = 'Error al eliminar el usuario.';
            }
        } else {
            $message['generales'] = 'La contraseña actual es incorrecta.';
        }
    }
    
    // Recargar usuarios para ver cambios
    $usuarios = $db->obtenerTodosLosUsuarios($RolUsuario);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Registro de Administradores</title>
    <link rel="stylesheet" href="style/style.css">
    <link rel="stylesheet" href="output.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" href="img/favicon.ico" type="image/x-icon">
</head>
<style>
    .swal-confirm-button {
        background-color: #d67830ff !important;
        color: white !important;
        border: none !important;
        padding: 10px 24px !important;
        border-radius: 5px !important;
    }
</style>
<body>
    <main>
        <header>
            <div class="header-bar">
                <a href="index_admin.php"><img src="img/Image.jpeg" alt="Logo" id="logo"></a>
            </div>
        </header>
        <h1 class="my-5 text-center"><b>Registro de Administradores</b></h1>

        <div class="flex justify-center mb-5">
            <button onclick="abrirCrearModal()" class="flex items-center gap-2 px-4 py-2 rounded-md transform transition duration-300 hover:scale-105" style="background-color: #09a787;">
                <img src="img/crear.png" alt="Crear" class="w-6 h-6">
                <span class="text-white font-semibold">Crear Usuario</span>
            </button>
        </div>

        <table class="mx-auto border-collapse border border-gray-400 w-4/5">
            <thead>
                <tr>
                    <th class="border border-gray-400 px-4 py-2 text-center">Usuario</th>
                    <th class="border border-gray-400 px-4 py-2 text-center">Rol</th>
                    <th class="border border-gray-400 px-4 py-2 text-center">Matrícula</th>
                    <th class="border border-gray-400 px-4 py-2 text-center">Correo</th>
                    <th class="border border-gray-400 px-4 py-2 text-center">Contraseña</th>
                    <th class="border border-gray-400 px-4 py-2 text-center">Opciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $usuario) : ?>
                    <tr>
                        <td class="border border-gray-400 px-4 py-2 text-center"><?php echo htmlspecialchars($usuario['user']); ?></td>
                        <td class="border border-gray-400 px-4 py-2 text-center font-bold text-gray-600">
                            <?php echo htmlspecialchars($usuario['nombreRol'] ?? 'Sin Rol'); ?>
                        </td>
                        <td class="border border-gray-400 px-4 py-2 text-center"><?php echo htmlspecialchars($usuario['Matricula']); ?></td>
                        <td class="border border-gray-400 px-4 py-2 text-center text-sm"><?php echo htmlspecialchars($usuario['correo']); ?></td>
                        <td class="border border-gray-400 px-4 py-2 text-center">*****</td>
                        <td class="border border-gray-400 px-4 py-2 text-center">
                            <div class="flex justify-center gap-2">
                                <button onclick="abrirModal('<?php echo $usuario['id_user']; ?>', '<?php echo htmlspecialchars($usuario['user']); ?>', '<?php echo htmlspecialchars($usuario['Matricula']); ?>', '<?php echo htmlspecialchars($usuario['correo']); ?>', '<?php echo $usuario['rol']; ?>')" class="inline-block bg-yellow-300 hover:bg-yellow-400 rounded p-1">
                                    <img src="img/edit-2.png" alt="Editar" class="w-5 h-5">
                                </button>

                                <button onclick="abrirEliminarModal('<?php echo $usuario['id_user']; ?>')" class="inline-block bg-red-400 hover:bg-red-500 rounded p-1 transform transition duration-300 ease-in-out hover:scale-110">
                                    <img src="img/delete-2.png" alt="Borrar" class="w-6 h-6">
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
    <br><br><br>
    <?php include 'footer.php'; ?>

    <div id="crearModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background-color: rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:50;">
        <div style="background-color: #D9D9D9;" class="rounded-lg p-5 w-80">
            <div class="flex justify-between items-center mb-2">
                <h2 class="text-xl font-bold">Crear Usuario</h2>
                <button onclick="cerrarCrearModal()" class="text-xl" style="color: #09a787;">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                
                <label class="block font-bold mb-1">Nombre de Usuario</label>
                <input type="text" name="user" class="w-full mb-3 px-3 py-2 rounded-lg focus:outline-none bg-white" required>

                <label class="block font-bold mb-1">Matrícula</label>
                <input type="text" name="matricula" class="w-full mb-3 px-3 py-2 rounded-lg focus:outline-none bg-white" required>

                <label class="block font-bold mb-1">Correo</label>
                <input type="email" name="correo" class="w-full mb-3 px-3 py-2 rounded-lg focus:outline-none bg-white" required>

                <?php if($RolUsuario == 1): ?>
                <label class="block font-bold mb-1">Rol</label>
                <select name="rol" class="w-full mb-3 px-3 py-2 rounded-lg bg-white focus:outline-none">
                    <?php foreach($listaRoles as $rolOp): ?>
                        <option value="<?php echo $rolOp['IdRol']; ?>"><?php echo $rolOp['nombreRol']; ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>

                <label class="block font-bold mb-1">Contraseña</label>
                <div class="password-toggle-wrapper relative">
                    <input type="password" name="password" id="crear-password" class="w-full mb-5 px-3 py-2 rounded-lg focus:outline-none bg-white pr-10" required>
                    <button type="button" class="password-toggle-btn absolute right-3 top-1/2 transform -translate-y-1/2" onclick="mostrarPassword('crear-password', this)">
                        <span class="fa fa-eye-slash icon text-gray-600"></span>
                    </button>
                </div>

                <button type="submit" class="w-full text-white font-bold py-2 rounded-md" style="background-color: #09a787;">Guardar</button>
            </form>
        </div>
    </div>

    <div id="editarModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background-color: rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:50;">
        <div style="background-color: #D9D9D9;" class="rounded-lg p-5 w-80">
            <div class="flex justify-between items-center mb-2">
                <h2 class="text-xl font-bold">Editar Usuario</h2>
                <button onclick="cerrarModal()" class="text-xl" style="color: #09a787;">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit-id" name="id">
                <input type="hidden" id="edit-rol-hidden" name="rol_actual_hidden">

                <label class="block font-bold mb-1">Usuario</label>
                <input type="text" name="user" id="edit-user" class="w-full mb-3 px-3 py-2 rounded-lg bg-white" required>

                <label class="block font-bold mb-1">Matrícula</label>
                <input type="text" name="matricula" id="edit-matricula" class="w-full mb-3 px-3 py-2 rounded-lg bg-white" required>

                <label class="block font-bold mb-1">Correo</label>
                <input type="email" name="correo" id="edit-correo" class="w-full mb-3 px-3 py-2 rounded-lg bg-white" required>

                <?php if($RolUsuario == 1): ?>
                <label class="block font-bold mb-1">Rol</label>
                <select name="rol" id="edit-rol" class="w-full mb-3 px-3 py-2 rounded-lg bg-white focus:outline-none">
                    <?php foreach($listaRoles as $rolOp): ?>
                        <option value="<?php echo $rolOp['IdRol']; ?>"><?php echo $rolOp['nombreRol']; ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>

                <label class="block font-bold mb-1">Nueva Contraseña</label>
                <div class="password-toggle-wrapper relative">
                    <input type="password" name="password" id="edit-password" class="w-full mb-3 px-3 py-2 rounded-lg bg-white pr-10" required>
                    <button type="button" class="password-toggle-btn absolute right-3 top-1/2 transform -translate-y-1/2" onclick="mostrarPassword('edit-password', this)">
                        <span class="fa fa-eye-slash icon text-gray-600"></span>
                    </button>
                </div>

                <label class="block font-bold mb-1">Contraseña Actual</label>
                <div class="password-toggle-wrapper relative">
                    <input type="password" name="current_password" id="edit-current-password" class="w-full mb-5 px-3 py-2 rounded-lg bg-white pr-10" required>
                    <button type="button" class="password-toggle-btn absolute right-3 top-1/2 transform -translate-y-1/2" onclick="mostrarPassword('edit-current-password', this)">
                        <span class="fa fa-eye-slash icon text-gray-600"></span>
                    </button>
                </div>

                <button type="submit" class="w-full text-white font-bold py-2 rounded-md" style="background-color: #09a787;">Guardar</button>
            </form>
        </div>
    </div>

    <div id="eliminarModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background-color: rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:50;">
        <div style="background-color: #D9D9D9;" class="rounded-lg p-5 w-80">
            <h2 class="text-xl font-bold mb-3">Eliminar Usuario</h2>
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" id="delete-id" name="id">
                <label class="block font-bold mb-1">Confirmar Contraseña</label>
                <div class="password-toggle-wrapper relative">
                    <input type="password" id="eliminar-password" name="password" placeholder="Contraseña" class="w-full px-3 py-2 rounded-lg focus:outline-none bg-white pr-10" required>
                    <button type="button" class="password-toggle-btn absolute right-3 top-1/2 transform -translate-y-1/2" onclick="mostrarPassword('eliminar-password', this)">
                        <span class="fa fa-eye-slash icon text-gray-600"></span>
                    </button>
                </div>
                <button type="submit" class="w-full text-white font-bold py-2 rounded-md mt-3" style="background-color: #09a787;">Eliminar</button>
            </form>
        </div>
    </div>

    <script>
        // JAVASCRIPT ACTUALIZADO
        // Ahora recibe 'rol' para llenar el select en editar
        function abrirModal(id, user, matricula, correo, rol) {
            document.getElementById('edit-id').value = id;
            document.getElementById('edit-user').value = user;
            document.getElementById('edit-matricula').value = matricula;
            document.getElementById('edit-correo').value = correo;
            document.getElementById('edit-rol-hidden').value = rol;
            
            // Si el selector de rol existe (es master), lo seleccionamos
            var selectRol = document.getElementById('edit-rol');
            if (selectRol) {
                selectRol.value = rol;
            }
            
            document.getElementById('editarModal').style.display = 'flex';
        }

        function cerrarModal() { document.getElementById('editarModal').style.display = 'none'; }
        function abrirEliminarModal(id) { document.getElementById('delete-id').value = id; document.getElementById('eliminarModal').style.display = 'flex'; }
        function cerrarEliminarModal() { document.getElementById('eliminarModal').style.display = 'none'; }
        function abrirCrearModal() { document.getElementById('crearModal').style.display = 'flex'; }
        function cerrarCrearModal() { document.getElementById('crearModal').style.display = 'none'; }

        function mostrarPassword(inputId, button) {
            var input = document.getElementById(inputId);
            var icon = button.querySelector('.icon');
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            } else {
                input.type = "password";
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            }
        }

        document.addEventListener('click', function(event) {
            if (event.target === document.getElementById('crearModal')) cerrarCrearModal();
            if (event.target === document.getElementById('editarModal')) cerrarModal();
            if (event.target === document.getElementById('eliminarModal')) cerrarEliminarModal();
        });
    </script>

    <?php if (!empty($exito)): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    position: 'top-end',
                    icon: 'success',
                    title: '<?php echo $exito; ?>',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    toast: true,
                    background: '#f0f9f0',
                    iconColor: '#28a745',
                }).then(function(result) {
                    window.location.href = 'registro_admin.php';
                });
            });
        </script>
    <?php endif; ?>

    <?php if (isset($message['generales']) && !empty($message['generales'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    position: 'top-end',
                    icon: 'error',
                    title: 'Error',
                    text: '<?php echo $message['generales']; ?>',
                    showConfirmButton: false,
                    timer: 4000,
                    timerProgressBar: true,
                    toast: true,
                    background: '#fdf2f2',
                    iconColor: '#dc3545'
                }).then(function(result) {
                    window.location.href = 'registro_admin.php';
                });
            });
        </script>
    <?php endif; ?>

    <?php if (isset($message['usuario_existente']) && !empty($message['usuario_existente'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    position: 'center',
                    icon: 'warning',
                    title: 'Usuario Existente',
                    text: '<?php echo $message['usuario_existente']; ?>',
                    showConfirmButton: true,
                    confirmButtonColor: '#3085d6',
                    customClass: { confirmButton: 'swal-confirm-button' },
                    buttonsStyling: false
                }).then(function(result) {
                    window.location.href = 'registro_admin.php';
                });
            });
        </script>
    <?php endif; ?>

</body>
</html>