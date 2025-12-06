<?php
// setup_database.php - Versión corregida para Railway
// Este archivo está en /app/public/setup_database.php

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Configuración de Base de Datos - MediRecord</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .card {
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: none;
        }
        .card-header {
            background: linear-gradient(to right, #4f46e5, #7c3aed);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 25px;
        }
        .step-card {
            border-left: 5px solid #4f46e5;
            margin-bottom: 20px;
        }
        .log-output {
            background: #1e293b;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            max-height: 400px;
            overflow-y: auto;
            margin: 10px 0;
        }
        .success-badge {
            background-color: #10b981;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-left: 10px;
        }
        .error-badge {
            background-color: #ef4444;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-left: 10px;
        }
        .warning-badge {
            background-color: #f59e0b;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <div class='row justify-content-center'>
            <div class='col-lg-10'>
                <div class='card'>
                    <div class='card-header text-center'>
                        <h1 class='display-5'>🚀 MediRecord - Configuración</h1>
                        <p class='lead'>Sistema de recordatorio de medicamentos para adultos mayores</p>
                    </div>
                    <div class='card-body'>";

// =============================================================================
// FUNCIÓN PARA REGISTRAR LOG
// =============================================================================
function logMessage($message, $type = 'info') {
    $badge = '';
    $color = '';
    
    switch($type) {
        case 'success':
            $badge = '<span class="success-badge">✓</span>';
            $color = 'text-success';
            break;
        case 'error':
            $badge = '<span class="error-badge">✗</span>';
            $color = 'text-danger';
            break;
        case 'warning':
            $badge = '<span class="warning-badge">!</span>';
            $color = 'text-warning';
            break;
        default:
            $badge = '<span class="badge bg-secondary">i</span>';
            $color = 'text-info';
    }
    
    $timestamp = date('H:i:s');
    return "<div class='$color'>[$timestamp] $badge $message</div>";
}

// =============================================================================
// PASO 1: DETECCIÓN DEL ENTORNO
// =============================================================================
echo "<div class='step-card card'>
        <div class='card-body'>
            <h4 class='card-title'>Paso 1: Detección del entorno</h4>
            <div class='log-output'>";

$isRailway = getenv('MYSQLHOST') !== false || 
             getenv('RAILWAY_ENVIRONMENT') !== false ||
             getenv('RAILWAY_PUBLIC_DOMAIN') !== false;

echo logMessage("Entorno detectado: " . ($isRailway ? 'Railway 🚄' : 'Local 🖥️'), 'info');
echo logMessage("PHP Version: " . PHP_VERSION, 'info');

// Variables de entorno
$env_vars = [
    'MYSQLHOST' => getenv('MYSQLHOST'),
    'MYSQLPORT' => getenv('MYSQLPORT'),
    'MYSQLDATABASE' => getenv('MYSQLDATABASE'),
    'MYSQLUSER' => getenv('MYSQLUSER'),
    'MYSQLPASSWORD' => getenv('MYSQLPASSWORD') ? '***' . substr(getenv('MYSQLPASSWORD'), -4) : null,
    'MYSQL_URL' => getenv('MYSQL_URL'),
    'RAILWAY_PUBLIC_DOMAIN' => getenv('RAILWAY_PUBLIC_DOMAIN')
];

foreach ($env_vars as $key => $value) {
    if ($value) {
        echo logMessage("$key: $value", 'success');
    } else {
        echo logMessage("$key: NO DEFINIDO", 'warning');
    }
}

echo "</div></div></div>";

// =============================================================================
// PASO 2: CONFIGURACIÓN DE CONEXIÓN
// =============================================================================
echo "<div class='step-card card'>
        <div class='card-body'>
            <h4 class='card-title'>Paso 2: Configuración de conexión a MySQL</h4>
            <div class='log-output'>";

// Configuración
if ($isRailway) {
    $host = getenv('MYSQLHOST') ?: 'localhost';
    $port = getenv('MYSQLPORT') ?: '3306';
    $database = getenv('MYSQLDATABASE') ?: 'railway';
    $username = getenv('MYSQLUSER') ?: 'root';
    $password = getenv('MYSQLPASSWORD') ?: '';
    
    $mysql_url = getenv('MYSQL_URL');
    if ($mysql_url) {
        echo logMessage("Usando MYSQL_URL para configuración", 'info');
        $url = parse_url($mysql_url);
        $host = $url['host'] ?? $host;
        $port = $url['port'] ?? $port;
        $database = isset($url['path']) ? ltrim($url['path'], '/') : $database;
        $username = $url['user'] ?? $username;
        $password = $url['pass'] ?? $password;
    }
} else {
    $host = 'localhost';
    $port = '3306';
    $database = 'medirecord_db';
    $username = 'root';
    $password = '';
}

echo logMessage("Host: $host", 'info');
echo logMessage("Puerto: $port", 'info');
echo logMessage("Base de datos: $database", 'info');
echo logMessage("Usuario: $username", 'info');

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
    
    echo logMessage("✅ Conexión exitosa al servidor MySQL", 'success');
    
    // Verificar si la base de datos existe
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$database'");
    $dbExists = $stmt->fetch();
    
    if (!$dbExists) {
        echo logMessage("La base de datos '$database' no existe. Creando...", 'warning');
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo logMessage("✅ Base de datos '$database' creada", 'success');
    }
    
    // Seleccionar la base de datos
    $pdo->exec("USE `$database`");
    echo logMessage("✅ Conectado a la base de datos: $database", 'success');
    
} catch (PDOException $e) {
    echo logMessage("❌ Error de conexión: " . $e->getMessage(), 'error');
    echo "<div class='alert alert-danger mt-3'>
            <h5>Solución de problemas:</h5>
            <ol>
                <li>Verifica que hayas añadido un servicio MySQL en Railway</li>
                <li>En Railway, ve a tu proyecto → Variables → Deben aparecer las variables MySQL automáticamente</li>
                <li>Si estás localmente, asegúrate de que MySQL esté corriendo (XAMPP/MAMP)</li>
                <li>Revisa que el usuario y contraseña sean correctos</li>
            </ol>
            <a href='test_variables.php' class='btn btn-outline-danger btn-sm'>Probar variables</a>
          </div>";
    echo "</div></div></div></div></div></body></html>";
    exit;
}

echo "</div></div></div>";

// =============================================================================
// PASO 3: VERIFICAR TABLAS EXISTENTES
// =============================================================================
echo "<div class='step-card card'>
        <div class='card-body'>
            <h4 class='card-title'>Paso 3: Verificación de tablas existentes</h4>
            <div class='log-output'>";

$force = isset($_GET['force']) && $_GET['force'] == '1';

try {
    $stmt = $pdo->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $tableCount = count($existingTables);
    
    if ($tableCount > 0) {
        echo logMessage("⚠️ Ya existen $tableCount tablas en la base de datos", 'warning');
        foreach ($existingTables as $table) {
            echo logMessage("- $table", 'info');
        }
        
        if (!$force) {
            echo "</div>
                  <div class='mt-3'>
                    <p class='alert alert-warning'>¿Qué deseas hacer?</p>
                    <a href='?force=1' class='btn btn-danger'>Forzar recreación (borrará datos existentes)</a>
                    <a href='index.php' class='btn btn-secondary'>Ir al inicio</a>
                    <a href='test_variables.php' class='btn btn-info'>Probar conexión</a>
                  </div>
                  </div></div></div></div></div></body></html>";
            exit;
        } else {
            echo logMessage("⚠️ MODO FORZADO ACTIVADO - Se eliminarán tablas existentes", 'warning');
        }
    } else {
        echo logMessage("✅ No hay tablas existentes. Creando estructura nueva...", 'success');
    }
    
} catch (Exception $e) {
    echo logMessage("Error verificando tablas: " . $e->getMessage(), 'error');
}

echo "</div></div></div>";

// =============================================================================
// PASO 4: ELIMINAR TABLAS EXISTENTES (si está en modo forzado)
// =============================================================================
if ($force && $tableCount > 0) {
    echo "<div class='step-card card'>
            <div class='card-body'>
                <h4 class='card-title'>Paso 4: Eliminando tablas existentes</h4>
                <div class='log-output'>";
    
    // Deshabilitar claves foráneas temporalmente
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    foreach ($existingTables as $table) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
            echo logMessage("✅ Tabla '$table' eliminada", 'success');
        } catch (Exception $e) {
            echo logMessage("❌ Error eliminando tabla '$table': " . $e->getMessage(), 'error');
        }
    }
    
    // Rehabilitar claves foráneas
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "</div></div></div>";
}

// =============================================================================
// PASO 5: CREACIÓN DE TABLAS
// =============================================================================
echo "<div class='step-card card'>
        <div class='card-body'>
            <h4 class='card-title'>Paso 5: Creando estructura de la base de datos</h4>
            <div class='log-output'>";

$tables = [
    "usuarios" => "CREATE TABLE IF NOT EXISTS usuarios (
        id_usuario int(11) NOT NULL AUTO_INCREMENT,
        nombre varchar(100) NOT NULL,
        email varchar(150) NOT NULL,
        password varchar(255) NOT NULL,
        tipo enum('paciente','cuidador') NOT NULL,
        fecha_registro timestamp NOT NULL DEFAULT current_timestamp(),
        telefono varchar(20) DEFAULT NULL,
        whatsapp_token varchar(100) DEFAULT NULL,
        telefono_verificado tinyint(1) DEFAULT 0,
        PRIMARY KEY (id_usuario),
        UNIQUE KEY email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "medicamentos" => "CREATE TABLE IF NOT EXISTS medicamentos (
        id_medicamento int(11) NOT NULL AUTO_INCREMENT,
        id_usuario int(11) NOT NULL,
        nombre_medicamento varchar(100) NOT NULL,
        dosis varchar(50) NOT NULL,
        instrucciones text DEFAULT NULL,
        agregado_por int(11) DEFAULT NULL,
        fecha_agregado timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (id_medicamento),
        KEY id_usuario (id_usuario),
        KEY agregado_por (agregado_por)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "horarios" => "CREATE TABLE IF NOT EXISTS horarios (
        id_horario int(11) NOT NULL AUTO_INCREMENT,
        id_medicamento int(11) NOT NULL,
        hora time NOT NULL,
        frecuencia enum('diario','lunes-viernes','personalizado') DEFAULT 'diario',
        activo tinyint(1) DEFAULT 1,
        ultimo_recordatorio datetime DEFAULT NULL,
        ultima_alerta datetime DEFAULT NULL,
        PRIMARY KEY (id_horario),
        KEY id_medicamento (id_medicamento)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "historial_tomas" => "CREATE TABLE IF NOT EXISTS historial_tomas (
        id_registro int(11) NOT NULL AUTO_INCREMENT,
        id_horario int(11) NOT NULL,
        fecha_hora_toma timestamp NOT NULL DEFAULT current_timestamp(),
        estado enum('tomado','omitido','pospuesto') NOT NULL,
        PRIMARY KEY (id_registro),
        KEY id_horario (id_horario)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "vinculaciones" => "CREATE TABLE IF NOT EXISTS vinculaciones (
        id_vinculacion int(11) NOT NULL AUTO_INCREMENT,
        id_paciente int(11) NOT NULL,
        id_cuidador int(11) NOT NULL,
        confirmado tinyint(1) DEFAULT 0,
        fecha_vinculacion timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (id_vinculacion),
        KEY id_paciente (id_paciente),
        KEY id_cuidador (id_cuidador)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    
    "recordatorios_whatsapp" => "CREATE TABLE IF NOT EXISTS recordatorios_whatsapp (
        id_recordatorio int(11) NOT NULL AUTO_INCREMENT,
        id_horario int(11) NOT NULL,
        id_usuario int(11) NOT NULL,
        mensaje text NOT NULL,
        fecha_envio timestamp NOT NULL DEFAULT current_timestamp(),
        estado enum('enviado','entregado','leido','confirmado') DEFAULT 'enviado',
        token_confirmacion varchar(100) NOT NULL,
        PRIMARY KEY (id_recordatorio),
        KEY id_horario (id_horario),
        KEY id_usuario (id_usuario)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

$createdTables = 0;
foreach ($tables as $tableName => $sql) {
    try {
        $pdo->exec($sql);
        echo logMessage("✅ Tabla '$tableName' creada", 'success');
        $createdTables++;
        
        // Pequeña pausa para evitar sobrecarga
        usleep(100000); // 100ms
        
    } catch (Exception $e) {
        echo logMessage("❌ Error creando tabla '$tableName': " . $e->getMessage(), 'error');
    }
}

echo "</div></div></div>";

// =============================================================================
// PASO 6: CLAVES FORÁNEAS
// =============================================================================
echo "<div class='step-card card'>
        <div class='card-body'>
            <h4 class='card-title'>Paso 6: Configurando relaciones entre tablas</h4>
            <div class='log-output'>";

// Deshabilitar temporalmente claves foráneas para evitar errores
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

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
        // Primero eliminar la clave si existe
        $pdo->exec("ALTER TABLE `$table` DROP FOREIGN KEY IF EXISTS `fk_{$table}_{$column}`");
        
        // Crear nueva clave
        $sql = "ALTER TABLE `$table` 
                ADD CONSTRAINT `fk_{$table}_{$column}` 
                FOREIGN KEY (`$column`) 
                REFERENCES `$refTable` (`$refColumn`) 
                ON DELETE $onDelete 
                ON UPDATE CASCADE";
        
        $pdo->exec($sql);
        echo logMessage("✅ Relación $table.$column → $refTable.$refColumn", 'success');
        $addedKeys++;
        
    } catch (Exception $e) {
        echo logMessage("⚠️ Relación $table.$column: " . $e->getMessage(), 'warning');
    }
}

// Rehabilitar claves foráneas
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "</div></div></div>";

// =============================================================================
// PASO 7: DATOS INICIALES
// =============================================================================
echo "<div class='step-card card'>
        <div class='card-body'>
            <h4 class='card-title'>Paso 7: Insertando datos iniciales</h4>
            <div class='log-output'>";

try {
    // Verificar si ya hay usuarios
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM usuarios");
    $userCount = $stmt->fetch()['count'];
    
    if ($userCount == 0) {
        echo logMessage("Insertando datos de prueba...", 'info');
        
        // Usar password_hash para contraseñas reales
        $password_hash = password_hash('password123', PASSWORD_DEFAULT);
        
        // Usuarios
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
        
        echo logMessage("✅ 5 usuarios de prueba insertados", 'success');
        
        // Vincular usuarios
        $vinculaciones = [
            [1, 2, 1], // María (paciente) - Carlos (cuidador)
            [3, 4, 1], // Ana - Javier
            [5, 2, 1]  // Isabel - Carlos
        ];
        
        foreach ($vinculaciones as $vin) {
            $stmt = $pdo->prepare("INSERT INTO vinculaciones (id_paciente, id_cuidador, confirmado) VALUES (?, ?, ?)");
            $stmt->execute($vin);
        }
        
        echo logMessage("✅ 3 vinculaciones creadas", 'success');
        
        // Medicamentos
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
            
            // Crear horarios
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
        
        echo logMessage("✅ 4 medicamentos con horarios creados", 'success');
        
        // Insertar algunas tomas de ejemplo
        $pdo->exec("INSERT INTO historial_tomas (id_horario, estado) VALUES (1, 'tomado')");
        $pdo->exec("INSERT INTO historial_tomas (id_horario, estado) VALUES (2, 'tomado')");
        $pdo->exec("INSERT INTO historial_tomas (id_horario, estado) VALUES (3, 'tomado')");
        
        echo logMessage("✅ 3 registros de tomas creados", 'success');
        
    } else {
        echo logMessage("Ya existen $userCount usuarios. Saltando inserción de datos de prueba.", 'info');
    }
    
} catch (Exception $e) {
    echo logMessage("❌ Error insertando datos: " . $e->getMessage(), 'error');
}

echo "</div></div></div>";

// =============================================================================
// RESUMEN FINAL
// =============================================================================
echo "<div class='card mt-4'>
        <div class='card-body text-center'>
            <h2 class='text-success mb-4'>🎉 ¡Configuración completada!</h2>
            
            <div class='row mb-4'>
                <div class='col-md-4'>
                    <div class='card bg-light'>
                        <div class='card-body'>
                            <h5>Tablas creadas</h5>
                            <h2 class='text-primary'>$createdTables</h2>
                            <small>de " . count($tables) . " totales</small>
                        </div>
                    </div>
                </div>
                <div class='col-md-4'>
                    <div class='card bg-light'>
                        <div class='card-body'>
                            <h5>Relaciones</h5>
                            <h2 class='text-primary'>$addedKeys</h2>
                            <small>claves foráneas</small>
                        </div>
                    </div>
                </div>
                <div class='col-md-4'>
                    <div class='card bg-light'>
                        <div class='card-body'>
                            <h5>Entorno</h5>
                            <h4>" . ($isRailway ? 'Railway 🚄' : 'Local 🖥️') . "</h4>
                            <small>" . getenv('RAILWAY_PUBLIC_DOMAIN') ?: 'localhost' . "</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class='alert alert-info'>
                <h5>📋 Resumen de la base de datos</h5>
                <p><strong>Base de datos:</strong> $database</p>
                <p><strong>Host:</strong> $host:$port</p>
                <p><strong>Usuario:</strong> $username</p>
            </div>
            
            <div class='d-grid gap-2 d-md-flex justify-content-center mt-4'>
                <a href='index.php' class='btn btn-success btn-lg'>
                    🚀 Ir al sistema MediRecord
                </a>
                <a href='test_variables.php' class='btn btn-outline-primary btn-lg'>
                    🔧 Probar conexión
                </a>
                <a href='?force=1' class='btn btn-outline-warning btn-lg'>
                    🔄 Reiniciar configuración
                </a>
            </div>
            
            <div class='alert alert-warning mt-4'>
                <h5>👥 Credenciales de prueba:</h5>
                <p><strong>Paciente:</strong> maria.gonzalez@email.com / password123</p>
                <p><strong>Cuidador:</strong> carlos.rodriguez@email.com / password123</p>
                <p class='mb-0'><small>Todas las contraseñas son: <code>password123</code></small></p>
            </div>
            
            <div class='text-muted mt-3'>
                <p>MediRecord v2.0 &copy; " . date('Y') . " - Sistema de recordatorio de medicamentos</p>
            </div>
        </div>
    </div>
    </div>
    </div>
    </div>

    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js'></script>
    <script>
        // Auto-scroll al final de los logs
        document.querySelectorAll('.log-output').forEach(function(element) {
            element.scrollTop = element.scrollHeight;
        });
        
        // Confirmar reinicio
        document.querySelector('a[href*=\"force=1\"]').addEventListener('click', function(e) {
            if (!confirm('⚠️ ¿Estás seguro de reiniciar la configuración?\\n\\nSe eliminarán todas las tablas y datos existentes.')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>";
