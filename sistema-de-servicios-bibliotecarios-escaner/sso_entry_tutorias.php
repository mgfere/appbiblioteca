<?php

session_start();
require_once 'database/Database.php';
require_once 'database/DatabaseAPI.php';

$token = $_GET['token'] ?? 'NO TOKEN';
$matricula = $_GET['matricula'] ?? 'NO MATRICULA';
$timestamp = $_GET['timestamp'] ?? 'NO TIMESTAMP';

error_log("========================================");
error_log("SSO_REGISTROS INICIADO");
error_log("========================================");
error_log("Token recibido: " . $token);
error_log("Matrícula: " . $matricula);
error_log("Timestamp: " . $timestamp);
error_log("Hora PHP local: " . date('Y-m-d H:i:s'));

// Limpiar sesión anterior
session_unset();

if (isset($_GET['token']) && !empty($_GET['token'])) {

    try {
        $databaseAPI = new DatabaseAPI();
        $sso_token = $_GET['token'];
        $matricula_recibida = $_GET['matricula'] ?? '';

        error_log("----------------------------------------");
        error_log("BUSCANDO TOKEN EN TABLA USER...");
        error_log("----------------------------------------");

        // 🎯 BUSCAR TOKEN EN LA TABLA USER (NUEVA PLATAFORMA)
        $token_data = $databaseAPI->obtenerTokenUsuarioPorToken($sso_token);

        if ($token_data) {
            error_log("✅ TOKEN ENCONTRADO EN NUEVA PLATAFORMA");
            error_log("Datos del token:");
            error_log("  - Matricula: " . $token_data['matricula']);
            error_log("  - Token expira: " . $token_data['token_expira']);
            error_log("  - Tiempo actual DB: " . $token_data['tiempo_actual_db']);

            $username_sso = $token_data['matricula'];
            $token_expira = $token_data['token_expira'];
            $tiempo_actual_db = $token_data['tiempo_actual_db'];

            // Verificar expiración usando timestamps
            $expiracion_timestamp = strtotime($token_expira);
            $tiempo_actual_timestamp = strtotime($tiempo_actual_db);

            error_log("----------------------------------------");
            error_log("VERIFICACIÓN DE EXPIRACIÓN:");
            error_log("  - Expira en timestamp: " . $expiracion_timestamp . " (" . $token_expira . ")");
            error_log("  - Tiempo actual timestamp: " . $tiempo_actual_timestamp . " (" . $tiempo_actual_db . ")");
            error_log("  - Diferencia en segundos: " . ($expiracion_timestamp - $tiempo_actual_timestamp));
            error_log("  - ¿Es válido?: " . ($expiracion_timestamp > $tiempo_actual_timestamp ? "SÍ" : "NO"));
            error_log("----------------------------------------");

            if ($expiracion_timestamp > $tiempo_actual_timestamp) {
                // TOKEN VÁLIDO
                error_log("✅ TOKEN VÁLIDO - Buscando usuario en tabla user...");

                // Buscar usuario por matrícula
                $userData = $databaseAPI->obtenerUsuarioPorMatricula($username_sso);

                if ($userData) {
                    error_log("✅ USUARIO ENCONTRADO:");
                    error_log("  - Username: " . $userData['user']);
                    error_log("  - Matrícula: " . $userData['matricula']);
                    error_log("  - Rol: " . $userData['rol']);

                    // LIMPIAR TOKEN USADO
                    $limpieza = $databaseAPI->limpiarTokenUsuario($username_sso);
                    error_log("Token limpiado: " . ($limpieza ? "SÍ" : "NO"));

                    // 🔥 RECIBIR DATOS ADICIONALES DESDE C# (si existen)
                    $es_admin_master = isset($_GET['esAdminMaster']) && $_GET['esAdminMaster'] === 'true';
                    $matricula_original = $_GET['matriculaReal'] ?? '';
                    $perfil_temporal = isset($_GET['perfil_temporal']) && $_GET['perfil_temporal'] === 'true';

                    // INICIAR SESIÓN
                    $_SESSION['user'] = $userData['user'];
                    $_SESSION['matricula'] = $userData['matricula'];
                    $_SESSION['nombre'] = $userData['user'];
                    $_SESSION['rol'] = $userData['rol'] ?? 0;
                    $_SESSION['timestamp'] = time();
                    $_SESSION['login'] = true;
                    $_SESSION['sistema'] = 'registros';

                    // 🔥 GUARDAR DATOS DE ADMIN MASTER
                    $_SESSION['es_admin_master'] = $es_admin_master;
                    $_SESSION['matricula_original'] = $matricula_original;
                    $_SESSION['perfil_temporal'] = $perfil_temporal;

                    $_SESSION['debe_retornar_sso'] = true;

                    error_log("✅✅✅ LOGIN EXITOSO - Usuario: " . $username_sso);
                    error_log("  - Es Admin Master: " . ($es_admin_master ? 'SI' : 'NO'));
                    error_log("  - Matrícula original: " . ($matricula_original ?: 'Ninguna'));
                    error_log("Redirigiendo a index_admin.php");
                    error_log("========================================");

                    // Redirigir al panel principal
                    header("Location: index_admin.php");
                    exit();

                } else {
                    error_log("❌ USUARIO NO ENCONTRADO EN TABLA USER: " . $username_sso);
                    $_SESSION['error'] = "Usuario no encontrado en CEIT Registros.";
                    header("Location: login_admin.php");
                    exit();
                }

            } else {
                // TOKEN EXPIRADO
                $databaseAPI->limpiarTokenUsuario($username_sso);
                error_log("❌ TOKEN EXPIRADO");
                error_log("  Token expiró hace " . ($tiempo_actual_timestamp - $expiracion_timestamp) . " segundos");
                $_SESSION['error'] = "El enlace ha expirado. Por favor intenta de nuevo.";
                header("Location: login_admin.php");
                exit();
            }

        } else {
            error_log("❌ TOKEN NO ENCONTRADO EN TABLA USER");
            error_log("Buscando en sistema principal (sso_tokens)...");

            // 🎯 BUSCAR EN TOKENS SSO DEL SISTEMA PRINCIPAL (FALLBACK)
            $token_data_principal = $databaseAPI->obtenerTokenSSOConTiempo($sso_token);

            if ($token_data_principal) {
                error_log("✅ TOKEN ENCONTRADO EN SISTEMA PRINCIPAL");

                $username_sso = $token_data_principal['matricula'];
                $creado_en = $token_data_principal['creado_en'];
                $tiempo_actual_db = $token_data_principal['tiempo_actual_db'];

                $creado_en_timestamp = strtotime($creado_en);
                $tiempo_actual_db_timestamp = strtotime($tiempo_actual_db);
                $diferencia_segundos = $tiempo_actual_db_timestamp - $creado_en_timestamp;

                error_log("Verificación (sistema principal):");
                error_log("  - Creado: " . $creado_en);
                error_log("  - Tiempo actual: " . $tiempo_actual_db);
                error_log("  - Diferencia: " . $diferencia_segundos . " segundos");

                if ($diferencia_segundos <= 300) {
                    $databaseAPI->eliminarTokenSSO($sso_token);

                    $userData = $databaseAPI->obtenerUsuarioPorUsername($username_sso);

                    if ($userData) {
                        $_SESSION['user'] = $username_sso;
                        $_SESSION['timestamp'] = time();
                        $_SESSION['login'] = true;

                        error_log("✅ LOGIN EXITOSO (sistema principal): " . $username_sso);
                        header("Location: index_admin.php");
                        exit();

                    } else {
                        error_log("❌ Usuario no encontrado: " . $username_sso);
                        $_SESSION['error'] = "Usuario no encontrado.";
                        header("Location: login_admin.php");
                        exit();
                    }

                } else {
                    $databaseAPI->eliminarTokenSSO($sso_token);
                    error_log("❌ TOKEN EXPIRADO (sistema principal)");
                    $_SESSION['error'] = "El enlace ha expirado.";
                    header("Location: login_admin.php");
                    exit();
                }

            } else {
                error_log("❌❌ TOKEN NO ENCONTRADO EN NINGÚN SISTEMA");
                $_SESSION['error'] = "Token inválido o ya usado.";
                header("Location: login_admin.php");
                exit();
            }
        }

    } catch (Exception $e) {
        error_log("❌❌❌ ERROR CRÍTICO:");
        error_log("Mensaje: " . $e->getMessage());
        error_log("Archivo: " . $e->getFile() . " línea " . $e->getLine());
        error_log("Stack Trace: " . $e->getTraceAsString());
        error_log("========================================");
        $_SESSION['error'] = "Error en el proceso de autenticación.";
        header("Location: login_admin.php");
        exit();
    }

} else {
    error_log("❌ TOKEN NO PROPORCIONADO EN URL");
    $_SESSION['error'] = "Token no proporcionado.";
    header("Location: login_admin.php");
    exit();
}
?>