<?php

require_once 'Database.php';
class DatabaseAPI {
    private $dbh;

    public function __construct() {
        $db = new Database();
        $this->dbh = $db->getDBH();
                $this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
    }


    // public function insertarsolicitudservicio($matricula, $nombre, $id_carrera, $id_especialidad, $id_servicio, $hora_entrada)
    // {
    //     try {
    //         // Llamamos al procedimiento almacenado para insertar el nuevo registro
    //         $stmt = $this->dbh->prepare("CALL insertar_solicitud_servicio(:matricula, :nombre, :id_carrera, :id_especialidad, :id_servicio, :hora_entrada)");
    //         $stmt->bindParam(':matricula', $matricula);
    //         $stmt->bindParam(':nombre', $nombre);
    //         $stmt->bindParam(':id_carrera', $id_carrera);
    //         $stmt->bindParam(':id_especialidad', $id_especialidad);
    //         $stmt->bindParam(':id_servicio', $id_servicio);
    //         $stmt->bindParam(':hora_entrada', $hora_entrada);

    //         // Ejecutamos el procedimiento almacenado
    //         $stmt->execute();

    //         // Si la ejecución es exitosa, devolvemos true
    //         return true;
    //     } catch (PDOException $e) {
    //         // Si ocurre un error, devolvemos el mensaje de error en la variable $message
    //         if ($e->getCode() === '45000') {
    //             return "Ya existe una solicitud que no se ha registrado la salida para esta matrícula";
    //         } else {
    //             return "Error al insertar el registro: " . $e->getMessage();
    //         }
    //     }
    // }
    
// ... dentro de la class DatabaseAPI { ...
// ... después de tu última función existente ...

    /**
     * Guarda el token temporal de SSO en la base de datos principal.
     * @param string $token Token de seguridad único.
     * @param string $matricula Matrícula del usuario.
     * @return bool
     */
    public function guardarSsoToken($token, $matricula) {
        try {
            $stmt = $this->dbh->prepare("CALL guardar_sso_token(:token, :matricula)");
            $stmt->bindParam(':token', $token, PDO::PARAM_STR);
            $stmt->bindParam(':matricula', $matricula, PDO::PARAM_STR);
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            error_log("Error al guardar token SSO: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca el token y retorna la matrícula del usuario si es válido.
     * Este NO debe ser un procedimiento almacenado ya que debe ser rápido 
     * y necesita la restricción de tiempo en el PHP de la app de biblioteca.
     * * NOTA: ESTA FUNCIÓN DEBE ESTAR EN LA DB DE LA APP PRINCIPAL 
     * PERO SER LLAMADA DESDE LA APP DE BIBLIOTECA (como se explicó en el plan).
     *
     * @param string $token Token de seguridad.
     * @return array|false Datos del token (matricula, creado_en) o false si no existe.
     */
    public function obtenerMatriculaYTiempoPorToken($token) {
        try {
            $sql = "SELECT matricula, creado_en FROM sso_tokens WHERE token = :token";
            $stmt = $this->dbh->prepare($sql);
            $stmt->bindParam(':token', $token, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error al obtener matrícula por token: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Elimina el token una vez que ha sido consumido.
     *
     * @param string $token Token de seguridad.
     * @return bool
     */
    public function eliminarSsoToken($token) {
        try {
            $sql = "DELETE FROM sso_tokens WHERE token = :token";
            $stmt = $this->dbh->prepare($sql);
            $stmt->bindParam(':token', $token, PDO::PARAM_STR);
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            error_log("Error al eliminar token SSO: " . $e->getMessage());
            return false;
        }
    }

public function obtenerUsuarioPorUsername($username) {
    try {
        $sql = "SELECT * FROM user WHERE user = :username";
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        $userData = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("obtenerUsuarioPorUsername('$username') retornó: " . ($userData ? 'DATOS' : 'FALSE'));
        
        return $userData;
    } catch (PDOException $e) {
        error_log("Error en obtenerUsuarioPorUsername: " . $e->getMessage());
        return false;
    }
}
public function obtenerTokenSSOConTiempo($token) {
    try {
        // Obtener token y tiempo actual de MySQL en la misma consulta
        $sql = "SELECT matricula, creado_en, NOW() AS tiempo_actual_db FROM sso_tokens WHERE token = :token";
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("obtenerTokenSSOConTiempo('$token') retornó: " . ($result ? print_r($result, true) : 'FALSE'));
        
        return $result;
    } catch (PDOException $e) {
        error_log("Error obtenerTokenSSOConTiempo: " . $e->getMessage());
        return false;
    }
}
public function eliminarTokenSSO($token) {
    try {
        $sql = "DELETE FROM sso_tokens WHERE token = :token";
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':token', $token);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error eliminarTokenSSO: " . $e->getMessage());
        return false;
    }
}
// ============================================
// FUNCIONES SSO CORREGIDAS - DatabaseAPI.php
// ============================================

/**
 * Obtener token de usuario por token (CORREGIDO)
 * NOTA: Usar Matricula con MAYÚSCULA según estructura de tu tabla
 */
public function obtenerTokenUsuarioPorToken($token) {
    try {
        // 🔥 IMPORTANTE: Usar "Matricula" con mayúscula
        $sql = "SELECT 
                    Matricula as matricula,
                    token_expira,
                    NOW() as tiempo_actual_db
                FROM user 
                WHERE token = :token 
                LIMIT 1";
        
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':token', $token, PDO::PARAM_STR); 
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC); 
        
        if ($result) {
            error_log("✅ obtenerTokenUsuarioPorToken encontró token:");
            error_log("   - Matricula: " . $result['matricula']);
            error_log("   - Token expira: " . $result['token_expira']);
            error_log("   - Tiempo DB: " . $result['tiempo_actual_db']);
        } else {
            error_log("❌ obtenerTokenUsuarioPorToken: Token no encontrado en BD");
        }
        
        return $result ?: null; 

    } catch (PDOException $e) {
        error_log("❌ Error obtenerTokenUsuarioPorToken: " . $e->getMessage());
        return null;
    }
}

/**
 * Obtener usuario por matrícula (CORREGIDO)
 * NOTA: Usar Matricula con MAYÚSCULA
 */
public function obtenerUsuarioPorMatricula($matricula) {
    try {
        // 🔥 IMPORTANTE: Usar "Matricula" con mayúscula en WHERE
        $sql = "SELECT 
                    Matricula as matricula,
                    user,
                    rol
                FROM user 
                WHERE Matricula = :matricula
                LIMIT 1";
        
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':matricula', $matricula, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            error_log("✅ obtenerUsuarioPorMatricula encontró usuario:");
            error_log("   - Matricula: " . $result['matricula']);
            error_log("   - User: " . $result['user']);
            error_log("   - Rol: " . $result['rol']);
        } else {
            error_log("❌ obtenerUsuarioPorMatricula: Usuario no encontrado para matricula: $matricula");
        }
        
        return $result ?: null;

    } catch (PDOException $e) {
        error_log("❌ Error obtenerUsuarioPorMatricula: " . $e->getMessage());
        return null;
    }
}

/**
 * Limpiar token de usuario después de usarlo (CORREGIDO)
 */
public function limpiarTokenUsuario($identificador) {
    try {
        $sql = "UPDATE user 
                SET token = NULL, 
                    token_expira = NULL 
                WHERE Matricula = :id OR user = :id";
        
        $stmt = $this->dbh->prepare($sql);
        $idLimpio = trim($identificador);
        $stmt->bindParam(':id', $idLimpio, PDO::PARAM_STR);
        
        $resultado = $stmt->execute();
        
        $filasAfectadas = $stmt->rowCount();
        
        if ($filasAfectadas > 0) {
            error_log("✅ Token limpiado exitosamente para: $idLimpio");
        } else {
            error_log("⚠️ Se ejecutó la consulta pero no se encontró usuario para limpiar: $idLimpio");
        }
        
        return $resultado;
        
    } catch (PDOException $e) {
        error_log("❌ Error limpiarTokenUsuario: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtener usuario por username (CORREGIDO - para fallback)
 */


/**
 * Limpiar tokens expirados (mantenimiento) - CORREGIDO
 */
public function limpiarTokensExpirados() {
    try {
        $sql = "UPDATE user 
                SET token = NULL, 
                    token_expira = NULL 
                WHERE token_expira IS NOT NULL 
                AND token_expira < NOW()";
        
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute();
        
        $count = $stmt->rowCount();
        
        if ($count > 0) {
            error_log("✅ Se limpiaron $count tokens expirados");
        }
        
        return $count; 
        
    } catch (PDOException $e) {
        error_log("❌ Error limpiarTokensExpirados: " . $e->getMessage());
        return 0;
    }
}

public function obtenerCarrerasSQLServer() {
    try {
        $conn = conectarDB3();
        
        if (!$conn) {
            return [];
        }
        
        $sql = "SELECT IdCarrera, Nombre, Nomenclatura 
                FROM Carreras 
                WHERE Habilitado = 1 
                ORDER BY Nombre ASC";
        
        $stmt = sqlsrv_query($conn, $sql);
        
        if ($stmt === false) {
            sqlsrv_close($conn);
            return [];
        }
        
        $carreras = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $carreras[] = [
                'id_carrera' => $row['IdCarrera'],
                'nombre_carrera' => $row['Nombre'] . ' (' . $row['Nomenclatura'] . ')'
            ];
        }
        
        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);
        
        return $carreras;
        
    } catch (Exception $e) {
        return [];
    }
}

    public function obtenerCarreras() {
        try {
            $sql = "CALL obtener_carreras()";
            $stmt = $this->dbh->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Manejar el error en caso de que ocurra.
            return false;
        }
    }
    public function obtenerEspecialidades() {
        try {
            $sql = "CALL obtener_especialidades()";
            $stmt = $this->dbh->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Manejar el error en caso de que ocurra.
            return false;
        }
    }
    public function obtener_especialidades_por_carrera($carreraId) {
        try {
            $sql = "CALL obtener_especialidades_por_carrera(:carreraId)";
            $stmt = $this->dbh->prepare($sql);
            $stmt->bindParam(':carreraId', $carreraId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('Error fetching especialidades: ' . $e->getMessage());
        }
    }
    
    public function obtenerServicios() {
        try {
            $sql = "CALL obtener_servicios()";
            $stmt = $this->dbh->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Manejar el error en caso de que ocurra.
            return false;
        }
    }
    public function obtenerNombreServicio($servicioId) {
        $sql = "CALL ObtenerNombreServicio(:servicioId, @nombreServicio)";
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':servicioId', $servicioId, PDO::PARAM_INT);
        $stmt->execute();

        // Obtener el resultado del procedimiento almacenado
        $stmt = $this->dbh->query("SELECT @nombreServicio as nombreServicio");
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        return $resultado['nombreServicio'];
    }

    public function RegistrarSalida($registroId) {
    try {
        $sql = "CALL RegistrarSalida(:registroId)";
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':registroId', $registroId, PDO::PARAM_INT);
        return $stmt->execute();
        } 
        catch (PDOException $e) {
        error_log("Error en RegistrarSalida: " . $e->getMessage());
        return false;
        }
    }
    public function obtenerRegistroPorMatricula($matricula) {
    try {
        $sql = "CALL BuscarRegistroPorMatricula(:matricula)";
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':matricula', $matricula, PDO::PARAM_STR);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        
        return $results;
    } catch (PDOException $e) {
        error_log("Error en obtenerRegistroPorMatricula: " . $e->getMessage());
        return [];
    }
}
   public function obtenerPasswordHash($user) {
    try {
        // SOLUCIÓN: Sin COLLATE, solo la consulta normal
        $stmt = $this->dbh->prepare("
            SELECT password_user 
            FROM user 
            WHERE user = :user
            LIMIT 1
        ");
        $stmt->bindValue(':user', $user, PDO::PARAM_STR);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && isset($result['password_user'])) {
            return $result['password_user'];
        } else {
            // Retornar un hash inválido si no encuentra usuario
            return '$2y$10$invalidhashfornonexistentuser';
        }
        
    } catch (PDOException $e) {
        throw new PDOException("Error en la base de datos: " . $e->getMessage());
    }
}
   public function registrarAdministrador($user, $password, $matricula, $correo, $rol) {
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    try {
        // Verificar duplicados de usuario o matrícula
        $checkSql = "SELECT COUNT(*) FROM user WHERE user = :user OR (Matricula = :matricula AND Matricula != '')";
        $checkStmt = $this->dbh->prepare($checkSql);
        $checkStmt->bindParam(':user', $user);
        $checkStmt->bindParam(':matricula', $matricula);
        $checkStmt->execute();

        if ($checkStmt->fetchColumn() == 0) {
+            $sql = "INSERT INTO user (user, password_user, Matricula, correo, rol) 
                    VALUES (:user, :password, :matricula, :correo, :rol)";
            
            $stmt = $this->dbh->prepare($sql);
            $stmt->bindParam(':user', $user);
            $stmt->bindParam(':password', $hashedPassword);
            $stmt->bindParam(':matricula', $matricula);
            $stmt->bindParam(':correo', $correo);
            $stmt->bindParam(':rol', $rol);

            return $stmt->execute();
        } else {
            return false; // Ya existe
        }
    } catch (PDOException $e) {
        die("Error DB: " . $e->getMessage());
    }
}
    public function verificarUsuarioExistente($user) {
        try {
            $checkSql = "SELECT COUNT(*) FROM user WHERE user = :user";
            $checkStmt = $this->dbh->prepare($checkSql);
            $checkStmt->bindParam(':user', $user);
            $checkStmt->execute();
            $count = $checkStmt->fetchColumn();

            if ($count == 0) {
                return false;
            } else {
                return true;
            }
        } catch (PDOException $e) {
            // En caso de error, puedes manejarlo según tus necesidades (lanzar excepciones, loggear el error, etc.)
            die("Error en la base de datos: " . $e->getMessage());
        }
    }

    public function getRecords($startTime, $endTime, $searchTerm) {
        try {
            $sql = "CALL GetRecords(:startTime, :endTime, :searchTerm)";
            $stmt = $this->dbh->prepare($sql);
            $stmt->bindParam(':startTime', $startTime, PDO::PARAM_STR);
            $stmt->bindParam(':endTime', $endTime, PDO::PARAM_STR);
            $stmt->bindParam(':searchTerm', $searchTerm, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // En caso de error, puedes manejarlo según tus necesidades (lanzar excepciones, loggear el error, etc.)
            die("Error en la consulta de servicios: " . $e->getMessage());
        }
    }
    public function obtenerSolicitudServicioPorMatricula($matricula)
    {
        try {
            // Preparar la llamada al procedimiento almacenado
            $sql = "CALL obtener_solicitud_servicio_por_matricula(:matricula)";
            $stmt = $this->dbh->prepare($sql);
            $stmt->bindParam(':matricula', $matricula, PDO::PARAM_STR);
            $stmt->execute();

            // Obtener el resultado del procedimiento almacenado
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            // Devolver el resultado de la consulta
            return $resultado;
        } catch (PDOException $e) {
            // Manejar el error de la consulta si es necesario
            die("Error en la consulta: " . $e->getMessage());
        }
    }
    public function obtenerNombreCarrera($carreraId) {
        try {
            $sql = "CALL ObtenerNombreCarrera(:p_id_carrera, @p_nombreCarrera)";
            $stmt = $this->dbh->prepare($sql);
            $stmt->bindParam(':p_id_carrera', $carreraId, PDO::PARAM_INT);
            $stmt->execute();
    
            // Obtener el resultado del procedimiento almacenado
            $stmt = $this->dbh->query("SELECT @p_nombreCarrera as nombre_carrera");
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado['nombre_carrera'];
        } catch (PDOException $e) {
            // Manejar el error en caso de que ocurra.
            return false;
        }
    }
    public function obtenerDatosGraficas() {
    try {
        $serviciosData = array();
        $carrerasData = array();

        // Obtén los datos de las gráficas, similar a como lo hiciste en tu archivo original
        
        return array('servicios' => $serviciosData, 'carreras' => $carrerasData);
    } catch (PDOException $e) {
        // Maneja el error
        return false;
    }
}
public function obtenerNombreUsuario($user_id) {
    try {
        $sql = "CALL ObtenerNombreUsuario(:p_user_id)";
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':p_user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();

        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado) {
            return $resultado['user'];
        } else {
            return "Nombre de usuario no encontrado";
        }
    } catch (PDOException $e) {
        // Manejar el error en caso de que ocurra.
        return "Error al obtener el nombre del usuario: " . $e->getMessage();
    }
}

// --- AGREGAR ESTA FUNCIÓN NUEVA ---
public function obtenerRoles() {
    try {
        $sql = "SELECT IdRol, nombreRol FROM rol ORDER BY IdRol ASC";
        $stmt = $this->dbh->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

// --- REEMPLAZAR ESTA FUNCIÓN (Ahora hace JOIN para ver el nombre del rol) ---
public function obtenerTodosLosUsuarios($rolUsuarioActual = 0)
{
    try {
        // Hacemos LEFT JOIN para traer el nombre del rol
        $sql = "SELECT u.id_user, u.user, u.password_user, u.rol, u.Matricula, u.correo, r.nombreRol 
                FROM user u
                LEFT JOIN rol r ON u.rol = r.IdRol";
        
        // Si NO es Master (1), ocultamos a los usuarios con rol 1
        if ($rolUsuarioActual != 1) {
            $sql .= " WHERE u.rol != 1";
        }
        
        $sql .= " ORDER BY u.user ASC";

        $stmt = $this->dbh->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}
public function obtenerUsuarioPorId($id) {
    try {
        $sql = "SELECT id_user, user, password_user FROM user WHERE id_user = :id";
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Error al obtener el usuario: " . $e->getMessage());
    }
}

public function editarUsuario($id, $user, $password, $matricula, $correo, $rol) {
    try {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $sql = "UPDATE user 
                SET user = :user, 
                    password_user = :password,
                    Matricula = :matricula,
                    correo = :correo,
                    rol = :rol
                WHERE id_user = :id";
        
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':user', $user, PDO::PARAM_STR);
        $stmt->bindParam(':password', $hashedPassword, PDO::PARAM_STR);
        $stmt->bindParam(':matricula', $matricula, PDO::PARAM_STR);
        $stmt->bindParam(':correo', $correo, PDO::PARAM_STR);
        $stmt->bindParam(':rol', $rol, PDO::PARAM_INT);

        return $stmt->execute();
    } catch (PDOException $e) {
        return false;
    }
}

public function eliminarUsuario($id) {
    try {
        // Preparar la consulta para eliminar el usuario
        $sql = "DELETE FROM user WHERE id_user = :id";
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);

        // Ejecutar la consulta
        return $stmt->execute();
    } catch (PDOException $e) {
        die("Error al eliminar el usuario: " . $e->getMessage());
    }
}

public function verificarContraseñaActual($id, $currentPassword) {
    try {
        // Obtener el hash de la contraseña del usuario
        $sql = "SELECT password_user FROM user WHERE id_user = :id";
        $stmt = $this->dbh->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verificar si la contraseña actual es correcta
        if ($result && password_verify($currentPassword, $result['password_user'])) {
            return true; // Contraseña correcta
        } else {
            return false; // Contraseña incorrecta
        }
    } catch (PDOException $e) {
        die("Error al verificar la contraseña: " . $e->getMessage());
    }
}



public function getNombresDeServicios() {
    $servicios = $this->obtenerServicios();
    if ($servicios && is_array($servicios)) {
        return array_column($servicios, 'nombre_servicio');
    }
    return [];
}

//NUEVAS FUNCIONES / PI - 2025
public function insertingTeachersAndStudents($matricula, $nombreUsser, $id_especialidad, $id_carrera, $id_servicio, $userType){
    try {
        $stmt = $this->dbh->prepare("CALL insertUsers(:matricula, :userName, :idEspecialidad, :idCarrera, :idServicio, :userType)");
        
        $stmt->bindValue(":matricula", $matricula, PDO::PARAM_STR);
        
        $stmt->bindValue(":userName", $nombreUsser, PDO::PARAM_STR);
        
        if (empty($id_especialidad)) {
            $stmt->bindValue(":idEspecialidad", null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(":idEspecialidad", $id_especialidad, PDO::PARAM_INT);
        }

        if (empty($id_carrera)) {
            $stmt->bindValue(":idCarrera", null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(":idCarrera", $id_carrera, PDO::PARAM_INT);
        }

        $stmt->bindValue(":idServicio", $id_servicio, PDO::PARAM_INT);

        $stmt->bindValue(":userType", $userType, PDO::PARAM_STR);

        $stmt->execute();
        return true;

    } catch (PDOException $e) {
        if ($e->getCode() === '45000') {
            return "Error de validación: " . $e->getMessage(); // Captura los mensajes del SIGNAL SQLSTATE
        } else {
            return "Error al insertar el registro: " . $e->getMessage();
        }
    }
}
    public function mostrarListaDeUsuarios() {
    $query = "CALL listaDeUsuarios()";
    
    try {
        $stmt = $this->dbh->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error en mostrarListaDeUsuarios: " . $e->getMessage());
        return [];
    }
}
    public function usuarioExistenteRevisar($matricula) {
    try {
        $stmt = $this->dbh->prepare("CALL revisionDeUsuarioExistente(:matricula)");
        $stmt->bindParam(":matricula", $matricula);
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    } catch (PDOException $e) {
        return false;
    }
}

   public function insertarRegistroSolicitud($matricula, $id_servicio) {
    try {
        $stmt = $this->dbh->prepare("CALL registrarPeticionServicio(:matricula, :id_servicio)");
        $stmt->bindParam(":matricula", $matricula);
        $stmt->bindParam(":id_servicio", $id_servicio);
        $stmt->execute();
        return true;
    } catch (PDOException $e) {
        return "Error: " . $e->getMessage();
    }
}


public function tablaTemporalAlumnos() {
    $sql = "CREATE TABLE IF NOT EXISTS temporalcsvdatos (
        matricula VARCHAR(20) NOT NULL,
        nombre VARCHAR(100) NOT NULL,
        tipo_usuario VARCHAR(50) DEFAULT 'Estudiante',
        nombre_carrera VARCHAR(100),
        nombre_especialidad VARCHAR(100)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    try {
        $this->dbh->exec($sql);
        return true;
    } catch (PDOException $e) {
        throw new Exception('Error creando tabla temporal: ' . $e->getMessage());
    }
}

public function cargarCSVaTemporal($rutaArchivo) {
    try {
        // Vaciar tabla temporal primero
        $this->dbh->exec("TRUNCATE TABLE temporalcsvdatos");
        
        // Verificar si el archivo existe y es legible
        if (!file_exists($rutaArchivo)) {
            throw new Exception("El archivo CSV no existe en la ruta especificada");
        }
        
        // Configurar conexión para permitir LOCAL INFILE
        $this->dbh->setAttribute(PDO::MYSQL_ATTR_LOCAL_INFILE, true);
        
        // Cargar datos desde CSV
        $sql = "LOAD DATA LOCAL INFILE '".str_replace("\\", "/", $rutaArchivo)."'
                INTO TABLE temporalcsvdatos
                CHARACTER SET utf8mb4
                FIELDS TERMINATED BY ',' 
                OPTIONALLY ENCLOSED BY '\"'
                ESCAPED BY '\"'
                LINES TERMINATED BY '\n'
                IGNORE 1 LINES
                (matricula, nombre, nombre_carrera, nombre_especialidad)";
        
        $this->dbh->exec($sql);
        
        $stmt = $this->dbh->query("SELECT COUNT(*) FROM temporalcsvdatos");
        $count = $stmt->fetchColumn();
        
        if ($count == 0) {
            throw new Exception("El archivo CSV no contenía datos válidos o no se pudo cargar");
        }
        
        return true;
    } catch (PDOException $e) {
        throw new Exception('Error al cargar CSV: ' . $e->getMessage());
    }
}
public function importarAlumnosDesdeCSV() {
    try {
        $stmt = $this->dbh->prepare("CALL importar_nuevos_alumnos()");
        $stmt->execute();
        
        $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        
        return $resultado['Resultado'] ?? 'Proceso completado.';
        
    } catch (PDOException $e) {
        throw new Exception('Error en procedimiento almacenado: ' . $e->getMessage());
    }
}


public function getPDO() {
    return $this->dbh;
}


    public function obtenerDatosCompletosUsuario($matricula) {
        try {
            $sql = "CALL obtenerDatosCompletosUsuario(:matricula)";
            $stmt = $this->dbh->prepare($sql);
            $stmt->bindParam(':matricula', $matricula, PDO::PARAM_STR);
            $stmt->execute();
            
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($userData && $userData['userType'] === 'Alumno') {
                $stmt->nextRowset(); // Mover al siguiente conjunto de resultados
                $alumnoData = $stmt->fetch(PDO::FETCH_ASSOC);
                $userData = array_merge($userData, $alumnoData);
            }
            
            $stmt->closeCursor();
            return $userData;
        } catch (PDOException $e) {
            error_log("Error en obtenerDatosCompletosUsuario: " . $e->getMessage());
            return false;
        }
    }

      public function especialidadesPorCarreras($carreraId, $especialidadId = null) {
        try {
            $sql = "CALL ObtenerEspecialidadesPorCarrera(:carreraId, :especialidadId)";
            $stmt = $this->dbh->prepare($sql);
            $stmt->bindParam(':carreraId', $carreraId, PDO::PARAM_INT);
            $especialidadId = $especialidadId ?: 0;
            $stmt->bindParam(':especialidadId', $especialidadId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('Error al obtener especialidades: ' . $e->getMessage());
        }
    }

public function especialidadesPorCarrerasSQL($carreraId, $especialidadId = null) {
    try {
        // 1. Conectar a SQL Server (GestionUsuarios)
        $conn = conectarDB3();
        
        if (!$conn) {
            error_log("Error al conectar a SQL Server en especialidadesPorCarreras");
            return [];
        }
        
        $sql = "SELECT IdArea, Nombre 
                FROM Areas 
                WHERE IdCarrera = ? AND Habilitado = 1 
                ORDER BY Nombre ASC";
        
        $params = array($carreraId);
        
        $stmt = sqlsrv_query($conn, $sql, $params);
        
        if ($stmt === false) {
            error_log("Error SQL en especialidadesPorCarreras: " . print_r(sqlsrv_errors(), true));
            sqlsrv_close($conn);
            return [];
        }
        
        // 3. Formatear resultados para que coincidan con lo que espera tu front-end
        $especialidades = [];
        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $especialidades[] = [
                'id_especialidad' => $row['IdArea'], 
                'nombre_especialidad' => $row['Nombre'] 
            ];
        }
        
        sqlsrv_free_stmt($stmt);
        sqlsrv_close($conn);
        
        return $especialidades;
        
    } catch (Exception $e) {
        error_log("Excepción en especialidadesPorCarreras: " . $e->getMessage());
        return [];
    }
}
    public function registrarSalidasBiblioteca($matricula){
        try{
            $stmt = $this->dbh->prepare("CALL BuscarRegistroPorMatricula(:matricula)");
            $stmt->bindParam(":matricula", $matricula);
            $stmt->execute();
            

        }
        catch (PDOException $e) {
            if ($e->getCode() === '45000') {
                return "No existe esta matricula";
            } else {
                return "Error al registrar la salida: " . $e->getMessage();
            }
        }
    }
    
}


