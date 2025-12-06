<?php
// Este script creará todas las tablas automáticamente
require_once 'config/database.php';

$db = getDBConnection();

// Array con las sentencias CREATE TABLE de tu SQL
$tables = [
    "CREATE TABLE IF NOT EXISTS usuarios (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS medicamentos (
        id_medicamento int(11) NOT NULL AUTO_INCREMENT,
        id_usuario int(11) NOT NULL,
        nombre_medicamento varchar(100) NOT NULL,
        dosis varchar(50) NOT NULL,
        instrucciones text DEFAULT NULL,
        agregado_por int(11) DEFAULT NULL,
        fecha_agregado timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (id_medicamento),
        FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE,
        FOREIGN KEY (agregado_por) REFERENCES usuarios(id_usuario) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    // ... Continúa con las demás tablas (horarios, historial_tomas, etc.)
];

try {
    $db->beginTransaction();
    
    foreach ($tables as $tableSQL) {
        $db->exec($tableSQL);
    }
    
    $db->commit();
    echo "✅ Tablas creadas exitosamente!";
    
} catch (PDOException $e) {
    $db->rollBack();
    echo "❌ Error: " . $e->getMessage();
}
?>
