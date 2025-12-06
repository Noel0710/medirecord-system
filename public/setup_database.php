<?php
// setup_database.php - Versión simplificada para Railway


echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Configuración de Base de Datos - MediRecord</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .container { max-width: 1000px; margin: 40px auto; background: white; border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden; }
        .header { background: linear-gradient(to right, #4f46e5, #7c3aed); color: white; padding: 30px; text-align: center; }
        .header h1 { font-size: 2.5rem; margin-bottom: 10px; }
        .header p { opacity: 0.9; }
        .content { padding: 40px; }
        .step { background: #f8fafc; border-radius: 10px; padding: 25px; margin-bottom: 25px; border-left: 5px solid #4f46e5; }
        .step h3 { color: #4f46e5; margin-bottom: 15px; font-size: 1.3rem; }
        .success { color: #10b981; background: #d1fae5; padding: 10px 15px; border-radius: 6px; margin: 10px 0; }
        .error { color: #ef4444; background: #fee2e2; padding: 10px 15px; border-radius: 6px; margin: 10px 0; }
        .warning { color: #f59e0b; background: #fef3c7; padding: 10px 15px; border-radius: 6px; margin: 10px 0; }
        .info-box { background: #e0e7ff; border-radius: 8px; padding: 20px; margin: 20px 0; }
        pre { background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 8px; overflow-x: auto; margin: 15px 0; font-family: 'Courier New', monospace; }
        .btn { background: #4f46e5; color: white; border: none; padding: 12px 24px; border-radius: 8px; font-size: 1rem; cursor: pointer; transition: all 0.3s; text-decoration: none; display: inline-block; }
        .btn:hover { background: #4338ca; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(79, 70, 229, 0.4); }
        .btn-danger { background: #ef4444; }
        .btn-danger:hover { background: #dc2626; }
        .btn-success { background: #10b981; }
        .btn-success:hover { background: #059669; }
        .footer { text-align: center; padding: 20px; color: #64748b; font-size: 0.9rem; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🚀 MediRecord - Configuración</h1>
            <p>Sistema de recordatorio de medicamentos para adultos mayores</p>
        </div>
        
        <div class='content'>";

// =============================================================================
// CONFIGURACIÓN DE CONEXIÓN DIRECTA (sin require_once)
// =============================================================================

// Detectar si estamos en Railway
$isRailway = getenv('MYSQLHOST') !== false || 
             getenv('RAILWAY_ENVIRONMENT') !== false ||
             getenv('RAILWAY_PUBLIC_DOMAIN') !== false;

// Configuración de base de datos
if ($isRailway) {
    $host = getenv('MYSQLHOST') ?: 'localhost';
    $port = getenv('MYSQLPORT') ?: '3306';
    $database = getenv('MYSQLDATABASE') ?: 'railway';
    $username = getenv('MYSQLUSER') ?: 'root';
    $password = getenv('MYSQLPASSWORD') ?: '';
    
    // Si MYSQL_URL está disponible
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
    // Configuración local
    $host = 'localhost';
    $port = '3306';
    $database = 'medirecord_db';
    $username = 'root';
    $password = '';
}

echo "<div class='step'>
        <h3>Paso 1: Verificación del entorno</h3>
        <p><strong>Entorno detectado:</strong> " . ($isRailway ? 'Railway 🚄' : 'Local 🖥️') . "</p>
        <p><strong>Variables de entorno:</strong></p>
        <pre>";
echo "MYSQLHOST: " . (getenv('MYSQLHOST') ?: 'NO DEFINIDO') . "\n";
echo "MYSQLPORT: " . (getenv('MYSQLPORT') ?: 'NO DEFINIDO') . "\n";
echo "MYSQLDATABASE: " . (getenv('MYSQLDATABASE') ?: 'NO DEFINIDO') . "\n";
echo "MYSQLUSER: " . (getenv('MYSQLUSER') ?: 'NO DEFINIDO') . "\n";
echo "MYSQLPASSWORD: " . (getenv('MYSQLPASSWORD') ? '***' . substr(getenv('MYSQLPASSWORD'), -4) : 'NO DEFINIDO') . "\n";
echo "MYSQL_URL: " . (getenv('MYSQL_URL') ? 'DEFINIDO' : 'NO DEFINIDO') . "\n";
echo "RAILWAY_PUBLIC_DOMAIN: " . (getenv('RAILWAY_PUBLIC_DOMAIN') ?: 'NO DEFINIDO') . "\n";
echo "</pre>
    </div>";

// =============================================================================
// INTENTAR CONEXIÓN
// =============================================================================

$pdo = null;
try {
    echo "<div class='step'>
            <h3>Paso 2: Conexión a MySQL</h3>";
    
    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
    
    // Intentar conexión sin especificar base de datos primero
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    echo "<div class='success'>✅ Conexión exitosa al servidor MySQL</div>";
    
    // Verificar si la base de datos existe
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$database'");
    $dbExists = $stmt->fetch();
    
    if (!$dbExists) {
        echo "<div class='warning'>⚠️ La base de datos '$database' no existe. Creando...</div>";
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "<div class='success'>✅ Base de datos '$database' creada exitosamente</div>";
    }
    
    // Seleccionar la base de datos
    $pdo->exec("USE `$database`");
    echo "<div class='success'>✅ Conectado a la base de datos: $database</div>";
    
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Error de conexión: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='info-box'>
            <h4>Solución de problemas:</h4>
            <p>1. Verifica que hayas añadido un servicio MySQL en Railway</p>
            <p>2. Asegúrate de que las variables de entorno estén configuradas</p>
            <p>3. En Railway, ve a tu proyecto → Variables</p>
            <p>4. Deben aparecer automáticamente MYSQLHOST, MYSQLUSER, MYSQLPASSWORD, etc.</p>
            <p>5. Si estás localmente, asegúrate de que XAMPP/MAMP esté corriendo</p>
          </div>";
    echo "</div></div></div></body></html>";
    exit;
}

// =============================================================================
// VERIFICAR SI YA HAY TABLAS
// =============================================================================

$force = isset($_GET['force']) && $_GET['force'] == '1';

echo "<div class='step'>
        <h3>Paso 3: Verificación de tablas existentes</h3>";

try {
    $stmt = $pdo->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $tableCount = count($existingTables);
    
    if ($tableCount > 0) {
        echo "<div class='warning'>⚠️ Ya existen $tableCount tablas en la base de datos:</div>
              <ul>";
        foreach ($existingTables as $table) {
            echo "<li>$table</li>";
        }
        echo "</ul>";
        
        if (!$force) {
            echo "<p>¿Qué deseas hacer?</p>
                  <a href='?force=1' class='btn btn-danger'>Forzar recreación (borrará datos)</a>
                  <a href='index.php' class='btn'>Ir al inicio</a>
                  </div></div></div></body></html>";
            exit;
        } else {
            echo "<div class='warning'>⚠️ MODO FORZADO ACTIVADO - Se eliminarán tablas existentes</div>";
        }
    } else {
        echo "<div class='success'>✅ No hay tablas existentes. Creando estructura nueva...</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>Error verificando tablas: " . $e->getMessage() . "</div>";
}

echo "</div>";

// =============================================================================
// CREACIÓN DE TABLAS
// =============================================================================

echo "<div class='step'>
        <h3>Paso 4: Creando estructura de la base de datos</h3>";

// Array con las sentencias SQL para crear tablas
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

// Crear tablas
$createdTables = 0;
foreach ($tables as $tableName => $sql) {
    try {
        $pdo->exec($sql);
        echo "<div class='success'>✅ Tabla '$tableName' creada</div>";
        $createdTables++;
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error creando tabla '$tableName': " . $e->getMessage() . "</div>";
    }
}

echo "</div>";

// =============================================================================
// CLAVES FORÁNEAS
// =============================================================================

echo "<div class='step'>
        <h3>Paso 5: Configurando relaciones entre tablas</h3>";

$foreignKeys = [
    "ALTER TABLE medicamentos ADD CONSTRAINT medicamentos_ibfk_1 FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE",
    "ALTER TABLE medicamentos ADD CONSTRAINT medicamentos_ibfk_2 FOREIGN KEY (agregado_por) REFERENCES usuarios(id_usuario) ON DELETE SET NULL",
    "ALTER TABLE horarios ADD CONSTRAINT horarios_ibfk_1 FOREIGN KEY (id_medicamento) REFERENCES medicamentos(id_medicamento) ON DELETE CASCADE",
    "ALTER TABLE historial_tomas ADD CONSTRAINT historial_tomas_ibfk_1 FOREIGN KEY (id_horario) REFERENCES horarios(id_horario) ON DELETE CASCADE",
    "ALTER TABLE vinculaciones ADD CONSTRAINT vinculaciones_ibfk_1 FOREIGN KEY (id_paciente) REFERENCES usuarios(id_usuario) ON DELETE CASCADE",
    "ALTER TABLE vinculaciones ADD CONSTRAINT vinculaciones_ibfk_2 FOREIGN KEY (id_cuidador) REFERENCES usuarios(id_usuario) ON DELETE CASCADE",
    "ALTER TABLE recordatorios_whatsapp ADD CONSTRAINT recordatorios_whatsapp_ibfk_1 FOREIGN KEY (id_horario) REFERENCES horarios(id_horario) ON DELETE CASCADE",
    "ALTER TABLE recordatorios_whatsapp ADD CONSTRAINT recordatorios_whatsapp_ibfk_2 FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE"
];

$addedKeys = 0;
foreach ($foreignKeys as $fk) {
    try {
        $pdo->exec($fk);
        echo "<div class='success'>✅ Relación configurada</div>";
        $addedKeys++;
    } catch (Exception $e) {
        // Ignorar errores de claves ya existentes
        if (strpos($e->getMessage(), 'errno: 121') === false) {
            echo "<div class='warning'>⚠️ " . $e->getMessage() . "</div>";
        }
    }
}

echo "</div>";

// =============================================================================
// DATOS INICIALES
// =============================================================================

echo "<div class='step'>
        <h3>Paso 6: Insertando datos iniciales</h3>";

try {
    // Verificar si ya hay usuarios
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM usuarios");
    $userCount = $stmt->fetch()['count'];
    
    if ($userCount == 0) {
        // Insertar usuarios de prueba
        $users = [
            ['María González López', 'maria.gonzalez@email.com', '$2y$10$hashedpassword1', 'paciente', '+521234567890'],
            ['Carlos Rodríguez Pérez', 'carlos.rodriguez@email.com', '$2y$10$hashedpassword2', 'cuidador', '+521234567891'],
            ['Ana Martínez Sánchez', 'ana.martinez@email.com', '$2y$10$hashedpassword3', 'paciente', '+521234567892'],
            ['Javier López García', 'javier.lopez@email.com', '$2y$10$hashedpassword4', 'cuidador', '+521234567893'],
            ['Isabel Díaz Fernández', 'isabel.diaz@email.com', '$2y$10$hashedpassword5', 'paciente', '+521234567894']
        ];
        
        foreach ($users as $user) {
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, tipo, telefono) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute($user);
        }
        
        echo "<div class='success'>✅ 5 usuarios de prueba insertados</div>";
        
        // Insertar vinculaciones
        $vinculaciones = [
            [1, 2, 1], // María - Carlos
            [3, 4, 1], // Ana - Javier
            [5, 2, 1]  // Isabel - Carlos
        ];
        
        foreach ($vinculaciones as $vin) {
            $stmt = $pdo->prepare("INSERT INTO vinculaciones (id_paciente, id_cuidador, confirmado) VALUES (?, ?, ?)");
            $stmt->execute($vin);
        }
        
        echo "<div class='success'>✅ Vinculaciones creadas</div>";
        
        // Insertar medicamentos de ejemplo
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
            if ($med[0] == 1 && $med[1] == 'Losartán') {
                $pdo->exec("INSERT INTO horarios (id_medicamento, hora) VALUES ($medId, '08:00:00')");
                $pdo->exec("INSERT INTO horarios (id_medicamento, hora) VALUES ($medId, '20:00:00')");
            } elseif ($med[0] == 1 && $med[1] == 'Metformina') {
                $pdo->exec("INSERT INTO horarios (id_medicamento, hora) VALUES ($medId, '08:00:00')");
                $pdo->exec("INSERT INTO horarios (id_medicamento, hora) VALUES ($medId, '14:00:00')");
            } else {
                $pdo->exec("INSERT INTO horarios (id_medicamento, hora) VALUES ($medId, '07:00:00')");
            }
        }
        
        echo "<div class='success'>✅ Medicamentos y horarios creados</div>";
        
    } else {
        echo "<div class='info-box'>Ya existen $userCount usuarios en la base de datos. No se insertaron datos de prueba.</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ Error insertando datos: " . $e->getMessage() . "</div>";
}

echo "</div>";

// =============================================================================
// RESUMEN FINAL
// =============================================================================

echo "<div class='step'>
        <h3>🎉 ¡Configuración completada!</h3>
        <div class='success' style='font-size: 1.2rem; padding: 20px; text-align: center;'>
            <p><strong>Base de datos configurada exitosamente</strong></p>
            <p>Tablas creadas: $createdTables de " . count($tables) . "</p>
            <p>Relaciones configuradas: $addedKeys</p>
        </div>
        
        <div style='text-align: center; margin-top: 30px;'>
            <a href='index.php' class='btn btn-success' style='font-size: 1.2rem; padding: 15px 30px;'>
                🚀 Ir al sistema MediRecord
            </a>
        </div>
        
        <div class='info-box' style='margin-top: 30px;'>
            <h4>Información para Railway:</h4>
            <p><strong>URL del sistema:</strong> <a href='index.php' target='_blank'>index.php</a></p>
            <p><strong>Credenciales de prueba:</strong></p>
            <ul>
                <li><strong>Paciente:</strong> maria.gonzalez@email.com / password123</li>
                <li><strong>Cuidador:</strong> carlos.rodriguez@email.com / password123</li>
            </ul>
            <p><strong>Nota:</strong> Las contraseñas en la base de datos están hasheadas. Para pruebas locales, usa 'password123'</p>
        </div>
    </div>
    </div>
    
    <div class='footer'>
        <p>MediRecord v2.0 &copy; " . date('Y') . " - Sistema de recordatorio de medicamentos</p>
        <p>Entorno: " . ($isRailway ? 'Railway' : 'Local') . "</p>
    </div>
</div>
</body>
</html>";
?>
