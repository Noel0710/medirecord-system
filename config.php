<?php
// config.php - MediRecord - Configuración para Railway y Local

// =============================================================================
// CONFIGURACIÓN INICIAL
// =============================================================================

// Verificar si la sesión ya está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Detectar entorno
define('IS_RAILWAY', getenv('RAILWAY_ENVIRONMENT') !== false);
define('IS_LOCAL', !IS_RAILWAY);

// Configuración de errores
if (IS_LOCAL) {
    // Desarrollo: mostrar errores
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    // Producción: ocultar errores
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// Headers de seguridad
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Zona horaria
date_default_timezone_set('America/Mexico_City');

// =============================================================================
// CONFIGURACIÓN DE BASE DE DATOS
// =============================================================================

// Credenciales para Railway (MySQL)
if (IS_RAILWAY) {
    // Railway proporciona estas variables automáticamente
    $host = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
    $port = getenv('MYSQLPORT') ?: '3306';
    $dbname = getenv('MYSQLDATABASE') ?: 'railway';
    $username = getenv('MYSQLUSER') ?: 'root';
    $password = getenv('MYSQLPASSWORD') ?: '';
} else {
    // Configuración local de desarrollo
    $host = 'localhost';
    $port = '3306';
    $dbname = 'medirecord_db';
    $username = 'root';
    $password = '';
}

// Intentar conexión con PDO
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    
    // Verificar conexión exitosa
    if (IS_LOCAL) {
        error_log("✅ Conexión local exitosa a: $dbname");
    } else {
        error_log("✅ Conexión Railway exitosa");
    }
    
} catch (PDOException $e) {
    // Manejo de errores amigable
    if (IS_RAILWAY) {
        $error_message = "
        <div style='font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin: 50px auto; border: 1px solid #e0e0e0; border-radius: 8px;'>
            <h2 style='color: #d32f2f;'>🚨 Error de conexión a la base de datos</h2>
            <p>No se pudo conectar a la base de datos en Railway.</p>
            
            <h3>📋 Variables de entorno detectadas:</h3>
            <ul>
                <li>MYSQLHOST: " . (getenv('MYSQLHOST') ? '✅ ' . getenv('MYSQLHOST') : '❌ No configurado') . "</li>
                <li>MYSQLPORT: " . (getenv('MYSQLPORT') ? '✅ ' . getenv('MYSQLPORT') : '❌ No configurado') . "</li>
                <li>MYSQLDATABASE: " . (getenv('MYSQLDATABASE') ? '✅ ' . getenv('MYSQLDATABASE') : '❌ No configurado') . "</li>
                <li>MYSQLUSER: " . (getenv('MYSQLUSER') ? '✅ ' . getenv('MYSQLUSER') : '❌ No configurado') . "</li>
                <li>MYSQLPASSWORD: " . (getenv('MYSQLPASSWORD') ? '✅ ****' . substr(getenv('MYSQLPASSWORD'), -4) : '❌ No configurado') . "</li>
            </ul>
            
            <h3>🔧 Solución:</h3>
            <ol>
                <li>Ve a <strong>Railway Dashboard</strong> → tu proyecto → <strong>Variables</strong></li>
                <li>Verifica que las variables MYSQL_* estén configuradas</li>
                <li>Si faltan, agrega una base de datos MySQL desde <strong>New → Database → MySQL</strong></li>
                <li>Railway creará automáticamente las variables</li>
            </ol>
            
            <p style='background: #f5f5f5; padding: 15px; border-radius: 5px;'>
                <strong>Error técnico:</strong><br>
                <code>" . htmlspecialchars($e->getMessage()) . "</code>
            </p>
        </div>
        ";
        die($error_message);
    } else {
        die("
        <div style='font-family: Arial; padding: 20px;'>
            <h2>Error de conexión local</h2>
            <p>No se pudo conectar a MySQL local.</p>
            <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p><strong>DSN intentado:</strong> $dsn</p>
            <p>Verifica que XAMPP/WAMP esté corriendo y que la base de datos 'medirecord_db' exista.</p>
        </div>
        ");
    }
}

// =============================================================================
// CONFIGURACIÓN DE WHATSAPP
// =============================================================================
$whatsapp_config = [
    'api_url' => 'https://graph.facebook.com/v20.0/',
    'message_prefix' => 'MediRecord:',
    'enable_whatsapp' => true,
    'timezone' => 'America/Mexico_City',
    'recordatorio_minutos_antes' => 15,
    'max_intentos' => 3,
    'token' => getenv('WHATSAPP_TOKEN') ?: '',
    'phone_id' => getenv('WHATSAPP_PHONE_ID') ?: ''
];

// =============================================================================
// CONFIGURACIÓN DEL SITIO
// =============================================================================
$site_config = [
    'name' => 'MediRecord',
    'version' => '2.0',
    'description' => 'Sistema de recordatorio de medicamentos para adultos mayores',
    'admin_email' => 'admin@medirecord.com',
    'url' => IS_RAILWAY ? ('https://' . getenv('RAILWAY_STATIC_URL')) : 'http://localhost',
    'environment' => IS_RAILWAY ? 'production' : 'development'
];

// =============================================================================
// FUNCIONES DE AUTENTICACIÓN Y USUARIO
// =============================================================================

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
}

function getCurrentUser() {
    global $pdo;
    if (isLoggedIn()) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    return null;
}

function isPaciente() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'paciente';
}

function isCuidador() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'cuidador';
}

// =============================================================================
// FUNCIONES DE SEGURIDAD Y UTILIDAD
// =============================================================================

function redirectWithMessage($url, $type, $message) {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit();
}

function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        
        $alert_class = 'alert-' . $type;
        $icon = '';
        
        switch ($type) {
            case 'success': $icon = '✅'; break;
            case 'error': $icon = '❌'; break;
            case 'warning': $icon = '⚠️'; break;
            default: $icon = 'ℹ️';
        }
        
        echo "<div class='alert $alert_class' style='padding: 15px; margin: 20px 0; border-radius: 5px;'>
                $icon $message
              </div>";
        
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
}

function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// =============================================================================
// FUNCIONES DE MEDICAMENTOS Y HORARIOS
// =============================================================================

function getUserMedications($user_id, $user_type = null) {
    global $pdo;
    
    if ($user_type === null) {
        $user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'paciente';
    }
    
    if ($user_type === 'paciente') {
        $stmt = $pdo->prepare("
            SELECT m.*, h.hora, h.frecuencia, h.activo, h.id_horario,
                   u.nombre as agregado_por_nombre 
            FROM medicamentos m 
            LEFT JOIN horarios h ON m.id_medicamento = h.id_medicamento 
            LEFT JOIN usuarios u ON m.agregado_por = u.id_usuario
            WHERE m.id_usuario = ? 
            ORDER BY m.nombre_medicamento, h.hora
        ");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT m.*, h.hora, h.frecuencia, h.activo, h.id_horario,
                   p.nombre as paciente_nombre, 
                   u.nombre as agregado_por_nombre
            FROM medicamentos m
            JOIN usuarios p ON m.id_usuario = p.id_usuario
            LEFT JOIN usuarios u ON m.agregado_por = u.id_usuario
            LEFT JOIN horarios h ON m.id_medicamento = h.id_medicamento
            JOIN vinculaciones v ON m.id_usuario = v.id_paciente AND v.id_cuidador = ?
            WHERE v.confirmado = 1
            ORDER BY p.nombre, m.nombre_medicamento, h.hora
        ");
        $stmt->execute([$user_id]);
    }
    
    return $stmt->fetchAll();
}

function getNextMedication($user_id, $user_type = null) {
    global $pdo;
    
    if ($user_type === null) {
        $user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'paciente';
    }
    
    $current_time = date('H:i');
    
    if ($user_type === 'paciente') {
        $stmt = $pdo->prepare("
            SELECT m.nombre_medicamento, m.dosis, h.hora, h.id_horario
            FROM medicamentos m 
            JOIN horarios h ON m.id_medicamento = h.id_medicamento 
            WHERE m.id_usuario = ? AND h.hora >= ? AND h.activo = 1
            ORDER BY h.hora ASC 
            LIMIT 1
        ");
        $stmt->execute([$user_id, $current_time]);
    } else {
        $stmt = $pdo->prepare("
            SELECT m.nombre_medicamento, m.dosis, h.hora, h.id_horario, p.nombre as paciente_nombre
            FROM medicamentos m 
            JOIN horarios h ON m.id_medicamento = h.id_medicamento 
            JOIN usuarios p ON m.id_usuario = p.id_usuario
            JOIN vinculaciones v ON m.id_usuario = v.id_paciente AND v.id_cuidador = ?
            WHERE h.hora >= ? AND h.activo = 1 AND v.confirmado = 1
            ORDER BY h.hora ASC 
            LIMIT 1
        ");
        $stmt->execute([$user_id, $current_time]);
    }
    
    return $stmt->fetch();
}

function recordMedicationTaken($horario_id, $estado = 'tomado') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO historial_tomas (id_horario, estado) VALUES (?, ?)");
        $stmt->execute([$horario_id, $estado]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Error registrando toma: " . $e->getMessage());
        return false;
    }
}

function getUserStats($user_id, $user_type = null) {
    global $pdo;
    
    if ($user_type === null) {
        $user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'paciente';
    }
    
    $stats = [
        'total_medicamentos' => 0,
        'total_tomas' => 0,
        'tomas_hoy' => 0,
        'tomas_pendientes' => 0
    ];
    
    if ($user_type === 'paciente') {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM medicamentos WHERE id_usuario = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        $stats['total_medicamentos'] = $result['total'];
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM historial_tomas ht
            JOIN horarios h ON ht.id_horario = h.id_horario
            JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
            WHERE m.id_usuario = ? AND ht.estado = 'tomado'
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        $stats['total_tomas'] = $result['total'];
        
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM historial_tomas ht
            JOIN horarios h ON ht.id_horario = h.id_horario
            JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
            WHERE m.id_usuario = ? AND ht.estado = 'tomado' AND DATE(ht.fecha_hora_toma) = ?
        ");
        $stmt->execute([$user_id, $today]);
        $result = $stmt->fetch();
        $stats['tomas_hoy'] = $result['total'];
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT m.id_medicamento) as total_medicamentos
            FROM medicamentos m
            JOIN vinculaciones v ON m.id_usuario = v.id_paciente AND v.id_cuidador = ?
            WHERE v.confirmado = 1
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        $stats['total_medicamentos'] = $result['total_medicamentos'];
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM historial_tomas ht
            JOIN horarios h ON ht.id_horario = h.id_horario
            JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
            JOIN vinculaciones v ON m.id_usuario = v.id_paciente AND v.id_cuidador = ?
            WHERE ht.estado = 'tomado' AND v.confirmado = 1
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        $stats['total_tomas'] = $result['total'];
        
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM historial_tomas ht
            JOIN horarios h ON ht.id_horario = h.id_horario
            JOIN medicamentos m ON h.id_medicamento = m.id_medicamento
            JOIN vinculaciones v ON m.id_usuario = v.id_paciente AND v.id_cuidador = ?
            WHERE ht.estado = 'tomado' AND DATE(ht.fecha_hora_toma) = ? AND v.confirmado = 1
        ");
        $stmt->execute([$user_id, $today]);
        $result = $stmt->fetch();
        $stats['tomas_hoy'] = $result['total'];
    }
    
    return $stats;
}

// =============================================================================
// FUNCIONES DE VINCULACIÓN Y PERMISOS
// =============================================================================

function getPacientesVinculados($cuidador_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT u.id_usuario, u.nombre, u.email, v.confirmado
        FROM vinculaciones v
        JOIN usuarios u ON v.id_paciente = u.id_usuario
        WHERE v.id_cuidador = ? AND v.confirmado = 1
        ORDER BY u.nombre
    ");
    $stmt->execute([$cuidador_id]);
    return $stmt->fetchAll();
}

function cuidadorTieneAccesoPaciente($cuidador_id, $paciente_id) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT id_vinculacion 
        FROM vinculaciones 
        WHERE id_cuidador = ? AND id_paciente = ? AND confirmado = 1
    ");
    $stmt->execute([$cuidador_id, $paciente_id]);
    return $stmt->fetch() !== false;
}

function verificarPermisoMedicamento($user_id, $medicamento_id) {
    global $pdo;
    $user_type = $_SESSION['user_type'];

    if ($user_type === 'paciente') {
        $stmt = $pdo->prepare("SELECT id_medicamento FROM medicamentos WHERE id_medicamento = ? AND id_usuario = ?");
        $stmt->execute([$medicamento_id, $user_id]);
        return $stmt->fetch() !== false;
    } else {
        $stmt = $pdo->prepare("
            SELECT m.id_medicamento 
            FROM medicamentos m
            JOIN vinculaciones v ON m.id_usuario = v.id_paciente
            WHERE m.id_medicamento = ? AND v.id_cuidador = ? AND v.confirmado = 1
        ");
        $stmt->execute([$medicamento_id, $user_id]);
        return $stmt->fetch() !== false;
    }
}

function obtenerMedicamentoPorId($medicamento_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM medicamentos WHERE id_medicamento = ?");
    $stmt->execute([$medicamento_id]);
    return $stmt->fetch();
}

function obtenerHorariosMedicamento($medicamento_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM horarios WHERE id_medicamento = ? ORDER BY hora");
    $stmt->execute([$medicamento_id]);
    return $stmt->fetchAll();
}

// =============================================================================
// FUNCIONES DE WHATSAPP
// =============================================================================

function enviarWhatsApp($telefono, $mensaje, $tipo = 'recordatorio', $id_horario = null, $id_usuario = null) {
    global $pdo, $whatsapp_config;
    
    if (!$whatsapp_config['enable_whatsapp'] || empty($telefono)) {
        return false;
    }
    
    try {
        $telefono_limpio = preg_replace('/[^0-9]/', '', $telefono);
        
        if (!preg_match('/^\+/', $telefono_limpio) && strlen($telefono_limpio) == 10) {
            $telefono_limpio = '52' . $telefono_limpio;
        }
        
        $mensaje_completo = $whatsapp_config['message_prefix'] . " " . $mensaje;
        $token_confirmacion = bin2hex(random_bytes(16));
        
        $stmt = $pdo->prepare("
            INSERT INTO recordatorios_whatsapp 
            (id_horario, id_usuario, mensaje, estado, token_confirmacion) 
            VALUES (?, ?, ?, 'enviado', ?)
        ");
        $stmt->execute([$id_horario, $id_usuario, $mensaje_completo, $token_confirmacion]);
        $log_id = $pdo->lastInsertId();
        
        // Simulación de envío (en producción usarías la API real)
        error_log("WhatsApp $tipo enviado a $telefono_limpio: $mensaje_completo");
        
        return [
            'success' => true,
            'log_id' => $log_id,
            'token' => $token_confirmacion
        ];
        
    } catch (Exception $e) {
        error_log("Error enviando WhatsApp: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

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
    
    foreach ($cuidadores as $cuidador) {
        if (!empty($cuidador['telefono'])) {
            $mensaje = $medicamento_info['paciente_nombre'] . " ha confirmado la toma de " . 
                      $medicamento_info['nombre_medicamento'] . " - " . 
                      $medicamento_info['dosis'] . " a las " . date('H:i');
            
            enviarWhatsApp(
                $cuidador['telefono'], 
                $mensaje, 
                'confirmacion',
                null,
                $cuidador['id_usuario']
            );
        }
    }
}

// =============================================================================
// FUNCIONES DE INICIALIZACIÓN
// =============================================================================

function inicializarDirectorios() {
    $directorios = ['logs', 'temp', 'uploads'];
    foreach ($directorios as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }
}

// Inicializar directorios al cargar
inicializarDirectorios();

// =============================================================================
// VERIFICACIÓN DE ESTRUCTURA DE BASE DE DATOS (solo en local)
// =============================================================================

if (IS_LOCAL && basename($_SERVER['PHP_SELF']) === 'index.php') {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'usuarios'");
        if ($stmt->rowCount() === 0) {
            error_log("⚠️ La base de datos no tiene tablas. Ejecuta setup_database.php");
        }
    } catch (Exception $e) {
        // Ignorar error en producción
    }
}

// =============================================================================
// MANEJO DE CORS PARA WEBHOOKS
// =============================================================================

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

?>
