<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}
require_once 'database/Database.php';
require_once 'database/DatabaseAPI.php'; 

try {
    $databaseAPI = new DatabaseAPI();
    
    if (!$databaseAPI) {
        throw new Exception("No se pudo inicializar DatabaseAPI");
    }

    $user_matricula = $_SESSION['user'];

    //Vamos a generar el token SSO para poder inicar sesion en nuestros sitemas
    $sso_token = bin2hex(random_bytes(32)); 

    //Guardar token en la base de datos principal, en el proyecto actual, esto es para que lo pueda leer el sistema de biblioteca y asi el pueda borrarlo, las apis son basura, perdon, son buenas, asi 
    $resultado = $databaseAPI->guardarSsoToken($sso_token, $user_matricula);
    
    if (!$resultado) {
        throw new Exception("Error al guardar el token SSO en la base de datos");
    }

    //Si no te dio error en absolutamente nada, redirige al sistema de biblioteca con el token SSO
    $url_biblioteca_sso = "https://biblioteca.uttn.app/admin/sso_entry.php?token=" . $sso_token;
    
    error_log("SSO_REDIRECT: Redirigiendo a: " . $url_biblioteca_sso);
    
    header("Location: " . $url_biblioteca_sso);
    exit();

} catch (Exception $e) {
    error_log("Error en generar_sso_biblioteca.php: " . $e->getMessage());
    session_start();
    $_SESSION['error'] = "Error al acceder a la biblioteca. Por favor, intente nuevamente.";
    header("Location: index_admin.php");
    exit();
}
?>