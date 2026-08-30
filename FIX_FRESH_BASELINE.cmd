@echo off
setlocal
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0FIX_FRESH_BASELINE.ps1" -MigrateFresh
if errorlevel 1 (
  echo.
  echo Fresh baseline failed. Review the error above.
  exit /b 1
)
echo.
echo Fresh baseline completed successfully.
endlocal
