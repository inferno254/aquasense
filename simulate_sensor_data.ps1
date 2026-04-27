param(
    [string]$ApiBase = "http://127.0.0.1:8000",
    [string]$FarmId = "550e8400-e29b-41d4-a716-446655440002",
    [int]$Count = 12,
    [int]$IntervalSeconds = 5,
    [switch]$DryRun,
    [switch]$LowMoisture
)

function Get-RandomReading {
    param([bool]$ForceLow)

    if ($ForceLow) {
        $soil = Get-Random -Minimum 18 -Maximum 34
        $temp = Get-Random -Minimum 28 -Maximum 35
        $humidity = Get-Random -Minimum 42 -Maximum 58
    }
    else {
        $soil = Get-Random -Minimum 30 -Maximum 76
        $temp = Get-Random -Minimum 23 -Maximum 33
        $humidity = Get-Random -Minimum 55 -Maximum 86
    }

    return @{
        soilMoisture = [math]::Round($soil + (Get-Random -Minimum 0 -Maximum 10) / 10, 1)
        temperature = [math]::Round($temp + (Get-Random -Minimum 0 -Maximum 10) / 10, 1)
        humidity = [math]::Round($humidity + (Get-Random -Minimum 0 -Maximum 10) / 10, 1)
        timestamp = (Get-Date).ToUniversalTime().ToString("o")
    }
}

$uri = "$ApiBase/api/sensor/hardware/$FarmId/reading"
Write-Host "Streaming mock sensor data to $uri" -ForegroundColor Cyan
Write-Host "Messages: $Count | Interval: ${IntervalSeconds}s | Low moisture mode: $($LowMoisture.IsPresent)" -ForegroundColor DarkCyan

for ($index = 1; $index -le $Count; $index++) {
    $reading = Get-RandomReading -ForceLow:$LowMoisture.IsPresent
    $json = $reading | ConvertTo-Json

    try {
        $response = Invoke-RestMethod -Uri $uri -Method Post -ContentType "application/json" -Body $json
        $alertFlag = if ($response.alert) { "YES" } else { "NO" }
        Write-Host ("[{0}/{1}] Moisture={2}% Temp={3}C Humidity={4}% Alert={5}" -f $index, $Count, $reading.soilMoisture, $reading.temperature, $reading.humidity, $alertFlag) -ForegroundColor Green
    }
    catch {
        Write-Host ("[{0}/{1}] Failed: {2}" -f $index, $Count, $_.Exception.Message) -ForegroundColor Red
    }

    if ($index -lt $Count) {
        Start-Sleep -Seconds $IntervalSeconds
    }
}

Write-Host "Simulation complete." -ForegroundColor Cyan
