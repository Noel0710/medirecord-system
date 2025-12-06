<?php
// setup_database.php - VERSIÓN COMPLETA Y CORREGIDA
// NO TIENE require_once - ES AUTÓNOMO

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
        .container-custom {
            max-width: 1000px;
            margin: 0 auto;
        }
        .card-custom {
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: none;
            margin-bottom: 20px;
        }
        .card-header-custom {
            background: linear-gradient(to right, #4f46e5, #7c3aed);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 25px;
            text-align: center;
        }
        .log-box {
            background: #1e293b;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            max-height: 400px;
            overflow-y: auto;
            margin: 10px 0;
        }
        .step {
            border-left: 4px solid #4f46e5;
            padding-left: 15px;
            margin-bottom: 25px;
        }
        .success {
            color: #10b981;
        }
        .error {
            color: #ef4444;
        }
        .warning {
            color: #f59e0b;
        }
        .info {
            color: #3b82f6;
        }
    </style>
</head>
<body>
    <div class='container-custom'>
        <div class='card-custom'>
            <div class='card-header-custom'>
                <h1 class='display-5 mb-3'>🚀 MediRecord - Configuración</h1>
                <p class='lead mb-0'>Sistema de recordatorio de medicamentos para adultos mayores</p>
            </div>
            <div class='card-body p-4'>";

// =============================================================================
// PASO 1: DETECCIÓN DEL ENTORNO
// =============================================================================
echo "<div class='step'>
        <h4 class='mb-3'>📋 Paso 1: Detección del entorno</h4>
        <div class='log-box'>";

// Función para mostrar mensajes
function showLog($message, $type = 'info') {
    $icon = '';
    switch($type) {
        case 'success': $icon = '✅'; break;
        case 'error': $icon = '❌'; break;
        case 'warning': $icon = '⚠️'; break;
        default: $icon = 'ℹ️';
    }
    $time = date('H:i:s');
    $class = $type;
    return "<div class='$class'>[$time] $icon $message</div>";
}

// Detectar entorno
$isRailway = getenv('MYSQLHOST') !== false || getenv('RAILWAY_ENVIRONMENT') !== false;

echo showLog("PHP Version: " . PHP_VERSION, 'info');
echo showLog("Entorno: " . ($isRailway ? 'Railway 🚄' : 'Local 🖥️'), 'info');

// Mostrar variables de entorno
$env_vars = [
    'MYSQLHOST' => getenv('MYSQLHOST'),
    'MYSQLPORT' => getenv('MYSQLPORT'),
    'MYSQLDATABASE' => getenv('MYSQLDATABASE'),
    'MYSQLUSER' => getenv('MYSQLUSER'),
    'MYSQLPASSWORD' => getenv('MYSQLPASSWORD'),
    'MYSQL_URL' => getenv('MYSQL_URL'),
    'RAILWAY_PUBLIC_DOMAIN' => getenv('RAILWAY_PUBLIC_DOMAIN')
];

foreach ($env_vars as $key => $value) {
    if ($value) {
        $display = ($key == 'MYSQLPASSWORD') ? '••••••••' : $value;
        echo showLog("$key: $display", 'success');
    } else {
        echo showLog("$key: NO DEFINIDO", 'warning');
    }
}

echo "</div></div>";

// =============================================================================
// PASO 2: CONFIGURACIÓN DE CONEXIÓN
// =============================================================================
echo "<div class='step'>
        <h4 class='mb-3'>🔌 Paso 2: Configuración de conexión MySQL</h4>
        <div class='log-box'>";

// Configuración según entorno
if ($isRailway) {
    $host = getenv('MYSQLHOST') ?: 'localhost';
    $port = getenv('MYSQLPORT') ?: '3306';
    $database = getenv('MYSQLDATABASE') ?: 'railway';
    $username = getenv('MYSQLUSER') ?: 'root';
    $password = getenv('MYSQLPASSWORD') ?: '';
    
    // Si hay MYSQL_URL, usarla
    $mysql_url = getenv('MYSQL_URL');
    if ($mysql_url) {
        echo showLog("Usando MYSQL_URL para configuración", 'info');
        $url = parse_url($mysql_url);
        $host = $url['host'] ?? $host;
        $port = $url['port'] ?? $port;
        $database = isset($url['path']) ? ltrim($url['path'], '/') : $database;
        $username = $url['user'] ?? $username;
        $password = $url['pass'] ?? $password;
    }
} else {
    // Configuración local
    $host = 'localhost';
    $port = '3306';
    $database = 'medirecord_db';
    $username = 'root';
    $password = '';
}

echo showLog("Host: $host", 'info');
echo showLog("Puerto: $port", 'info');
echo showLog("Base de datos: $database", 'info');
echo showLog("Usuario: $username", 'info');

// =============================================================================
// INTENTAR CONEXIÓN
// =============================================================================
$pdo = null;
try {
    // Primero intentar conexión sin base de datos
    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    echo showLog("✅ Conexión exitosa al servidor MySQL", 'success');
    
    // Verificar si la base de datos existe
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$database'");
    $dbExists = $stmt->fetch();
    
    if (!$dbExists) {
        echo showLog("La base de datos '$database' no existe. Creando...", 'warning');
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo showLog("✅ Base de datos '$database' creada exitosamente", 'success');
    }
    
    // Seleccionar la base de datos
    $pdo->exec("USE `$database`");
    echo showLog("✅ Conectado a la base de datos: $database", 'success');
    
} catch (PDOException $e) {
    echo showLog("❌ Error de conexión: " . $e->getMessage(), 'error');
    echo "<div class='alert alert-danger mt-3'>
            <h5>📋 Solución de problemas:</h5>
            <ol>
                <li>Verifica que hayas añadido un servicio MySQL en Railway</li>
                <li>En Railway Dashboard, ve a tu proyecto → Variables</li>
                <li>Las variables MySQL deben aparecer automáticamente</li>
                <li>Si estás localmente, asegúrate de que XAMPP/MAMP esté corriendo</li>
                <li>Revisa que el usuario y contraseña sean correctos</li>
            </ol>
            <div class='mt-2'>
                <a href='test_variables.php' class='btn btn-outline-danger btn-sm me-2'>Probar variables</a>
                <a href='index.php' class='btn btn-outline-secondary btn-sm'>Ir al inicio</a>
            </div>
          </div>";
    echo "</div></div></div></div></div></div></body></html>";
    exit;
}

echo "</div></div>";

// =============================================================================
// PASO 3: VERIFICAR TABLAS EXISTENTES
// =============================================================================
echo "<div class='step'>
        <h4 class='mb-3'>📊 Paso 3: Verificación de tablas existentes</h4>
        <div class='log-box'>";

$force = isset($_GET['force']) && $_GET['force'] == '1';

try {
    $stmt = $pdo->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $tableCount = count($existingTables);
    
    if ($tableCount > 0) {
        echo showLog("⚠️ Ya existen $tableCount tablas en la base de datos:", 'warning');
        foreach ($existingTables as $table) {
            echo showLog("- $table", 'info');
        }
        
        if (!$force) {
            echo "</div>
                  <div class='mt-3'>
                    <div class='alert alert-warning'>
                        <h5>¿Qué deseas hacer?</h5>
                        <p>La base de datos ya tiene tablas existentes.</p>
                        <div class='mt-2'>
                            <a href='?force=1' class='btn btn-danger me-2'>Forzar recreación (borrará datos existentes)</a>
                            <a href='index.php' class='btn btn-secondary me-2'>Ir al inicio</a>
                            <a href='test_variables.php' class='btn btn-info'>Probar conexión</a>
                        </div>
                    </div>
                  </div>
                  </div></div></div></div></div></body></html>";
            exit;
        } else {
            echo showLog("⚠️ MODO FORZADO ACTIVADO - Se eliminarán tablas existentes", 'warning');
        }
    } else {
        echo showLog("✅ No hay tablas existentes. Creando estructura nueva...", 'success');
    }
    
} catch (Exception $e) {
    echo showLog("Error verificando tablas: " . $e->getMessage(), 'error');
}

echo "</div></div>";

// =============================================================================
// PASO 4: ELIMINAR TABLAS EXISTENTES (si está en modo forzado)
// =============================================================================
if ($force && $tableCount > 0) {
    echo "<div class='step'>
            <h4 class='mb-3'>🗑️ Paso 4: Eliminando tablas existentes</h4>
            <div class='log-box'>";
    
    // Deshabilitar claves foráneas temporalmente
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    foreach ($existingTables as $table) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
            echo showLog("✅ Tabla '$table' eliminada", 'success');
        } catch (Exception $e) {
            echo showLog("❌ Error eliminando tabla '$table': " . $e->getMessage(), 'error');
        }
    }
    
    // Rehabilitar claves foráneas
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "</div></div>";
}

// =============================================================================
// PASO 5: CREACIÓN DE TABLAS
// =============================================================================
echo "<div class='step'>
        <h4 class='mb-3'>🏗️ Paso 5: Creando estructura de la base de datos</h4>
        <div class='log-box'>";

// Tablas del sistema MediRecord
$tables = [
    "usuarios" => "CREATE TABLE usuarios (
        id_usuario INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        email VARCHAR(150) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        tipo ENUM('paciente', 'cuidador') NOT NULL,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        telefono VARCHAR(20) DEFAULT NULL,
        whatsapp_token VARCHAR(100) DEFAULT NULL,
        telefono_verificado BOOLEAN DEFAULT FALSE,
        INDEX idx_tipo (tipo),
        INDEX idx_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "medicamentos" => "CREATE TABLE medicamentos (
        id_medicamento INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        nombre_medicamento VARCHAR(100) NOT NULL,
        dosis VARCHAR(50) NOT NULL,
        instrucciones TEXT,
        agregado_por INT DEFAULT NULL,
        fecha_agregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_usuario (id_usuario),
        INDEX idx_agregado (agregado_por)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "horarios" => "CREATE TABLE horarios (
        id_horario INT AUTO_INCREMENT PRIMARY KEY,
        id_medicamento INT NOT NULL,
        hora TIME NOT NULL,
        frecuencia ENUM('diario', 'lunes-viernes', 'personalizado') DEFAULT 'diario',
        activo BOOLEAN DEFAULT TRUE,
        ultimo_recordatorio DATETIME DEFAULT NULL,
        ultima_alerta DATETIME DEFAULT NULL,
        INDEX idx_medicamento (id_medicamento),
        INDEX idx_hora (hora),
        INDEX idx_activo (activo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "historial_tomas" => "CREATE TABLE historial_tomas (
        id_registro INT AUTO_INCREMENT PRIMARY KEY,
        id_horario INT NOT NULL,
        fecha_hora_toma TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        estado ENUM('tomado', 'omitido', 'pospuesto') NOT NULL,
        INDEX idx_horario (id_horario),
        INDEX idx_fecha (fecha_hora_toma),
        INDEX idx_estado (estado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "vinculaciones" => "CREATE TABLE vinculaciones (
        id_vinculacion INT AUTO_INCREMENT PRIMARY KEY,
        id_paciente INT NOT NULL,
        id_cuidador INT NOT NULL,
        confirmado BOOLEAN DEFAULT FALSE,
        fecha_vinculacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_paciente (id_paciente),
        INDEX idx_cuidador (id_cuidador),
        INDEX idx_confirmado (confirmado),
        UNIQUE KEY idx_unique_vinculo (id_paciente, id_cuidador)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "recordatorios_whatsapp" => "CREATE TABLE recordatorios_whatsapp (
        id_recordatorio INT AUTO_INCREMENT PRIMARY KEY,
        id_horario INT NOT NULL,
        id_usuario INT NOT NULL,
        mensaje TEXT NOT NULL,
        fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        estado ENUM('enviado', 'entregado', 'leido', 'confirmado') DEFAULT 'enviado',
        token_confirmacion VARCHAR(100) NOT NULL,
        INDEX idx_horario (id_horario),
        INDEX idx_usuario (id_usuario),
        INDEX idx_fecha (fecha_envio),
        INDEX idx_token (token_confirmacion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

$createdTables = 0;
foreach ($tables as $tableName => $sql) {
    try {
        $pdo->exec($sql);
        echo showLog("✅ Tabla '$tableName' creada", 'success');
        $createdTables++;
        
        // Pequeña pausa para evitar sobrecarga
        usleep(50000);
        
    } catch (Exception $e) {
        echo showLog("❌ Error creando tabla '$tableName': " . $e->getMessage(), 'error');
    }
}

echo "</div></div>";

// =============================================================================
// PASO 6: CLAVES FORÁNEAS
// =============================================================================
echo "<div class='step'>
        <h4 class='mb-3'>🔗 Paso 6: Configurando relaciones entre tablas</h4>
        <div class='log-box'>";

// Deshabilitar temporalmente claves foráneas
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

// Relaciones del sistema
$foreignKeys = [
    ["medicamentos", "id_usuario", "usuarios", "id_usuario", "CASCADE"],
    ["medicamentos", "agregado_por", "usuarios", "id_usuario", "SET NULL"],
    ["horarios", "id_medicamento", "medicamentos", "id_medicamento", "CASCADE"],
    ["historial_tomas", "id_horario", "horarios", "id_horario", "CASCADE"],
    ["vinculaciones", "id_paciente", "usuarios", "id_usuario", "CASCADE"],
    ["vinculaciones", "id_cuidador", "usuarios", "id_usuario", "CASCADE"],
    ["recordatorios_whatsapp", "id_horario", "horarios", "id_horario", "CASCADE"],
    ["recordatorios_whatsapp", "id_usuario", "usuarios", "id_usuario", "CASCADE"]
];

$addedKeys = 0;
foreach ($foreignKeys as $fk) {
    list($table, $column, $refTable, $refColumn, $onDelete) = $fk;
    
    try {
        // Eliminar clave existente si hay
        $pdo->exec("ALTER TABLE `$table` DROP FOREIGN KEY IF EXISTS `fk_{$table}_{$column}`");
        
        // Crear nueva clave
        $sql = "ALTER TABLE `$table` 
                ADD CONSTRAINT `fk_{$table}_{$column}` 
                FOREIGN KEY (`$column`) 
                REFERENCES `$refTable` (`$refColumn`) 
                ON DELETE $onDelete 
                ON UPDATE CASCADE";
        
        $pdo->exec($sql);
        echo showLog("✅ Relación $table.$column → $refTable.$refColumn", 'success');
        $addedKeys++;
        
    } catch (Exception $e) {
        echo showLog("⚠️ Relación $table.$column: " . $e->getMessage(), 'warning');
    }
}

// Rehabilitar claves foráneas
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "</div></div>";

// =============================================================================
// PASO 7: DATOS INICIALES
// =============================================================================
echo "<div class='step'>
        <h4 class='mb-3'>📝 Paso 7: Insertando datos iniciales</h4>
        <div class='log-box'>";

try {
    // Verificar si ya hay usuarios
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM usuarios");
    $userCount = $stmt->fetch()['count'];
    
    if ($userCount == 0) {
        echo showLog("Insertando datos de prueba para MediRecord...", 'info');
        
        // Usar password_hash para contraseñas seguras
        $password_hash = password_hash('password123', PASSWORD_DEFAULT);
        
        // Usuarios de prueba
        $users = [
            ['María González López', 'maria.gonzalez@email.com', $password_hash, 'paciente', '+521234567890'],
            ['Carlos Rodríguez Pérez', 'carlos.rodriguez@email.com', $password_hash, 'cuidador', '+521234567891'],
            ['Ana Martínez Sánchez', 'ana.martinez@email.com', $password_hash, 'paciente', '+521234567892'],
            ['Javier López García', 'javier.lopez@email.com', $password_hash, 'cuidador', '+521234567893'],
            ['Isabel Díaz Fernández', 'isabel.diaz@email.com', $password_hash, 'paciente', '+521234567894']
        ];
        
        foreach ($users as $user) {
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, tipo, telefono) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute($user);
        }
        
        echo showLog("✅ 5 usuarios de prueba insertados", 'success');
        
        // Vincular usuarios (pacientes con cuidadores)
        $vinculaciones = [
            [1, 2, 1], // María (paciente) con Carlos (cuidador)
            [3, 4, 1], // Ana con Javier
            [5, 2, 1]  // Isabel con Carlos
        ];
        
        foreach ($vinculaciones as $vin) {
            $stmt = $pdo->prepare("INSERT INTO vinculaciones (id_paciente, id_cuidador, confirmado) VALUES (?, ?, ?)");
            $stmt->execute($vin);
        }
        
        echo showLog("✅ 3 vinculaciones creadas", 'success');
        
        // Medicamentos de ejemplo
        $medicamentos = [
            [1, 'Losartán', '1 tableta de 50mg', 'Tomar con el desayuno', 2],
            [1, 'Metformina', '1 tableta de 850mg', 'Tomar con alimentos', 2],
            [3, 'Omeprazol', '1 cápsula de 20mg', 'Tomar en ayunas 30 min antes del desayuno', 4],
            [5, 'Levotiroxina', '1 tableta de 50mcg', 'Tomar en ayunas 30-60 min antes del desayuno', 2]
        ];
        
        foreach ($medicamentos as $med) {
            $stmt = $pdo->prepare("INSERT INTO medicamentos (id_usuario, nombre_medicamento, dosis, instrucciones, agregado_por) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute($med);
            $medId = $pdo->lastInsertId();
            
            // Crear horarios según el medicamento
            if ($med[1] == 'Losartán') {
                $pdo->exec("INSERT INTO horarios (id_medicamento, hora) VALUES ($medId, '08:00:00')");
                $pdo->exec("INSERT INTO horarios (id_medicamento, hora) VALUES ($medId, '20:00:00')");
            } elseif ($med[1] == 'Metformina') {
                $pdo->exec("INSERT INTO horarios (id_medicamento, hora) VALUES ($medId, '08:00:00')");
                $pdo->exec("INSERT INTO horarios (id_medicamento, hora) VALUES ($medId, '14:00:00')");
            } else {
                $pdo->exec("INSERT INTO horarios (id_medicamento, hora) VALUES ($medId, '07:00:00')");
            }
        }
        
        echo showLog("✅ 4 medicamentos con horarios creados", 'success');
        
        // Insertar algunas tomas de ejemplo para hoy
        $pdo->exec("INSERT INTO historial_tomas (id_horario, estado) VALUES (1, 'tomado')");
        $pdo->exec("INSERT INTO historial_tomas (id_horario, estado) VALUES (2, 'tomado')");
        $pdo->exec("INSERT INTO historial_tomas (id_horario, estado) VALUES (3, 'tomado')");
        
        echo showLog("✅ 3 registros de tomas creados", 'success');
        
        echo showLog("✅ Todos los datos de prueba insertados correctamente", 'success');
        
    } else {
        echo showLog("Ya existen $userCount usuarios en la base de datos. Saltando inserción de datos de prueba.", 'info');
    }
    
} catch (Exception $e) {
    echo showLog("❌ Error insertando datos: " . $e->getMessage(), 'error');
}

echo "</div></div>";

// =============================================================================
// RESUMEN FINAL
// =============================================================================
echo "<div class='step'>
        <h4 class='mb-3'>🎉 Paso 8: Resumen final</h4>
        <div class='alert alert-success'>
            <h4 class='alert-heading'>¡Configuración completada exitosamente!</h4>
            <hr>
            <div class='row'>
                <div class='col-md-4'>
                    <div class='card bg-light'>
                        <div class='card-body text-center'>
                            <h5>Tablas creadas</h5>
                            <h2 class='text-primary'>$createdTables</h2>
                            <small>de " . count($tables) . " totales</small>
                        </div>
                    </div>
                </div>
                <div class='col-md-4'>
                    <div class='card bg-light'>
                        <div class='card-body text-center'>
                            <h5>Relaciones</h5>
                            <h2 class='text-primary'>$addedKeys</h2>
                            <small>claves foráneas</small>
                        </div>
                    </div>
                </div>
                <div class='col-md-4'>
                    <div class='card bg-light'>
                        <div class='card-body text-center'>
                            <h5>Entorno</h5>
                            <h4>" . ($isRailway ? 'Railway 🚄' : 'Local 🖥️') . "</h4>
                            <small>" . (getenv('RAILWAY_PUBLIC_DOMAIN') ?: 'localhost') . "</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class='mt-4'>
                <h5>📊 Información de la base de datos:</h5>
                <ul>
                    <li><strong>Base de datos:</strong> $database</li>
                    <li><strong>Host:</strong> $host:$port</li>
                    <li><strong>Usuario:</strong> $username</li>
                    <li><strong>Total tablas:</strong> $createdTables</li>
                </ul>
            </div>
            
            <div class='mt-4'>
                <h5>🔧 Acciones disponibles:</h5>
                <div class='d-grid gap-2 d-md-flex'>
                    <a href='index.php' class='btn btn-success btn-lg me-2'>
                        🚀 Ir al sistema MediRecord
                    </a>
                    <a href='test_variables.php' class='btn btn-outline-primary btn-lg me-2'>
                        🔍 Verificar configuración
                    </a>
                    <a href='?force=1' class='btn btn-outline-warning btn-lg'>
                        🔄 Reiniciar configuración
                    </a>
                </div>
            </div>
            
            <div class='alert alert-warning mt-4'>
                <h5>👥 Credenciales de prueba:</h5>
                <p><strong>Paciente:</strong> maria.gonzalez@email.com / password123</p>
                <p><strong>Cuidador:</strong> carlos.rodriguez@email.com / password123</p>
                <p class='mb-0'><small><em>Nota:</em> Las contraseñas están hasheadas. Para iniciar sesión usa: <code>password123</code></small></p>
            </div>
            
            <div class='text-center text-muted mt-3'>
                <p>MediRecord v2.0 &copy; " . date('Y') . " - Sistema de recordatorio de medicamentos</p>
            </div>
        </div>
    </div>";

echo "</div></div></div></div>

<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js'></script>
<script>
    // Auto-scroll en las cajas de log
    document.querySelectorAll('.log-box').forEach(function(element) {
        element.scrollTop = element.scrollHeight;
    });
    
    // Confirmación para reiniciar
    document.querySelector('a[href*=\"force=1\"]').addEventListener('click', function(e) {
        if (!confirm('⚠️ ¿Estás seguro de reiniciar la configuración?\\n\\nSe eliminarán TODAS las tablas y datos existentes.')) {
            e.preventDefault();
        }
    });
    
    // Mostrar hora de finalización
    var now = new Date();
    var timeString = now.getHours().toString().padStart(2, '0') + ':' + 
                     now.getMinutes().toString().padStart(2, '0') + ':' + 
                     now.getSeconds().toString().padStart(2, '0');
    console.log('✅ MediRecord setup completed at: ' + timeString);
</script>
</body>
</html>";
