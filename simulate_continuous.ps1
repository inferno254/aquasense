param(
    [string]$ApiBase = "http://aquasense.local/php/api",
    [string]$FarmId = "550e8400-e29b-41d4-a716-446655440002",
    [int]$AlertsPerDay = 10,
    [switch]$Stop
)

# PID file for stopping
$pidFile = "$PSScriptRoot\simulate_continuous.pid"

if ($Stop) {
    if (Test-Path $pidFile) {
        $oldPid = Get-Content $pidFile
        try {
            Stop-Process -Id $oldPid -Force -ErrorAction Stop
            Write-Host "Stopped simulation (PID: $oldPid)" -ForegroundColor Green
        } catch {
            Write-Host "Process already stopped or not found" -ForegroundColor Yellow
        }
        Remove-Item $pidFile
    } else {
        Write-Host "No running simulation found" -ForegroundColor Yellow
    }
    exit 0
}

# Save current PID
$PID | Out-File $pidFile

Write-Host @"
=================================================
  AquaSense Continuous Data Simulation
=================================================
API: $ApiBase
Farm: $FarmId
Target: ~$AlertsPerDay alerts per day
Stop with: .\simulate_continuous.ps1 -Stop
=================================================
"@ -ForegroundColor Cyan

# Alert counters
$alertCount = 0
$readingCount = 0
$startTime = Get-Date
$lastIrrigation = $startTime.AddHours(-2)

function Get-RealisticReading {
    $hour = (Get-Date).Hour
    
    # Temperature varies by time of day
    $baseTemp = if ($hour -ge 6 -and $hour -le 18) { 28 } else { 22 }
    $temp = $baseTemp + (Get-Random -Minimum -3 -Maximum 8)
    
    # Humidity inversely related to temperature
    $baseHumidity = if ($hour -ge 6 -and $hour -le 18) { 55 } else { 75 }
    $humidity = $baseHumidity + (Get-Random -Minimum -10 -Maximum 15)
    
    # Soil moisture decreases gradually then jumps after "irrigation"
    $soil = Get-Random -Minimum 35 -Maximum 72
    
    return @{
        soilMoisture = [math]::Round($soil, 1)
        temperature = [math]::Round($temp + (Get-Random -Minimum 0 -Maximum 10) / 10, 1)
        humidity = [math]::Round($humidity, 1)
        batteryLevel = [math]::Round(85 - ($readingCount * 0.001), 1)
        solarLevel = if ($hour -ge 6 -and $hour -le 18) { [math]::Round((Get-Random -Minimum 60 -Maximum 100), 1) } else { 0 }
        timestamp = (Get-Date).ToUniversalTime().ToString("o")
    }
}

function Send-SensorReading {
    param($Reading)
    
    $uri = "$ApiBase/api/sensor/hardware/$FarmId/reading"
    try {
        $response = Invoke-RestMethod -Uri $uri -Method Post -ContentType "application/json" -Body ($Reading | ConvertTo-Json) -TimeoutSec 10
        return $response
    } catch {
        return @{ alert = $false; error = $_.Exception.Message }
    }
}

function New-IrrigationEvent {
    param([string]$Type = "manual", [string]$Duration = "15")
    
    $uri = "$ApiBase/data/farms/$FarmId/events"
    $token = "demo-token-12345"
    
    $body = @{
        event_type = $Type
        duration_minutes = [int]$Duration
        timestamp = (Get-Date).ToUniversalTime().ToString("o")
    } | ConvertTo-Json
    
    try {
        Invoke-RestMethod -Uri $uri -Method Post -Headers @{Authorization = "Bearer $token"} -ContentType "application/json" -Body $body -TimeoutSec 10 | Out-Null
        Write-Host "[$(Get-Date -Format 'HH:mm:ss')] IRRIGATION EVENT created: $Type for ${Duration}min" -ForegroundColor Blue
        return $true
    } catch {
        return $false
    }
}

function New-SystemAlert {
    param([string]$Type, [string]$Severity = "warning", [string]$Message)
    
    $uri = "$ApiBase/data/farms/$FarmId/alerts"
    $token = "demo-token-12345"
    
    $body = @{
        alert_type = $Type
        severity = $Severity
        message = $Message
        created_at = (Get-Date).ToUniversalTime().ToString("o")
        acknowledged = $false
    } | ConvertTo-Json
    
    try {
        Invoke-RestMethod -Uri $uri -Method Post -Headers @{Authorization = "Bearer $token"} -ContentType "application/json" -Body $body -TimeoutSec 10 | Out-Null
        $script:alertCount++
        Write-Host "[$(Get-Date -Format 'HH:mm:ss')] ALERT #$($script:alertCount) created: $Type - $Message" -ForegroundColor $(if ($Severity -eq 'critical') { 'Red' } else { 'Yellow' })
        return $true
    } catch {
        return $false
    }
}

# Calculate interval for target alerts per day
# Assuming we want readings every 5 minutes = 288 readings/day
# For 10 alerts/day, we need ~1 alert per 29 readings
$alertInterval = [math]::Floor(288 / $AlertsPerDay)
$readingInterval = 300 # 5 minutes in seconds

Write-Host "Starting continuous simulation..." -ForegroundColor Green
Write-Host "Reading every $([math]::Round($readingInterval/60)) minutes, targeting ~$AlertsPerDay alerts/day`n" -ForegroundColor Gray

try {
    while ($true) {
        $readingCount++
        $reading = Get-RealisticReading
        
        # Send sensor reading
        $result = Send-SensorReading -Reading $reading
        
        $statusColor = if ($result.alert) { 'Red' } elseif ($reading.soilMoisture -lt 40) { 'Yellow' } else { 'Green' }
        Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Reading #$readingCount | Moisture:$($reading.soilMoisture)% Temp:$($reading.temperature)C Humidity:$($reading.humidity)% Battery:$($reading.batteryLevel)%" -ForegroundColor $statusColor
        
        # Check if we should create an irrigation event (low moisture)
        if ($reading.soilMoisture -lt 35 -and ((Get-Date) - $lastIrrigation).TotalHours -gt 1) {
            New-IrrigationEvent -Type "auto" -Duration "20"
            $lastIrrigation = Get-Date
        }
        
        # Create alert based on conditions (target ~10/day)
        $shouldAlert = ($readingCount % $alertInterval -eq 0) -or $result.alert
        if ($shouldAlert -and $alertCount -lt $AlertsPerDay) {
            $alertTypes = @(
                @{ Type = "low_moisture"; Severity = "warning"; Message = "Soil moisture dropped to $($reading.soilMoisture)%. Consider irrigation." },
                @{ Type = "high_temperature"; Severity = "warning"; Message = "Temperature reached $($reading.temperature)°C. Monitor crop stress." },
                @{ Type = "low_battery"; Severity = "warning"; Message = "Sensor battery at $($reading.batteryLevel)%. Schedule maintenance." },
                @{ Type = "optimal_conditions"; Severity = "info"; Message = "Field conditions are optimal for crop growth." },
                @{ Type = "irrigation_needed"; Severity = "critical"; Message = "Immediate irrigation required - moisture critical at $($reading.soilMoisture)%" },
                @{ Type = "system_status"; Severity = "info"; Message = "System operating normally. All sensors functional." },
                @{ Type = "weather_alert"; Severity = "warning"; Message = "High humidity ($($reading.humidity)%) may indicate rain." },
                @{ Type = "crop_health"; Severity = "info"; Message = "Crop health indicators are positive." }
            )
            
            # Pick alert based on actual conditions
            $chosenAlert = if ($reading.soilMoisture -lt 30) { $alertTypes[4] }
                         elseif ($reading.soilMoisture -lt 45) { $alertTypes[0] }
                         elseif ($reading.temperature -gt 35) { $alertTypes[1] }
                         elseif ($reading.batteryLevel -lt 30) { $alertTypes[2] }
                         elseif ($reading.humidity -gt 85) { $alertTypes[6] }
                         else { $alertTypes | Get-Random }
            
            New-SystemAlert -Type $chosenAlert.Type -Severity $chosenAlert.Severity -Message $chosenAlert.Message
        }
        
        # Reset daily counter
        if ((Get-Date).Date -ne $startTime.Date) {
            Write-Host "`n=== New Day - Reset alert counter (had $alertCount alerts yesterday) ===`n" -ForegroundColor Magenta
            $alertCount = 0
            $startTime = Get-Date
        }
        
        Start-Sleep -Seconds $readingInterval
    }
} catch {
    Write-Host "`nSimulation stopped: $($_.Exception.Message)" -ForegroundColor Red
} finally {
    if (Test-Path $pidFile) { Remove-Item $pidFile }
}
