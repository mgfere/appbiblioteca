<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '../includes/config/database.php';

$db_principal = conectarDB_registros(); 
$db_biblioteca = conectarDB();

error_log("SSO_ENTRY: Accedido con token: " . ($_GET['token'] ?? 'NO TOKEN'));
error_log("SSO_ENTRY: ID Admin recibido: " . ($_GET['idAdmin'] ?? 'NO ID'));
error_log("SSO_ENTRY: Es Admin Master: " . ($_GET['esAdminMaster'] ?? 'NO'));

// 🎯 VARIABLE PARA GUARDAR LA IDENTIDAD ORIGINAL DE YVES
$matricula_original_yves = null;

if (isset($_GET['token']) && !empty($_GET['token'])) {
    
    $sso_token_saneado = mysqli_real_escape_string($db_principal, $_GET['token']);
    $id_admin = isset($_GET['idAdmin']) ? intval($_GET['idAdmin']) : 0;
    $es_admin_master = isset($_GET['esAdminMaster']) && $_GET['esAdminMaster'] === 'true';

    // 🎯 PRIMERO VERIFICAR SI ES UN TOKEN DE LA NUEVA PLATAFORMA (MySQL)
    $query_token_mysql = "SELECT matricula, token_expira, matricula_original, NOW() AS tiempo_actual_db 
                         FROM administradores 
                         WHERE token = '$sso_token_saneado'";
    $resultado_token_mysql = mysqli_query($db_biblioteca, $query_token_mysql);

    if ($resultado_token_mysql && $resultado_token_mysql->num_rows > 0) {
        // 🎯 TOKEN DE LA NUEVA PLATAFORMA (MySQL)
        error_log("SSO_ENTRY: Token encontrado en MySQL (nueva plataforma)");
        $token_data = mysqli_fetch_assoc($resultado_token_mysql);
        $matricula_sso = $token_data['matricula'];
        
        // 🎯 GUARDAR MATRÍCULA ORIGINAL SI EXISTE (para Yves)
        if (!empty($token_data['matricula_original'])) {
            $matricula_original_yves = $token_data['matricula_original'];
            error_log("SSO_ENTRY: Matrícula original guardada: " . $matricula_original_yves);
        }
        
        // Verificar expiración
        $expiracion = strtotime($token_data['token_expira']);
        $tiempo_actual_db = strtotime($token_data['tiempo_actual_db']);

        if ($expiracion > $tiempo_actual_db) {
            
            // 🎯 BUSCAR POR ID SI SE ENVIÓ UN ID ESPECÍFICO
            if ($id_admin > 0) {
                error_log("SSO_ENTRY: Buscando administrador por ID: " . $id_admin);
                $query_admin = "SELECT * FROM administradores WHERE id = $id_admin";
            } else {
                // Buscar por matrícula
                error_log("SSO_ENTRY: Buscando administrador por matrícula: " . $matricula_sso);
                $query_admin = "SELECT * FROM administradores WHERE matricula = '$matricula_sso'";
            }
            
            $resultado_admin = mysqli_query($db_biblioteca, $query_admin);

            if ($resultado_admin && $resultado_admin->num_rows > 0) {
                $administrador = mysqli_fetch_assoc($resultado_admin);
                
                // 🎯 LIMPIAR EL TOKEN USADO
                mysqli_query($db_biblioteca, "UPDATE administradores SET token = NULL, token_expira = NULL, matricula_original = NULL WHERE matricula = '$matricula_sso'");
                
                // 🎯 INICIAR SESIÓN CON INFORMACIÓN ADICIONAL PARA YVES
                $_SESSION['administrador'] = $administrador['matricula'];
                $_SESSION['adminId'] = $id_admin;
                $_SESSION['nombre'] = $administrador['nombre'];
                $_SESSION['rol'] = $administrador['rol'];
                $_SESSION['id'] = $administrador['id'];
                $_SESSION['timestamp'] = time();
                $_SESSION['login'] = true;

               // 🎯 GUARDAR INFORMACIÓN ESPECIAL SI ES YVES USANDO UN PERFIL DIFERENTE
if ($es_admin_master && !empty($matricula_original_yves)) {
    $_SESSION['es_admin_master'] = true;
    $_SESSION['matricula_original'] = $matricula_original_yves;
    $_SESSION['perfil_temporal'] = $administrador['id']; // Guardar que está usando un perfil temporal
    $_SESSION['id_master_real'] = $id_admin; // 🔥 NUEVO: Guardar el ID del perfil suplantado
    error_log("SSO_ENTRY: Yves inició sesión con perfil temporal ID: " . $administrador['id']);
    error_log("SSO_ENTRY: ID Master Real guardado: " . $id_admin);
}
                error_log("SSO_ENTRY: Login exitoso desde nueva plataforma. ID: " . $administrador['id']);
                
                header("Location: panel-control.php");
                exit();
                
            } else {
                if ($id_admin > 0) {
                    error_log("SSO_ENTRY: ❌ ID de administrador no encontrado: " . $id_admin);
                    $_SESSION['sso_error'] = "El perfil de administrador seleccionado no existe.";
                } else {
                    error_log("SSO_ENTRY: ❌ Matrícula no encontrada: " . $matricula_sso);
                    $_SESSION['sso_error'] = "Usuario no encontrado en el sistema.";
                }
                header('Location: login.php');
                exit();
            }
            
        } else {
            // 🎯 LIMPIAR TOKEN EXPIRADO
            mysqli_query($db_biblioteca, "UPDATE administradores SET token = NULL, token_expira = NULL, matricula_original = NULL WHERE token = '$sso_token_saneado'");
            error_log("SSO_ENTRY: Token expirado (MySQL): $sso_token_saneado");
            $_SESSION['sso_error'] = "El enlace ha expirado.";
            header('Location: login.php');
            exit();
        }

    } else {
        // 🎯 TOKEN DEL SISTEMA PRINCIPAL (Tutorias/Estadias)
        error_log("SSO_ENTRY: Token del sistema principal, buscando en sso_tokens");
        
        // Buscar token en sistema principal
        $query_token = "SELECT matricula, creado_en, NOW() AS tiempo_actual_db 
                        FROM sso_tokens 
                        WHERE token = '$sso_token_saneado'";
        $resultado_token = mysqli_query($db_principal, $query_token);

        if ($resultado_token && $resultado_token->num_rows > 0) {
            $token_data = mysqli_fetch_assoc($resultado_token);
            $username_sso = $token_data['matricula'];
            
            // Verificar expiración
            $creado_en = strtotime($token_data['creado_en']);
            $tiempo_actual_db = strtotime($token_data['tiempo_actual_db']);
            $diferencia_segundos = $tiempo_actual_db - $creado_en;

            if ($diferencia_segundos <= 300) {
                
                // Eliminar token usado
                mysqli_query($db_principal, "DELETE FROM sso_tokens WHERE token = '$sso_token_saneado'");

                // 🎯 BUSCAR POR ID SI SE ENVIÓ UN ID ESPECÍFICO (desde nueva plataforma)
                if ($id_admin > 0) {
                    error_log("SSO_ENTRY: Buscando administrador por ID desde sistema principal: " . $id_admin);
                    $query_admin = "SELECT * FROM administradores WHERE id = $id_admin";
                } else {
                    // Buscar por nombre (comportamiento original)
                    $username_saneado = mysqli_real_escape_string($db_biblioteca, $username_sso);
                    error_log("SSO_ENTRY: Buscando administrador por nombre: " . $username_saneado);
                    $query_admin = "SELECT * FROM administradores WHERE nombre = '$username_saneado'";
                }
                
                $resultado_admin = mysqli_query($db_biblioteca, $query_admin);

                if ($resultado_admin && $resultado_admin->num_rows > 0) {
                    // Usuario existe en biblioteca
                    $administrador = mysqli_fetch_assoc($resultado_admin);
                    
                    // Iniciar sesión
                    $_SESSION['administrador'] = $administrador['matricula'];
                    $_SESSION['nombre'] = $administrador['nombre'];
                    $_SESSION['rol'] = $administrador['rol'];
                    $_SESSION['id'] = $administrador['id'];
                    $_SESSION['timestamp'] = time();
                    $_SESSION['login'] = true;

                    error_log("SSO_ENTRY: Login exitoso desde sistema principal: $username_sso");
                    
                    header("Location: panel-control.php");
                    exit();
                    
                } else {
                    // 🎯 SI SE ENVIÓ UN ID ESPECÍFICO Y NO EXISTE, ERROR
                    if ($id_admin > 0) {
                        error_log("SSO_ENTRY: ❌ ID de administrador no encontrado desde sistema principal: " . $id_admin);
                        $_SESSION['sso_error'] = "El perfil de administrador seleccionado no existe.";
                        header('Location: login.php');
                        exit();
                    }
                    
                    // Código original para crear nuevo usuario
                    error_log("SSO_ENTRY: Usuario no encontrado en biblioteca, creando nuevo: $username_sso");
                    
                    $password_principal = obtenerPasswordDelSistemaPrincipal($db_principal, $username_sso);
                    
                    if ($password_principal) {
                        $matricula_aleatoria = generarMatriculaAleatoria($db_biblioteca);
                        
                        $query_crear_admin = "INSERT INTO administradores 
                                            (nombre, matricula, password, rol, registrado) 
                                            VALUES 
                                            ('$username_saneado', '$matricula_aleatoria', '$password_principal', 1, NOW())";
                        
                        $resultado_crear = mysqli_query($db_biblioteca, $query_crear_admin);
                        
                        if ($resultado_crear) {
                            $nuevo_id = mysqli_insert_id($db_biblioteca);
                            
                            $query_nuevo_admin = "SELECT * FROM administradores WHERE id = $nuevo_id";
                            $resultado_nuevo_admin = mysqli_query($db_biblioteca, $query_nuevo_admin);
                            $administrador = mysqli_fetch_assoc($resultado_nuevo_admin);
                            
                            $_SESSION['administrador'] = $administrador['matricula'];
                            $_SESSION['nombre'] = $administrador['nombre'];
                            $_SESSION['rol'] = $administrador['rol'];
                            $_SESSION['id'] = $administrador['id'];
                            $_SESSION['timestamp'] = time();
                            $_SESSION['login'] = true;
                            $_SESSION['nuevo_usuario'] = true;

                            error_log("SSO_ENTRY: Nuevo usuario creado exitosamente: $username_sso");
                        
                            header("Location: panel-control.php");
                            exit();
                            
                        } else {
                            error_log("SSO_ENTRY: Error al crear nuevo usuario: " . mysqli_error($db_biblioteca));
                            $_SESSION['sso_error'] = "Error al crear usuario en el sistema de biblioteca.";
                        }
                    } else {
                        error_log("SSO_ENTRY: No se pudo obtener la contraseña del sistema principal para: $username_sso");
                        $_SESSION['sso_error'] = "Error al obtener datos del usuario del sistema principal.";
                    }
                }
                
            } else {
                mysqli_query($db_principal, "DELETE FROM sso_tokens WHERE token = '$sso_token_saneado'");
                error_log("SSO_ENTRY: Token expirado (sistema principal): $sso_token_saneado");
                $_SESSION['sso_error'] = "El enlace ha expirado.";
            }
            
        } else {
            error_log("SSO_ENTRY: Token no encontrado en ningún sistema: $sso_token_saneado");
            $_SESSION['sso_error'] = "Token inválido o ya usado.";
        }
    }
} else {
    $_SESSION['sso_error'] = "Token no proporcionado.";
}

// Si hay error, redirigir al login
header('Location: login.php');
exit();



//Obtiene la contraseña hash del sistema principal para evotar que hagamos otra contraseña y asi sea unico

function obtenerPasswordDelSistemaPrincipal($db_principal, $username) {
    $username_saneado = mysqli_real_escape_string($db_principal, $username);
    
    $query = "SELECT password_user FROM user WHERE user = '$username_saneado'";
    $resultado = mysqli_query($db_principal, $query);
    
    if ($resultado && $resultado->num_rows > 0) {
        $user_data = mysqli_fetch_assoc($resultado);
        $password_hash = $user_data['password_user'];
        
        error_log("SSO_ENTRY: Contraseña obtenida del sistema principal para: $username");
        return $password_hash;
    } else {
        error_log("SSO_ENTRY: ❌ No se encontró el usuario en sistema principal: $username");
        return false;
    }
}

 //Genera una matrícula aleatoria única

function generarMatriculaAleatoria($db_biblioteca) {
    $intentos = 0;
    $max_intentos = 10;
    
    do {
        // Generar matrícula de 10 dígitos
        $matricula = '23' . str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
        
        // Verificar si ya existe
        $query_check = "SELECT COUNT(*) as count FROM administradores WHERE matricula = '$matricula'";
        $resultado_check = mysqli_query($db_biblioteca, $query_check);
        $count_data = mysqli_fetch_assoc($resultado_check);
        
        $intentos++;
        
    } while ($count_data['count'] > 0 && $intentos < $max_intentos);
    
    if ($intentos >= $max_intentos) {
        // Si no encontramos única después de varios intentos, usar timestamp
        $matricula = '23' . time();
    }
    
    error_log("SSO_ENTRY: Matrícula generada: $matricula (intentos: $intentos)");
    return $matricula;
}
?>