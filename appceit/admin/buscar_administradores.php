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

$sql = "SELECT * FROM administradores WHERE nombre LIKE ? OR matricula LIKE ?";
$stmt = $db->prepare($sql);
$searchQuery = '%' . $query . '%';
$stmt->bind_param('ss', $searchQuery, $searchQuery);
$stmt->execute();
$resultado = $stmt->get_result();

$numeroFila = 1;

if ($resultado->num_rows > 0) {
    while ($administrador = $resultado->fetch_assoc()) {
        echo "<tr>
                <td>" . $numeroFila++ . "</td>
                <td>" . htmlspecialchars($administrador['nombre']) . "</td>
                <td>" . htmlspecialchars($administrador['matricula']) . "</td>
                <td>";
        if ($administrador['rol'] == 1) {
            echo "ADMINISTRADOR GENERAL";
        } else {
            echo "ADMINISTRADOR";
        }
        echo "</td>
                <td>
                    <div class='botones--accion--container'>
                        <form id='eliminar-form-" . $administrador['id'] . "' method='POST'>
                            <input type='hidden' name='id' value='" . $administrador['id'] . "'>
                            <button type='button' title='Eliminar' class='btnCancelar' onclick='confirmarEliminacion(" . $administrador['id'] . ", \"" . addslashes($administrador['nombre']) . "\")'>Eliminar</button>
                        </form>
                    </div>
                </td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='5'>No se encontraron resultados</td></tr>";
}
