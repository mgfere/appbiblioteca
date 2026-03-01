<?php
require_once '../database/Database.php';
require_once '../database/DatabaseAPI.php';

session_start();

if (!isset($_SESSION['user'])) {
    header("Location: login_admin.php");
    exit();
}

$db = new DatabaseAPI();
$message = '';

// Handle Create (Add) User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $user = $_POST['user'];
    $password = $_POST['password'];

    // Handle Create User
    if ($_POST['action'] == 'create') {
        // Check if user exists
        if ($db->verificarUsuarioExistente($user)) {
            $message = 'El usuario ya existe. Intente con otro nombre de usuario.';
        } else {
            // Register new user
            if ($db->registrarAdministrador($user, $password)) {
                $message = 'Usuario creado exitosamente.';
            } else {
                $message = 'Error al crear el usuario.';
            }
        }
    }

    // Handle Edit User
    if ($_POST['action'] == 'edit') {
        $id = $_POST['id'];
        $currentPassword = $_POST['current_password']; // Contraseña actual
        $user = $_POST['user'];
        $password = $_POST['password'];

        // Verificar si la contraseña actual es correcta
        if ($db->verificarContraseñaActual($id, $currentPassword)) {
            // Si la contraseña es correcta, proceder con la actualización
            if ($db->editarUsuario($id, $user, $password)) {
                $message = 'Usuario actualizado correctamente.';
            } else {
                $message = 'Error al actualizar el usuario.';
            }
        } else {
            $message = 'La contraseña actual es incorrecta.';
        }
    }

    // Handle Delete User
    if ($_POST['action'] == 'delete') {
        $id = $_POST['id'];
        $currentPassword = $_POST['password']; // Contraseña actual
        // Verificar si la contraseña actual es correcta
        if ($db->verificarContraseñaActual($id, $currentPassword)) {
            // Si la contraseña es correcta, eliminar el usuario
            if ($db->eliminarUsuario($id)) {
                $message = 'Usuario eliminado correctamente.';
            } else {
                $message = 'Error al eliminar el usuario.';
            }
        } else {
            $message = 'La contraseña actual es incorrecta.';
        }
    }

}

$usuarios = $db->obtenerTodosLosUsuarios();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Registro de Administradores</title>
    <link rel="stylesheet" href="../style/style.css">
    <link rel="stylesheet" href="../output.css">
</head>
<body>
<main>
    <header>
        <div class="header-bar">
            <a href="index_admin.php"><img src="../img/Image.jpeg" alt="Logo" id="logo"></a>
        </div>
    </header>
    <h1 class="my-5 text-center"><b>Registro de Administradores</b></h1>

    <?php if (!empty($message)) : ?>
        <h2 class="flex justify-center m-5"><?php echo $message; ?></h2>
    <?php endif; ?>

    <div class="flex justify-center mb-5">
        <button onclick="abrirCrearModal()" class="flex items-center gap-2 px-4 py-2 rounded-md transform transition duration-300 hover:scale-105" style="background-color: #09a787;">
            <img src="../img/crear.png" alt="Crear" class="w-6 h-6">
            <span class="text-white font-semibold">Crear Usuario</span>
        </button>
    </div>

    <table class="mx-auto border-collapse border border-gray-400">
        <thead>
            <tr>
                <th class="border border-gray-400 px-4 py-2 text-center">Usuario</th>
                <th class="border border-gray-400 px-4 py-2 text-center">Contraseña</th>
                <th class="border border-gray-400 px-4 py-2 text-center">Opciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($usuarios as $usuario) : ?>
                <tr>
                    <td class="border border-gray-400 px-4 py-2 text-center"><?php echo htmlspecialchars($usuario['user']); ?></td>
                    <td class="border border-gray-400 px-4 py-2 text-center">*****</td>
                    <td class="border border-gray-400 px-4 py-2 text-center">
                        <div class="flex justify-center gap-2">
                            <button onclick="abrirModal('<?php echo $usuario['id_user']; ?>', '<?php echo htmlspecialchars($usuario['user']); ?>')" class="inline-block bg-yellow-300 hover:bg-yellow-400 rounded p-1">
                                <img src="../img/edit-2.png" alt="Editar" class="w-5 h-5">
                            </button>

                            <button onclick="abrirEliminarModal('<?php echo $usuario['id_user']; ?>')" class="inline-block bg-red-400 hover:bg-red-500 rounded p-1 transform transition duration-300 ease-in-out hover:scale-110">
                                <img src="../img/delete-2.png" alt="Borrar" class="w-6 h-6">
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <br><br><br>
    <?php include 'footer.php'; ?>

    <!-- Modal Crear Usuario -->
    <div id="crearModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background-color: rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:50;">
        <div style="background-color: #D9D9D9;" class="rounded-lg p-5 w-80">
            <div class="flex justify-between items-center mb-2" style="display: flex; justify-content: space-between;">
                <h2 class="text-xl font-bold">Crear Usuario</h2>
                <button onclick="cerrarCrearModal()" class="text-xl ml-5 pr-5" style="color: #09a787; font-size: 1.5rem;">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <label class="block font-bold mb-1">Nombre de Usuario</label>
                <input type="text" name="user" placeholder="Usuario" class="w-full mb-3 px-3 py-2 rounded-lg focus:outline-none bg-white" required>
                
                <label class="block font-bold mb-1">Contraseña</label>
                <input type="password" name="password" placeholder="Contraseña" class="w-full mb-5 px-3 py-2 rounded-lg focus:outline-none bg-white" required>

                <button type="submit" class="w-full text-white font-bold py-2 rounded-md" style="background-color: #09a787;">Guardar</button>
            </form>
        </div>
    </div>

    <!-- Modal Editar Usuario -->
    <div id="editarModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background-color: rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:50;">
        <div style="background-color: #D9D9D9;" class="rounded-lg p-5 w-80">
            <div class="flex justify-between items-center mb-2" style="display: flex; justify-content: space-between;">
                <h2 class="text-xl font-bold">Editar Usuario</h2>
                <button onclick="cerrarModal()" class="text-xl ml-5 pr-5" style="color: #09a787; font-size: 1.5rem;">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" id="edit-id" name="id">
                
                <label class="block font-bold mb-1">Nuevo Nombre de Usuario</label>
                <input type="text" name="user" id="edit-user" class="w-full mb-3 px-3 py-2 rounded-lg focus:outline-none bg-white" required>
                
                <label class="block font-bold mb-1">Nueva Contraseña</label>
                <input type="password" name="password" id="edit-password" class="w-full mb-5 px-3 py-2 rounded-lg focus:outline-none bg-white" required>

                <label class="block font-bold mb-1">Contraseña Actual</label>
                <input type="password" name="current_password" id="edit-current-password" class="w-full mb-3 px-3 py-2 rounded-lg focus:outline-none bg-white" required>

                <button type="submit" class="w-full text-white font-bold py-2 rounded-md" style="background-color: #09a787;">Guardar</button>
            </form>
        </div>
    </div>

    <!-- Modal Eliminar Usuario -->
    <div id="eliminarModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background-color: rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:50;">
        <div style="background-color: #D9D9D9;" class="rounded-lg p-5 w-80">
            <div class="flex justify-between items-center mb-2" style="display: flex; justify-content: space-between;">
                <h2 class="text-xl font-bold">Eliminar Usuario</h2>
                <button onclick="cerrarEliminarModal()" class="text-xl ml-5 pr-5" style="color: #09a787; font-size: 1.5rem;">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" id="delete-id" name="id">
                <label class="block font-bold mb-1">Confirmar Contraseña</label>
                <input type="password" name="password" class="w-full mb-5 px-3 py-2 rounded-lg focus:outline-none bg-white" required>
                <button type="submit" class="w-full text-white font-bold py-2 rounded-md" style="background-color: #09a787;">Eliminar</button>
            </form>
        </div>
    </div>
</main>

<script>
// Open/Close Modals
function abrirModal(id, user) {
    document.getElementById('edit-id').value = id;
    document.getElementById('edit-user').value = user;
    document.getElementById('editarModal').style.display = 'flex';
}

function cerrarModal() {
    document.getElementById('editarModal').style.display = 'none';
}

function abrirEliminarModal(id) {
    document.getElementById('delete-id').value = id;
    document.getElementById('eliminarModal').style.display = 'flex';
}

function cerrarEliminarModal() {
    document.getElementById('eliminarModal').style.display = 'none';
}

function abrirCrearModal() {
    document.getElementById('crearModal').style.display = 'flex';
}

function cerrarCrearModal() {
    document.getElementById('crearModal').style.display = 'none';
}
</script>

</body>
</html>