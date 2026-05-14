@echo off
setlocal
powershell -ExecutionPolicy Bypass -File "%~dp0setup-xampp.ps1" %*
endlocal
