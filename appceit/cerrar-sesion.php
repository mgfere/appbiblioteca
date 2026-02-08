<?php
session_start();

require 'includes/config/database.php';


$tipo_usuario = $_SESSION['usuario_tipo'] ?? '';
$matricula = $_SESSION['usuario_matricula'] ?? '';

error_log("LOGOUT_USUARIOS: Tipo: $tipo_usuario, Matrícula: $matricula");

if (!empty($matricula) && in_array($tipo_usuario, ['alumno', 'docente'])) {
    
    $token_temporal = bin2hex(random_bytes(32));
    $db_gestion = conectarDB3();
    
    if ($db_gestion) {
        
        if ($tipo_usuario === 'alumno') {
            $update_token = "UPDATE Alumnos 
                            SET TokenSesion = ?, 
                                FechaSesion = GETUTCDATE() 
                            WHERE Matricula = ? 
                            AND Habilitado = 1";
        } else {
            $update_token = "UPDATE Docentes 
                            SET TokenSesion = ?, 
                                FechaSesion = GETUTCDATE() 
                            WHERE Matricula = ? 
                            AND Habilitado = 1";
        }
        
        $params = array($token_temporal, $matricula);
        $resultado_update = sqlsrv_query($db_gestion, $update_token, $params);
        
        if ($resultado_update) {
            $_SESSION = [];
            session_destroy();
            
            $url_retorno = "https://login.uttn.app/Home/SSOReturnFromBiblioteca2" .
                          "?matricula=" . urlencode($matricula) .
                          "&token=" . urlencode($token_temporal) .
                          "&tipo=" . urlencode($tipo_usuario);
            
            error_log("LOGOUT_USUARIOS: Redirigiendo a APPCEIT - $tipo_usuario: $matricula");
            
            sqlsrv_close($db_gestion);
            header("Location: " . $url_retorno);
            exit();
            
        } else {
            $errors = sqlsrv_errors();
            error_log("LOGOUT_USUARIOS: Error actualizando token: " . print_r($errors, true));
        }
        
        sqlsrv_close($db_gestion);
    } else {
        error_log("LOGOUT_USUARIOS: Error conectando a SQL Server");
    }
}

$_SESSION = [];
session_destroy();

error_log("LOGOUT_USUARIOS: Redirigiendo a login (sin retorno SSO)");
header("Location: login.php");
exit();
?>