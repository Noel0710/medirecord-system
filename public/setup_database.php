<?php
// setup_database.php - Versión COMPLETA y AUTÓNOMA
// NO usa require_once - NO depende de otros archivos

echo "<!DOCTYPE html>
<html lang='es'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Configuración de Base de Datos - MediRecord</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 20px; }
        .card { border-radius: 15px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); border: none; }
        .card-header { background: linear-gradient(to right, #4f46e5, #7c3aed); color: white; padding: 25px; border-radius: 15px 15px 0 0 !important; }
        .step-card { border-left: 5px solid #4f46e5; margin-bottom: 20px; }
        .log-output { background: #1e293b; color: #e2e8f0; padding: 15px; border-radius: 8px; font-family: monospace; max-height: 400px; overflow-y: auto; margin: 10px 0; }
        .text-success { color: #10b981 !important; }
        .text-danger { color: #ef4444 !important; }
        .text-warning { color: #f59e0b !important; }
    </style>
</head>
<body>
<div class='container'><div class='row justify-content-center'><div class='col-lg-10'><div class='card'>
<div class='card-header text-center'><h1 class='display-5'>🚀 MediRecord - Configuración</h1><p class='lead'>Sistema de recordatorio de medicamentos</p></div>
<div class='card-body'>";

// =============================================================================
// FUNCIÓN LOG
// =============================================================================
function logMsg($msg, $type = 'info') {
    $badge = $type == 'success' ? '✅' : ($type == 'error' ? '❌' : ($type == 'warning' ? '⚠️' : 'ℹ️'));
    $color = $type == 'success' ? 'text-success' : ($type == 'error' ? 'text-danger' : ($type == 'warning' ? 'text-warning' : 'text-info'));
    $time = date('H:i:s');
    return "<div class='$color'>[$time] $badge $msg</div>";
}

// =============================================================================
// PASO 1: DETECCIÓN
// =============================================================================
echo "<div class='step-card card'><div class='card-body'><h4>Paso 1: Detección del entorno</h4><div class='log-output'>";

$isRailway = getenv('MYSQLHOST') !== false;
echo logMsg("Entorno: " . ($isRailway ? 'Railway 🚄' : 'Local 🖥️'), 'info');
echo logMsg("PHP: " . PHP_VERSION, 'info');

$vars = ['MYSQLHOST','MYSQLPORT','MYSQLDATABASE','MYSQLUSER','MYSQLPASSWORD','MYSQL_URL','RAILWAY_PUBLIC_DOMAIN'];
foreach($vars as $var) {
    $val = getenv($var);
    echo logMsg("$var: " . ($val ? ($var=='MYSQLPASSWORD' ? '***' : $val) : 'NO DEFINIDO'), $val ? 'success' : 'warning');
}

echo "</div></div></div>";

// =============================================================================
// PASO 2: CONEXIÓN
// =============================================================================
echo "<div class='step-card card'><div class='card-body'><h4>Paso 2: Conexión MySQL</h4><div class='log-output'>";

if($isRailway) {
    $host = getenv('MYSQLHOST') ?: 'localhost';
    $port = getenv('MYSQLPORT') ?: '3306';
    $db = getenv('MYSQLDATABASE') ?: 'railway';
    $user = getenv('MYSQLUSER') ?: 'root';
    $pass = getenv('MYSQLPASSWORD') ?: '';
    $mysql_url = getenv('MYSQL_URL');
    if($mysql_url) {
        $url = parse_url($mysql_url);
        $host = $url['host'] ?? $host;
        $port = $url['port'] ?? $port;
        $db = isset($url['path']) ? ltrim($url['path'],'/') : $db;
        $user = $url['user'] ?? $user;
        $pass = $url['pass'] ?? $pass;
    }
} else {
    $host = 'localhost'; $port = '3306'; $db = 'medirecord_db'; $user = 'root'; $pass = '';
}

echo logMsg("Host: $host", 'info');
echo logMsg("Puerto: $port", 'info');
echo logMsg("BD: $db", 'info');
echo logMsg("Usuario: $user", 'info');

$pdo = null;
try {
    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo logMsg("✅ Conexión al servidor MySQL exitosa", 'success');
    
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$db'");
    if(!$stmt->fetch()) {
        echo logMsg("Creando base de datos '$db'...", 'warning');
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo logMsg("✅ Base de datos creada", 'success');
    }
    
    $pdo->exec("USE `$db`");
    echo logMsg("✅ Conectado a BD: $db", 'success');
    
} catch(PDOException $e) {
    echo logMsg("❌ Error conexión: " . $e->getMessage(), 'error');
    echo "<div class='alert alert-danger mt-3'><h5>Solución:</h5><ol>
          <li>Verifica servicio MySQL en Railway</li>
          <li>Ve a Railway → Variables</li>
          <li>Local: Asegúrate MySQL esté corriendo</li>
          </ol><a href='test_variables.php' class='btn btn-outline-danger btn-sm'>Probar variables</a></div>";
    echo "</div></div></div></div></div></div></div></body></html>";
    exit;
}

echo "</div></div></div>";

// =============================================================================
// PASO 3: TABLAS EXISTENTES
// =============================================================================
echo "<div class='step-card card'><div class='card-body'><h4>Paso 3: Tablas existentes</h4><div class='log-output'>";

$force = isset($_GET['force']) && $_GET['force'] == '1';
try {
    $stmt = $pdo->query("SHOW TABLES");
    $tablesExist = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $countTables = count($tablesExist);
    
    if($countTables > 0) {
        echo logMsg("⚠️ Existen $countTables tablas:", 'warning');
        foreach($tablesExist as $table) echo logMsg("- $table", 'info');
        
        if(!$force) {
            echo "</div><div class='mt-3'><p class='alert alert-warning'>Opciones:</p>
                  <a href='?force=1' class='btn btn-danger'>Forzar recreación</a>
                  <a href='index.php' class='btn btn-secondary'>Ir al inicio</a>
                  </div></div></div></div></div></div></div></body></html>";
            exit;
        } else {
            echo logMsg("MODO FORZADO ACTIVADO", 'warning');
        }
    } else {
        echo logMsg("✅ No hay tablas. Creando...", 'success');
    }
} catch(Exception $e) {
    echo logMsg("Error: " . $e->getMessage(), 'error');
}

echo "</div></div></div>";

// =============================================================================
// PASO 4: ELIMINAR TABLAS (si force)
// =============================================================================
if($force && $countTables > 0) {
    echo "<div class='step-card card'><div class='card-body'><h4>Paso 4: Eliminando tablas</h4><div class='log-output'>";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    foreach($tablesExist as $table) {
        try {
            $pdo->exec("DROP TABLE IF EXISTS `$table`");
            echo logMsg("✅ Tabla '$table' eliminada", 'success');
        } catch(Exception $e) {
            echo logMsg("❌ Error eliminando '$table': " . $e->getMessage(), 'error');
        }
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "</div></div></div>";
}

// =============================================================================
// PASO 5: CREAR TABLAS
// =============================================================================
echo "<div class='step-card card'><div class='card-body'><h4>Paso 5: Creando tablas</h4><div class='log-output'>";

$tablasSQL = [
    "usuarios" => "CREATE TABLE IF NOT EXISTS usuarios (
        id_usuario int AUTO_INCREMENT PRIMARY KEY,
        nombre varchar(100) NOT NULL,
        email varchar(150) NOT NULL UNIQUE,
        password varchar(255) NOT NULL,
        tipo enum('paciente','cuidador') NOT NULL,
        fecha_registro timestamp DEFAULT CURRENT_TIMESTAMP,
        telefono varchar(20),
        whatsapp_token varchar(100),
        telefono_verificado tinyint DEFAULT 0
    ) ENGINE=InnoDB CHARSET=utf8mb4",
    
    "medicamentos" => "CREATE TABLE IF NOT EXISTS medicamentos (
        id_medicamento int AUTO_INCREMENT PRIMARY KEY,
        id_usuario int NOT NULL,
        nombre_medicamento varchar(100) NOT NULL,
        dosis varchar(50) NOT NULL,
        instrucciones text,
        agregado_por int,
        fecha_agregado timestamp DEFAULT CURRENT_TIMESTAMP,
        KEY (id_usuario),
        KEY (agregado_por)
    ) ENGINE=InnoDB CHARSET=utf8mb4",
    
    "horarios" => "CREATE TABLE IF NOT EXISTS horarios (
        id_horario int AUTO_INCREMENT PRIMARY KEY,
        id_medicamento int NOT NULL,
        hora time NOT NULL,
        frecuencia enum('diario','lunes-viernes','personalizado') DEFAULT 'diario',
        activo tinyint DEFAULT 1,
        ultimo_recordatorio datetime,
        ultima_alerta datetime,
        KEY (id_medicamento)
    ) ENGINE=InnoDB CHARSET=utf8mb4",
    
    "historial_tomas" => "CREATE TABLE IF NOT EXISTS historial_tomas (
        id_registro int AUTO_INCREMENT PRIMARY KEY,
        id_horario int NOT NULL,
        fecha_hora_toma timestamp DEFAULT CURRENT_TIMESTAMP,
        estado enum('tomado','omitido','pospuesto') NOT NULL,
        KEY (id_horario)
    ) ENGINE=InnoDB CHARSET=utf8mb4",
    
    "vinculaciones" => "CREATE TABLE IF NOT EXISTS vinculaciones (
        id_vinculacion int AUTO_INCREMENT PRIMARY KEY,
        id_paciente int NOT NULL,
        id_cuidador int NOT NULL,
        confirmado tinyint DEFAULT 0,
        fecha_vinculacion timestamp DEFAULT CURRENT_TIMESTAMP,
        KEY (id_paciente),
        KEY (id_cuidador)
    ) ENGINE=InnoDB CHARSET=utf8mb4",
    
    "recordatorios_whatsapp" => "CREATE TABLE IF NOT EXISTS recordatorios_whatsapp (
        id_recordatorio int AUTO_INCREMENT PRIMARY KEY,
        id_horario int NOT NULL,
        id_usuario int NOT NULL,
        mensaje text NOT NULL,
        fecha_envio timestamp DEFAULT CURRENT_TIMESTAMP,
        estado enum('enviado','entregado','leido','confirmado') DEFAULT 'enviado',
        token_confirmacion varchar(100) NOT NULL,
        KEY (id_horario),
        KEY (id_usuario)
    ) ENGINE=InnoDB CHARSET=utf8mb4"
];

$creadas = 0;
foreach($tablasSQL as $nombre => $sql) {
    try {
        $pdo->exec($sql);
        echo logMsg("✅ Tabla '$nombre' creada", 'success');
        $creadas++;
        usleep(50000);
    } catch(Exception $e) {
        echo logMsg("❌ Error '$nombre': " . $e->getMessage(), 'error');
    }
}

echo "</div></div></div>";

// =============================================================================
// PASO 6: CLAVES FORÁNEAS
// =============================================================================
echo "<div class='step-card card'><div class='card-body'><h4>Paso 6: Relaciones</h4><div class='log-output'>";

$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$fks = [
    ["medicamentos","id_usuario","usuarios","id_usuario","CASCADE"],
    ["medicamentos","agregado_por","usuarios","id_usuario","SET NULL"],
    ["horarios","id_medicamento","medicamentos","id_medicamento","CASCADE"],
    ["historial_tomas","id_horario","horarios","id_horario","CASCADE"],
    ["vinculaciones","id_paciente","usuarios","id_usuario","CASCADE"],
    ["vinculaciones","id_cuidador","usuarios","id_usuario","CASCADE"],
    ["recordatorios_whatsapp","id_horario","horarios","id_horario","CASCADE"],
    ["recordatorios_whatsapp","id_usuario","usuarios","id_usuario","CASCADE"]
];

$fkCreadas = 0;
foreach($fks as $fk) {
    list($tabla,$col,$refTabla,$refCol,$onDelete) = $fk;
    try {
        $pdo->exec("ALTER TABLE `$tabla` DROP FOREIGN KEY IF EXISTS fk_{$tabla}_{$col}");
        $sql = "ALTER TABLE `$tabla` ADD CONSTRAINT fk_{$tabla}_{$col} FOREIGN KEY ($col) REFERENCES `$refTabla` ($refCol) ON DELETE $onDelete ON UPDATE CASCADE";
        $pdo->exec($sql);
        echo logMsg("✅ $tabla.$col → $refTabla.$refCol", 'success');
        $fkCreadas++;
    } catch(Exception $e) {
        echo logMsg("⚠️ $tabla.$col: " . $e->getMessage(), 'warning');
    }
}
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "</div></div></div>";

// =============================================================================
// PASO 7: DATOS INICIALES
// =============================================================================
echo "<div class='step-card card'><div class='card-body'><h4>Paso 7: Datos iniciales</h4><div class='log-output'>";

try {
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM usuarios");
    if($stmt->fetch()['cnt'] == 0) {
        echo logMsg("Insertando datos prueba...", 'info');
        
        $passHash = password_hash('password123', PASSWORD_DEFAULT);
        
        // Usuarios
        $usuarios = [
            ['María González López', 'maria@email.com', $passHash, 'paciente', '+521234567890'],
            ['Carlos Rodríguez Pérez', 'carlos@email.com', $passHash, 'cuidador', '+521234567891'],
            ['Ana Martínez Sánchez', 'ana@email.com', $passHash, 'paciente', '+521234567892'],
            ['Javier López García', 'javier@email.com', $passHash, 'cuidador', '+521234567893'],
            ['Isabel Díaz Fernández', 'isabel@email.com', $passHash, 'paciente', '+521234567894']
        ];
        
        foreach($usuarios as $u) {
            $pdo->prepare("INSERT INTO usuarios (nombre,email,password,tipo,telefono) VALUES (?,?,?,?,?)")->execute($u);
        }
        echo logMsg("✅ 5 usuarios creados", 'success');
        
        // Vinculaciones
        $vinc = [[1,2,1],[3,4,1],[5,2,1]];
        foreach($vinc as $v) {
            $pdo->prepare("INSERT INTO vinculaciones (id_paciente,id_cuidador,confirmado) VALUES (?,?,?)")->execute($v);
        }
        echo logMsg("✅ 3 vinculaciones", 'success');
        
        // Medicamentos
        $meds = [
            [1, 'Losartán', '1 tableta 50mg', 'Tomar con desayuno', 2],
            [1, 'Metformina', '1 tableta 850mg', 'Tomar con alimentos', 2],
            [3, 'Omeprazol', '1 cápsula 20mg', 'En ayunas 30 min antes', 4],
            [5, 'Levotiroxina', '1 tableta 50mcg', 'En ayunas 30-60 min antes', 2]
        ];
        
        foreach($meds as $m) {
            $pdo->prepare("INSERT INTO medicamentos (id_usuario,nombre_medicamento,dosis,instrucciones,agregado_por) VALUES (?,?,?,?,?)")->execute($m);
            $medId = $pdo->lastInsertId();
            
            if($m[1]=='Losartán') {
                $pdo->exec("INSERT INTO horarios (id_medicamento,hora) VALUES ($medId,'08:00:00'),($medId,'20:00:00')");
            } elseif($m[1]=='Metformina') {
                $pdo->exec("INSERT INTO horarios (id_medicamento,hora) VALUES ($medId,'08:00:00'),($medId,'14:00:00')");
            } else {
                $pdo->exec("INSERT INTO horarios (id_medicamento,hora) VALUES ($medId,'07:00:00')");
            }
        }
        echo logMsg("✅ 4 medicamentos con horarios", 'success');
        
        // Toma ejemplo
        $pdo->exec("INSERT INTO historial_tomas (id_horario,estado) VALUES (1,'tomado'),(2,'tomado'),(3,'tomado')");
        echo logMsg("✅ 3 tomas registradas", 'success');
        
    } else {
        echo logMsg("Ya existen usuarios. Saltando datos prueba.", 'info');
    }
} catch(Exception $e) {
    echo logMsg("❌ Error datos: " . $e->getMessage(), 'error');
}

echo "</div></div></div>";

// =============================================================================
// RESUMEN
// =============================================================================
echo "<div class='card mt-4'><div class='card-body text-center'>
        <h2 class='text-success mb-4'>🎉 ¡Configuración completada!</h2>
        <div class='row mb-4'>
            <div class='col-md-4'><div class='card bg-light'><div class='card-body'><h5>Tablas</h5><h2 class='text-primary'>$creadas</h2><small>de " . count($tablasSQL) . "</small></div></div></div>
            <div class='col-md-4'><div class='card bg-light'><div class='card-body'><h5>Relaciones</h5><h2 class='text-primary'>$fkCreadas</h2><small>claves foráneas</small></div></div></div>
            <div class='col-md-4'><div class='card bg-light'><div class='card-body'><h5>Entorno</h5><h4>" . ($isRailway ? 'Railway 🚄' : 'Local 🖥️') . "</h4><small>" . (getenv('RAILWAY_PUBLIC_DOMAIN')?:'localhost') . "</small></div></div></div>
        </div>
        <div class='alert alert-info'><h5>📋 Resumen BD</h5><p><strong>Base de datos:</strong> $db</p><p><strong>Host:</strong> $host:$port</p><p><strong>Usuario:</strong> $user</p></div>
        <div class='d-grid gap-2 d-md-flex justify-content-center mt-4'>
            <a href='index.php' class='btn btn-success btn-lg'>🚀 Ir al sistema</a>
            <a href='test_variables.php' class='btn btn-outline-primary btn-lg'>🔧 Probar conexión</a>
            <a href='?force=1' class='btn btn-outline-warning btn-lg'>🔄 Reiniciar</a>
        </div>
        <div class='alert alert-warning mt-4'><h5>👥 Credenciales prueba:</h5>
            <p><strong>Paciente:</strong> maria@email.com / password123</p>
            <p><strong>Cuidador:</strong> carlos@email.com / password123</p>
            <p class='mb-0'><small>Todas: password123</small></p>
        </div>
        <div class='text-muted mt-3'><p>MediRecord v2.0 &copy; " . date('Y') . "</p></div>
    </div></div>
</div></div></div></div>

<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js'></script>
<script>
    document.querySelectorAll('.log-output').forEach(el => el.scrollTop = el.scrollHeight);
    document.querySelector('a[href*=\"force=1\"]').addEventListener('click', e => {
        if(!confirm('⚠️ ¿Reiniciar configuración?\\nSe eliminarán todas las tablas y datos.')) e.preventDefault();
    });
</script>
</body>
</html>";
