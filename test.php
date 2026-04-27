<?php
echo "Loaded php.ini: " . php_ini_loaded_file() . "<br>";
echo "PDO SQLite enabled: " . (extension_loaded('pdo_sqlite') ? 'YES' : 'NO') . "<br>";
echo "SQLite3 enabled: " . (extension_loaded('sqlite3') ? 'YES' : 'NO') . "<br>";
print_r(PDO::getAvailableDrivers());
?>
