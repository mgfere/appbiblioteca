<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '../includes/config/database.php';

if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header('Location: login.php');
    exit;
}

// Obtener datos del administrador actual
$nombre_admin = $_SESSION['nombre'] ?? '';
$username_admin = $_SESSION['nombre'] ?? '';

error_log("=== DEBUG GENERAR SSO PRINCIPAL ===");
error_log("Nombre en sesión: " . $nombre_admin);
error_log("Username a usar: " . $username_admin);

if (empty($nombre_admin) || empty($username_admin)) {
    error_log("ERROR: Nombre o username vacío");
    header('Location: login.php');
    exit;
}

try {
    error_log("Conectando a DB principal...");
    $db_principal = conectarDB_registros();
    
    if (!$db_principal) {
        throw new Exception("No se pudo conectar a la base de datos principal");
    }
    error_log("Conexión a DB principal exitosa");
    
    // Generar token único para el sistema de SSO implementado
    $sso_token = bin2hex(random_bytes(32));
    error_log("Token generado: " . $sso_token);
    
    $token_saneado = mysqli_real_escape_string($db_principal, $sso_token);
    $username_saneado = mysqli_real_escape_string($db_principal, $username_admin);
    
    error_log("Token saneado: " . $token_saneado);
    error_log("Username saneado: " . $username_saneado);
    
    $query = "INSERT INTO sso_tokens (token, matricula, creado_en) 
              VALUES ('$token_saneado', '$username_saneado', NOW())";
    
    error_log("Query a ejecutar: " . $query);
    
    $resultado = mysqli_query($db_principal, $query);
    
    if ($resultado) {
        error_log("Token guardado exitosamente en DB principal");
        
        // Verificar que realmente se guardó
        $query_verify = "SELECT COUNT(*) as count FROM sso_tokens WHERE token = '$token_saneado'";
        $result_verify = mysqli_query($db_principal, $query_verify);
        $count_data = mysqli_fetch_assoc($result_verify);
        
        error_log("Verificación: " . $count_data['count'] . " tokens encontrados");
        
        // URL del sistema principal con el token leido para poder redireccionarnos
        $url_sistema_principal = "https://ceitregistros.uttn.app/sso_entry_principal.php?token=" . $sso_token;
        
        error_log("SSO_BIBLIOTECA: Redirigiendo a sistema principal con username: " . $username_admin);
        error_log("URL de redirección: " . $url_sistema_principal);
        
        header("Location: " . $url_sistema_principal);
        exit;
    } else {
        $error = mysqli_error($db_principal);
        error_log("Error en INSERT: " . $error);
        throw new Exception("Error al guardar token SSO: " . $error);
    }
    
} catch (Exception $e) {
    error_log("Exception capturada: " . $e->getMessage());
    header("Location: https://ceitregistros.uttn.app/index.php");
    exit;
}
?>