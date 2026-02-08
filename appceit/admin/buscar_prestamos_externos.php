<?php
session_start();

require '../includes/funciones.php';

$rolAdministrador = isset($_SESSION['rol']) ? $_SESSION['rol'] : null;

require '../includes/config/database.php';
$db = conectarDB();

$query = $_GET['query'] ?? '';

$sql = "SELECT p.*, l.codigo AS codigoLibro, s.color AS colorSeccion
        FROM prestamospresencial p
        LEFT JOIN libros l ON p.codigoLibro = l.codigo
        LEFT JOIN secciones s ON l.seccionId = s.id
        WHERE p.estatus = '1'";

if (!empty($query)) {
    $searchTerms = explode(' ', $query);
    $searchTerms = array_filter($searchTerms, 'strlen');

    if (!empty($searchTerms)) {
        $sql .= " AND (";

        $conditions = [];
        $params = [];
        $types = '';

        foreach ($searchTerms as $term) {
            $conditions[] = "(l.codigo LIKE ? OR p.nombreCompleto LIKE ? OR p.entregado LIKE ?)";
            $params[] = '%' . $term . '%';
            $params[] = '%' . $term . '%';
            $params[] = '%' . $term . '%';
            $types .= 'sss';
        }

        $sql .= implode(' AND ', $conditions);
        $sql .= ")";
    }
}

$stmt = $db->prepare($sql);

if (!$stmt) {
    // Si la preparación falla, es un error de SQL. Muy útil para depurar.
    die('Error al preparar la consulta: ' . $db->error);
}


if (!empty($query) && !empty($searchTerms)) {
    // CORRECCIÓN CLAVE AQUÍ: Asegurar que los parámetros se pasen por referencia
    // PHP 7.1+ permite unbinding de parámetros directamente, pero para compatibilidad
    // o para asegurar que sea por referencia, se construye el array de esta forma.
    
    // Crear un array de referencias para bind_param
    $bindParams = [];
    $bindParams[] = $types; // El primer elemento es la cadena de tipos

    foreach ($params as $key => $value) {
        $bindParams[] = &$params[$key]; // Pasar cada valor por referencia
    }

    call_user_func_array([$stmt, 'bind_param'], $bindParams);
}

$stmt->execute();
$resultadoUsuario = $stmt->get_result();

if ($resultadoUsuario->num_rows > 0) {
    while ($prestamo = $resultadoUsuario->fetch_assoc()) {
        echo '<tr>
                <td class="textosm">' . date('d/m/Y', strtotime($prestamo['fechaPrestamo'])) . '</td>
                <td class="textosm">' . date('d/m/Y', strtotime($prestamo['fechaDevolucion'])) . '</td>
                <td class="textosm">Prestamo</td>
                <td class="textosm">
                    <button style="background-color: ' . htmlspecialchars($prestamo['colorSeccion']) . '" class="reservacion--libro"></button>
                    ' . htmlspecialchars($prestamo['codigoLibro']) . '
                </td>
                <td class="textosm">' . htmlspecialchars($prestamo['cantidad']) . '</td>
                <td class="textosm">' . htmlspecialchars($prestamo['nombreCompleto']) . '</td>
                <td class="textosm">' . htmlspecialchars($prestamo['email']) . '</td>';

        if ($rolAdministrador == 1) {
            echo '<td>' . htmlspecialchars($prestamo['entregado'] ?? 'N/A') . '</td>';
        }

        echo '  <td>
                    <div class="botones--accion--container">
                        <button title="Devuelto" type="button" class="btnAceptado" value="' . $prestamo['id'] . '" >Devuelto</button>
                    </div>
                </td>
            </tr>';
    }
} else {
    $colspan = ($rolAdministrador == 1) ? '9' : '8';
    echo "<tr><td colspan='" . $colspan . "'>No se encontraron resultados</td></tr>";
}

$stmt->close();
$db->close();
?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        $(document).on('click', '.btnAceptado', function() {
            var prestamoId = $(this).val();

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
                            title: "Préstamo devuelto correctamente",
                            icon: "success"
                        }).then(() => {
                            window.location = "panel-prestamos-presenciales.php?resultado=2";
                        });
                    } else {
                        alert('Hubo un error al devolver el préstamo: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.log(xhr.responseText);
                    alert('Error en la solicitud AJAX: ' + error);
                }
            });
        });
    });
</script>