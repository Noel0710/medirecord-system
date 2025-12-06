<?php
// setup_database.php - VERSIÓN COMPLETA Y FUNCIONAL
// Este archivo está en public/setup_database.php

// =============================================================================
// CABECERA HTML
// =============================================================================
echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Configuración Base de Datos - MediRecord</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container-main {
            max-width: 1000px;
            margin: 0 auto;
        }
        .card-main {
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: none;
            margin-bottom: 20px;
        }
        .card-header-main {
            background: linear-gradient(to right, #4f46e5, #7c3aed);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 25px;
            text-align: center;
        }
        .log-container {
            background: #1e293b;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            max-height: 400px;
            overflow-y: auto;
            margin: 10px 0;
        }
        .step-container {
            border-left: 4px solid #4f46e5;
            padding-left: 15px;
            margin-bottom: 25px;
        }
        .text-success-custom { color: #10b981 !important; }
        .text-error-custom { color: #ef4444 !important; }
        .text-warning-custom { color: #f59e0b !important; }
        .text-info-custom { color: #3b82f6 !important; }
    </style>
</head>
<body>
    <div class='container-main'>
        <div class='card-main'>
            <div class='card-header-main'>
                <h1 class='display-5 mb-3'>🚀 MediRecord - Configuración</h1>
                <p class='lead mb-0'>Sistema de recordatorio de medicamentos</p>
            </div>
            <div class='card-body p-4'>";

// =============================================================================
// PASO 1: INCLUIR CONFIG.PHP SEGURO
// =============================================================================
echo "<div class='step-container'>
        <h4 class='mb-3'>📋 Paso 1: Cargando configuración</h4>
        <div class='log-container'>";

// Función para logs
function logMessage($text, $type = 'info') {
    $icons = [
        'success' => '✅',
        'error' => '❌', 
        'warning' => '⚠️',
        'info' => 'ℹ️'
    ];
    $icon = $icons[$type] ?? 'ℹ️';
    $time = date('H:i:s');
    $class = "text-{$type}-custom";
    return "<div class='$class'>[$time] $icon $text</div>";
}

echo logMessage("PHP Version: " . PHP_VERSION, 'info');

// Intentar incluir config.php de forma segura
$configPath = __DIR__ . '/../config.php';
$configLoaded = false;
$pdo = null;

if (file_exists($configPath)) {
    echo logMessage("Encontrado config.php en: " . realpath($configPath), 'success');
    
    // Incluir config.php pero capturar posibles errores
    try {
        include $configPath;
        $configLoaded = true;
        echo logMessage("Config.php incluido exitosamente", 'success');
        
        // Verificar si $pdo se creó
        if (isset($pdo) && $pdo instanceof PDO) {
            echo logMessage("Conexión PDO creada por config.php", 'success');
        } else {
            echo logMessage("config.php no creó \$pdo, continuando con setup...", 'warning');
            $configLoaded = false;
        }
        
    } catch (Exception $e) {
        echo logMessage("Error incluyendo config.php: " . $e->getMessage(), 'error');
        $configLoaded = false;
    }
} else {
    echo logMessage("config.php no encontrado en: $configPath", 'warning');
    echo logMessage("Continuando con configuración manual...", 'info');
}

echo "</div></div>";

// =============================================================================
// PASO 2: CONFIGURACIÓN DE CONEXIÓN
// =============================================================================
echo "<div class='step-container'>
        <h4 class='mb-3'>🔌 Paso 2: Configuración de conexión</h4>
        <div class='log-container'>";

// Si config.php no cargó correctamente, crear conexión manual
if (!$configLoaded || !$pdo) {
    echo logMessage("Creando conexión manual para setup...", 'info');
    
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
    
    echo logMessage("Host: $host", 'info');
    echo logMessage("Base de datos: $database", 'info');
    echo logMessage("Usuario: $username", 'info');
    
    try {
        // Primero conectar sin base de datos
        $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        
        echo logMessage("✅ Conexión al servidor MySQL exitosa", 'success');
        
        // Verificar/crear base de datos
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$database`");
        
        echo logMessage("✅ Base de datos '$database' lista", 'success');
        
    } catch (PDOException $e) {
        echo logMessage("❌ Error de conexión manual: " . $e->getMessage(), 'error');
        echo "</div>
              <div class='alert alert-danger mt-3'>
                <h5>Error crítico</h5>
                <p>No se pudo establecer conexión con MySQL.</p>
                <p>En Railway, asegúrate de:</p>
                <ol>
                    <li>Tener un servicio MySQL agregado</li>
                    <li>Esperar 1-2 minutos después de agregarlo</li>
                    <li>Las variables de entorno deben aparecer automáticamente</li>
                </ol>
                <p>Error detallado: " . htmlspecialchars($e->getMessage()) . "</p>
              </div>
              </div></div></div></div></body></html>";
        exit;
    }
} else {
    echo logMessage("✅ Usando conexión existente de config.php", 'success');
}

// Verificar conexión
try {
    $pdo->query("SELECT 1");
    echo logMessage("✅ Conexión verificada y funcionando", 'success');
} catch (Exception $e) {
    echo logMessage("❌ Error verificando conexión: " . $e->getMessage(), 'error');
    echo "</div></div></div></div></body></html>";
    exit;
}

echo "</div></div>";

// =============================================================================
// PASO 3: VERIFICAR TABLAS EXISTENTES
// =============================================================================
echo "<div class='step-container'>
        <h4 class='mb-3'>📊 Paso 3: Verificando estructura existente</h4>
        <div class='log-container'>";

$force = isset($_GET['force']) && $_GET['force'] == '1';

try {
    $stmt = $pdo->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $tableCount = count($existingTables);
    
    if ($tableCount > 0) {
        echo logMessage("⚠️ Encontradas $tableCount tablas existentes:", 'warning');
        foreach ($existingTables as $table) {
            echo logMessage(" - $table", 'info');
        }
        
        if (!$force) {
            echo "</div>
                  <div class='mt-3'>
                    <div class='alert alert-warning'>
                        <h5>Base de datos ya configurada</h5>
                        <p>¿Qué deseas hacer?</p>
                        <div class='mt-2'>
                            <a href='?force=1' class='btn btn-danger me-2'>Forzar recreación (borrará datos)</a>
                            <a href='index.php' class='btn btn-secondary me-2'>Ir al sistema</a>
                            <a href='test_variables.php' class='btn btn-info'>Probar conexión</a>
                        </div>
                    </div>
                  </div>
                  </div></div></div></div></body></html>";
            exit;
        } else {
            echo logMessage("🔄 MODO FORZADO: Eliminando tablas existentes...", 'warning');
        }
    } else {
        echo logMessage("✅ No hay tablas existentes. Creando estructura...", 'success');
    }
} catch (Exception $e) {
    echo logMessage("Error verificando tablas: " . $e->getMessage(), 'error');
}

echo "</div></div>";

// =============================================================================
// PASO 4: ELIMINAR TABLAS (si force)
// =============================================================================
if ($force && $tableCount > 0) {
    echo "<div class='step-container'>
            <h4 class='mb-3'>🗑️ Paso 4: Limpiando estructura anterior</h4>
            <div class='log-container'>";
    
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        foreach ($existingTables as $table) {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
            echo logMessage("✅ Tabla '$table' eliminada", 'success');
        }
        
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        echo logMessage("✅ Todas las tablas eliminadas", 'success');
        
    } catch (Exception $e) {
        echo logMessage("❌ Error eliminando tablas: " . $e->getMessage(), 'error');
    }
    
    echo "</div></div>";
}

// =============================================================================
// PASO 5: CREAR TABLAS
// =============================================================================
$currentStep = $force ? 5 : 4;
echo "<div class='step-container'>
        <h4 class='mb-3'>🏗️ Paso $currentStep: Creando estructura</h4>
        <div class='log-container'>";

// Tablas del sistema MediRecord
$tableDefinitions = [
    'usuarios' => "CREATE TABLE usuarios (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'medicamentos' => "CREATE TABLE medicamentos (
        id_medicamento INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        nombre_medicamento VARCHAR(100) NOT NULL,
        dosis VARCHAR(50) NOT NULL,
        instrucciones TEXT,
        agregado_por INT,
        fecha_agregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_usuario (id_usuario),
        INDEX idx_agregado (agregado_por)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'horarios' => "CREATE TABLE horarios (
        id_horario INT AUTO_INCREMENT PRIMARY KEY,
        id_medicamento INT NOT NULL,
        hora TIME NOT NULL,
        frecuencia ENUM('diario', 'lunes-viernes', 'personalizado') DEFAULT 'diario',
        activo BOOLEAN DEFAULT TRUE,
        ultimo_recordatorio DATETIME,
        ultima_alerta DATETIME,
        INDEX idx_medicamento (id_medicamento),
        INDEX idx_activo (activo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'historial_tomas' => "CREATE TABLE historial_tomas (
        id_registro INT AUTO_INCREMENT PRIMARY KEY,
        id_horario INT NOT NULL,
        fecha_hora_toma TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        estado ENUM('tomado', 'omitido', 'pospuesto') NOT NULL,
        INDEX idx_horario (id_horario),
        INDEX idx_fecha (fecha_hora_toma)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'vinculaciones' => "CREATE TABLE vinculaciones (
        id_vinculacion INT AUTO_INCREMENT PRIMARY KEY,
        id_paciente INT NOT NULL,
        id_cuidador INT NOT NULL,
        confirmado BOOLEAN DEFAULT FALSE,
        fecha_vinculacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_paciente (id_paciente),
        INDEX idx_cuidador (id_cuidador),
        UNIQUE KEY unique_vinculo (id_paciente, id_cuidador)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    'recordatorios_whatsapp' => "CREATE TABLE recordatorios_whatsapp (
        id_recordatorio INT AUTO_INCREMENT PRIMARY KEY,
        id_horario INT NOT NULL,
        id_usuario INT NOT NULL,
        mensaje TEXT NOT NULL,
        fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        estado ENUM('enviado', 'entregado', 'leido', 'confirmado') DEFAULT 'enviado',
        token_confirmacion VARCHAR(100) NOT NULL,
        INDEX idx_horario (id_horario),
        INDEX idx_usuario (id_usuario),
        INDEX idx_token (token_confirmacion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

$createdTables = 0;
foreach ($tableDefinitions as $name => $sql) {
    try {
        $pdo->exec($sql);
        echo logMessage("✅ Tabla '$name' creada", 'success');
        $createdTables++;
        usleep(50000); // Pequeña pausa
    } catch (Exception $e) {
        echo logMessage("❌ Error creando '$name': " . $e->getMessage(), 'error');
    }
}

echo "</div></div>";

// =============================================================================
// PASO 6: CLAVES FORÁNEAS
// =============================================================================
$currentStep++;
echo "<div class='step-container'>
        <h4 class='mb-3'>🔗 Paso $currentStep: Creando relaciones</h4>
        <div class='log-container'>";

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

$createdKeys = 0;
foreach ($foreignKeys as $sql) {
    try {
        $pdo->exec($sql);
        echo logMessage("✅ Relación creada", 'success');
        $createdKeys++;
    } catch (Exception $e) {
        echo logMessage("⚠️ " . $e->getMessage(), 'warning');
    }
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "</div></div>";

// =============================================================================
// PASO 7: DATOS INICIALES
// =============================================================================
$currentStep++;
echo "<div class='step-container'>
        <h4 class='mb-3'>📝 Paso $currentStep: Datos iniciales</h4>
        <div class='log-container'>";

try {
    // Verificar si ya hay usuarios
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $userCount = $stmt->fetch()['total'];
    
    if ($userCount == 0) {
        echo logMessage("Insertando datos de demostración...", 'info');
        
        // Hash para contraseñas
        $passwordHash = password_hash('password123', PASSWORD_DEFAULT);
        
        // Usuarios de ejemplo
        $users = [
            ['María González', 'maria@medirecord.com', $passwordHash, 'paciente', '+521234567890'],
            ['Carlos Rodríguez', 'carlos@medirecord.com', $passwordHash, 'cuidador', '+521234567891'],
            ['Ana Martínez', 'ana@medirecord.com', $passwordHash, 'paciente', '+521234567892']
        ];
        
        foreach ($users as $user) {
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, tipo, telefono) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute($user);
        }
        
        echo logMessage("✅ 3 usuarios creados", 'success');
        
        // Vincular María con Carlos
        $pdo->exec("INSERT INTO vinculaciones (id_paciente, id_cuidador, confirmado) VALUES (1, 2, 1)");
        echo logMessage("✅ Vinculación paciente-cuidador creada", 'success');
        
        // Medicamento de ejemplo para María
        $pdo->exec("INSERT INTO medicamentos (id_usuario, nombre_medicamento, dosis, instrucciones, agregado_por) VALUES (1, 'Paracetamol', '1 tableta', 'Tomar con alimentos', 2)");
        $medId = $pdo->lastInsertId();
        
        // Horarios
        $pdo->exec("INSERT INTO horarios (id_medicamento, hora) VALUES ($medId, '08:00:00'), ($medId, '20:00:00')");
        
        echo logMessage("✅ Medicamento con horarios creado", 'success');
        
        // Toma de ejemplo
        $pdo->exec("INSERT INTO historial_tomas (id_horario, estado) VALUES (1, 'tomado')");
        
        echo logMessage("✅ Datos de demostración completos", 'success');
        
    } else {
        echo logMessage("Ya existen $userCount usuarios. Saltando inserción de datos.", 'info');
    }
    
} catch (Exception $e) {
    echo logMessage("⚠️ Error en datos: " . $e->getMessage(), 'warning');
}

echo "</div></div>";

// =============================================================================
// RESUMEN FINAL
// =============================================================================
echo "<div class='step-container'>
        <div class='alert alert-success'>
            <h3 class='alert-heading text-center'>🎉 ¡Configuración completada!</h3>
            <hr>
            
            <div class='row text-center mb-4'>
                <div class='col-md-4'>
                    <div class='card bg-light'>
                        <div class='card-body'>
                            <h5>Tablas</h5>
                            <h2 class='text-primary'>$createdTables</h2>
                            <small>de " . count($tableDefinitions) . " creadas</small>
                        </div>
                    </div>
                </div>
                <div class='col-md-4'>
                    <div class='card bg-light'>
                        <div class='card-body'>
                            <h5>Relaciones</h5>
                            <h2 class='text-primary'>$createdKeys</h2>
                            <small>claves foráneas</small>
                        </div>
                    </div>
                </div>
                <div class='col-md-4'>
                    <div class='card bg-light'>
                        <div class='card-body'>
                            <h5>Estado</h5>
                            <h4>✅ LISTO</h4>
                            <small>Sistema operativo</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class='mb-3'>
                <h5>👥 Credenciales de acceso:</h5>
                <div class='row'>
                    <div class='col-md-6'>
                        <div class='card'>
                            <div class='card-body'>
                                <h6>Paciente</h6>
                                <p><strong>Email:</strong> maria@medirecord.com</p>
                                <p><strong>Contraseña:</strong> password123</p>
                            </div>
                        </div>
                    </div>
                    <div class='col-md-6'>
                        <div class='card'>
                            <div class='card-body'>
                                <h6>Cuidador</h6>
                                <p><strong>Email:</strong> carlos@medirecord.com</p>
                                <p><strong>Contraseña:</strong> password123</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class='text-center mt-4'>
                <a href='index.php' class='btn btn-success btn-lg me-3'>
                    🚀 Comenzar a usar MediRecord
                </a>
                <a href='?force=1' class='btn btn-outline-warning btn-lg'>
                    🔄 Reiniciar configuración
                </a>
            </div>
            
            <div class='text-center text-muted mt-4'>
                <p>MediRecord v2.0 &copy; " . date('Y') . " - Sistema de recordatorio de medicamentos</p>
            </div>
        </div>
    </div>";

// =============================================================================
// PIE DE PÁGINA
// =============================================================================
echo "</div></div></div>

<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js'></script>
<script>
    // Auto-scroll en contenedores de log
    document.querySelectorAll('.log-container').forEach(function(el) {
        el.scrollTop = el.scrollHeight;
    });
    
    // Confirmación para reinicio
    document.querySelector('a[href*=\"force=1\"]').addEventListener('click', function(e) {
        if (!confirm('⚠️ ¿Reiniciar toda la configuración?\\n\\nSe eliminarán TODAS las tablas y datos.')) {
            e.preventDefault();
        }
    });
    
    // Mostrar hora de finalización
    console.log('✅ MediRecord setup completado: ' + new Date().toLocaleString());
</script>
</body>
</html>";
