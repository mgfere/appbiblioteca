<?php
require '../includes/funciones.php';

$auth = adminAutenticado();

if (!$auth) {
    header('Location: login.php');
    exit;
}

require '../includes/config/database.php';
$db = conectarDB();

$query = isset($_GET['query']) ? $_GET['query'] : '';

// Modificación 1: Incluir 'id' en la cláusula WHERE para la búsqueda (opcional, pero buena práctica si el ID es buscable)
$sql = "SELECT * FROM secciones WHERE nombre_seccion LIKE ?";

// Si quieres buscar por ID también, descomenta la siguiente línea y ajusta bind_param
// $sql = "SELECT * FROM secciones WHERE nombre_seccion LIKE ? OR id LIKE ?";

$stmt = $db->prepare($sql);
$searchQuery = '%' . $query . '%';

// Modificación 2: Si habilitaste la búsqueda por ID, cambia a 'ss' y añade otro $searchQuery
$stmt->bind_param('s', $searchQuery); // Si solo buscas por nombre_seccion

// Si habilitaste la búsqueda por ID:
// $stmt->bind_param('ss', $searchQuery, $searchQuery);

$stmt->execute();
$resultado = $stmt->get_result();

if ($resultado->num_rows > 0) {
    while ($seccion = $resultado->fetch_assoc()) {
        echo "<tr>
                    <td>" . htmlspecialchars($seccion['id']) . "</td> <td>" . htmlspecialchars($seccion['nombre_seccion']) . "</td>
                    <td>
                        <button title='" . htmlspecialchars($seccion['nombre_seccion']) . "' style='background-color: " . htmlspecialchars($seccion['color']) . "' class='reservacion--libro'></button>
                    </td>
                    <td>
                        <div class='botones--accion--container'>
                            <a href='./secciones/actualizar-seccion.php?id=" . $seccion['id'] . "'>
                                <button title='Editar' class='btnAceptado'>Editar</button>
                            </a>
                            <form id='eliminar-form-" . $seccion['id'] . "' method='POST'>
                                <input type='hidden' name='id' value='" . $seccion['id'] . "'>
                                <button type='button' title='Eliminar' class='btnCancelar' onclick='confirmarEliminacion(" . $seccion['id'] . ", \"" . addslashes($seccion['nombre_seccion']) . "\")'>Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>";
    }
} else {
    // Modificación 4: Ajustar el colspan si has añadido una columna más (la del ID)
    // Antes era 3, ahora debería ser 4
    echo "<tr><td colspan='4'>No se encontraron resultados</td></tr>";
}

$stmt->close();
$db->close();
?>