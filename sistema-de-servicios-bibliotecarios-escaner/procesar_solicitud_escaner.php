<?php
require_once 'database/Database.php';
require_once 'database/DatabaseAPI.php';
require_once 'src/TokenDecrypter.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$matriculaInput = $_POST['matricula'] ?? '';

// Intentar descifrar el token
$matriculaDescifrada = TokenDecrypter::decrypt($matriculaInput);

// Si se descifró correctamente, usarla; si no, asumir que es una matrícula manual
$matricula = strtoupper(trim($matriculaDescifrada ? $matriculaDescifrada : $matriculaInput));


$id_servicio = $_POST['id_servicio'] ?? '';

if (empty($matricula) || empty($id_servicio)) {
    echo json_encode(['success' => false, 'message' => 'Faltan datos (matrícula o servicio)']);
    exit;
}

$dbAPI = new DatabaseAPI();

try {
    // 1. Revisar si usuario existe localmente
    $existeLocal = $dbAPI->usuarioExistenteRevisar($matricula);

    if ($existeLocal) {
        // Usuario existe -> Registrar Solicitud DIRECTAMENTE
        $res = $dbAPI->insertarRegistroSolicitud($matricula, $id_servicio);
        if ($res === true) {
            echo json_encode(['success' => true, 'message' => 'Se ha registrado la solicitud exitosamente']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar solicitud: ' . $res]);
        }
    } else {
        // 2. Usuario NO existe localmente -> Buscar en SQL Server
        $datosExternos = buscarEnSQLServer($matricula);

        if ($datosExternos) {
            // Encontrado en SQL Server -> Registrar Usuario Y Solicitud
            $res = $dbAPI->insertingTeachersAndStudents(
                $matricula,
                $datosExternos['nameUser'],
                $datosExternos['id_especialidad'],
                $datosExternos['id_carrera'],
                $id_servicio,
                $datosExternos['userType']
            );

            if ($res === true) {
                echo json_encode(['success' => true, 'message' => 'Se ha registrado la solicitud exitosamente']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al registrar usuario: ' . $res]);
            }
        } else {
            // No encontrado en ningún lado
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado en base de datos escolar']);
        }
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error servidor: ' . $e->getMessage()]);
}

// ------ Helper function based on autocompletar_info.php logic ------
function buscarEnSQLServer($matricula)
{
    $conn = conectarDB3(); // Defined in database/Database.php
    if (!$conn)
        return null;

    $userData = null;

    // Lógica Docentes
    if (strlen($matricula) <= 4) {
        $sql = "SELECT TOP 1 Nombre, ApellidoPaterno, ApellidoMaterno, NumeroEmpleado 
                FROM Docentes WHERE NumeroEmpleado = ? AND Habilitado = 1";
        $stmt = sqlsrv_query($conn, $sql, array($matricula));
        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $userData = [
                'nameUser' => trim($row['Nombre'] . ' ' . $row['ApellidoPaterno'] . ' ' . $row['ApellidoMaterno']),
                'userType' => 'Profesor',
                'id_carrera' => null,
                'id_especialidad' => null
            ];
        }
        if ($stmt)
            sqlsrv_free_stmt($stmt);
    }
    // Lógica Alumnos
    else {
        $sql = "SELECT TOP 1 
                    A.Nombre, 
                    A.ApellidoPaterno, 
                    A.ApellidoMaterno, 
                    A.Matricula, 
                    A.IdCarrera, 
                    GC.IdArea AS IdEspecialidad
                FROM Alumnos A
                LEFT JOIN GruposCuatrimestres GC ON A.IdGrupoCuatrimestre = GC.IdGrupoCuatrimestre
                WHERE A.Matricula = ? AND A.Habilitado = 1";

        $stmt = sqlsrv_query($conn, $sql, array($matricula));
        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $userData = [
                'nameUser' => trim($row['Nombre'] . ' ' . $row['ApellidoPaterno'] . ' ' . $row['ApellidoMaterno']),
                'userType' => 'Alumno',
                'id_carrera' => isset($row['IdCarrera']) ? $row['IdCarrera'] : null,
                'id_especialidad' => isset($row['IdEspecialidad']) ? $row['IdEspecialidad'] : null
            ];
        }
        if ($stmt)
            sqlsrv_free_stmt($stmt);
    }

    sqlsrv_close($conn);
    return $userData;
}
?>