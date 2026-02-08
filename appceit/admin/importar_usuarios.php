<?php
require '../includes/funciones.php';
$auth = adminAutenticado();

if (!$auth) {
    header('Location: login.php');
    exit;
}

require '../includes/config/database.php';
$db = conectarDB();

require '../vendor/autoload.php';

use Shuchkin\SimpleXLSX;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['archivo_excel']) && $_FILES['archivo_excel']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['archivo_excel']['tmp_name'];
        $fileName = $_FILES['archivo_excel']['name'];
        $fileSize = $_FILES['archivo_excel']['size'];
        $fileType = $_FILES['archivo_excel']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        $allowedfileExtensions = ['xls', 'xlsx', 'csv'];
        if (in_array($fileExtension, $allowedfileExtensions)) {
            if ($xlsx = SimpleXLSX::parse($fileTmpPath)) {
                $errores = [];
                $insertados = 0;
                
                foreach ($xlsx->sheetNames() as $sheetIndex => $sheetName) {
                    $rows = $xlsx->rows($sheetIndex);
                    foreach ($rows as $index => $row) {
                        if ($index === 0) continue; // Saltar la fila de encabezado

                        // Omitir filas en blanco
                        if (empty(array_filter($row))) continue;

                        // Mapeo correcto según la estructura del archivo
                        $nombreCompleto = isset($row[0]) ? trim($row[0]) : '';
                        $matricula = isset($row[1]) ? trim($row[1]) : '';
                        $carrera = isset($row[2]) ? trim($row[2]) : '';
                        $especialidad = isset($row[3]) ? trim($row[3]) : '';
                        $email = isset($row[4]) ? trim($row[4]) : '';
                        $turno = isset($row[5]) ? trim($row[5]) : ''; // Agregar turno desde columna 6

                        // Separar nombre y apellido si están en una sola columna
                        $nombreParts = explode(' ', $nombreCompleto, 2);
                        $nombre = isset($nombreParts[0]) ? $nombreParts[0] : '';
                        $apellido = isset($nombreParts[1]) ? $nombreParts[1] : '';

                        // Validar campos obligatorios
                        if (empty($nombre) || empty($matricula) || empty($email)) {
                            $errores[] = "Fila " . ($index + 1) . ": Faltan datos obligatorios (nombre, matrícula, email)";
                            continue;
                        }

                        // Escapar datos
                        $nombre = $db->real_escape_string($nombre);
                        $apellido = $db->real_escape_string($apellido);
                        $email = $db->real_escape_string($email);
                        $matricula = $db->real_escape_string($matricula);
                        $carrera = $db->real_escape_string($carrera);
                        $especialidad = $db->real_escape_string($especialidad);
                        $turno = $db->real_escape_string($turno);

                        // Hashear la matrícula para usarla como contraseña
                        $passwordHashed = password_hash($matricula, PASSWORD_BCRYPT);

                        // Verificar si el usuario ya existe
                        $checkQuery = "SELECT id FROM usuarios WHERE matricula = UPPER('$matricula') OR email = LOWER('$email')";
                        $checkResult = mysqli_query($db, $checkQuery);
                        
                        if (mysqli_num_rows($checkResult) > 0) {
                            $errores[] = "Fila " . ($index + 1) . ": Usuario con matrícula $matricula o email $email ya existe";
                            continue;
                        }

                        // Insertar usuario
                        $query = "INSERT INTO usuarios (nombre, apellido, matricula, email, password, carreraId, especialidadId, estatus, registrado, turno) 
                                 VALUES (UPPER('$nombre'), UPPER('$apellido'), UPPER('$matricula'), LOWER('$email'), '$passwordHashed', UPPER('$carrera'), UPPER('$especialidad'), 1, NOW(), UPPER('$turno'))";
                        
                        $result = mysqli_query($db, $query);
                        
                        if ($result) {
                            $insertados++;
                        } else {
                            $errores[] = "Fila " . ($index + 1) . ": Error en BD - " . mysqli_error($db);
                        }
                    }
                }
                
                // Mostrar resultados
                if ($insertados > 0) {
                    $_SESSION['mensaje'] = "Se importaron $insertados usuarios correctamente";
                    if (!empty($errores)) {
                        $_SESSION['errores'] = $errores;
                    }
                    header('Location: panel-usuarios.php?resultado=2');
                } else {
                    $_SESSION['mensaje'] = "No se pudo importar ningún usuario";
                    $_SESSION['errores'] = $errores;
                    header('Location: panel-usuarios.php?resultado=3');
                }
                exit;
            } else {
                echo "Error al procesar el archivo: " . SimpleXLSX::parseError();
            }
        } else {
            echo "Tipo de archivo no permitido. Solo se permiten: " . implode(', ', $allowedfileExtensions);
        }
    } else {
        echo "Error al cargar el archivo: " . $_FILES['archivo_excel']['error'];
    }
}
?>