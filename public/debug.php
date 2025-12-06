<?php
echo "<h1>Debug Setup</h1>";
echo "<h3>Primeras 20 líneas de setup_database.php:</h3>";
$lines = file(__DIR__ . '/setup_database.php');
for($i=0; $i<20 && $i<count($lines); $i++) {
    echo ($i+1) . ": " . htmlspecialchars($lines[$i]) . "<br>";
}
?>
