<?php
// crear_tablas.php - Crear todas las tablas de MediRecord automáticamente
echo "<!DOCTYPE html>";
echo "<html>";
echo "<head>";
echo "<title>Crear Tablas - MediRecord</title>";
echo "<style>";
echo "body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }";
echo ".container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }";
echo ".success { color: green; background: #e8f5e9; padding: 15px; border-radius: 5px; margin: 10px 0; }";
echo ".error { color: red; background: #ffebee; padding: 15px; border-radius: 5px; margin: 10px 0; }";
echo ".warning { color: orange; background: #fff3e0; padding: 15px; border-radius: 5px; margin: 10px 0; }";
echo "pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }";
echo "button { background: #4CAF50; color: white; border: none; padding: 12px 24px; border-radius: 5px; cursor: pointer; font-size: 16px; }";
echo "button:hover { background: #45a049; }";
echo "</style>";
echo "</head>";
echo "<body>";
echo "<div class='container'>";
echo "<h1>📊 Configuración de Base de Datos MediRecord</h1>";

// Verificar si ya existe una conexión en config.php
if (file_exists('config.php')) {
    try {
        require_once 'config.php';
        echo "<div class='success'>✅ config.php cargado correctamente</div>";
    } catch (Exception $e) {
        echo "<div class='warning'>⚠️ No se pudo cargar config.php: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Obtener credenciales de Railway
$host = getenv('MYSQLHOST');
$port = getenv('MYSQLPORT');
$dbname = getenv('MYSQLDATABASE');
$username = getenv('MYSQLUSER');
$password = getenv('MYSQLPASSWORD');

echo "<h2>🔍 Verificando variables de entorno...</h2>";
echo "<pre>";
echo "MYSQLHOST: " . ($host ? "✅ $host" : "❌ NO DEFINIDO") . "\n";
echo "MYSQLPORT: " . ($port ? "✅ $port" : "❌ NO DEFINIDO") . "\n";
echo "MYSQLDATABASE: " . ($dbname ? "✅ $dbname" : "❌ NO DEFINIDO") . "\n";
echo "MYSQLUSER: " . ($username ? "✅ $username" : "❌ NO DEFINIDO") . "\n";
echo "MYSQLPASSWORD: " . ($password ? "✅ DEFINIDO" : "❌ NO DEFINIDO") . "\n";
echo "</pre>";

// Si no hay variables de entorno, usar valores por defecto
if (!$host || !$username) {
    echo "<div class='warning'>⚠️ Usando valores por defecto (localhost)</div>";
    $host = 'localhost';
    $port = 3306;
    $dbname = 'medirecord_db';
    $username = 'root';
    $password = '';
}

// Intentar conexión
try {
    echo "<h2>🔗 Conectando a MySQL...</h2>";
    
    // Primero intentar conectar sin base de datos
    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 10
    ]);
    
    echo "<div class='success'>✅ Conexión exitosa al servidor MySQL</div>";
    
    // Crear base de datos si no existe
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<div class='success'>✅ Base de datos '$dbname' verificada/creada</div>";
    
    // Usar la base de datos
    $pdo->exec("USE `$dbname`");
    echo "<div class='success'>✅ Usando base de datos '$dbname'</div>";
    
    // ==============================================
    // SQL COMPLETO PARA CREAR TABLAS
    // ==============================================
    $sql_tablas = "
    -- 1. TABLA USUARIOS
    CREATE TABLE IF NOT EXISTS usuarios (
        id_usuario INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        telefono VARCHAR(20),
        tipo ENUM('paciente', 'cuidador') DEFAULT 'paciente',
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        activo BOOLEAN DEFAULT TRUE
    );
    
    -- 2. TABLA MEDICAMENTOS
    CREATE TABLE IF NOT EXISTS medicamentos (
        id_medicamento INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        nombre_medicamento VARCHAR(100) NOT NULL,
        dosis VARCHAR(50),
        instrucciones TEXT,
        agregado_por INT,
        fecha_agregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
    );
    
    -- 3. TABLA HORARIOS
    CREATE TABLE IF NOT EXISTS horarios (
        id_horario INT AUTO_INCREMENT PRIMARY KEY,
        id_medicamento INT NOT NULL,
        hora TIME NOT NULL,
        frecuencia ENUM('diario', 'lunes-viernes', 'semanal', 'personalizado') DEFAULT 'diario',
        activo BOOLEAN DEFAULT TRUE,
        FOREIGN KEY (id_medicamento) REFERENCES medicamentos(id_medicamento) ON DELETE CASCADE,
        UNIQUE KEY (id_medicamento, hora)
    );
    
    -- 4. TABLA HISTORIAL_TOMAS
    CREATE TABLE IF NOT EXISTS historial_tomas (
        id_registro INT AUTO_INCREMENT PRIMARY KEY,
        id_horario INT NOT NULL,
        fecha_hora_toma TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        estado ENUM('tomado', 'omitido', 'pospuesto') NOT NULL,
        FOREIGN KEY (id_horario) REFERENCES horarios(id_horario) ON DELETE CASCADE
    );
    
    -- 5. TABLA RECORDATORIOS_WHATSAPP
    CREATE TABLE IF NOT EXISTS recordatorios_whatsapp (
        id_recordatorio INT AUTO_INCREMENT PRIMARY KEY,
        id_horario INT NOT NULL,
        id_usuario INT NOT NULL,
        mensaje TEXT NOT NULL,
        fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        estado ENUM('enviado', 'entregado', 'leido', 'confirmado', 'fallido') DEFAULT 'enviado',
        token_confirmacion VARCHAR(100),
        FOREIGN KEY (id_horario) REFERENCES horarios(id_horario) ON DELETE CASCADE,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
    );
    
    -- 6. TABLA VINCULACIONES
    CREATE TABLE IF NOT EXISTS vinculaciones (
        id_vinculacion INT AUTO_INCREMENT PRIMARY KEY,
        id_paciente INT NOT NULL,
        id_cuidador INT NOT NULL,
        confirmado BOOLEAN DEFAULT FALSE,
        fecha_vinculacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY (id_paciente, id_cuidador)
    );
    ";
    
    echo "<h2>📊 Creando tablas...</h2>";
    
    // Separar y ejecutar cada sentencia SQL
    $statements = explode(';', $sql_tablas);
    $tablas_creadas = 0;
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            try {
                $pdo->exec($statement . ';');
                if (strpos($statement, 'CREATE TABLE') !== false) {
                    $tablas_creadas++;
                    echo "<div class='success'>✅ Tabla creada: " . extractTableName($statement) . "</div>";
                }
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'already exists') !== false) {
                    echo "<div class='warning'>⚠️ Tabla ya existe: " . extractTableName($statement) . "</div>";
                } else {
                    echo "<div class='warning'>⚠️ Error en: " . substr($statement, 0, 50) . "...<br>" . htmlspecialchars($e->getMessage()) . "</div>";
                }
            }
        }
    }
    
    echo "<div class='success'>🎉 Total tablas creadas/verificadas: $tablas_creadas</div>";
    
    // ==============================================
    // INSERTAR DATOS DE PRUEBA
    // ==============================================
    echo "<h2>👥 Insertando datos de prueba...</h2>";
    
    // Hash de la contraseña "password123"
    $hashed_password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
    
    // Insertar usuarios
    $sql_usuarios = "INSERT IGNORE INTO usuarios (nombre, email, password, telefono, tipo) VALUES 
                    (?, ?, ?, ?, ?), 
                    (?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql_usuarios);
    $stmt->execute([
        'Juan Pérez', 'paciente@medirecord.com', $hashed_password, '5215512345678', 'paciente',
        'María García', 'cuidador@medirecord.com', $hashed_password, '5215512345679', 'cuidador'
    ]);
    echo "<div class='success'>✅ 2 usuarios de prueba insertados</div>";
    
    // Insertar medicamento
    $sql_medicamento = "INSERT IGNORE INTO medicamentos (id_usuario, nombre_medicamento, dosis, instrucciones) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql_medicamento);
    $stmt->execute([1, 'Paracetamol', '1 tableta', 'Tomar cada 8 horas con alimentos']);
    echo "<div class='success'>✅ Medicamento de prueba insertado</div>";
    
    // Insertar horario (para dentro de 5 minutos)
    $hora_prueba = date('H:i', strtotime('+5 minutes'));
    $sql_horario = "INSERT IGNORE INTO horarios (id_medicamento, hora, activo) VALUES (?, ?, ?)";
    $stmt = $pdo->prepare($sql_horario);
    $stmt->execute([1, $hora_prueba, 1]);
    echo "<div class='success'>✅ Horario de prueba para las $hora_prueba</div>";
    
    // ==============================================
    // VERIFICAR TODO
    // ==============================================
    echo "<h2>🔍 Verificando creación...</h2>";
    
    // Mostrar todas las tablas
    $stmt = $pdo->query("SHOW TABLES");
    $tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<div class='success'>📋 Tablas en la base de datos:</div>";
    echo "<ul>";
    foreach ($tablas as $tabla) {
        echo "<li>✅ $tabla</li>";
    }
    echo "</ul>";
    
    // Contar registros
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM usuarios");
    $usuarios = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM medicamentos");
    $medicamentos = $stmt->fetch()['total'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM horarios");
    $horarios = $stmt->fetch()['total'];
    
    echo "<div class='success'>📊 Estadísticas:</div>";
    echo "<ul>";
    echo "<li>👥 Usuarios: $usuarios</li>";
    echo "<li>💊 Medicamentos: $medicamentos</li>";
    echo "<li>⏰ Horarios: $horarios</li>";
    echo "</ul>";
    
    // ==============================================
    // MOSTRAR CREDENCIALES
    // ==============================================
    echo "<h2>🔑 Credenciales de prueba:</h2>";
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
    echo "<h3>PACIENTE:</h3>";
    echo "<pre>";
    echo "Email: paciente@medirecord.com\n";
    echo "Contraseña: password123\n";
    echo "Teléfono: 5215512345678\n";
    echo "</pre>";
    
    echo "<h3>CUIDADOR:</h3>";
    echo "<pre>";
    echo "Email: cuidador@medirecord.com\n";
    echo "Contraseña: password123\n";
    echo "Teléfono: 5215512345679\n";
    echo "</pre>";
    echo "</div>";
    
    echo "<h2 style='color: green;'>🎉 ¡BASE DE DATOS CONFIGURADA EXITOSAMENTE!</h2>";
    
    echo "<div style='margin: 20px 0;'>";
    echo "<a href='index.php' style='background: #4CAF50; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-size: 18px;'>🚀 Ir al Login</a>";
    echo "</div>";
    
    // Advertencia de seguridad
    echo "<div class='warning' style='margin-top: 30px;'>";
    echo "<h3>⚠️ IMPORTANTE:</h3>";
    echo "<p>Este archivo <strong>debe ser eliminado</strong> después de usar por seguridad.</p>";
    echo "<p>Ejecuta estos comandos:</p>";
    echo "<pre>";
    echo "git rm crear_tablas.php\n";
    echo "git commit -m 'Eliminar script de configuración'\n";
    echo "git push\n";
    echo "</pre>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<h3>❌ ERROR DE CONEXIÓN</h3>";
    echo "<p><strong>Mensaje:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    
    echo "<h4>🔧 Solución paso a paso:</h4>";
    echo "<ol>";
    echo "<li>Ve a Railway → tu proyecto → Variables</li>";
    echo "<li>Asegúrate de tener estas 5 variables:</li>";
    echo "<pre>";
    echo "MYSQLHOST=xxxx.railway.app\n";
    echo "MYSQLPORT=xxxx\n";
    echo "MYSQLDATABASE=railway\n";
    echo "MYSQLUSER=root\n";
    echo "MYSQLPASSWORD=xxxx\n";
    echo "</pre>";
    echo "<li>Espera 2 minutos después de agregar variables</li>";
    echo "<li>Verifica que la base de datos está en estado 'Running'</li>";
    echo "</ol>";
    
    echo "<h4>💡 Alternativa:</h4>";
    echo "<p>Si no puedes configurar Railway, puedes:</p>";
    echo "<ol>";
    echo "<li>Modificar config.php para usar localhost</li>";
    echo "<li>Usar XAMPP localmente</li>";
    echo "<li>O usar otro hosting con MySQL</li>";
    echo "</ol>";
    echo "</div>";
}

echo "</div>"; // Cierra container
echo "</body>";
echo "</html>";

// Función auxiliar para extraer nombre de tabla
function extractTableName($sql) {
    if (preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`?(\w+)`?/i', $sql, $matches)) {
        return $matches[1];
    }
    return 'Desconocida';
}
?>
