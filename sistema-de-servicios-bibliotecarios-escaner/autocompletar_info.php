<?php
require_once 'database/Database.php';
require_once 'database/DatabaseAPI.php';

ob_clean();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['matricula'])) {
    $matricula = strtoupper(trim($_POST['matricula']));

    try {
        $conn = conectarDB3();

        if (!$conn) {
            echo json_encode(['success' => false, 'message' => 'Error de conexión a SQL Server']);
            exit;
        }

        $userData = null;

        if (strlen($matricula) <= 4) {
            $sql = "SELECT TOP 1 Nombre, ApellidoPaterno, ApellidoMaterno, NumeroEmpleado, 'Profesor' as TipoUsuario 
                    FROM Docentes WHERE NumeroEmpleado = ? AND Habilitado = 1";

            $params = array($matricula);
            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt === false)
                throw new Exception(print_r(sqlsrv_errors(), true));

            if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $userData = [
                    'success' => true,
                    'userData' => [
                        'nameUser' => trim($row['Nombre'] . ' ' . $row['ApellidoPaterno'] . ' ' . $row['ApellidoMaterno']),
                        'userType' => 'Profesor',
                        'id_carrera' => null,
                        'id_especialidad' => null
                    ]
                ];
            }
            sqlsrv_free_stmt($stmt);

        } elseif (strlen($matricula) == 10) {
            $sql = "SELECT TOP 1 
                A.Nombre, 
                A.ApellidoPaterno, 
                A.ApellidoMaterno, 
                A.Matricula, 
                A.IdCarrera, 
                GC.IdArea AS IdEspecialidad,
                'Alumno' as TipoUsuario 
            FROM Alumnos A
            LEFT JOIN GruposCuatrimestres GC ON A.IdGrupoCuatrimestre = GC.IdGrupoCuatrimestre
            WHERE A.Matricula = ? AND A.Habilitado = 1";

            $params = array($matricula);
            $stmt = sqlsrv_query($conn, $sql, $params);

            if ($stmt === false)
                throw new Exception(print_r(sqlsrv_errors(), true));

            if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $userData = [
                    'success' => true,
                    'userData' => [
                        'nameUser' => trim($row['Nombre'] . ' ' . $row['ApellidoPaterno'] . ' ' . $row['ApellidoMaterno']),
                        'userType' => 'Alumno',
                        'id_carrera' => isset($row['IdCarrera']) ? $row['IdCarrera'] : null,
                        // Asignamos el IdArea obtenido a id_especialidad
                        'id_especialidad' => isset($row['IdEspecialidad']) ? $row['IdEspecialidad'] : null
                    ]
                ];
            }
            sqlsrv_free_stmt($stmt);
        }

        sqlsrv_close($conn);

        if ($userData) {
            echo json_encode($userData);
        } else {
            echo json_encode(['success' => false, 'message' => 'No se encontró usuario']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Solicitud inválida']);
}
exit;