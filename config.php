<?php
// config.php - Sistema MediRecord - Configuración Completa CORREGIDA
// Verificar si la sesión ya está iniciada antes de llamar session_start()
// config.php para Railway

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuración de la base de datos desde variables de entorno
$host = getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'medirecord_db';
$username = getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root';
$password = getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '';
$port = getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: '3306';

// Conexión a la base de datos con puerto
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Para Railway, podemos mostrar un error más amigable
    if (getenv('RAILWAY_ENVIRONMENT')) {
        die("Error de conexión a la base de datos. Verifica las variables de entorno.");
    } else {
        die("Error de conexión a la base de datos: " . $e->getMessage());
    }
}

// =============================================================================
// FUNCIONES DE AUTENTICACIÓN Y USUARIO
// =============================================================================

// Función para verificar si el usuario está logueado
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirigir a login si no está autenticado
function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
}

// Función para obtener información del usuario actual
function getCurrentUser() {
    global $pdo;
    if (isLoggedIn()) {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id_usuario = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    return null;
}

// Función para verificar permisos (si es paciente o cuidador)
function isPaciente() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'paciente';
}

function isCuidador() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'cuidador';
}

// =============================================================================
// FUNCIONES DE SEGURIDAD Y UTILIDAD
// =============================================================================

// Función para hashear contraseñas
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Función para verificar contraseña hasheada
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Función para redirigir con mensaje flash
function redirectWithMessage($url, $type, $message) {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type; // 'success', 'error', 'warning'
    header("Location: $url");
    exit();
}

// Función para mostrar mensajes flash
function displayFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        
        $alert_class = '';
        switch ($type) {
            case 'success': $alert_class = 'alert-success'; break;
            case 'error': $alert_class = 'alert-danger'; break;
            case 'warning': $alert_class = 'alert-warning'; break;
            default: $alert_class = 'alert-info';
        }
        
        echo "<div class='alert $alert_class alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
        
        // Limpiar el mensaje después de mostrarlo
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
    }
}

// Función para validar email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Función para sanitizar entrada de usuario
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// =============================================================================
// FUNCIONES DE MEDICAMENTOS Y HORARIOS
// =============================================================================

// FUNCIÓN UNIFICADA: Obtener medicamentos según el tipo de usuario
function getUserMedications($user_id, $user_type = null) {
    global $pdo;
    
    // Si no se especifica el tipo de usuario, usar el de la sesión
    if ($user_type === null) {
        $user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : 'paciente';
    }
    
    if ($user_type === 'paciente') {
        // Paciente ve solo sus medicamentos
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
        // Cuidador ve medicamentos de todos sus pacientes
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

// Función para obtener la próxima medicación del usuario
function getNextMedication($user_id, $user_type = null) {
    global $pdo;
    
    // Si no se especifica el tipo de usuario, usar el de la sesión
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
        // Para cuidadores, obtener la próxima medicación de cualquiera de sus pacientes
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

// Función para registrar una toma en el historial - CORREGIDA
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

// Función para obtener estadísticas del usuario - CORREGIDA
function getUserStats($user_id, $user_type = null) {
    global $pdo;
    
    // Si no se especifica el tipo de usuario, usar el de la sesión
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
        // Total de medicamentos
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM medicamentos WHERE id_usuario = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        $stats['total_medicamentos'] = $result['total'];
        
        // Total de tomas registradas - CORREGIDO: historial_tomas
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
        
        // Tomas de hoy - CORREGIDO: historial_tomas
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
        // Estadísticas para cuidadores (de todos sus pacientes)
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT m.id_medicamento) as total_medicamentos
            FROM medicamentos m
            JOIN vinculaciones v ON m.id_usuario = v.id_paciente AND v.id_cuidador = ?
            WHERE v.confirmado = 1
        ");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        $stats['total_medicamentos'] = $result['total_medicamentos'];
        
        // Total de tomas registradas - CORREGIDO: historial_tomas
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
        
        // Tomas de hoy - CORREGIDO: historial_tomas
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

// Función para obtener pacientes vinculados (para cuidadores)
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

// Función para verificar si un cuidador tiene acceso a un paciente
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

// Función para verificar permisos sobre un medicamento
function verificarPermisoMedicamento($user_id, $medicamento_id) {
    global $pdo;
    $user_type = $_SESSION['user_type'];

    if ($user_type === 'paciente') {
        // Paciente solo puede editar sus propios medicamentos
        $stmt = $pdo->prepare("SELECT id_medicamento FROM medicamentos WHERE id_medicamento = ? AND id_usuario = ?");
        $stmt->execute([$medicamento_id, $user_id]);
        return $stmt->fetch() !== false;
    } else {
        // Cuidador puede editar medicamentos de sus pacientes
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

// Función para obtener un medicamento por ID
function obtenerMedicamentoPorId($medicamento_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM medicamentos WHERE id_medicamento = ?");
    $stmt->execute([$medicamento_id]);
    return $stmt->fetch();
}

// Función para obtener horarios de un medicamento
function obtenerHorariosMedicamento($medicamento_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM horarios WHERE id_medicamento = ? ORDER BY hora");
    $stmt->execute([$medicamento_id]);
    return $stmt->fetchAll();
}

// =============================================================================
// FUNCIONES DE WHATSAPP
// =============================================================================

// Función para enviar mensajes de WhatsApp
function enviarWhatsApp($telefono, $mensaje, $tipo = 'recordatorio', $id_horario = null, $id_usuario = null) {
    global $pdo, $whatsapp_config;
    
    if (!$whatsapp_config['enable_whatsapp'] || empty($telefono)) {
        return false;
    }
    
    try {
        // Limpiar y formatear teléfono
        $telefono_limpio = preg_replace('/[^0-9]/', '', $telefono);
        
        // Si no tiene código de país, agregar +52 para México
        if (!preg_match('/^\+/', $telefono_limpio) && strlen($telefono_limpio) == 10) {
            $telefono_limpio = '52' . $telefono_limpio;
        }
        
        // Preparar mensaje
        $mensaje_completo = $whatsapp_config['message_prefix'] . " " . $mensaje;
        
        // Generar token de confirmación único
        $token_confirmacion = bin2hex(random_bytes(16));
        
        // Guardar en la tabla recordatorios_whatsapp
        $stmt = $pdo->prepare("
            INSERT INTO recordatorios_whatsapp 
            (id_horario, id_usuario, mensaje, estado, token_confirmacion) 
            VALUES (?, ?, ?, 'enviado', ?)
        ");
        $stmt->execute([$id_horario, $id_usuario, $mensaje_completo, $token_confirmacion]);
        $log_id = $pdo->lastInsertId();
        
        // Crear URL de WhatsApp para redirección (si se usa en navegador)
        $mensaje_codificado = urlencode($mensaje_completo);
        $url_whatsapp = $whatsapp_config['api_url'] . "?phone=" . $telefono_limpio . "&text=" . $mensaje_codificado;
        
        // En entorno de producción, aquí integrarías con la API de WhatsApp Business
        // Por ahora simulamos el envío exitoso y guardamos el log
        
        error_log("WhatsApp $tipo enviado a $telefono_limpio: $mensaje_completo");
        
        return [
            'success' => true,
            'log_id' => $log_id,
            'url_whatsapp' => $url_whatsapp,
            'token' => $token_confirmacion
        ];
        
    } catch (Exception $e) {
        error_log("Error enviando WhatsApp: " . $e->getMessage());
        
        // Intentar guardar el error en el log
        try {
            $stmt = $pdo->prepare("
                INSERT INTO recordatorios_whatsapp 
                (id_horario, id_usuario, mensaje, estado, token_confirmacion) 
                VALUES (?, ?, ?, 'fallido', ?)
            ");
            $stmt->execute([$id_horario, $id_usuario, $mensaje_completo, 'error_' . time()]);
        } catch (Exception $ex) {
            error_log("Error guardando log de WhatsApp: " . $ex->getMessage());
        }
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

// Función para enviar recordatorios automáticos (para uso en cron)
function enviarRecordatoriosAutomaticos() {
    global $pdo;
    
    $hora_actual = date('H:i');
    $hora_recordatorio = date('H:i', strtotime('+15 minutes'));
    
    // Buscar medicamentos programados para los próximos 15 minutos
    $stmt = $pdo->prepare("
        SELECT 
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
        LEFT JOIN vinculaciones v ON u.id_usuario = v.id_paciente
        LEFT JOIN usuarios c ON v.id_cuidador = c.id_usuario
        WHERE h.hora BETWEEN ? AND ?
        AND h.activo = 1
        AND (v.confirmado = 1 OR v.id_cuidador IS NULL)
        AND NOT EXISTS (
            SELECT 1 FROM recordatorios_whatsapp rw 
            WHERE rw.id_horario = h.id_horario 
            AND DATE(rw.fecha_envio) = CURDATE()
            AND rw.mensaje LIKE '%RECORDATORIO%'
        )
    ");
    $stmt->execute([$hora_actual, $hora_recordatorio]);
    $recordatorios = $stmt->fetchAll();
    
    $enviados = 0;
    foreach ($recordatorios as $recordatorio) {
        // Enviar recordatorio al paciente
        if (!empty($recordatorio['paciente_telefono'])) {
            $mensaje_paciente = "RECORDATORIO: En 15 minutos debes tomar " . 
                               $recordatorio['nombre_medicamento'] . " - " . 
                               $recordatorio['dosis'] . ". Hora: " . $recordatorio['hora'];
            
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
            }
        }
        
        // Notificar al cuidador
        if (!empty($recordatorio['cuidador_telefono'])) {
            $mensaje_cuidador = "RECORDATORIO: " . $recordatorio['paciente_nombre'] . 
                               " debe tomar en 15 minutos: " . $recordatorio['nombre_medicamento'] . 
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
    
    return $enviados;
}

// Función para notificar confirmación al cuidador
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
// CONFIGURACIONES DE SEGURIDAD Y SERVIDOR
// =============================================================================

// Headers para seguridad básica
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Configuración de zona horaria
date_default_timezone_set('America/Mexico_City');

// Manejo de errores (en desarrollo mostrar errores, en producción ocultarlos)
if ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
}

// Función para crear directorios de logs si no existen
function inicializarDirectoriosLogs() {
    $directorios = ['logs', 'temp', 'uploads'];
    foreach ($directorios as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }
}

// Inicializar directorios al cargar config
inicializarDirectoriosLogs();

// Función para limpiar datos antiguos (puede usarse en cron de mantenimiento)
function limpiarDatosAntiguos($dias = 30) {
    global $pdo;
    
    $fecha_limite = date('Y-m-d H:i:s', strtotime("-$dias days"));
    
    try {
        // Limpiar logs de WhatsApp antiguos
        $stmt = $pdo->prepare("DELETE FROM recordatorios_whatsapp WHERE fecha_envio < ?");
        $stmt->execute([$fecha_limite]);
        
        return true;
    } catch (Exception $e) {
        error_log("Error limpiando datos antiguos: " . $e->getMessage());
        return false;
    }
}


?>
