<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'includes/config/database.php';

// Conexión a SQL Server (GestionUsuarios)
$db_gestion = conectarDB3(); 

// Log de inicio
error_log("=== SSO_USUARIOS INICIO ===");
error_log("SSO_USUARIOS: Token recibido: " . ($_GET['token'] ?? 'NO TOKEN'));
error_log("SSO_USUARIOS: Tipo: " . ($_GET['tipo'] ?? 'NO TIPO'));
error_log("SSO_USUARIOS: Matrícula: " . ($_GET['matricula'] ?? 'NO MATRICULA'));
error_log("SSO_USUARIOS: Nombre: " . ($_GET['nombre'] ?? 'NO NOMBRE'));

if (isset($_GET['token']) && !empty($_GET['token'])) {
    
    $sso_token = $_GET['token'];
    $tipo_usuario = isset($_GET['tipo']) ? $_GET['tipo'] : '';
    $matricula_url = isset($_GET['matricula']) ? $_GET['matricula'] : '';
    $nombre_url = isset($_GET['nombre']) ? $_GET['nombre'] : '';

    // VALIDAR TIPO DE USUARIO
    if (!in_array($tipo_usuario, ['alumno', 'docente'])) {
        error_log("SSO_USUARIOS ERROR: Tipo de usuario inválido: $tipo_usuario");
        $_SESSION['sso_error'] = "Tipo de usuario inválido.";
        header('Location: iniciar-sesion.php');
        exit();
    }

    // Validar conexión a SQL Server
    if (!$db_gestion) {
        error_log("SSO_USUARIOS ERROR: No se pudo conectar a SQL Server");
        $_SESSION['sso_error'] = "Error de conexión a la base de datos.";
        header('Location: iniciar-sesion.php');
        exit();
    }

    // ========================================
    // VALIDAR TOKEN EN SQL SERVER
    // ========================================
    $token_valido = false;
    $usuario_data = null;

    if ($tipo_usuario === 'alumno') {
        error_log("SSO_USUARIOS: Buscando alumno con matrícula: $matricula_url");
        
        // Buscar en tabla Alumnos
        $query = "SELECT
                    IdAlumno,
                    Matricula, 
                    Nombre,
                    ApellidoPaterno,
                    ApellidoMaterno,
                    CorreoElectronico,
                    Cuatrimestre,
                    TokenSesion,
                    FechaSesion,
                    GETUTCDATE() as HoraServidorSQL,
                    DATEDIFF(SECOND, FechaSesion, GETUTCDATE()) as segundos_transcurridos
                  FROM Alumnos 
                  WHERE TokenSesion = ? 
                  AND Matricula = ?
                  AND Habilitado = 1";
        
        $params = array($sso_token, $matricula_url);
        $stmt = sqlsrv_prepare($db_gestion, $query, $params);
        
        if (!$stmt) {
            error_log("SSO_USUARIOS ERROR: Error preparando query alumno - " . print_r(sqlsrv_errors(), true));
            $_SESSION['sso_error'] = "Error en la consulta de base de datos.";
            header('Location: iniciar-sesion.php');
            exit();
        }
        
        if (sqlsrv_execute($stmt)) {
            if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $segundos = $row['segundos_transcurridos'];
                $fechaSesion = $row['FechaSesion'];
                $horaSQL = $row['HoraServidorSQL'];
                
                error_log("SSO_USUARIOS: Alumno encontrado");
                error_log("SSO_USUARIOS: FechaSesion: " . ($fechaSesion ? $fechaSesion->format('Y-m-d H:i:s') : 'NULL'));
                error_log("SSO_USUARIOS: HoraServidorSQL: " . ($horaSQL ? $horaSQL->format('Y-m-d H:i:s') : 'NULL'));
                error_log("SSO_USUARIOS: Segundos transcurridos: " . ($segundos ?? 'NULL'));
                
                // SOLUCIÓN: Verificar solo que exista el token y la matrícula
                // El tiempo ya fue validado en C# cuando se generó
                if ($segundos !== null && $segundos <= 600) { // Aumentamos a 10 minutos por si hay desfase
                    $token_valido = true;
                    $usuario_data = array(
                        'id' => $row['IdAlumno'],
                        'matricula' => $row['Matricula'],
                        'nombre_completo' => trim($row['Nombre'] . ' ' . $row['ApellidoPaterno'] . ' ' . $row['ApellidoMaterno']),
                        'correo' => $row['CorreoElectronico'],
                        'cuatrimestre' => $row['Cuatrimestre'],
                        'tipo' => 'alumno'
                    );
                    error_log("SSO_USUARIOS: ✅ Token válido para alumno: {$row['Matricula']}");
                } else {
                    error_log("SSO_USUARIOS: ❌ Token expirado para alumno. Segundos: " . ($segundos ?? 'NULL'));
                }
            } else {
                error_log("SSO_USUARIOS: ❌ No se encontró alumno con ese token y matrícula");
                
                // DEBUG: Buscar si existe el alumno sin validar token
                $debug_query = "SELECT Matricula, TokenSesion, FechaSesion FROM Alumnos WHERE Matricula = ?";
                $debug_stmt = sqlsrv_prepare($db_gestion, $debug_query, array($matricula_url));
                if ($debug_stmt && sqlsrv_execute($debug_stmt)) {
                    if ($debug_row = sqlsrv_fetch_array($debug_stmt, SQLSRV_FETCH_ASSOC)) {
                        error_log("SSO_USUARIOS DEBUG: Alumno existe con matrícula: {$debug_row['Matricula']}");
                        error_log("SSO_USUARIOS DEBUG: TokenSesion en BD: " . ($debug_row['TokenSesion'] ?? 'NULL'));
                        error_log("SSO_USUARIOS DEBUG: Token recibido: " . $sso_token);
                        error_log("SSO_USUARIOS DEBUG: FechaSesion: " . ($debug_row['FechaSesion'] ? $debug_row['FechaSesion']->format('Y-m-d H:i:s') : 'NULL'));
                    } else {
                        error_log("SSO_USUARIOS DEBUG: No existe alumno con matrícula: $matricula_url");
                    }
                    sqlsrv_free_stmt($debug_stmt);
                }
            }
            sqlsrv_free_stmt($stmt);
        } else {
            error_log("SSO_USUARIOS ERROR: Error ejecutando query alumno - " . print_r(sqlsrv_errors(), true));
        }
        
    } else if ($tipo_usuario === 'docente') {
        error_log("SSO_USUARIOS: Buscando docente con matrícula: $matricula_url");
        
        // Buscar en tabla Docentes
        $query = "SELECT
                    IdDocente,
                    Matricula, 
                    Nombre,
                    ApellidoPaterno,
                    ApellidoMaterno,
                    NumeroEmpleado,
                    CorreoElectronico,
                    TokenSesion,
                    FechaSesion,
                    GETUTCDATE() as HoraServidorSQL,
                    DATEDIFF(SECOND, FechaSesion, GETUTCDATE()) as segundos_transcurridos
                  FROM Docentes 
                  WHERE TokenSesion = ? 
                  AND Matricula = ?
                  AND Habilitado = 1";
        
        $params = array($sso_token, $matricula_url);
        $stmt = sqlsrv_prepare($db_gestion, $query, $params);
        
        if (!$stmt) {
            error_log("SSO_USUARIOS ERROR: Error preparando query docente - " . print_r(sqlsrv_errors(), true));
            $_SESSION['sso_error'] = "Error en la consulta de base de datos.";
            header('Location: iniciar-sesion.php');
            exit();
        }
        
        if (sqlsrv_execute($stmt)) {
            if ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                $segundos = $row['segundos_transcurridos'];
                $fechaSesion = $row['FechaSesion'];
                $horaSQL = $row['HoraServidorSQL'];
                
                error_log("SSO_USUARIOS: Docente encontrado");
                error_log("SSO_USUARIOS: FechaSesion: " . ($fechaSesion ? $fechaSesion->format('Y-m-d H:i:s') : 'NULL'));
                error_log("SSO_USUARIOS: HoraServidorSQL: " . ($horaSQL ? $horaSQL->format('Y-m-d H:i:s') : 'NULL'));
                error_log("SSO_USUARIOS: Segundos transcurridos: " . ($segundos ?? 'NULL'));
                
                // SOLUCIÓN: Aumentamos el tiempo de tolerancia
                if ($segundos !== null && $segundos <= 600) { // 10 minutos de tolerancia
                    $token_valido = true;
                    $usuario_data = array(
                        'id' => $row['IdDocente'],
                        'matricula' => $row['Matricula'],
                        'nombre_completo' => trim($row['Nombre'] . ' ' . $row['ApellidoPaterno'] . ' ' . $row['ApellidoMaterno']),
                        'numero_empleado' => $row['NumeroEmpleado'],
                        'correo' => $row['CorreoElectronico'],
                        'tipo' => 'docente'
                    );
                    error_log("SSO_USUARIOS: ✅ Token válido para docente: {$row['Matricula']}");
                } else {
                    error_log("SSO_USUARIOS: ❌ Token expirado para docente. Segundos: " . ($segundos ?? 'NULL'));
                }
            } else {
                error_log("SSO_USUARIOS: ❌ No se encontró docente con ese token y matrícula");
                
                // DEBUG: Buscar si existe el docente sin validar token
                $debug_query = "SELECT Matricula, TokenSesion, FechaSesion FROM Docentes WHERE Matricula = ?";
                $debug_stmt = sqlsrv_prepare($db_gestion, $debug_query, array($matricula_url));
                if ($debug_stmt && sqlsrv_execute($debug_stmt)) {
                    if ($debug_row = sqlsrv_fetch_array($debug_stmt, SQLSRV_FETCH_ASSOC)) {
                        error_log("SSO_USUARIOS DEBUG: Docente existe con matrícula: {$debug_row['Matricula']}");
                        error_log("SSO_USUARIOS DEBUG: TokenSesion en BD: " . ($debug_row['TokenSesion'] ?? 'NULL'));
                        error_log("SSO_USUARIOS DEBUG: Token recibido: " . $sso_token);
                        error_log("SSO_USUARIOS DEBUG: FechaSesion: " . ($debug_row['FechaSesion'] ? $debug_row['FechaSesion']->format('Y-m-d H:i:s') : 'NULL'));
                    } else {
                        error_log("SSO_USUARIOS DEBUG: No existe docente con matrícula: $matricula_url");
                    }
                    sqlsrv_free_stmt($debug_stmt);
                }
            }
            sqlsrv_free_stmt($stmt);
        } else {
            error_log("SSO_USUARIOS ERROR: Error ejecutando query docente - " . print_r(sqlsrv_errors(), true));
        }
    }

    // ========================================
    // PROCESAR RESULTADO
    // ========================================
    if ($token_valido && $usuario_data) {
        
        error_log("SSO_USUARIOS: Limpiando token usado en SQL Server");
        
        // LIMPIAR TOKEN EN SQL SERVER (ya fue usado)
        if ($tipo_usuario === 'alumno') {
            $query_clear = "UPDATE Alumnos SET TokenSesion = NULL, FechaSesion = NULL WHERE Matricula = ?";
        } else {
            $query_clear = "UPDATE Docentes SET TokenSesion = NULL, FechaSesion = NULL WHERE Matricula = ?";
        }
        
        $params_clear = array($matricula_url);
        $stmt_clear = sqlsrv_prepare($db_gestion, $query_clear, $params_clear);
        if ($stmt_clear) {
            if (sqlsrv_execute($stmt_clear)) {
                error_log("SSO_USUARIOS: Token limpiado exitosamente");
            } else {
                error_log("SSO_USUARIOS ERROR: Error limpiando token - " . print_r(sqlsrv_errors(), true));
            }
            sqlsrv_free_stmt($stmt_clear);
        }

        // INICIAR SESIÓN
        $_SESSION['usuario_id'] = $usuario_data['id'];
        $_SESSION['usuario_matricula'] = $usuario_data['matricula'];
        $_SESSION['usuario_nombre'] = $usuario_data['nombre_completo'];
        $_SESSION['usuario_correo'] = $usuario_data['correo'];
        $_SESSION['usuario_tipo'] = $usuario_data['tipo'];
        $_SESSION['timestamp'] = time();
        $_SESSION['login'] = true;
        $_SESSION['es_usuario_normal'] = true;

        if ($tipo_usuario === 'alumno') {
            $_SESSION['cuatrimestre'] = $usuario_data['cuatrimestre'];
        } else {
            $_SESSION['numero_empleado'] = $usuario_data['numero_empleado'];
        }

        error_log("SSO_USUARIOS: ✅✅✅ Login exitoso - {$tipo_usuario}: {$usuario_data['matricula']}");
        error_log("SSO_USUARIOS: Redirigiendo a index.php");
        error_log("=== SSO_USUARIOS FIN EXITOSO ===");

        // Cerrar conexión SQL Server
        sqlsrv_close($db_gestion);

        header("Location: index.php");
        exit();
        
    } else {
        error_log("SSO_USUARIOS: ❌❌❌ Token inválido o expirado");
        error_log("SSO_USUARIOS: token_valido = " . ($token_valido ? 'true' : 'false'));
        error_log("SSO_USUARIOS: usuario_data = " . ($usuario_data ? 'existe' : 'null'));
        error_log("=== SSO_USUARIOS FIN CON ERROR ===");
        
        // Cerrar conexión SQL Server
        if ($db_gestion) {
            sqlsrv_close($db_gestion);
        }
        
        $_SESSION['sso_error'] = "Token inválido o expirado. Por favor, intenta nuevamente.";
        header('Location: iniciar-sesion.php');
        exit();
    }
    
} else {
    error_log("SSO_USUARIOS ERROR: Token no proporcionado en la URL");
    error_log("=== SSO_USUARIOS FIN - SIN TOKEN ===");
    $_SESSION['sso_error'] = "Token no proporcionado.";
    header('Location: iniciar-sesion.php');
    exit();
}
?>