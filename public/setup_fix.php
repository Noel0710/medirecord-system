<?php
// setup_fix.php - Archivo NUEVO para reemplazar setup_database.php
// Copia TODO este contenido

echo "<!DOCTYPE html>
<html>
<head>
    <title>Fix MediRecord Database</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f0f0f0; }
        .container { max-width: 800px; margin: auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; }
        pre { background: #333; color: #fff; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🛠️ Fix MediRecord Database Setup</h1>
    
    <h2>Paso 1: Ver contenido actual de setup_database.php</h2>";

// Mostrar contenido actual
$currentFile = __DIR__ . '/setup_database.php';
if (file_exists($currentFile)) {
    echo "<p>Contenido actual (primeras 10 líneas):</p><pre>";
    $lines = file($currentFile);
    for ($i = 0; $i < 10 && $i < count($lines); $i++) {
        echo htmlspecialchars($lines[$i]);
    }
    echo "</pre>";
    
    // Buscar require_once
    $hasRequire = false;
    foreach ($lines as $line) {
        if (strpos($line, 'require_once') !== false) {
            $hasRequire = true;
            break;
        }
    }
    
    if ($hasRequire) {
        echo "<div class='error'>❌ ERROR: setup_database.php todavía tiene require_once</div>";
    } else {
        echo "<div class='success'>✅ OK: No tiene require_once</div>";
    }
} else {
    echo "<div class='error'>❌ ERROR: setup_database.php no existe</div>";
}

echo "<h2>Paso 2: Reemplazar setup_database.php</h2>";

// Contenido CORRECTO del nuevo setup_database.php
$newContent = '<?php
// setup_database.php - VERSIÓN CORREGIDA - NO REQUIRE_ONCE
// Este archivo es AUTÓNOMO - No necesita otros archivos

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset=\"UTF-8\">
    <title>MediRecord Setup - Fixed Version</title>
    <link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css\" rel=\"stylesheet\">
    <style>
        body { background: #f8f9fa; padding: 20px; }
        .card { margin-bottom: 20px; }
        .log { background: #212529; color: #fff; padding: 15px; border-radius: 5px; font-family: monospace; }
    </style>
</head>
<body>
<div class=\"container\">
    <div class=\"card\">
        <div class=\"card-header bg-primary text-white\">
            <h2>🚀 MediRecord - Database Setup (Fixed)</h2>
        </div>
        <div class=\"card-body\">';

// Continuación del contenido
$newContent .= '
<?php
// Detectar entorno
$isRailway = getenv(\'MYSQLHOST\') !== false;

echo "<h4>Entorno: " . ($isRailway ? "Railway" : "Local") . "</h4>";

// Configurar conexión
if ($isRailway) {
    $host = getenv(\'MYSQLHOST\') ?: \'localhost\';
    $port = getenv(\'MYSQLPORT\') ?: \'3306\';
    $dbname = getenv(\'MYSQLDATABASE\') ?: \'railway\';
    $user = getenv(\'MYSQLUSER\') ?: \'root\';
    $pass = getenv(\'MYSQLPASSWORD\') ?: \'\';
} else {
    $host = \'localhost\'; $port = \'3306\'; $dbname = \'medirecord_db\'; $user = \'root\'; $pass = \'\';
}

try {
    // Primero conectar sin base de datos
    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "<div class=\"alert alert-success\">✅ Conectado a MySQL en $host:$port</div>";
    
    // Verificar/crear base de datos
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4");
    $pdo->exec("USE `$dbname`");
    echo "<div class=\"alert alert-success\">✅ Usando base de datos: $dbname</div>";
    
    // Crear tablas básicas
    $tables = [
        "usuarios" => "CREATE TABLE IF NOT EXISTS usuarios (
            id_usuario INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            tipo ENUM(\'paciente\',\'cuidador\') NOT NULL,
            fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            telefono VARCHAR(20),
            telefono_verificado TINYINT DEFAULT 0
        )",
        
        "medicamentos" => "CREATE TABLE IF NOT EXISTS medicamentos (
            id_medicamento INT AUTO_INCREMENT PRIMARY KEY,
            id_usuario INT NOT NULL,
            nombre_medicamento VARCHAR(100) NOT NULL,
            dosis VARCHAR(50) NOT NULL,
            instrucciones TEXT,
            fecha_agregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",
        
        "horarios" => "CREATE TABLE IF NOT EXISTS horarios (
            id_horario INT AUTO_INCREMENT PRIMARY KEY,
            id_medicamento INT NOT NULL,
            hora TIME NOT NULL,
            activo TINYINT DEFAULT 1
        )"
    ];
    
    foreach ($tables as $name => $sql) {
        $pdo->exec($sql);
        echo "<div class=\"alert alert-info\">✅ Tabla \'$name\' creada</div>";
    }
    
    echo "<div class=\"alert alert-success\"><h4>🎉 ¡Base de datos configurada exitosamente!</h4></div>";
    echo "<a href=\"index.php\" class=\"btn btn-success\">Ir al sistema</a>";
    
} catch (Exception $e) {
    echo "<div class=\"alert alert-danger\">❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<pre>Variables detectadas:\n";
    echo "MYSQLHOST: " . (getenv(\'MYSQLHOST\') ?: \'NO\') . "\n";
    echo "MYSQLUSER: " . (getenv(\'MYSQLUSER\') ?: \'NO\') . "\n";
    echo "MYSQLPASSWORD: " . (getenv(\'MYSQLPASSWORD\') ? \'SI\' : \'NO\') . "\n";
    echo "</pre>";
}
?>
        </div>
    </div>
</div>
</body>
</html>";

// Intentar reemplazar el archivo
if (isset($_POST['replace'])) {
    if (file_put_contents($currentFile, $newContent)) {
        echo "<div class='success'>✅ setup_database.php REEMPLAZADO exitosamente</div>";
        echo "<p>Ahora accede a: <a href='setup_database.php' target='_blank'>setup_database.php</a></p>";
    } else {
        echo "<div class='error'>❌ Error al reemplazar el archivo</div>";
    }
} else {
    echo "<form method='POST'>
        <p>Este botón reemplazará setup_database.php con la versión corregida:</p>
        <button type='submit' name='replace' class='btn btn-primary'>REEMPLAZAR setup_database.php</button>
    </form>";
}

echo "<h2>Paso 3: Crear config.php simple (si no existe)</h2>";

$configPath = __DIR__ . '/../config.php';
if (!file_exists($configPath)) {
    $configContent = '<?php
// config.php simple para MediRecord
$isRailway = getenv(\'MYSQLHOST\') !== false;

if ($isRailway) {
    $host = getenv(\'MYSQLHOST\') ?: \'localhost\';
    $port = getenv(\'MYSQLPORT\') ?: \'3306\';
    $dbname = getenv(\'MYSQLDATABASE\') ?: \'railway\';
    $user = getenv(\'MYSQLUSER\') ?: \'root\';
    $pass = getenv(\'MYSQLPASSWORD\') ?: \'\';
} else {
    $host = \'localhost\'; $port = \'3306\'; $dbname = \'medirecord_db\'; $user = \'root\'; $pass = \'\';
}

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Error DB: " . $e->getMessage());
}

session_start();
?>';

    if (file_put_contents($configPath, $configContent)) {
        echo "<div class='success'>✅ config.php creado en la raíz</div>";
    }
} else {
    echo "<div class='info'>ℹ️ config.php ya existe</div>";
}

echo "<hr>
    <h3>Enlaces útiles:</h3>
    <ul>
        <li><a href='setup_database.php' target='_blank'>setup_database.php actual</a></li>
        <li><a href='test_variables.php' target='_blank'>test_variables.php</a></li>
        <li><a href='index.php' target='_blank'>index.php</a></li>
    </ul>
</div>
</body>
</html>";
?>
