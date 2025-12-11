<?php
// setup_database.php - CREA LAS TABLAS en Railway (USAR UNA SOLA VEZ)
require_once 'config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Setup Database - MediRecord</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 1000px; margin: 0 auto; }
        .success { color: green; padding: 10px; background: #e8f5e8; border: 1px solid green; }
        .error { color: red; padding: 10px; background: #ffe8e8; border: 1px solid red; }
        .warning { color: orange; padding: 10px; background: #fff8e8; border: 1px solid orange; }
        .sql { background: #f5f5f5; padding: 10px; border-left: 4px solid #ccc; font-family: monospace; }
    </style>
</head>
<body>";

echo "<h1>🧰 Configuración de Base de Datos MediRecord</h1>";

// Verificar que estamos en Railway
if (!IS_RAILWAY) {
    die("<div class='warning'>
        <h2>⚠️ Advertencia</h2>
        <p>Este script solo debe ejecutarse en Railway (producción).</p>
        <p>En local, importa el archivo <code>medirecord_db.sql</code> directamente en phpMyAdmin.</p>
    </div>");
}

// Verificar conexión
try {
    $pdo->query("SELECT 1");
    echo "<div class='success'>✅ Conexión a Railway MySQL establecida</div>";
} catch (Exception $e) {
    die("<div class='error'>
        <h2>❌ Error de conexión</h2>
        <p>No se pudo conectar a la base de datos.</p>
        <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <p>Verifica las variables MYSQL_* en Railway Dashboard → Variables</p>
    </div>");
}

// =============================================================================
// CREAR TABLAS (EN ORDEN CORRECTO POR RESTRICCIONES FOREIGN KEY)
// =============================================================================

$sql_commands = [
    // 1. Tabla usuarios (PRIMERO - no tiene dependencias)
    "CREATE TABLE IF NOT EXISTS usuarios (
        id_usuario INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        email VARCHAR(150) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        tipo ENUM('paciente','cuidador') NOT NULL,
        fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        telefono VARCHAR(20),
        whatsapp_token VARCHAR(100),
        telefono_verificado BOOLEAN DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // 2. Tabla medicamentos (depende de usuarios)
    "CREATE TABLE IF NOT EXISTS medicamentos (
        id_medicamento INT AUTO_INCREMENT PRIMARY KEY,
        id_usuario INT NOT NULL,
        nombre_medicamento VARCHAR(100) NOT NULL,
        dosis VARCHAR(50) NOT NULL,
        instrucciones TEXT,
        agregado_por INT,
        fecha_agregado TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
        FOREIGN KEY (agregado_por) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // 3. Tabla horarios (depende de medicamentos)
    "CREATE TABLE IF NOT EXISTS horarios (
        id_horario INT AUTO_INCREMENT PRIMARY KEY,
        id_medicamento INT NOT NULL,
        hora TIME NOT NULL,
        frecuencia ENUM('diario','lunes-viernes','personalizado') DEFAULT 'diario',
        activo BOOLEAN DEFAULT 1,
        ultimo_recordatorio DATETIME,
        ultima_alerta DATETIME,
        FOREIGN KEY (id_medicamento) REFERENCES medicamentos(id_medicamento) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // 4. Tabla historial_tomas (depende de horarios)
    "CREATE TABLE IF NOT EXISTS historial_tomas (
        id_registro INT AUTO_INCREMENT PRIMARY KEY,
        id_horario INT NOT NULL,
        fecha_hora_toma TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        estado ENUM('tomado','omitido','pospuesto') NOT NULL,
        FOREIGN KEY (id_horario) REFERENCES horarios(id_horario) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // 5. Tabla vinculaciones (depende de usuarios)
    "CREATE TABLE IF NOT EXISTS vinculaciones (
        id_vinculacion INT AUTO_INCREMENT PRIMARY KEY,
        id_paciente INT NOT NULL,
        id_cuidador INT NOT NULL,
        confirmado BOOLEAN DEFAULT 0,
        fecha_vinculacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (id_paciente) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
        FOREIGN KEY (id_cuidador) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
        UNIQUE KEY unique_vinculacion (id_paciente, id_cuidador)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // 6. Tabla recordatorios_whatsapp (depende de horarios y usuarios)
    "CREATE TABLE IF NOT EXISTS recordatorios_whatsapp (
        id_recordatorio INT AUTO_INCREMENT PRIMARY KEY,
        id_horario INT NOT NULL,
        id_usuario INT NOT NULL,
        mensaje TEXT NOT NULL,
        fecha_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        estado ENUM('enviado','entregado','leido','confirmado') DEFAULT 'enviado',
        token_confirmacion VARCHAR(100) NOT NULL,
        FOREIGN KEY (id_horario) REFERENCES horarios(id_horario) ON DELETE CASCADE,
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

echo "<h2>📊 Creando tablas...</h2>";

foreach ($sql_commands as $index => $sql) {
    $table_names = ['usuarios', 'medicamentos', 'horarios', 'historial_tomas', 'vinculaciones', 'recordatorios_whatsapp'];
    $table_name = $table_names[$index] ?? 'Tabla ' . ($index + 1);
    
    echo "<h3>🛠️ Creando: $table_name</h3>";
    echo "<div class='sql'>" . htmlspecialchars(substr($sql, 0, 200)) . "...</div>";
    
    try {
        $pdo->exec($sql);
        echo "<div class='success'>✅ Tabla <strong>$table_name</strong> creada exitosamente</div>";
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error creando $table_name: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    echo "<hr>";
}

// =============================================================================
// INSERTAR DATOS DE PRUEBA (OPCIONAL)
// =============================================================================

echo "<h2>👥 Insertando datos de prueba...</h2>";

$test_data = [
    // Usuario administrador
    "INSERT IGNORE INTO usuarios (nombre, email, password, tipo, telefono) VALUES 
    ('Administrador Demo', 'admin@medirecord.com', '\$2y\$10\$hashedpassword', 'paciente', '+521234567890'),
    ('Cuidador Demo', 'cuidador@medirecord.com', '\$2y\$10\$hashedpassword', 'cuidador', '+521234567891');",
    
    // Vinculación
    "INSERT IGNORE INTO vinculaciones (id_paciente, id_cuidador, confirmado) 
    SELECT u1.id_usuario, u2.id_usuario, 1 
    FROM usuarios u1, usuarios u2 
    WHERE u1.email = 'admin@medirecord.com' 
    AND u2.email = 'cuidador@medirecord.com'
    LIMIT 1;",
    
    // Medicamento de prueba
    "INSERT IGNORE INTO medicamentos (id_usuario, nombre_medicamento, dosis, instrucciones) 
    SELECT id_usuario, 'Paracetamol', '1 tableta de 500mg', 'Tomar cada 8 horas con alimentos' 
    FROM usuarios WHERE email = 'admin@medirecord.com'
    LIMIT 1;",
    
    // Horario para el medicamento
    "INSERT IGNORE INTO horarios (id_medicamento, hora, frecuencia) 
    SELECT id_medicamento, '08:00:00', 'diario' 
    FROM medicamentos WHERE nombre_medicamento = 'Paracetamol'
    LIMIT 1;"
];

foreach ($test_data as $sql) {
    try {
        $pdo->exec($sql);
        echo "<div class='success'>✅ Datos insertados</div>";
    } catch (Exception $e) {
        echo "<div class='warning'>⚠️ " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// =============================================================================
// VERIFICACIÓN FINAL
// =============================================================================

echo "<h2>✅ Verificación final</h2>";

$tables_to_check = ['usuarios', 'medicamentos', 'horarios', 'historial_tomas', 'vinculaciones', 'recordatorios_whatsapp'];
$all_tables_exist = true;

foreach ($tables_to_check as $table) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<div class='success'>✅ Tabla <strong>$table</strong> existe</div>";
        } else {
            echo "<div class='error'>❌ Tabla <strong>$table</strong> NO existe</div>";
            $all_tables_exist = false;
        }
    } catch (Exception $e) {
        echo "<div class='error'>❌ Error verificando $table: " . $e->getMessage() . "</div>";
        $all_tables_exist = false;
    }
}

if ($all_tables_exist) {
    echo "<div style='background: #d4edda; color: #155724; padding: 20px; border-radius: 5px; margin: 20px 0;'>
        <h2>🎉 ¡CONFIGURACIÓN COMPLETADA!</h2>
        <p>Todas las tablas se crearon exitosamente en Railway MySQL.</p>
        <p><strong>Credenciales de prueba:</strong></p>
        <ul>
            <li><strong>Paciente:</strong> admin@medirecord.com / password123</li>
            <li><strong>Cuidador:</strong> cuidador@medirecord.com / password123</li>
        </ul>
        <p><a href='index.php' style='display: inline-block; background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Ir al sistema MediRecord</a></p>
    </div>";
} else {
    echo "<div class='error'>
        <h2>⚠️ Configuración incompleta</h2>
        <p>Algunas tablas no se crearon. Revisa los errores arriba.</p>
    </div>";
}

echo "<div class='warning'>
    <h3>⚠️ IMPORTANTE</h3>
    <p><strong>Elimina este archivo (setup_database.php) después de usarlo por seguridad.</strong></p>
    <p>En Railway, puedes hacerlo desde GitHub o subiendo una nueva versión sin este archivo.</p>
</div>";

echo "</body></html>";
?>
