<?php
// ============================================
// LOGOUT - CEIT REGISTROS (CORREGIDO)
// ============================================
session_start();
require_once 'database/Database.php';
require_once 'database/DatabaseAPI.php';

error_log("========================================");
error_log("LOGOUT_REGISTROS INICIADO");

// 1. OBTENER DATOS Y LIMPIAR ESPACIOS
$matricula_sesion = isset($_SESSION['matricula']) ? trim($_SESSION['matricula']) : '';
// Si no hay matrícula, intentamos usar el 'user' (que en tu caso es el nombre "GRISELDA...")
if (empty($matricula_sesion) && isset($_SESSION['user'])) {
    $matricula_sesion = trim($_SESSION['user']);
}

$debe_retornar_sso = $_SESSION['debe_retornar_sso'] ?? false;
$es_admin_master = $_SESSION['es_admin_master'] ?? false;
$matricula_original = $_SESSION['matricula_original'] ?? '';

error_log("Datos sesión para logout: Matricula='$matricula_sesion', RetornarSSO=" . ($debe_retornar_sso?'SI':'NO'));

// --- FUNCIÓN DE CONEXIÓN SQL SERVER (AGREGADA MANUALMENTE AQUÍ) ---
function conectarSQLServerParaLogout() {
    $serverName = "172.16.0.149"; // El servidor donde está GestionUsuarios
    $connectionInfo = array(
        "Database" => "GestionUsuarios",
        "UID" => "sa",
        "PWD" => "TicUtt2017",
        "CharacterSet" => "UTF-8"
    );
    $conn = sqlsrv_connect($serverName, $connectionInfo);
    if ($conn === false) {
        error_log("Error conectando a SQL Server: " . print_r(sqlsrv_errors(), true));
        return false;
    }
    return $conn;
}
// ------------------------------------------------------------------

try {
    // 2. LIMPIAR TOKEN LOCAL (MySQL)
    if (!empty($matricula_sesion)) {
        $databaseAPI = new DatabaseAPI();
        // Intentamos limpiar usando la matrícula/nombre
        // IMPORTANTE: Tu función limpiarTokenUsuario en API espera una matrícula. 
        // Si $matricula_sesion es un nombre ("GRISELDA"), asegúrate que la API busque por nombre o matricula.
        $databaseAPI->limpiarTokenUsuario($matricula_sesion);
    }
    
    // 3. PROCESO DE RETORNO AL SSO (C#)
    if ($debe_retornar_sso && !empty($matricula_sesion)) {
        error_log(">>> INICIANDO RETORNO A APPCEIT (SSO) <<<");
        
        // Determinar qué ID usar para el regreso
        $id_retorno = ($es_admin_master && !empty($matricula_original)) ? $matricula_original : $matricula_sesion;
        
        $token_temporal = bin2hex(random_bytes(32));
        
        $conn_sqlserver = conectarSQLServerParaLogout(); 
        
        if ($conn_sqlserver) {
            // Actualizar Token en SQL Server (Tabla Docentes)
            // NOTA: Si $id_retorno es un NOMBRE (ej: GRISELDA), esto fallará si la columna Matricula es numérica.
            // Asumimos que $id_retorno coincide con lo que hay en SQL Server.
            
            $sql = "UPDATE Docentes 
                    SET TokenSesion = ?, FechaSesion = GETUTCDATE() 
                    WHERE (Matricula = ? OR Nombre + ' ' + ApellidoPaterno + ' ' + ApellidoMaterno = ?) 
                    AND Habilitado = 1";
            
            // Intentamos hacer match por Matricula O por Nombre completo para asegurar que encuentre al usuario
            $params = array($token_temporal, $id_retorno, $id_retorno);
            
            $resultado = sqlsrv_query($conn_sqlserver, $sql, $params);
            
            if ($resultado) {
                $rows = sqlsrv_rows_affected($resultado);
                error_log("Update SQL Server ejecutado. Filas afectadas: " . $rows);
                
                // URL de retorno a C#
                $url_appceit = "https://login.uttn.app/Home/SSOReturnFromRegistros" .
                               "?matricula=" . urlencode($id_retorno) .
                               "&token=" . urlencode($token_temporal);
                
                error_log("REDIRECCIONANDO A: $url_appceit");
                
                sqlsrv_close($conn_sqlserver);
                session_destroy();
                header("Location: " . $url_appceit);
                exit();
                
            } else {
                error_log("Fallo en Update SQL: " . print_r(sqlsrv_errors(), true));
            }
            sqlsrv_close($conn_sqlserver);
        }
    } else {
        error_log("No se cumple condición de retorno SSO (Flag: $debe_retornar_sso)");
    }

} catch (Exception $e) {
    error_log("EXCEPCIÓN CRÍTICA EN LOGOUT: " . $e->getMessage());
}

// 4. FALLBACK: LOGOUT NORMAL
error_log("Logout normal (local)");
session_destroy();
header("Location: login_admin.php");
exit();
?>