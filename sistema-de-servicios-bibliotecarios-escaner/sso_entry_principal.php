<?php
session_start();

// Asegúrate de que las rutas sean correctas
require_once 'database/Database.php';
require_once 'database/DatabaseAPI.php';

$token = $_GET['token'] ?? '';
error_log("=== SSO PRINCIPAL ENTRY ===");
error_log("Token recibido: " . $token);

if (empty($token)) {
    $_SESSION['error'] = "Token no proporcionado.";
    header("Location: login_admin.php");
    exit;
}

try {
    $databaseAPI = new DatabaseAPI();
    
    // 1. OBTENER TOKEN Y VALIDAR TIEMPO EN DB
    // Esta función debe devolver 'tiempo_actual_db' calculado por SQL
    $token_data = $databaseAPI->obtenerTokenSSOConTiempo($token);
    
    if (!$token_data) {
        error_log("SSO_PRINCIPAL: Token no encontrado o inválido en DB");
        $_SESSION['error'] = "Token inválido o ya usado.";
        header("Location: login_admin.php");
        exit;
    }

    // 2. VERIFICAR EXPIRACIÓN (5 minutos / 300 segundos)
    $creado_en = $token_data['creado_en'];
    $tiempo_actual_db = $token_data['tiempo_actual_db'];
    
    $ts_creado = strtotime($creado_en);
    $ts_actual = strtotime($tiempo_actual_db);
    $diferencia = $ts_actual - $ts_creado;

    error_log("SSO_PRINCIPAL: Diferencia tiempo: $diferencia segundos");

    if ($diferencia > 300) { 
        $databaseAPI->eliminarTokenSSO($token);
        error_log("SSO_PRINCIPAL: Token expirado");
        $_SESSION['error'] = "El enlace ha expirado.";
        header("Location: login_admin.php");
        exit;
    }

    // 3. PROCESAR LOGIN
    // En la tabla sso_tokens, la columna 'matricula' guarda el username (ej: Yves Ananías)
    $username_sso = $token_data['matricula']; 
    
    // Quemamos el token para que no se reuse
    $databaseAPI->eliminarTokenSSO($token);

    // Buscamos el usuario en la tabla 'user'
    $userData = $databaseAPI->obtenerUsuarioPorUsername($username_sso);
    
    if ($userData) {
        // --- INICIO DE SESIÓN EXITOSO ---
        // Asignamos las variables de sesión que usa tu sistema 'appceit'
        $_SESSION['user'] = $userData['user']; // Nombre de usuario
        $_SESSION['id_user'] = $userData['id_user']; // ID numérico
        $_SESSION['debe_retornar_sso'] = true; // <--- ESTO ACTIVA EL RETORNO
        // Si usas roles, asegúrate de asignarlos
        if(isset($userData['rol'])) {
            $_SESSION['rol'] = $userData['rol']; 
        }
        
        $_SESSION['timestamp'] = time();
        $_SESSION['login'] = true;

        error_log("SSO_PRINCIPAL: Login exitoso para: " . $userData['user']);
        
        header("Location: index_admin.php");
        exit;
        
    } else {
        error_log("SSO_PRINCIPAL: Usuario '$username_sso' no existe en tabla user");
        $_SESSION['error'] = "Usuario no encontrado en el sistema principal.";
        header("Location: login_admin.php");
        exit;
    }

} catch (Exception $e) {
    error_log("SSO_PRINCIPAL: Excepción: " . $e->getMessage());
    $_SESSION['error'] = "Error interno de autenticación.";
    header("Location: login_admin.php");
    exit;
}
?>