<?php
// config.php - MediRecord - Versión corregida para Railway
// Archivo de configuración principal del sistema - VERSIÓN SEGURA

// =============================================================================
// CONFIGURACIÓN DE BASE DE DATOS PARA RAILWAY - VERSIÓN SEGURA
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

// IMPORTANTE: Detectar si estamos en setup_database.php
$current_script = basename($_SERVER['PHP_SELF'] ?? '');
$is_setup_script = ($current_script == 'setup_database.php');

// Conexión a la base de datos - VERSIÓN MEJORADA
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
    // MANEJO DE ERRORES MEJORADO - NO MUERE SI ES SETUP SCRIPT
    $error_message = "Error de conexión a la base de datos";
    $error_details = "Host: $host, Port: $port, DB: $database, User: $username";
    
    error_log("❌ $error_message: " . $e->getMessage());
    error_log("📋 $error_details");
    
    if ($is_setup_script) {
        // Si estamos en setup_database.php, NO morir - permitir que continúe
        // Simplemente dejamos $pdo como null y setup_database.php lo manejará
        $pdo = null;
    } else {
        // Para otros scripts, mostrar error amigable
        if ($isRailway) {
            die("<h2>Error de configuración en Railway</h2>
                 <p>No se pudo conectar a la base de datos.</p>
                 <p>Por favor:</p>
                 <ol>
                    <li>Asegúrate de haber añadido un servicio MySQL a tu proyecto</li>
                    <li>Espera 1-2 minutos a que se inicialice la base de datos</li>
                    <li>Recarga esta página</li>
                 </ol>
                 <p>Si el problema persiste, verifica las variables de entorno en Railway.</p>");
        } else {
            die("<h2>Error de conexión local</h2>
                 <p>No se pudo conectar a MySQL local.</p>
                 <p>Asegúrate de que:</p>
                 <ol>
                    <li>MySQL esté instalado y corriendo (XAMPP/MAMP)</li>
                    <li>La base de datos '$database' exista</li>
                    <li>Las credenciales sean correctas (usuario: $username)</li>
                 </ol>");
        }
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
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   GROUP_CONCAT(DISTINCT h.hora ORDER BY h.hora SEPARATOR ', ') as horas,
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
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   GROUP_CONCAT(DISTINCT h.hora ORDER BY h.hora SEPARATOR ', ') as horas,
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
        SELECT m.*, u.nombre as paciente_nombre
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
        'tomas_pendientes' => 0
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

// =============================================================================
// FUNCIONES DE VINCULACIÓN Y PERMISOS
// =============================================================================

/**
 * Obtener pacientes vinculados (para cuidadores)
 */
function getPacientesVinculados($cuidador_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT u.id_usuario, u.nombre, u.email, u.telefono, v.confirmado
        FROM vinculaciones v
        JOIN usuarios u ON v.id_paciente = u.id_usuario
        WHERE v.id_cuidador = ? 
        ORDER BY u.nombre
    ");
    $stmt->execute([$cuidador_id]);
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

// =============================================================================
// INICIALIZACIÓN
// =============================================================================

// Verificar y actualizar sesión de usuario
if (isLoggedIn()) {
    getCurrentUser();
}

// Log de inicio (solo en Railway)
if ($isRailway) {
    error_log("🚀 MediRecord iniciado - Ambiente: " . $site_config['environment']);
}
?>
