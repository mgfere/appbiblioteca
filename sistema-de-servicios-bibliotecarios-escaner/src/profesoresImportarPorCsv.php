<?php
require_once '../database/DatabaseAPI.php';
require_once '../database/Database.php';

// Configuración para depuración (desactivar en producción)
header('Content-Type: application/json');
ini_set('display_errors', 1);
error_reporting(E_ALL);

function debugLog($message) {
    file_put_contents('debug.log', date('Y-m-d H:i:s')." - ".$message.PHP_EOL, FILE_APPEND);
}

function limpiarCSV($archivo) {
    $content = file_get_contents($archivo);
    // Eliminar BOM si existe
    $bom = pack('H*','EFBBBF');
    $content = preg_replace("/^$bom/", '', $content);
    // Normalizar saltos de línea
    $content = preg_replace('/\r\n?/', "\n", $content);
    // Eliminar líneas vacías
    $content = preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", "\n", $content);
    file_put_contents($archivo, $content);
    return $content;
}

try {
    debugLog("Inicio del proceso de importación");
    
    // Verificación básica
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido', 405);
    }

    if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No se recibió archivo o hubo un error en la subida', 400);
    }

    // Validar archivo
    $archivo = $_FILES['archivo']['tmp_name'];
    $nombreOriginal = $_FILES['archivo']['name'];
    $tamano = $_FILES['archivo']['size'];
    
    debugLog("Archivo recibido: ".$nombreOriginal." (".$tamano." bytes)");

    if (!is_uploaded_file($archivo)) {
        throw new Exception('Archivo no subido correctamente', 400);
    }

    $extension = strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
    if ($extension !== 'csv') {
        throw new Exception('El archivo debe ser CSV', 400);
    }

    // Limpiar y validar contenido CSV
    $contenido = limpiarCSV($archivo);
    debugLog("Contenido del CSV después de limpieza:\n".$contenido);

    // Conexión a BD
    $db = new DatabaseAPI();
    $pdo = $db->getPDO();
    
    if (!$pdo) {
        throw new Exception('Error al conectar con la base de datos', 500);
    }

    // Configurar conexión
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES 'utf8'");

    // Procesar CSV
    $registrosInsertados = 0;
    $registrosOmitidos = 0;
    $errores = [];
    $lineNumber = 0;

    $pdo->beginTransaction();
    debugLog("Transacción iniciada");

    if (($handle = fopen($archivo, 'r')) !== false) {
        // Leer y validar encabezados
        $encabezados = fgetcsv($handle, 1000, ',');
        $lineNumber++;
        
        debugLog("Encabezados encontrados: ".print_r($encabezados, true));
        
        if ($encabezados === false || count($encabezados) < 2 || 
            strtolower(trim($encabezados[0])) !== 'matricula' || 
            strtolower(trim($encabezados[1])) !== 'nombre') {
            throw new Exception('El archivo CSV debe tener encabezados: matricula,nombre', 400);
        }

        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            $lineNumber++;
            
            // Saltar líneas vacías
            if ($data === null || (count($data) === 1 && empty(trim($data[0])))) {
                continue;
            }

            debugLog("Procesando línea $lineNumber: ".print_r($data, true));
            
            // Validar datos básicos
            if (count($data) < 2) {
                $error = "Línea $lineNumber: Formato inválido (se esperaban 2 columnas)";
                $errores[] = $error;
                debugLog($error);
                continue;
            }

            $matricula = strtoupper(trim($data[0]));
            $nameUser = trim($data[1]);

            if (empty($matricula)) {
                $error = "Línea $lineNumber: Matrícula vacía";
                $errores[] = $error;
                debugLog($error);
                continue;
            }

            if (empty($nameUser)) {
                $error = "Línea $lineNumber: Nombre vacío";
                $errores[] = $error;
                debugLog($error);
                continue;
            }

            try {
                debugLog("Llamando procedimiento con: $matricula, $nameUser");
                
                // Llamar al procedimiento almacenado
                $stmt = $pdo->prepare("CALL importar_profesores(:matricula, :nameUser, @resultado, @accion)");
                $stmt->bindParam(':matricula', $matricula, PDO::PARAM_STR);
                $stmt->bindParam(':nameUser', $nameUser, PDO::PARAM_STR);
                
                if (!$stmt->execute()) {
                    throw new PDOException("Error al ejecutar el procedimiento almacenado");
                }
                
                $stmt->closeCursor();

                // Obtener resultado del procedimiento
                $result = $pdo->query("SELECT @resultado as resultado, @accion as accion")->fetch(PDO::FETCH_ASSOC);
                debugLog("Resultado del procedimiento: ".print_r($result, true));

                if (!$result) {
                    throw new PDOException("No se pudo obtener resultado del procedimiento almacenado");
                }

                if (isset($result['accion']) && $result['accion'] === 'insertado') {
                    $registrosInsertados++;
                    debugLog("Registro insertado: $matricula");
                } elseif (isset($result['accion']) && $result['accion'] === 'omitido') {
                    $registrosOmitidos++;
                    debugLog("Registro omitido: $matricula");
                } else {
                    $error = "Línea $lineNumber: " . ($result['resultado'] ?? 'Error desconocido');
                    $errores[] = $error;
                    debugLog($error);
                }
            } catch (PDOException $e) {
                $error = "Línea $lineNumber: Error en base de datos - " . $e->getMessage();
                $errores[] = $error;
                debugLog($error);
            }
        }
        fclose($handle);
    }

    $pdo->commit();
    debugLog("Transacción completada con éxito");

    // Preparar respuesta
    $response = [
        'success' => true,
        'total' => $lineNumber - 1,
        'inserted' => $registrosInsertados,
        'skipped' => $registrosOmitidos,
        'errors' => count($errores),
        'message' => "Proceso completado. Insertados: $registrosInsertados, Omitidos: $registrosOmitidos",
    ];

    if (!empty($errores)) {
        $response['error_details'] = $errores;
        file_put_contents('import_errors_'.date('Ymd_His').'.log', implode("\n", $errores));
    }

    debugLog("Respuesta final: ".print_r($response, true));
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    debugLog("EXCEPCIÓN: ".$e->getMessage()."\n".$e->getTraceAsString());
    
    if (isset($pdo) && $pdo->inTransaction()) {
        try {
            $pdo->rollBack();
            debugLog("Rollback ejecutado");
        } catch (PDOException $rollbackEx) {
            debugLog("Error al hacer rollback: ".$rollbackEx->getMessage());
        }
    }

    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_UNESCAPED_UNICODE);
}
?>