<?php
// Check extension directory
$extDir = ini_get('extension_dir');
echo "Extension Dir: " . $extDir . "<br>";

// Check if files exist
$pdo_sqlite = $extDir . '\php_pdo_sqlite.dll';
$sqlite3 = $extDir . '\php_sqlite3.dll';

echo "pdo_sqlite.dll exists: " . (file_exists($pdo_sqlite) ? 'YES' : 'NO') . "<br>";
echo "sqlite3.dll exists: " . (file_exists($sqlite3) ? 'YES' : 'NO') . "<br>";

// Check php.ini path
echo "Loaded php.ini: " . php_ini_loaded_file() . "<br>";

// Try to read the extension lines from php.ini
$iniContent = file_get_contents(php_ini_loaded_file());
echo "pdo_sqlite in ini: " . (strpos($iniContent, 'extension=pdo_sqlite') !== false ? 'YES' : 'NO') . "<br>";
echo "sqlite3 in ini: " . (strpos($iniContent, 'extension=sqlite3') !== false ? 'YES' : 'NO') . "<br>";

// Try to manually load
echo "<br>Trying to load pdo_sqlite...<br>";
$loaded = @dl('php_pdo_sqlite.dll');
if ($loaded === false) {
    echo "Failed to load. Error: " . error_get_last()['message'] . "<br>";
}

phpinfo(INFO_MODULES);
?>
