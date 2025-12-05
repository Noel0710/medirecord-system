<?php
echo "<h1>🔍 Test Variables Railway</h1>";
echo "<pre>";

$variables = [
    'MYSQLHOST' => getenv('MYSQLHOST'),
    'MYSQLPORT' => getenv('MYSQLPORT'),
    'MYSQLDATABASE' => getenv('MYSQLDATABASE'),
    'MYSQLUSER' => getenv('MYSQLUSER'),
    'MYSQLPASSWORD' => getenv('MYSQLPASSWORD')
];

foreach ($variables as $key => $value) {
    echo "$key: ";
    if ($value) {
        echo "✅ " . ($key === 'MYSQLPASSWORD' ? 'DEFINIDO' : $value);
    } else {
        echo "❌ NO DEFINIDO";
    }
    echo "\n";
}

echo "\nTodas las variables están definidas: ";
echo (count(array_filter($variables)) === 5) ? "✅ SÍ" : "❌ NO";
echo "</pre>";
?>
