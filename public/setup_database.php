<?php
// setup_database.php - Versión para Railway
// Este script creará las tablas automáticamente

// Incluir config.php directamente (mismo directorio)
require_once __DIR__ . '/config.php';

// Verificar si ya existen tablas
function databaseExists($pdo, $database) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables 
                            WHERE table_schema = '$database'");
        return $stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

try {
    echo "<h2>Configuración de Base de Datos - MediRecord</h2>";
    echo "<p>Conectando a MySQL en Railway...</p>";
    
    // Verificar conexión
    $pdo->query("SELECT 1");
    echo "✅ Conexión exitosa<br>";
    
    // Verificar si ya hay tablas
    if (databaseExists($pdo, $database)) {
        echo "⚠️  La base de datos ya tiene tablas. ¿Quieres recrearlas?<br>";
        echo "<a href='?force=1'>Forzar recreación (borrará datos existentes)</a><br><br>";
        
        if (!isset($_GET['force'])) {
            echo "✅ Base de datos ya configurada. No es necesario hacer nada más.";
            exit;
        }
    }
    
    // =========================================================================
    // CREACIÓN DE TABLAS
    // =========================================================================
    
    echo "<h3>Creando tablas...</h3>";
    
    // 1. Tabla USUARIOS
    echo "Creando tabla 'usuarios'... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✅<br>";
    
    // 2. Tabla MEDICAMENTOS
    echo "Creando tabla 'medicamentos'... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS medicamentos (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✅<br>";
    
    // 3. Tabla HORARIOS
    echo "Creando tabla 'horarios'... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS horarios (
        id_horario int(11) NOT NULL AUTO_INCREMENT,
        id_medicamento int(11) NOT NULL,
        hora time NOT NULL,
        frecuencia enum('diario','lunes-viernes','personalizado') DEFAULT 'diario',
        activo tinyint(1) DEFAULT 1,
        ultimo_recordatorio datetime DEFAULT NULL,
        ultima_alerta datetime DEFAULT NULL,
        PRIMARY KEY (id_horario),
        KEY id_medicamento (id_medicamento)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✅<br>";
    
    // 4. Tabla HISTORIAL_TOMAS
    echo "Creando tabla 'historial_tomas'... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS historial_tomas (
        id_registro int(11) NOT NULL AUTO_INCREMENT,
        id_horario int(11) NOT NULL,
        fecha_hora_toma timestamp NOT NULL DEFAULT current_timestamp(),
        estado enum('tomado','omitido','pospuesto') NOT NULL,
        PRIMARY KEY (id_registro),
        KEY id_horario (id_horario)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✅<br>";
    
    // 5. Tabla VINCULACIONES
    echo "Creando tabla 'vinculaciones'... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS vinculaciones (
        id_vinculacion int(11) NOT NULL AUTO_INCREMENT,
        id_paciente int(11) NOT NULL,
        id_cuidador int(11) NOT NULL,
        confirmado tinyint(1) DEFAULT 0,
        fecha_vinculacion timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (id_vinculacion),
        KEY id_paciente (id_paciente),
        KEY id_cuidador (id_cuidador)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✅<br>";
    
    // 6. Tabla RECORDATORIOS_WHATSAPP
    echo "Creando tabla 'recordatorios_whatsapp'... ";
    $pdo->exec("CREATE TABLE IF NOT EXISTS recordatorios_whatsapp (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "✅<br>";
    
    // =========================================================================
    // CLAVES FORÁNEAS
    // =========================================================================
    
    echo "<h3>Añadiendo restricciones de claves foráneas...</h3>";
    
    try {
        // Medicamentos -> Usuarios
        $pdo->exec("ALTER TABLE medicamentos 
                   ADD CONSTRAINT medicamentos_ibfk_1 
                   FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE");
        echo "Clave foránea medicamentos->usuarios ✅<br>";
        
        $pdo->exec("ALTER TABLE medicamentos 
                   ADD CONSTRAINT medicamentos_ibfk_2 
                   FOREIGN KEY (agregado_por) REFERENCES usuarios(id_usuario) ON DELETE SET NULL");
        echo "Clave foránea medicamentos->agregado_por ✅<br>";
        
        // Horarios -> Medicamentos
        $pdo->exec("ALTER TABLE horarios 
                   ADD CONSTRAINT horarios_ibfk_1 
                   FOREIGN KEY (id_medicamento) REFERENCES medicamentos(id_medicamento) ON DELETE CASCADE");
        echo "Clave foránea horarios->medicamentos ✅<br>";
        
        // Historial -> Horarios
        $pdo->exec("ALTER TABLE historial_tomas 
                   ADD CONSTRAINT historial_tomas_ibfk_1 
                   FOREIGN KEY (id_horario) REFERENCES horarios(id_horario) ON DELETE CASCADE");
        echo "Clave foránea historial_tomas->horarios ✅<br>";
        
        // Vinculaciones
        $pdo->exec("ALTER TABLE vinculaciones 
                   ADD CONSTRAINT vinculaciones_ibfk_1 
                   FOREIGN KEY (id_paciente) REFERENCES usuarios(id_usuario) ON DELETE CASCADE");
        echo "Clave foránea vinculaciones->paciente ✅<br>";
        
        $pdo->exec("ALTER TABLE vinculaciones 
                   ADD CONSTRAINT vinculaciones_ibfk_2 
                   FOREIGN KEY (id_cuidador) REFERENCES usuarios(id_usuario) ON DELETE CASCADE");
        echo "Clave foránea vinculaciones->cuidador ✅<br>";
        
        // Recordatorios
        $pdo->exec("ALTER TABLE recordatorios_whatsapp 
                   ADD CONSTRAINT recordatorios_whatsapp_ibfk_1 
                   FOREIGN KEY (id_horario) REFERENCES horarios(id_horario) ON DELETE CASCADE");
        echo "Clave foránea recordatorios->horarios ✅<br>";
        
        $pdo->exec("ALTER TABLE recordatorios_whatsapp 
                   ADD CONSTRAINT recordatorios_whatsapp_ibfk_2 
                   FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE");
        echo "Clave foránea recordatorios->usuarios ✅<br>";
        
    } catch (Exception $e) {
        echo "⚠️  Algunas claves foráneas ya existían. Continuando...<br>";
    }
    
    // =========================================================================
    // DATOS INICIALES (OPCIONAL)
    // =========================================================================
    
    echo "<h3>Insertando datos iniciales...</h3>";
    
    // Verificar si ya hay usuarios
    $stmt = $pdo->query("SELECT COUNT(*) FROM usuarios");
    if ($stmt->fetchColumn() == 0) {
        
        // Insertar usuarios de prueba
        $users = [
            ['María González López', 'maria.gonzalez@email.com', '$2y$10$hashedpassword1', 'paciente'],
            ['Carlos Rodríguez Pérez', 'carlos.rodriguez@email.com', '$2y$10$hashedpassword2', 'cuidador'],
            ['Ana Martínez Sánchez', 'ana.martinez@email.com', '$2y$10$hashedpassword3', 'paciente'],
            ['Javier López García', 'javier.lopez@email.com', '$2y$10$hashedpassword4', 'cuidador'],
            ['Isabel Díaz Fernández', 'isabel.diaz@email.com', '$2y$10$hashedpassword5', 'paciente']
        ];
        
        foreach ($users as $user) {
            $stmt = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, tipo) VALUES (?, ?, ?, ?)");
            $stmt->execute($user);
        }
        echo "Usuarios de prueba insertados ✅<br>";
        
        // Nota: La contraseña hasheada es solo para demostración
        // En producción, usarías: password_hash('password123', PASSWORD_DEFAULT)
    } else {
        echo "Ya existen usuarios en la base de datos. Saltando inserción de datos de prueba.<br>";
    }
    
    echo "<hr>";
    echo "<h2>🎉 ¡Base de datos configurada exitosamente!</h2>";
    echo "<p>Puedes <a href='index.php'>ir al inicio</a> para comenzar a usar MediRecord.</p>";
    echo "<p><strong>Nota:</strong> En Railway, asegúrate de que las variables de entorno estén configuradas:</p>";
    echo "<ul>";
    echo "<li>MYSQLHOST</li>";
    echo "<li>MYSQLPORT</li>";
    echo "<li>MYSQLDATABASE</li>";
    echo "<li>MYSQLUSER</li>";
    echo "<li>MYSQLPASSWORD</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<div style='color: red; padding: 20px; background: #fee; border: 1px solid red;'>";
    echo "<h3>❌ Error en la configuración:</h3>";
    echo "<p><strong>Mensaje:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>Archivo:</strong> " . $e->getFile() . ":" . $e->getLine() . "</p>";
    echo "<p><strong>Variables detectadas:</strong></p>";
    echo "<pre>";
    echo "MYSQLHOST: " . (getenv('MYSQLHOST') ?: 'NO DEFINIDO') . "\n";
    echo "MYSQLPORT: " . (getenv('MYSQLPORT') ?: 'NO DEFINIDO') . "\n";
    echo "MYSQLDATABASE: " . (getenv('MYSQLDATABASE') ?: 'NO DEFINIDO') . "\n";
    echo "MYSQLUSER: " . (getenv('MYSQLUSER') ?: 'NO DEFINIDO') . "\n";
    echo "MYSQLPASSWORD: " . (getenv('MYSQLPASSWORD') ? '***' : 'NO DEFINIDO') . "\n";
    echo "MYSQL_URL: " . (getenv('MYSQL_URL') ? 'DEFINIDO' : 'NO DEFINIDO') . "\n";
    echo "</pre>";
    echo "</div>";
}
?>
