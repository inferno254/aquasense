param(
    [string]$HostName = "127.0.0.1",
    [int]$Port = 8000
)

$php = Get-Command php -ErrorAction SilentlyContinue
if (-not $php) {
    Write-Error "PHP is not installed or not on PATH. Install PHP 8+ and rerun this script."
    exit 1
}

$initScript = Join-Path $PSScriptRoot "php\init_db.php"
$routerScript = Join-Path $PSScriptRoot "php\router.php"
$phpRoot = Join-Path $PSScriptRoot "php"

& $php.Source $initScript
if ($LASTEXITCODE -ne 0) {
    Write-Error "Failed to initialize the PHP database."
    exit $LASTEXITCODE
}

Write-Host "Serving AquaSense PHP API at http://$HostName`:$Port/api" -ForegroundColor Cyan
& $php.Source -S "$HostName`:$Port" -t $phpRoot $routerScript
