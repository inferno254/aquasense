<?php
echo "PHP Version: " . phpversion() . "<br>";
echo "PHP Architecture: " . (PHP_INT_SIZE == 8 ? '64-bit' : '32-bit') . "<br>";
echo "OS: " . php_uname('s') . " " . php_uname('m') . "<br>";
echo "Server API: " . php_sapi_name() . "<br>";
echo "<br>";

// Check if we can get startup errors
$errors = [];
if (function_exists('error_get_last')) {
    $last = error_get_last();
    if ($last) $errors[] = $last;
}

echo "Startup errors: " . count($errors) . "<br>";
foreach ($errors as $e) {
    echo " - " . $e['message'] . "<br>";
}

echo "<br>Loaded Extensions:<br>";
$exts = get_loaded_extensions();
sort($exts);
foreach ($exts as $ext) {
    echo " - $ext<br>";
}

echo "<br>PDO Drivers:<br>";
print_r(PDO::getAvailableDrivers());

// Check for common dependency issues
echo "<br><br>Checking VC++ Redistributable indicators...<br>";
$vcruntime = 'C:\Windows\System32\vcruntime140.dll';
echo "vcruntime140.dll exists: " . (file_exists($vcruntime) ? 'YES' : 'NO - MAY BE THE PROBLEM') . "<br>";

phpinfo();
?>
