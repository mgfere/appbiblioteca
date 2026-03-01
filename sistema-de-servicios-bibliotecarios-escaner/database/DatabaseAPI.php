<?php

require_once 'Database.php';
class DatabaseAPI
{
    private $dbh;

    public function __construct()
    {
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
    public function guardarSsoToken($token, $matricula)
    {
        try {
            // Reemplazo de CALL guardar_sso_token por INSERT directo
            $sql = "INSERT INTO sso_tokens (token, matricula, creado_en) VALUES (:token, :matricula, NOW())";
            $stmt = $this->dbh->prepare($sql);
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
     * PERO SER LLAMADA DESDE LA APP DE BIBLIOTECA.
     *
     * @param string $token Token de seguridad.
     * @return array|false Datos del token (matricula, creado_en) o false si no existe.
     */
    public function obtenerMatriculaYTiempoPorToken($token)
    {
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
    public function eliminarSsoToken($token)
    {
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

    public function obtenerUsuarioPorUsername($username)
    {
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
    public function obtenerTokenSSOConTiempo($token)
    {
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
    public function eliminarTokenSSO($token)
    {
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
    public function obtenerTokenUsuarioPorToken($token)
    {
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
    public function obtenerUsuarioPorMatricula($matricula)
    {
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
    public function limpiarTokenUsuario($identificador)
    {
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
    public function limpiarTokensExpirados()
    {
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

    public function obtenerCarrerasSQLServer()
    {
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

    public function obtenerCarreras()
    {
        try {
            // Reemplazo de CALL obtener_carreras por SELECT directo
            $sql = "SELECT id_carrera, nombre_carrera FROM carrera";
            $stmt = $this->dbh->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Manejar el error en caso de que ocurra.
            return false;
        }
    }
    public function obtenerEspecialidades()
    {
        try {
            // Reemplazo de CALL obtener_especialidades por SELECT directo
            $sql = "SELECT id_especialidad, nombre_especialidad, id_carrera FROM especialidades";
            $stmt = $this->dbh->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Manejar el error en caso de que ocurra.
            return false;
        }
    }
    public function obtener_especialidades_por_carrera($carreraId)
    {
        try {
            // Reemplazo de CALL obtener_especialidades_por_carrera por SELECT directo
            $sql = "SELECT id_especialidad, nombre_especialidad FROM especialidades WHERE id_carrera = :carreraId";
            $stmt = $this->dbh->prepare($sql);
            $stmt->bindParam(':carreraId', $carreraId, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('Error fetching especialidades: ' . $e->getMessage());
        }
    }

    public function obtenerServicios()
    {
        try {
            // Reemplazo de CALL obtener_servicios por SELECT directo
            $sql = "SELECT id_servicio, nombre_servicio FROM servicios";
            $stmt = $this->dbh->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo "Error DB: " . $e->getMessage();
            return false;
        }
    }
    public function obtenerNombreServicio($servicioId)
    {
        try {
            // Reemplazo de CALL ObtenerNombreServicio por SELECT directo
            $sql = "SELECT nombre_servicio FROM servicios WHERE id_servicio = :servicioId";
            $stmt = $this->dbh->prepare($sql);
            $stmt->bindParam(':servicioId', $servicioId, PDO::PARAM_INT);
            $stmt->execute();
            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado ? $resultado['nombre_servicio'] : null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function RegistrarSalida($registroId)
    {
        try {
            // Reemplazo de CALL RegistrarSalida por UPDATE directo
            $sql = "UPDATE registro SET hora_salida = NOW(), status = 1 WHERE id_registro = :registroId";
            $stmt = $this->dbh->prepare($sql);
            $stmt->bindParam(':registroId', $registroId, PDO::PARAM_INT);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error en RegistrarSalida: " . $e->getMessage());
            return false;
        }
    }
    public function obtenerRegistroPorMatricula($matricula)
    {
        try {
            // Unir registro con registereduser para obtener matricula y nombre
            $sql = "SELECT r.id_registro, r.hora_entrada, r.hora_salida, r.id_servicio, 
                           ru.matricula, ru.nameUser, ru.userType, r.status
                    FROM registro r
                    JOIN registereduser ru ON r.id_registeredUser = ru.id_registeredUser
                    WHERE ru.matricula LIKE CONCAT('%', :matricula, '%') 
                    AND r.status = 0";

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
    public function obtenerPasswordHash($user)
    {
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
    public function registrarAdministrador($user, $password, $matricula, $correo, $rol)
    {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            // Verificar duplicados de usuario o matrícula
            $checkSql = "SELECT COUNT(*) FROM user WHERE user = :user OR (Matricula = :matricula AND Matricula != '')";
            $checkStmt = $this->dbh->prepare($checkSql);
            $checkStmt->bindParam(':user', $user);
            $checkStmt->bindParam(':matricula', $matricula);
            $checkStmt->execute();

            if ($checkStmt->fetchColumn() == 0) {
                +$sql = "INSERT INTO user (user, password_user, Matricula, correo, rol) 
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
    public function verificarUsuarioExistente($user)
    {
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

    public function getRecords($startTime, $endTime, $searchTerm)
    {
        try {
            // Reemplazo de CALL GetRecords por SELECT directo con JOINs
            $sql = "SELECT r.*, ru.matricula, ru.nameUser, ru.userType, s.nombre_servicio
                    FROM registro r
                    JOIN registereduser ru ON r.id_registeredUser = ru.id_registeredUser
                    JOIN servicios s ON r.id_servicio = s.id_servicio
                    WHERE r.hora_entrada BETWEEN :startTime AND :endTime
                    AND (ru.matricula LIKE CONCAT('%', :searchTerm, '%') 
                         OR ru.nameUser LIKE CONCAT('%', :searchTerm, '%'))
                    ORDER BY r.hora_entrada DESC";

            $stmt = $this->dbh->prepare($sql);
            $stmt->bindParam(':startTime', $startTime, PDO::PARAM_STR);
            $stmt->bindParam(':endTime', $endTime, PDO::PARAM_STR);
            $stmt->bindParam(':searchTerm', $searchTerm, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Error en la consulta de servicios: " . $e->getMessage());
        }
    }
    public function obtenerSolicitudServicioPorMatricula($matricula)
    {
        try {
            // Reemplazo de CALL obtener_solicitud_servicio_por_matricula por SELECT directo
            $sql = "SELECT r.id_registro, r.hora_entrada, r.id_servicio, 
                           ru.matricula, ru.nameUser, s.nombre_servicio
                    FROM registro r
                    JOIN registereduser ru ON r.id_registeredUser = ru.id_registeredUser
                    JOIN servicios s ON r.id_servicio = s.id_servicio
                    WHERE ru.matricula = :matricula 
                    AND r.status = 0
                    ORDER BY r.hora_entrada DESC LIMIT 1";

            $stmt = $this->dbh->prepare($sql);
            $stmt->bindParam(':matricula', $matricula, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die("Error en la consulta: " . $e->getMessage());
        }
    }
    public function obtenerNombreCarrera($carreraId)
    {
        try {
            // Reemplazo de CALL ObtenerNombreCarrera por SELECT directo
            $sql = "SELECT nombre_carrera FROM carrera WHERE id_carrera = :p_id_carrera";
            $stmt = $this->dbh->prepare($sql);
            $stmt->bindParam(':p_id_carrera', $carreraId, PDO::PARAM_INT);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
            return $resultado ? $resultado['nombre_carrera'] : null;
        } catch (PDOException $e) {
            // Manejar el error en caso de que ocurra.
            return false;
        }
    }
    public function obtenerDatosGraficas()
    {
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
    public function obtenerNombreUsuario($user_id)
    {
        try {
            // Reemplazo de CALL ObtenerNombreUsuario por SELECT directo
            $sql = "SELECT user FROM user WHERE id_user = :p_user_id";
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
    public function obtenerRoles()
    {
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
    public function obtenerUsuarioPorId($id)
    {
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

    public function editarUsuario($id, $user, $password, $matricula, $correo, $rol)
    {
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

    public function eliminarUsuario($id)
    {
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

    public function verificarContraseñaActual($id, $currentPassword)
    {
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



    public function getNombresDeServicios()
    {
        $servicios = $this->obtenerServicios();
        if ($servicios && is_array($servicios)) {
            return array_column($servicios, 'nombre_servicio');
        }
        return [];
    }

    //NUEVAS FUNCIONES / PI - 2025
    public function insertingTeachersAndStudents($matricula, $nombreUsser, $id_especialidad, $id_carrera, $id_servicio, $userType)
    {
        try {
            $this->dbh->beginTransaction();

            // 1. Verificar/Insertar en registereduser
            $sqlCheckRU = "SELECT id_registeredUser FROM registereduser WHERE matricula = :matricula";
            $stmtCheckRU = $this->dbh->prepare($sqlCheckRU);
            $stmtCheckRU->bindParam(":matricula", $matricula);
            $stmtCheckRU->execute();
            $idRegisteredUser = $stmtCheckRU->fetchColumn();

            if (!$idRegisteredUser) {
                $sqlInsertRU = "INSERT INTO registereduser (matricula, nameUser, userType) VALUES (:matricula, :nombre, :tipo)";
                $stmtInsertRU = $this->dbh->prepare($sqlInsertRU);
                $stmtInsertRU->bindValue(":matricula", $matricula);
                $stmtInsertRU->bindValue(":nombre", $nombreUsser);
                $stmtInsertRU->bindValue(":tipo", $userType);
                $stmtInsertRU->execute();
                $idRegisteredUser = $this->dbh->lastInsertId();
            }

            // 2. Verificar/Insertar en Registro
            // Verificar si ya tiene entrada activa (status 0)
            $sqlCheckActivo = "SELECT COUNT(*) FROM registro WHERE id_registeredUser = :id AND status = 0";
            $stmtCheckActivo = $this->dbh->prepare($sqlCheckActivo);
            $stmtCheckActivo->bindParam(":id", $idRegisteredUser);
            $stmtCheckActivo->execute();

            if ($stmtCheckActivo->fetchColumn() > 0) {
                // Ya tiene entrada activa, lanzamos excepción con código 45000 para que lo atrape el catch
                throw new PDOException("Ya existe una solicitud pendiente de salida para esta matrícula", 45000);
            }

            // status 0 = Activo (sin salida)
            $sqlRegistro = "INSERT INTO registro (id_registeredUser, id_servicio, hora_entrada, status)
                            VALUES (:id, :id_servicio, NOW(), 0)";

            $stmt = $this->dbh->prepare($sqlRegistro);
            $stmt->bindValue(":id", $idRegisteredUser);
            $stmt->bindValue(":id_servicio", $id_servicio, PDO::PARAM_INT);
            $stmt->execute();
            $this->dbh->commit();
            return true;

        } catch (PDOException $e) {
            $this->dbh->rollBack();
            if ($e->getCode() === '45000') {
                return "Error de validación: " . $e->getMessage();
            } else {
                return "Error al insertar el registro: " . $e->getMessage();
            }
        }
    }
    public function mostrarListaDeUsuarios()
    {
        try {
            // Reemplazo de CALL listaDeUsuarios por SELECT directo de user
            $query = "SELECT u.id_user, u.user, u.Matricula, u.correo, r.nombreRol 
                      FROM user u
                      LEFT JOIN rol r ON u.rol = r.IdRol";
            $stmt = $this->dbh->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en mostrarListaDeUsuarios: " . $e->getMessage());
            return [];
        }
    }
    public function usuarioExistenteRevisar($matricula)
    {
        try {
            // Verificar en la nueva tabla padrón de visitantes 'registereduser' no en 'user'
            $sql = "SELECT COUNT(*) FROM registereduser WHERE matricula = :matricula";
            $stmt = $this->dbh->prepare($sql);
            $stmt->bindParam(":matricula", $matricula, PDO::PARAM_STR);
            $stmt->execute();
            return ((int) $stmt->fetchColumn()) > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function insertarRegistroSolicitud($matricula, $id_servicio)
    {
        try {
            // 1. Obtener id_registeredUser
            // Primero buscamos en registereduser
            $sqlUser = "SELECT id_registeredUser FROM registereduser WHERE matricula = :matricula";
            $stmtUser = $this->dbh->prepare($sqlUser);
            $stmtUser->bindParam(":matricula", $matricula);
            $stmtUser->execute();
            $idRegisteredUser = $stmtUser->fetchColumn();

            if (!$idRegisteredUser) {
                // Si no existe, deberíamos crearlo? 
                // El flujo actual asume que si llegamos aquí, "usuarioExistenteRevisar" retornó true.
                // Pero usuarioExistenteRevisar chequea la tabla 'user'.
                // Vamos a intentar sincronizar o lanzar error.
                // Intentemos buscar en 'user' para recuperar el nombre si es necesario, o usar valores por defecto.
                return "Error: Usuario no encontrado en tabla de registros (registereduser).";
            }

            // 2. Verificar si ya tiene entrada activa (status 0)
            $sqlCheck = "SELECT COUNT(*) FROM registro WHERE id_registeredUser = :id AND status = 0";
            $stmtCheck = $this->dbh->prepare($sqlCheck);
            $stmtCheck->bindParam(":id", $idRegisteredUser);
            $stmtCheck->execute();

            if ($stmtCheck->fetchColumn() > 0) {
                // Ya tiene entrada activa
                // Dependiendo de la lógica, permitimos o no. El SP original lanzaba error si status=0.
                return "Usuario ya tiene una entrada activa.";
            }

            // 3. Insertar en registro
            $sqlInsert = "INSERT INTO registro (id_registeredUser, id_servicio, hora_entrada, status) 
                          VALUES (:id, :id_servicio, NOW(), 0)";

            $stmt = $this->dbh->prepare($sqlInsert);
            $stmt->bindParam(":id", $idRegisteredUser);
            $stmt->bindParam(":id_servicio", $id_servicio);
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            return "Error: " . $e->getMessage();
        }
    }


    public function tablaTemporalAlumnos()
    {
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

    public function cargarCSVaTemporal($rutaArchivo)
    {
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
            $sql = "LOAD DATA LOCAL INFILE '" . str_replace("\\", "/", $rutaArchivo) . "'
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
    public function importarAlumnosDesdeCSV()
    {
        try {
            // Reemplazo de CALL importar_nuevos_alumnos con lógica PHP/SQL
            // Insertar nuevos usuarios en registereduser desde temporalcsvdatos
            // Asumimos mapeo: matricula -> matricula, nombre -> nameUser, tipo_usuario -> userType

            $sqlIndices = "ALTER TABLE temporalcsvdatos ADD INDEX (matricula)";
            $this->dbh->exec($sqlIndices);

            $sqlInsert = "INSERT INTO registereduser (matricula, nameUser, userType)
                          SELECT t.matricula, t.nombre, t.tipo_usuario
                          FROM temporalcsvdatos t
                          LEFT JOIN registereduser ru ON t.matricula = ru.matricula
                          WHERE ru.matricula IS NULL";

            $stmt = $this->dbh->prepare($sqlInsert);
            $stmt->execute();
            $inserted = $stmt->rowCount();

            return "Se importaron $inserted alumnos nuevos.";

        } catch (PDOException $e) {
            throw new Exception('Error al importar alumnos: ' . $e->getMessage());
        }
    }


    public function getPDO()
    {
        return $this->dbh;
    }


    public function obtenerDatosCompletosUsuario($matricula)
    {
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

    public function especialidadesPorCarreras($carreraId, $especialidadId = null)
    {
        try {
            // Reemplazo de CALL ObtenerEspecialidadesPorCarrera por SELECT directo
            $sql = "SELECT id_especialidad, nombre_especialidad FROM especialidades WHERE id_carrera = :carreraId";
            $stmt = $this->dbh->prepare($sql);
            $stmt->bindParam(':carreraId', $carreraId, PDO::PARAM_INT);
            // El parametro especialidadId no se usa en el filtro comun? 
            // Si el SP original lo usaba, tal vez era para filtrar una especifica o nada?
            // Asumiremos listar todas por carrera
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception('Error al obtener especialidades: ' . $e->getMessage());
        }
    }

    public function especialidadesPorCarrerasSQL($carreraId, $especialidadId = null)
    {
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
    public function obtenerNombreEspecialidad($id)
    {
        $conn = conectarDB3();
        if (!$conn)
            return null;

        $sql = "SELECT Nombre FROM Areas WHERE IdArea = ?";
        $params = array($id);
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt && $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
            $nombre = $row['Nombre'];
            sqlsrv_free_stmt($stmt);
            sqlsrv_close($conn);
            return $nombre;
        }

        sqlsrv_close($conn);
        return null;
    }

    public function registrarSalidasBiblioteca($matricula)
    {
        try {
            // Reutilizamos obtenerRegistroPorMatricula para verificar existencia
            $registros = $this->obtenerRegistroPorMatricula($matricula);

            // Si retorna vacio, no hay registros activos
            if (empty($registros)) {
                return "No existe registro activo para esta matricula";
            }
            return true;

        } catch (PDOException $e) {
            return "Error al verificar salida: " . $e->getMessage();
        }
    }

}
