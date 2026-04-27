@echo off
echo Updating all demo.html references to dashboard.html...

for %%f in (*.html) do (
    echo Processing %%f...
    powershell -Command "(Get-Content '%%f') -replace 'href=\"demo\.html\"', 'href=\"dashboard.html\"' | Set-Content '%%f'"
    powershell -Command "(Get-Content '%%f') -replace 'href=\"demo.html\" class=\"active\"', 'href=\"dashboard.html\" class=\"active\"' | Set-Content '%%f'"
)

echo.
echo Done! All references updated.
pause
