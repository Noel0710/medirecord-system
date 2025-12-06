<?php
// setup_database.php - VERSIÓN SUPER SIMPLE
// NO require_once - SOLO código básico

echo "<h1>MediRecord Setup - PHP " . PHP_VERSION . "</h1>";

// Probar MySQL
$host = getenv('MYSQLHOST') ?: 'localhost';
$port = getenv('MYSQLPORT') ?: '3306';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: '';
$db = getenv('MYSQLDATABASE') ?: 'railway';

echo "<p>Host: $host</p>";
echo "<p>User: $user</p>";
echo "<p>DB: $db</p>";

try {
    $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "<p style='color:green;'>✅ MySQL CONECTADO</p>";
    
    // Crear DB si no existe
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4");
    $pdo->exec("USE `$db`");
    
    echo "<p style='color:green;'>✅ Base de datos lista: $db</p>";
    
    // Tabla simple
    $pdo->exec("CREATE TABLE IF NOT EXISTS test (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    echo "<p style='color:green;'>✅ Tabla 'test' creada</p>";
    echo "<h2 style='color:green;'>🎉 ¡SISTEMA LISTO!</h2>";
    echo "<a href='index.php'>Ir al inicio</a>";
    
} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<h3>Variables detectadas:</h3>";
    echo "<pre>";
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'MYSQL') === 0) {
            echo "$key: $value\n";
        }
    }
    echo "</pre>";
}
?>
