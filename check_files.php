<?php
echo "<h3>File Check</h3>";

$files = [
    'buy_ticket.php',
    'inc/ajax.php',
    'inc/database.php',
    'inc/basic_template.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "✅ $file exists<br>";
    } else {
        echo "❌ $file MISSING!<br>";
    }
}

echo "<br><h3>Current Directory:</h3>";
echo getcwd();
?>