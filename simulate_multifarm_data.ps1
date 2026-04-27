param(
    [string]$ApiBase = "http://127.0.0.1:8000",
    [int]$Cycles = 0,
    [int]$IntervalSeconds = 8,
    [switch]$DrySpell
)

$farms = @(
    @{ Id = "550e8400-e29b-41d4-a716-446655440002"; Name = "Ngong Hills"; BaseSoil = 28; BaseTemp = 27; BaseHumidity = 67 },
    @{ Id = "550e8400-e29b-41d4-a716-446655440003"; Name = "Machakos"; BaseSoil = 41; BaseTemp = 29; BaseHumidity = 56 },
    @{ Id = "550e8400-e29b-41d4-a716-446655440004"; Name = "Kitui"; BaseSoil = 58; BaseTemp = 26; BaseHumidity = 63 }
)

function New-FarmReading {
    param(
        [hashtable]$Farm,
        [bool]$ForceDry
    )

    $soilDrift = Get-Random -Minimum -6 -Maximum 7
    $tempDrift = (Get-Random -Minimum -15 -Maximum 16) / 10
    $humidityDrift = Get-Random -Minimum -7 -Maximum 8

    $soil = if ($ForceDry) {
        [math]::Max(18, $Farm.BaseSoil - 10 + $soilDrift)
    }
    else {
        [math]::Min(82, [math]::Max(20, $Farm.BaseSoil + $soilDrift))
    }

    return @{
        soilMoisture = [math]::Round($soil + (Get-Random -Minimum 0 -Maximum 10) / 10, 1)
        temperature = [math]::Round($Farm.BaseTemp + $tempDrift, 1)
        humidity = [math]::Round([math]::Min(90, [math]::Max(35, $Farm.BaseHumidity + $humidityDrift)), 1)
        timestamp = (Get-Date).ToUniversalTime().ToString("o")
    }
}

function Send-FarmReading {
    param(
        [hashtable]$Farm,
        [hashtable]$Reading
    )

    $uri = "$ApiBase/api/sensor/hardware/$($Farm.Id)/reading"
    $json = $Reading | ConvertTo-Json
    $response = Invoke-RestMethod -Uri $uri -Method Post -ContentType "application/json" -Body $json
    $alertFlag = if ($response.alert) { "YES" } else { "NO" }

    Write-Host ("[{0}] Soil={1}% Temp={2}C Humidity={3}% Alert={4}" -f $Farm.Name, $Reading.soilMoisture, $Reading.temperature, $Reading.humidity, $alertFlag) -ForegroundColor Green
}

Write-Host "Starting multi-farm simulation against $ApiBase" -ForegroundColor Cyan
Write-Host "Farms: $($farms.Count) | Interval: ${IntervalSeconds}s | Dry spell mode: $($DrySpell.IsPresent)" -ForegroundColor DarkCyan
Write-Host "Use Ctrl+C to stop when Cycles is 0." -ForegroundColor DarkYellow

$cycle = 0
while ($Cycles -eq 0 -or $cycle -lt $Cycles) {
    $cycle++
    Write-Host ("Cycle {0}" -f $cycle) -ForegroundColor Yellow

    foreach ($farm in $farms) {
        try {
            $reading = New-FarmReading -Farm $farm -ForceDry:$DrySpell.IsPresent
            Send-FarmReading -Farm $farm -Reading $reading
        }
        catch {
            Write-Host ("[{0}] Failed: {1}" -f $farm.Name, $_.Exception.Message) -ForegroundColor Red
        }
    }

    if ($Cycles -eq 0 -or $cycle -lt $Cycles) {
        Start-Sleep -Seconds $IntervalSeconds
    }
}

Write-Host "Multi-farm simulation complete." -ForegroundColor Cyan
