<?php
require '../includes/funciones.php';

$auth = adminAutenticado();

if (!$auth) {
    header('Location: login.php');
    exit;
}

require '../includes/config/database.php';
$db = conectarDB();

$query = $_GET['query'] ?? '';


// Modificación 1: Incluir 'id' en la cláusula WHERE para la búsqueda
$sql = "SELECT * FROM usuariosexternos 
        WHERE nombreCompleto LIKE ? 
        OR identificacion LIKE ? 
        OR email LIKE ?
        OR id LIKE ?"; // <-- ¡Aquí se añade la búsqueda por ID!

$stmt = $db->prepare($sql);
$searchTerm = '%' . $query . '%';
// Modificación 2: Añadir un parámetro 's' para el nuevo campo 'id'
$stmt->bind_param('ssss', $searchTerm, $searchTerm, $searchTerm, $searchTerm); 
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($usuario = $result->fetch_assoc()) {
        echo '<tr>
                <td class="textosm">' . htmlspecialchars($usuario['id']) . '</td> <td class="textosm nombretable">' . htmlspecialchars($usuario['nombreCompleto']) . '</td>
                <td class="textosm">' . htmlspecialchars($usuario['identificacion']) . '</td>
                <td class="textosm">' . htmlspecialchars($usuario['email']) . '</td>
                <td class="textosm">' . htmlspecialchars($usuario['celular']) . '</td>
                <td class="domiciliotable textodomicilio">Calle: ' . htmlspecialchars($usuario['calle']) . ' Col: ' . htmlspecialchars($usuario['colonia']) . ' CP: #' . htmlspecialchars($usuario['CP']) . ' Ciudad: ' . htmlspecialchars($usuario['ciudad']) . '</td>
                <td>
                    <div class="botones--accion--container">
                        <a href="./prestamos/actualizar-prestamo.php?id=' . htmlspecialchars($usuario['id']) . '">
                            <button title="Prestamo" class="btnAceptado">Prestamo</button>
                        </a>
                    </div>
                </td>
            </tr>';
    }
} else {
    // Modificación 4: Ajustar el colspan si has añadido una columna más (la del ID)
    // Antes era 6, ahora debería ser 7 (o la cantidad total de columnas visibles)
    echo "<tr><td colspan='7'>No se encontraron resultados</td></tr>"; 
}

$stmt->close();
$db->close();
?>