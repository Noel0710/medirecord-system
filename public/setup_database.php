<?php
// setup_database.php - VERSIÓN QUE USA include '../config.php';

// Incluir config.php desde la raíz (igual que tus otros archivos)
include '../config.php';

// Verificar si $pdo se creó correctamente
if (!isset($pdo) || !($pdo instanceof PDO)) {
    // Fallback: crear conexión manualmente
    echo "<h3 style='color: orange;'>⚠️ config.php no creó $pdo correctamente. Usando conexión manual...</h3>";
    
    // Detectar entorno
    $isRailway = getenv('MYSQLHOST') !== false;
    
    if ($isRailway) {
        $host = getenv('MYSQLHOST') ?: 'localhost';
        $port = getenv('MYSQLPORT') ?: '3306';
        $database = getenv('MYSQLDATABASE') ?: 'railway';
        $username = getenv('MYSQLUSER') ?: 'root';
        $password = getenv('MYSQLPASSWORD') ?: '';
    } else {
        $host = 'localhost';
        $port = '3306';
        $database = 'medirecord_db';
        $username = 'root';
        $password = '';
    }
    
    try {
        $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    } catch (PDOException $e) {
        die("<h1 style='color: red;'>❌ Error de conexión</h1><p>" . $e->getMessage() . "</p>");
    }
}

// Ahora $pdo está disponible
echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Configuración BD - MediRecord</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { background: #f8f9fa; padding: 20px; }
        .card { border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .card-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .log-box { background: #212529; color: #fff; padding: 15px; border-radius: 5px; font-family: monospace; }
        .success { color: #20c997; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='card mt-4'>
            <div class='card-header text-center'>
                <h2 class='mb-0'>🚀 MediRecord - Configuración Base de Datos</h2>
            </div>
            <div class='card-body'>
                <div class='mb-4'>
                    <h4>✅ Conexión establecida</h4>
                    <p>Config.php cargado correctamente. Procediendo a crear tablas...</p>
                </div>
                <div class='log-box'>";

// ============================================
// VERIFICAR SI YA EXISTEN TABLAS
// ============================================
$force = isset($_GET['force']) && $_GET['force'] == '1';

try {
    // Verificar tablas existentes
    $stmt = $pdo->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count($existingTables) > 0) {
        echo "<div class='warning'>⚠️ Ya existen " . count($existingTables) . " tablas:</div>";
        foreach ($existingTables as $table) {
            echo "<div class='text-light'>- $table</div>";
        }
        
        if (!$force) {
            echo "</div>
                  <div class='mt-3 text-center'>
                    <div class='alert alert-warning'>
                        <h5>¿Qué deseas hacer?</h5>
                        <p>La base de datos ya tiene estructura.</p>
                        <div class='mt-2'>
                            <a href='?force=1' class='btn btn-danger me-2'>Forzar recreación</a>
                            <a href='index.php' class='btn btn-secondary'>Ir al inicio</a>
                        </div>
                    </div>
                  </div>
                  </div></div></div></div></body></html>";
            exit;
        } else {
            echo "<div class='warning'>🔄 MODO FORZADO: Eliminando tablas existentes...</div>";
        }
    } else {
        echo "<div class='success'>✅ No hay tablas existentes. Creando estructura...</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Error: " . $e->getMessage() . "</div>";
}

// ============================================
// ELIMINAR TABLAS EXISTENTES (si force)
// ============================================
if ($force && count($existingTables) > 0) {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    foreach ($existingTables as $table) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
            echo "<div class='success'>✅ Tabla '$table' eliminada</div>";
        } catch (Exception $e) {
            echo "<div class='error'>❌ Error eliminando '$table': " . $e->getMessage() . "</div>";
        }
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
}

// ============================================
// CREAR TABLAS
// ============================================
echo "<div class='mt-3'><h5>Creando tablas:</h5>";

$tables = [
    'usuarios' => "CREATE TABLE IF NOT EXISTS usuarios (
        id_usuario INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        tipo ENUM('paciente', 'cuidador') NOT NULL,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        telefono VARCHAR(20),
        whatsapp_token VARCHAR(100),
        telefono_verificado BOOLEAN DEFAULT FALSE,
        INDEX idx_tipo (tipo),
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    'medicamentos' => "CREATE TABLE IF NOT EXISTS medicamentos (
        id_medicamento INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        nombre_medicamento VARCHAR(100) NOT NULL,
        dosis VARCHAR(50) NOT NULL,
        instrucciones TEXT,
        agregado_por INT,
        fecha_agregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_usuario (id_usuario)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    'horarios' => "CREATE TABLE IF NOT EXISTS horarios (
        id_horario INT AUTO_INCREMENT PRIMARY KEY,
        id_medicamento INT NOT NULL,
        hora TIME NOT NULL,
        frecuencia ENUM('diario', 'lunes-viernes', 'personalizado') DEFAULT 'diario',
        activo BOOLEAN DEFAULT TRUE,
        ultimo_recordatorio DATETIME,
        ultima_alerta DATETIME,
        INDEX idx_medicamento (id_medicamento)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    'historial_tomas' => "CREATE TABLE IF NOT EXISTS historial_tomas (
        id_registro INT AUTO_INCREMENT PRIMARY KEY,
        id_horario INT NOT NULL,
        fecha_hora_toma TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        estado ENUM('tomado', 'omitido', 'pospuesto') NOT NULL,
        INDEX idx_horario (id_horario)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    'vinculaciones' => "CREATE TABLE IF NOT EXISTS vinculaciones (
        id_vinculacion INT AUTO_INCREMENT PRIMARY KEY,
        id_paciente INT NOT NULL,
        id_cuidador INT NOT NULL,
        confirmado BOOLEAN DEFAULT FALSE,
        fecha_vinculacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_vinculo (id_paciente, id_cuidador)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    'recordatorios_whatsapp' => "CREATE TABLE IF NOT EXISTS recordatorios_whatsapp (
        id_recordatorio INT AUTO_INCREMENT PRIMARY KEY,
        id_horario INT NOT NULL,
        id_usuario INT NOT NULL,
        mensaje TEXT NOT NULL,
        fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        estado ENUM('enviado', 'entregado', 'leido', 'confirmado') DEFAULT 'enviado',
        token_confirmacion VARCHAR(100) NOT NULL,
        INDEX idx_horario (id_horario),
        INDEX idx_usuario (id_usuario)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

$created = 0;
foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        echo "<div class='success'>✅ Tabla '$name' creada</div>";
        $created++;
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error creando '$name': " . $e->getMessage() . "</div>";
    }
}

// ============================================
// CLAVES FORÁNEAS
// ============================================
echo "<div class='mt-3'><h5>Creando relaciones:</h5>";

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

$foreignKeys = [
    "ALTER TABLE medicamentos ADD CONSTRAINT fk_medicamentos_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE",
    "ALTER TABLE medicamentos ADD CONSTRAINT fk_medicamentos_agregado FOREIGN KEY (agregado_por) REFERENCES usuarios(id_usuario) ON DELETE SET NULL",
    "ALTER TABLE horarios ADD CONSTRAINT fk_horarios_medicamento FOREIGN KEY (id_medicamento) REFERENCES medicamentos(id_medicamento) ON DELETE CASCADE",
    "ALTER TABLE historial_tomas ADD CONSTRAINT fk_historial_horario FOREIGN KEY (id_horario) REFERENCES horarios(id_horario) ON DELETE CASCADE",
    "ALTER TABLE vinculaciones ADD CONSTRAINT fk_vinculaciones_paciente FOREIGN KEY (id_paciente) REFERENCES usuarios(id_usuario) ON DELETE CASCADE",
    "ALTER TABLE vinculaciones ADD CONSTRAINT fk_vinculaciones_cuidador FOREIGN KEY (id_cuidador) REFERENCES usuarios(id_usuario) ON DELETE CASCADE",
    "ALTER TABLE recordatorios_whatsapp ADD CONSTRAINT fk_recordatorios_horario FOREIGN KEY (id_horario) REFERENCES horarios(id_horario) ON DELETE CASCADE",
    "ALTER TABLE recordatorios_whatsapp ADD CONSTRAINT fk_recordatorios_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE"
];

foreach ($foreignKeys as $sql) {
    try {
        $pdo->exec($sql);
        echo "<div class='success'>✅ Relación creada</div>";
    } catch (Exception $e) {
        echo "<div class='warning'>⚠️ " . $e->getMessage() . "</div>";
    }
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

// ============================================
// DATOS DE PRUEBA
// ============================================
echo "<div class='mt-3'><h5>Datos iniciales:</h5>";

try {
    // Verificar si ya hay usuarios
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $count = $stmt->fetch()['total'];
    
    if ($count == 0) {
        // Insertar usuario de prueba
        $hash = password_hash('password123', PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, tipo) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Usuario Demo', 'demo@medirecord.com', $hash, 'paciente']);
        
        echo "<div class='success'>✅ Usuario demo creado</div>";
        echo "<div class='text-light'>Email: demo@medirecord.com</div>";
        echo "<div class='text-light'>Contraseña: password123</div>";
    } else {
        echo "<div class='info'>ℹ️ Ya existen $count usuarios en la base de datos</div>";
    }
} catch (Exception $e) {
    echo "<div class='error'>❌ Error datos: " . $e->getMessage() . "</div>";
}

// ============================================
// FINAL
// ============================================
echo "</div></div>
        <div class='mt-4 text-center'>
            <div class='alert alert-success'>
                <h4 class='alert-heading'>🎉 ¡Configuración completada!</h4>
                <p>Se crearon $created tablas correctamente.</p>
                <hr>
                <div class='mb-2'>
                    <a href='index.php' class='btn btn-success btn-lg'>🚀 Ir al sistema MediRecord</a>
                </div>
                <p class='mb-0'>
                    <small>Si necesitas reiniciar la configuración:</small><br>
                    <a href='?force=1' class='btn btn-sm btn-outline-warning mt-1'>🔄 Reiniciar base de datos</a>
                </p>
            </div>
        </div>
    </div>
</div>
</div>

<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js'></script>
<script>
    // Auto-scroll en la caja de logs
    const logBox = document.querySelector('.log-box');
    if (logBox) {
        logBox.scrollTop = logBox.scrollHeight;
    }
</script>
</body>
</html>";
