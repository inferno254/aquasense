$body = '{"email":"admin@example.com","password":"password123"}'
$login = Invoke-RestMethod -Uri 'http://localhost:5000/api/auth/login' -Method Post -ContentType 'application/json' -Body $body
Write-Output "TOKEN:$($login.token)"
$headers = @{ Authorization = "Bearer $($login.token)" }
Invoke-RestMethod -Uri 'http://localhost:5000/api/data/farms' -Headers $headers -Method Get | ConvertTo-Json -Depth 5
