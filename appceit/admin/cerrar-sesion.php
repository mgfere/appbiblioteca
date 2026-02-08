<?php
session_start();

require '../includes/config/database.php';
$db_biblioteca = conectarDB();

$es_admin_master = $_SESSION['es_admin_master'] ?? false;
$matricula_original = $_SESSION['matricula_original'] ?? '';
$perfil_temporal = $_SESSION['perfil_temporal'] ?? null;
$matricula_sesion = $_SESSION['administrador'] ?? ''; 

// Limpiar rastro de perfil temporal si existe
if ($perfil_temporal) {
    $clear_sql = "UPDATE administradores SET matricula_original = NULL WHERE id = ?";
    if ($stmt = mysqli_prepare($db_biblioteca, $clear_sql)) {
        mysqli_stmt_bind_param($stmt, 'i', $perfil_temporal);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}
mysqli_close($db_biblioteca);

// Destruir sesión local
$_SESSION = [];
session_destroy();

// Determinar matrícula para el retorno (Master o Normal)
$matricula_retorno = ($es_admin_master && !empty($matricula_original)) ? $matricula_original : $matricula_sesion;

if (!empty($matricula_retorno)) {
    
    // Usamos tu función existente para conectar a SQL Server
    $conn_gestion = conectarDB3();
    
    if ($conn_gestion) {
        $token_temporal = bin2hex(random_bytes(32));
        
        $sql = "UPDATE Docentes 
                SET TokenSesion = ?, FechaSesion = GETUTCDATE() 
                WHERE Matricula = ? AND Habilitado = 1";
        
        $params = array($token_temporal, $matricula_retorno);
        $stmt = sqlsrv_query($conn_gestion, $sql, $params);
        
        if ($stmt) {
            sqlsrv_close($conn_gestion);
            
            $url_retorno = "https://login.uttn.app/Home/SSOReturnFromBiblioteca" .
                           "?matricula=" . urlencode($matricula_retorno) .
                           "&token=" . urlencode($token_temporal);
            
            header("Location: " . $url_retorno);
            exit();
        }
        sqlsrv_close($conn_gestion);
    }
}

header("Location: login.php");
exit();
?>