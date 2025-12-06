<?php
// config.php - MediRecord - Versión completa para Railway
// Archivo de configuración principal del sistema

// =============================================================================
// CONFIGURACIÓN DE BASE DE DATOS PARA RAILWAY
// =============================================================================

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    }

// Detectar entorno Railway vs Local
$isRailway = getenv('MYSQLHOST') !== false || 
             getenv('RAILWAY_ENVIRONMENT') !== false ||
             getenv('RAILWAY_PUBLIC_DOMAIN') !== false;

// Configuración de base de datos
if ($isRailway) {
    // CONFIGURACIÓN PARA RAILWAY
    $host = getenv('MYSQLHOST') ?: 'localhost';
    $port = getenv('MYSQLPORT') ?: '3306';
    $database = getenv('MYSQLDATABASE') ?: 'railway';
    $username = getenv('MYSQLUSER') ?: 'root';
    $password = getenv('MYSQLPASSWORD') ?: '';
    
    // Si MYSQL_URL está disponible, usarla (mejor opción)
    $mysql_url = getenv('MYSQL_URL');
    if ($mysql_url) {
        $url = parse_url($mysql_url);
        $host = $url['host'] ?? $host;
        $port = $url['port'] ?? $port;
        $database = isset($url['path']) ? ltrim($url['path'], '/') : $database;
        $username = $url['user'] ?? $username;
        $password = $url['pass'] ?? $password;
    }
} else {
    // CONFIGURACIÓN LOCAL (XAMPP/MAMP/WAMP)
    $host = 'localhost';
    $port = '3306';
    $database = 'medirecord_db';
    $username = 'root';
    $password = '';
}

// Conexión a la base de datos
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false
    ]);
    
    // Para debugging en Railway (solo en logs)
    if ($isRailway) {
        error_log("✅ Conectado a MySQL en Railway: $host:$port/$database");
    }
    
} catch (PDOException $e) {
    // Manejo de errores mejorado para Railway
    $error_message = "Error de conexión a la base de datos";
    $error_details = "Host: $host, Port: $port, DB: $database, User: $username";
    
    error_log("❌ $error_message: " . $e->getMessage());
    error_log("📋 $error_details");
    
    // Mensaje amigable para el usuario
    if ($isRailway) {
        die("<h2>Error de configuración</h2>
             <p>No se pudo conectar a la base de datos en Railway.</p>
             <p>Verifica que:</p>
             <ul>
                <li>Hayas añadido un servicio MySQL a tu proyecto</li>
                <li>Las variables de entorno estén configuradas correctamente</li>
                <li>La base de datos esté activa</li>
             </ul>
             <p><a href='setup_database.php'>Intentar configuración automática</a></p>");
    } else {
        die("<h2>Error de conexión local</h2>
             <p>No se pudo conectar a MySQL local.</p>
             <p>Asegúrate de que:</p>
             <ul>
                <li>MySQL esté instalado y corriendo (XAMPP/MAMP)</li>
                <li>La base de datos '$database' exista</li>
                <li>Las credenciales sean correctas</li>
             </ul>
             <p><a href='setup_database.php'>Crear base de datos automáticamente</a></p>");
    }
}

// =============================================================================
// CONFIGURACIÓN GENERAL DEL SITIO
// =============================================================================

// Configuración de WhatsApp
$whatsapp_config = [
    'api_url' => 'https://api.whatsapp.com/send',
    'message_prefix' => 'MediRecord:',
    'enable_whatsapp' => true,
    'timezone' => 'America/Mexico_City',
    'recordatorio_minutos_antes' => 15,
    'max_intentos' => 3,
    'pais_codigo' => '52' // México
];

// Configuración del sitio
$railway_url = getenv('RAILWAY_PUBLIC_DOMAIN') ?: 'localhost';
$site_config = [
    'name' => 'MediRecord',
    'version' => '2.0',
    'description' => 'Sistema de recordatorio de medicamentos para adultos mayores',
    'admin_email' => 'admin@medirecord.com',
    'url' => $isRailway ? "https://$railway_url" : 'http://localhost',
    'environment' => $isRailway ? 'production' : 'development',
    'debug' => !$isRailway
];

// =============================================================================
// CONFIGURACIÓN DE SEGURIDAD Y SERVIDOR
// =============================================================================

// Headers para seguridad básica
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Configuración de zona horaria
date_default_timezone_set($whatsapp_config['timezone']);

// Manejo de errores
if ($site_config['debug']) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// =============================================================================
// FUNCIONES DE AUTENTICACIÓN Y USUARIO
// =============================================================================

/**
 * Verificar si el usuario está logueado
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Redirigir a login si no está autenticado
 */
function requireAuth() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit();
    }
}

/**
 * Redirigir si ya está logueado
 */
function redirectIfLoggedIn($url = 'dashboard.php') {
    if (isLoggedIn()) {
        header("Location: $url");
        exit();
    }
}

/**
 * Obtener información del usuario actual
 */
function getCurrentUser() {
    global $pdo;
    if (isLoggedIn()) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['user_type'] = $user['tipo'];
            $_SESSION['user_name'] = $user['nombre'];
        }
        
        return $user;
    }
    return null;
}

/**
 * Verificar si es paciente
 */
function isPaciente() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'paciente';
}

/**
 * Verificar si es cuidador
 */
function isCuidador() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'cuidador';
}

/**
 * Obtener tipo de usuario actual
 */
function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

// =============================================================================
// FUNCIONES DE SEGURIDAD Y UTILIDAD
// =============================================================================

/**
 * Hashear contraseñas
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verificar contraseña hasheada
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Redirigir con mensaje flash
 */
function redirectWithMessage($url, $type, $message) {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit();
}

/**
 * Mostrar mensajes flash
 */
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = htmlspecialchars($_SESSION['flash_message']);
        $type = $_SESSION['flash_type'] ?? 'info';
        
        $alert_class = '';
        $icon = '';
        
        switch ($type) {
            case 'success':
                $alert_class = 'alert-success';
                $icon = '✅ ';
                break;
            case 'error':
                $alert_class = 'alert-danger';
                $icon = '❌ ';
                break;
            case 'warning':
                $alert_class = 'alert-warning';
                $icon = '⚠️ ';
                break;
            default:
                $alert_class = 'alert-info';
                $icon = 'ℹ️ ';
        }
        
        echo "<div class='alert $alert_class alert-dismissible fade show' role='alert'>
                <strong>$icon</strong> $message
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
        
        // Limpiar el mensaje después de mostrarlo
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
}

/**
 * Validar email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Sanitizar entrada de usuario
 */
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Validar teléfono
 */
function isValidPhone($phone) {
    // Eliminar todo excepto números y +
    $clean_phone = preg_replace('/[^0-9+]/', '', $phone);
    
    // Validar formato básico de teléfono
    if (preg_match('/^\+?[0-9]{10,15}$/', $clean_phone)) {
        return $clean_phone;
    }
    
    return false;
}

/**
 * Generar token aleatorio
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// =============================================================================
// FUNCIONES DE MEDICAMENTOS Y HORARIOS
// =============================================================================

/**
 * Obtener medicamentos según el tipo de usuario
 */
function getUserMedications($user_id, $user_type = null) {
    global $pdo;
    
    if ($user_type === null) {
        $user_type = getUserType();
    }
    
    if ($user_type === 'paciente') {
        // Paciente ve solo sus medicamentos
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   GROUP_CONCAT(DISTINCT h.hora ORDER BY h.hora SEPARATOR ', ') as horas,
                   GROUP_CONCAT(DISTINCT h.id_horario ORDER BY h.hora SEPARATOR ',') as horarios_ids,
                   COUNT(DISTINCT h.id_horario) as total_horarios,
                   u.nombre as agregado_por_nombre 
            FROM medicamentos m 
            LEFT JOIN horarios h ON m.id_medicamento = h.id_medicamento AND h.activo = 1
            LEFT JOIN usuarios u ON m.agregado_por = u.id_usuario
            WHERE m.id_usuario = ? 
            GROUP BY m.id_medicamento
            ORDER BY m.nombre_medicamento
        ");
        $stmt->execute([$user_id]);
    } else {
        // Cuidador ve medicamentos de todos sus pacientes
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   GROUP_CONCAT(DISTINCT h.hora ORDER BY h.hora SEPARATOR ', ') as horas,
                   GROUP_CONCAT(DISTINCT h.id_horario ORDER BY h.hora SEPARATOR ',') as horarios_ids,
                   COUNT(DISTINCT h.id_horario) as total_horarios,
                   p.nombre as paciente_nombre, 
                   p.id_usuario as paciente_id,
                   u.nombre as agregado_por_nombre
            FROM medicamentos m
            JOIN usuarios p ON m.id_usuario = p.id_usuario
            LEFT JOIN usuarios u ON m.agregado_por = u.id_usuario
            LEFT JOIN horarios h ON m.id_medicamento = h.id_medicamento AND h.activo = 1
            JOIN vinculaciones v ON m.id_usuario = v.id_paciente AND v.id_cuidador = ?
            WHERE v.confirmado = 1
            GROUP BY m.id_medicamento
            ORDER BY p.nombre, m.nombre_medicamento
        ");
        $stmt->execute([$user_id]);
    }
    
    return $stmt->fetchAll();
}

/**
 * Obtener medicamento por ID
 */
function obtenerMedicamentoPorId($medicamento_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT m.*, u.nombre as paciente_nombre, u.tipo as paciente_tipo
        FROM medicamentos m
        JOIN usuarios u ON m.id_usuario = u.id_usuario
        WHERE m.id_medicamento = ?
    ");
    $stmt->execute([$medicamento_id]);
    return $stmt->fetch();
}

/**
 * Obtener horarios de un medicamento
 */
function obtenerHorariosMedicamento($medicamento_id) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT * FROM horarios 
        WHERE id_medicamento = ? 
        ORDER BY hora
    ");
    $stmt->execute([$medicamento_id]);
    return $stmt->fetchAll();
}

/**
 * Obtener la próxima medicación del usuario
 */
function getNextMedication($user_id, $user_type = null) {
    global $pdo;
    
    if ($user_type === null) {
        $user_type = getUserType();
    }
    
    $current_time = date('H:i');
    $today = date('Y-m-d');
    
    if ($user_type === 'paciente') {
        $stmt = $pdo->prepare("
            SELECT m.id_medicamento, m.nombre_medicamento, m.dosis, h.hora, h.id_horario
            FROM medicamentos m 
            JOIN horarios h ON m.id_medicamento = h.id_medicamento 
            WHERE m.id_usuario = ? 
            AND h.hora >= ? 
            AND h.activo = 1
            AND NOT EXISTS (
                SELECT 1 FROM historial_tomas ht 
                WHERE ht.id_horario = h.id_horario 
                AND DATE(ht.fecha_hora_toma) = ?
                AND ht.estado = 'tomado'
            )
            ORDER BY h.hora ASC 
            LIMIT 1
        ");
        $stmt->execute([$user_id, $current_time, $today]);
    } else {
        // Para cuidadores
        $stmt = $pdo->prepare("
            SELECT m.id_medicamento, m.nombre_medicamento, m.dosis, h.hora, h.id_horario, 
                   p.nombre as paciente_nombre, p.id_usuario as paciente_id
            FROM medicamentos m 
            JOIN horarios h ON m.id_medicamento = h.id_medicamento 
            JOIN usuarios p ON m.id_usuario = p.id_usuario
            JOIN vinculaciones v ON m.id_usuario = v.id_paciente AND v.id_cuidador = ?
            WHERE h.hora >= ? 
            AND h.activo = 1 
            AND v.confirmado = 1
            AND NOT EXISTS (
                SELECT 1 FROM historial_tomas ht 
                WHERE ht.id_horario = h.id_horario 
                AND DATE(ht.fecha_hora_toma) = ?
                AND ht.estado = 'tomado'
            )
            ORDER BY h.hora ASC 
            LIMIT 1
        ");
        $stmt->execute([$user_id, $current_time, $today]);
    }
    
    return $stmt->fetch();
}

/**
 * Registrar una toma en el historial
 */
function recordMedicationTaken($horario_id, $estado = 'tomado', $user_id = null) {
    global $pdo;
    
    if ($user_id === null) {
        $user_id = $_SESSION['user_id'] ?? null;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Registrar la toma
        $stmt = $pdo->prepare("
            INSERT INTO historial_tomas (id_horario, estado) 
            VALUES (?, ?)
        ");
        $stmt->execute([$horario_id, $estado]);
        $registro_id = $pdo->lastInsertId();
        
        // Actualizar último recordatorio en horario
        $stmt = $pdo->prepare("
            UPDATE horarios 
            SET ultima_alerta = NOW() 
            WHERE id_horario = ?
        ");
        $stmt->execute([$horario_id]);
        
        // Si fue tomado, obtener info para notificar al cuidador
        if ($estado === 'tomado' && $user_id) {
            $stmt = $pdo->prepare("
                SELECT m.nombre_medicamento, m.dosis, u.nombre as paciente_nombre,
                       u.id_usuario as paciente_id, m.id_usuario
                FROM historial_tomas ht
                JOIN horarios h ON ht.id_horario = h.id_horario
                JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
                JOIN usuarios u ON m.id_usuario = u.id_usuario
                WHERE ht.id_registro = ?
            ");
            $stmt->execute([$registro_id]);
            $medicamento_info = $stmt->fetch();
            
            // Notificar a cuidadores vinculados
            if ($medicamento_info) {
                notificarConfirmacionCuidador($medicamento_info['id_usuario'], $medicamento_info);
            }
        }
        
        $pdo->commit();
        return $registro_id;
        
    } catch (PDOException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error registrando toma: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtener estadísticas del usuario
 */
function getUserStats($user_id, $user_type = null) {
    global $pdo;
    
    if ($user_type === null) {
        $user_type = getUserType();
    }
    
    $stats = [
        'total_medicamentos' => 0,
        'total_tomas' => 0,
        'tomas_hoy' => 0,
        'tomas_pendientes' => 0,
        'tomas_omitidas' => 0
    ];
    
    $today = date('Y-m-d');
    
    if ($user_type === 'paciente') {
        // Total de medicamentos
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT m.id_medicamento) as total 
            FROM medicamentos m 
            WHERE m.id_usuario = ?
        ");
        $stmt->execute([$user_id]);
        $stats['total_medicamentos'] = $stmt->fetchColumn();
        
        // Total de tomas registradas
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM historial_tomas ht
            JOIN horarios h ON ht.id_horario = h.id_horario
            JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
            WHERE m.id_usuario = ? AND ht.estado = 'tomado'
        ");
        $stmt->execute([$user_id]);
        $stats['total_tomas'] = $stmt->fetchColumn();
        
        // Tomas de hoy
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM historial_tomas ht
            JOIN horarios h ON ht.id_horario = h.id_horario
            JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
            WHERE m.id_usuario = ? 
            AND ht.estado = 'tomado' 
            AND DATE(ht.fecha_hora_toma) = ?
        ");
        $stmt->execute([$user_id, $today]);
        $stats['tomas_hoy'] = $stmt->fetchColumn();
        
        // Tomas omitidas hoy
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM historial_tomas ht
            JOIN horarios h ON ht.id_horario = h.id_horario
            JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
            WHERE m.id_usuario = ? 
            AND ht.estado = 'omitido' 
            AND DATE(ht.fecha_hora_toma) = ?
        ");
        $stmt->execute([$user_id, $today]);
        $stats['tomas_omitidas'] = $stmt->fetchColumn();
        
        // Tomas pendientes hoy (horarios activos sin registro de toma hoy)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM horarios h
            JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
            WHERE m.id_usuario = ? 
            AND h.activo = 1
            AND NOT EXISTS (
                SELECT 1 FROM historial_tomas ht 
                WHERE ht.id_horario = h.id_horario 
                AND DATE(ht.fecha_hora_toma) = ?
            )
        ");
        $stmt->execute([$user_id, $today]);
        $stats['tomas_pendientes'] = $stmt->fetchColumn();
        
    } else {
        // Estadísticas para cuidadores
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT m.id_medicamento) as total_medicamentos
            FROM medicamentos m
            JOIN vinculaciones v ON m.id_usuario = v.id_paciente AND v.id_cuidador = ?
            WHERE v.confirmado = 1
        ");
        $stmt->execute([$user_id]);
        $stats['total_medicamentos'] = $stmt->fetchColumn();
        
        // Total de tomas registradas
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM historial_tomas ht
            JOIN horarios h ON ht.id_horario = h.id_horario
            JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
            JOIN vinculaciones v ON m.id_usuario = v.id_paciente AND v.id_cuidador = ?
            WHERE ht.estado = 'tomado' AND v.confirmado = 1
        ");
        $stmt->execute([$user_id]);
        $stats['total_tomas'] = $stmt->fetchColumn();
        
        // Tomas de hoy
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM historial_tomas ht
            JOIN horarios h ON ht.id_horario = h.id_horario
            JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
            JOIN vinculaciones v ON m.id_usuario = v.id_paciente AND v.id_cuidador = ?
            WHERE ht.estado = 'tomado' 
            AND DATE(ht.fecha_hora_toma) = ? 
            AND v.confirmado = 1
        ");
        $stmt->execute([$user_id, $today]);
        $stats['tomas_hoy'] = $stmt->fetchColumn();
    }
    
    return $stats;
}

/**
 * Obtener historial reciente de tomas
 */
function getRecentMedicationHistory($user_id, $limit = 10, $user_type = null) {
    global $pdo;
    
    if ($user_type === null) {
        $user_type = getUserType();
    }
    
    if ($user_type === 'paciente') {
        $stmt = $pdo->prepare("
            SELECT ht.*, m.nombre_medicamento, m.dosis, h.hora,
                   DATE_FORMAT(ht.fecha_hora_toma, '%d/%m/%Y %H:%i') as fecha_formateada
            FROM historial_tomas ht
            JOIN horarios h ON ht.id_horario = h.id_horario
            JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
            WHERE m.id_usuario = ?
            ORDER BY ht.fecha_hora_toma DESC
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
    } else {
        $stmt = $pdo->prepare("
            SELECT ht.*, m.nombre_medicamento, m.dosis, h.hora,
                   u.nombre as paciente_nombre,
                   DATE_FORMAT(ht.fecha_hora_toma, '%d/%m/%Y %H:%i') as fecha_formateada
            FROM historial_tomas ht
            JOIN horarios h ON ht.id_horario = h.id_horario
            JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
            JOIN usuarios u ON m.id_usuario = u.id_usuario
            JOIN vinculaciones v ON m.id_usuario = v.id_paciente AND v.id_cuidador = ?
            WHERE v.confirmado = 1
            ORDER BY ht.fecha_hora_toma DESC
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
    }
    
    return $stmt->fetchAll();
}

// =============================================================================
// FUNCIONES DE VINCULACIÓN Y PERMISOS
// =============================================================================

/**
 * Obtener pacientes vinculados (para cuidadores)
 */
function getPacientesVinculados($cuidador_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT u.id_usuario, u.nombre, u.email, u.telefono, v.confirmado, v.fecha_vinculacion
        FROM vinculaciones v
        JOIN usuarios u ON v.id_paciente = u.id_usuario
        WHERE v.id_cuidador = ? 
        ORDER BY u.nombre
    ");
    $stmt->execute([$cuidador_id]);
    return $stmt->fetchAll();
}

/**
 * Obtener cuidadores vinculados (para pacientes)
 */
function getCuidadoresVinculados($paciente_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT u.id_usuario, u.nombre, u.email, u.telefono, v.confirmado, v.fecha_vinculacion
        FROM vinculaciones v
        JOIN usuarios u ON v.id_cuidador = u.id_usuario
        WHERE v.id_paciente = ? 
        ORDER BY u.nombre
    ");
    $stmt->execute([$paciente_id]);
    return $stmt->fetchAll();
}

/**
 * Verificar si un cuidador tiene acceso a un paciente
 */
function cuidadorTieneAccesoPaciente($cuidador_id, $paciente_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT id_vinculacion 
        FROM vinculaciones 
        WHERE id_cuidador = ? 
        AND id_paciente = ? 
        AND confirmado = 1
    ");
    $stmt->execute([$cuidador_id, $paciente_id]);
    return $stmt->fetch() !== false;
}

/**
 * Verificar permisos sobre un medicamento
 */
function verificarPermisoMedicamento($user_id, $medicamento_id) {
    global $pdo;
    $user_type = getUserType();

    if ($user_type === 'paciente') {
        $stmt = $pdo->prepare("
            SELECT id_medicamento 
            FROM medicamentos 
            WHERE id_medicamento = ? 
            AND id_usuario = ?
        ");
        $stmt->execute([$medicamento_id, $user_id]);
        return $stmt->fetch() !== false;
    } else {
        $stmt = $pdo->prepare("
            SELECT m.id_medicamento 
            FROM medicamentos m
            JOIN vinculaciones v ON m.id_usuario = v.id_paciente
            WHERE m.id_medicamento = ? 
            AND v.id_cuidador = ? 
            AND v.confirmado = 1
        ");
        $stmt->execute([$medicamento_id, $user_id]);
        return $stmt->fetch() !== false;
    }
}

/**
 * Crear nueva vinculación
 */
function crearVinculacion($paciente_id, $cuidador_id) {
    global $pdo;
    
    try {
        // Verificar si ya existe la vinculación
        $stmt = $pdo->prepare("
            SELECT id_vinculacion 
            FROM vinculaciones 
            WHERE id_paciente = ? AND id_cuidador = ?
        ");
        $stmt->execute([$paciente_id, $cuidador_id]);
        
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'La vinculación ya existe'];
        }
        
        // Crear nueva vinculación
        $stmt = $pdo->prepare("
            INSERT INTO vinculaciones (id_paciente, id_cuidador, confirmado) 
            VALUES (?, ?, 0)
        ");
        $stmt->execute([$paciente_id, $cuidador_id]);
        
        return ['success' => true, 'id' => $pdo->lastInsertId()];
        
    } catch (PDOException $e) {
        error_log("Error creando vinculación: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error en la base de datos'];
    }
}

// =============================================================================
// FUNCIONES DE WHATSAPP
// =============================================================================

/**
 * Enviar mensajes de WhatsApp
 */
function enviarWhatsApp($telefono, $mensaje, $tipo = 'recordatorio', $id_horario = null, $id_usuario = null) {
    global $pdo, $whatsapp_config;
    
    if (!$whatsapp_config['enable_whatsapp'] || empty($telefono)) {
        return ['success' => false, 'message' => 'WhatsApp deshabilitado o teléfono vacío'];
    }
    
    try {
        // Limpiar y formatear teléfono
        $telefono_limpio = isValidPhone($telefono);
        
        if (!$telefono_limpio) {
            return ['success' => false, 'message' => 'Número de teléfono inválido'];
        }
        
        // Agregar código de país si no tiene
        if (!str_starts_with($telefono_limpio, '+')) {
            $telefono_limpio = '+' . $whatsapp_config['pais_codigo'] . ltrim($telefono_limpio, '0');
        }
        
        // Preparar mensaje completo
        $mensaje_completo = $whatsapp_config['message_prefix'] . " " . $mensaje;
        
        // Generar token de confirmación
        $token_confirmacion = generateToken();
        
        // Guardar en la base de datos
        $stmt = $pdo->prepare("
            INSERT INTO recordatorios_whatsapp 
            (id_horario, id_usuario, mensaje, estado, token_confirmacion) 
            VALUES (?, ?, ?, 'enviado', ?)
        ");
        $stmt->execute([$id_horario, $id_usuario, $mensaje_completo, $token_confirmacion]);
        $log_id = $pdo->lastInsertId();
        
        // Crear URL de WhatsApp
        $mensaje_codificado = urlencode($mensaje_completo);
        $url_whatsapp = $whatsapp_config['api_url'] . "?phone=" . $telefono_limpio . "&text=" . $mensaje_codificado;
        
        // En Railway, podrías integrar con una API real de WhatsApp Business
        // Por ahora solo simulamos y guardamos el log
        
        error_log("📱 WhatsApp $tipo enviado a $telefono_limpio");
        error_log("📝 Mensaje: $mensaje_completo");
        error_log("🔗 URL: $url_whatsapp");
        
        return [
            'success' => true,
            'log_id' => $log_id,
            'url_whatsapp' => $url_whatsapp,
            'token' => $token_confirmacion,
            'telefono' => $telefono_limpio
        ];
        
    } catch (Exception $e) {
        error_log("❌ Error enviando WhatsApp: " . $e->getMessage());
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Enviar recordatorios automáticos
 */
function enviarRecordatoriosAutomaticos() {
    global $pdo, $whatsapp_config;
    
    $hora_actual = date('H:i');
    $hora_recordatorio = date('H:i', strtotime('+' . $whatsapp_config['recordatorio_minutos_antes'] . ' minutes'));
    $today = date('Y-m-d');
    
    // Buscar medicamentos programados para los próximos X minutos
    $stmt = $pdo->prepare("
        SELECT 
            m.id_medicamento,
            m.nombre_medicamento,
            m.dosis,
            m.instrucciones,
            h.hora,
            h.id_horario,
            u.nombre as paciente_nombre,
            u.telefono as paciente_telefono,
            u.id_usuario as paciente_id,
            c.nombre as cuidador_nombre,
            c.telefono as cuidador_telefono,
            c.id_usuario as cuidador_id
        FROM horarios h
        JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
        JOIN usuarios u ON m.id_usuario = u.id_usuario
        LEFT JOIN vinculaciones v ON u.id_usuario = v.id_paciente AND v.confirmado = 1
        LEFT JOIN usuarios c ON v.id_cuidador = c.id_usuario
        WHERE h.hora BETWEEN ? AND ?
        AND h.activo = 1
        AND NOT EXISTS (
            SELECT 1 FROM historial_tomas ht 
            WHERE ht.id_horario = h.id_horario 
            AND DATE(ht.fecha_hora_toma) = ?
            AND ht.estado IN ('tomado', 'omitido')
        )
        AND NOT EXISTS (
            SELECT 1 FROM recordatorios_whatsapp rw 
            WHERE rw.id_horario = h.id_horario 
            AND DATE(rw.fecha_envio) = CURDATE()
            AND rw.mensaje LIKE '%RECORDATORIO%'
            AND rw.estado = 'enviado'
        )
        ORDER BY h.hora
    ");
    $stmt->execute([$hora_actual, $hora_recordatorio, $today]);
    $recordatorios = $stmt->fetchAll();
    
    $enviados = 0;
    foreach ($recordatorios as $recordatorio) {
        // Enviar recordatorio al paciente
        if (!empty($recordatorio['paciente_telefono'])) {
            $mensaje_paciente = "RECORDATORIO: En " . $whatsapp_config['recordatorio_minutos_antes'] . 
                               " minutos debes tomar " . $recordatorio['nombre_medicamento'] . 
                               " - " . $recordatorio['dosis'] . 
                               " - Hora programada: " . $recordatorio['hora'];
            
            if (!empty($recordatorio['instrucciones'])) {
                $mensaje_paciente .= " - Instrucciones: " . $recordatorio['instrucciones'];
            }
            
            $result = enviarWhatsApp(
                $recordatorio['paciente_telefono'], 
                $mensaje_paciente, 
                'recordatorio',
                $recordatorio['id_horario'],
                $recordatorio['paciente_id']
            );
            
            if ($result['success']) {
                $enviados++;
                
                // Actualizar último recordatorio en horario
                $stmt = $pdo->prepare("
                    UPDATE horarios 
                    SET ultimo_recordatorio = NOW() 
                    WHERE id_horario = ?
                ");
                $stmt->execute([$recordatorio['id_horario']]);
            }
        }
        
        // Notificar al cuidador
        if (!empty($recordatorio['cuidador_telefono'])) {
            $mensaje_cuidador = "RECORDATORIO: " . $recordatorio['paciente_nombre'] . 
                               " debe tomar en " . $whatsapp_config['recordatorio_minutos_antes'] . 
                               " minutos: " . $recordatorio['nombre_medicamento'] . 
                               " a las " . $recordatorio['hora'];
            
            enviarWhatsApp(
                $recordatorio['cuidador_telefono'], 
                $mensaje_cuidador, 
                'alerta_cuidador',
                $recordatorio['id_horario'],
                $recordatorio['cuidador_id']
            );
        }
    }
    
    return ['enviados' => $enviados, 'total' => count($recordatorios)];
}

/**
 * Notificar confirmación al cuidador
 */
function notificarConfirmacionCuidador($paciente_id, $medicamento_info) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT c.nombre, c.telefono, c.id_usuario
        FROM vinculaciones v
        JOIN usuarios c ON v.id_cuidador = c.id_usuario
        WHERE v.id_paciente = ? AND v.confirmado = 1
    ");
    $stmt->execute([$paciente_id]);
    $cuidadores = $stmt->fetchAll();
    
    $notificados = 0;
    foreach ($cuidadores as $cuidador) {
        if (!empty($cuidador['telefono'])) {
            $mensaje = $medicamento_info['paciente_nombre'] . 
                      " ha confirmado la toma de " . 
                      $medicamento_info['nombre_medicamento'] . 
                      " - " . $medicamento_info['dosis'] . 
                      " a las " . date('H:i');
            
            $result = enviarWhatsApp(
                $cuidador['telefono'], 
                $mensaje, 
                'confirmacion',
                null,
                $cuidador['id_usuario']
            );
            
            if ($result['success']) {
                $notificados++;
            }
        }
    }
    
    return $notificados;
}

// =============================================================================
// FUNCIONES DE MANTENIMIENTO Y UTILIDAD
// =============================================================================

/**
 * Inicializar directorios de logs
 */
function inicializarDirectoriosLogs() {
    $directorios = ['logs', 'temp', 'uploads'];
    foreach ($directorios as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }
}

/**
 * Limpiar datos antiguos
 */
function limpiarDatosAntiguos($dias = 30) {
    global $pdo;
    
    $fecha_limite = date('Y-m-d H:i:s', strtotime("-$dias days"));
    
    try {
        // Limpiar logs de WhatsApp antiguos
        $stmt = $pdo->prepare("
            DELETE FROM recordatorios_whatsapp 
            WHERE fecha_envio < ? 
            AND estado != 'pendiente'
        ");
        $stmt->execute([$fecha_limite]);
        $whatsapp_eliminados = $stmt->rowCount();
        
        // Limpiar historial de tomas antiguas (mantener solo 90 días)
        $fecha_limite_historial = date('Y-m-d H:i:s', strtotime("-90 days"));
        $stmt = $pdo->prepare("
            DELETE FROM historial_tomas 
            WHERE fecha_hora_toma < ?
        ");
        $stmt->execute([$fecha_limite_historial]);
        $historial_eliminados = $stmt->rowCount();
        
        return [
            'success' => true,
            'whatsapp_eliminados' => $whatsapp_eliminados,
            'historial_eliminados' => $historial_eliminados
        ];
        
    } catch (Exception $e) {
        error_log("Error limpiando datos antiguos: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Verificar estado del sistema
 */
function verificarEstadoSistema() {
    global $pdo, $site_config;
    
    $estado = [
        'database' => false,
        'tablas' => [],
        'total_usuarios' => 0,
        'total_medicamentos' => 0,
        'environment' => $site_config['environment']
    ];
    
    try {
        // Verificar conexión a base de datos
        $pdo->query("SELECT 1");
        $estado['database'] = true;
        
        // Verificar tablas existentes
        $stmt = $pdo->query("SHOW TABLES");
        $estado['tablas'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Contar usuarios
        $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios");
        $estado['total_usuarios'] = $stmt->fetchColumn();
        
        // Contar medicamentos
        $stmt = $pdo->query("SELECT COUNT(*) FROM medicamentos");
        $estado['total_medicamentos'] = $stmt->fetchColumn();
        
    } catch (Exception $e) {
        $estado['error'] = $e->getMessage();
    }
    
    return $estado;
}

// =============================================================================
// INICIALIZACIÓN
// =============================================================================

// Inicializar directorios de logs
inicializarDirectoriosLogs();

// Verificar y actualizar sesión de usuario
if (isLoggedIn()) {
    getCurrentUser(); // Actualizar información de sesión
}

// Log de inicio (solo en Railway)
if ($isRailway) {
    error_log("🚀 MediRecord iniciado en Railway - Ambiente: " . $site_config['environment']);
    error_log("🌐 URL: " . $site_config['url']);
}
?>
