# Script para sincronizar archivos CSS y JS de resources/ a public/
# Uso: .\sync-assets.ps1

Write-Host "===================================" -ForegroundColor Cyan
Write-Host "Sincronizando archivos de recursos" -ForegroundColor Cyan
Write-Host "===================================" -ForegroundColor Cyan

$files = @{
    "resources/css/auth/login.css" = "public/css/auth/login.css"
    "resources/css/dashboard/dashboard.css" = "public/css/dashboard/dashboard.css"
    "resources/css/layouts/app.css" = "public/css/layouts/app.css"
    "resources/css/layouts/navbar.css" = "public/css/layouts/navbar.css"
    "resources/css/layouts/sidebar.css" = "public/css/layouts/sidebar.css"
    "resources/css/pages/403.css" = "public/css/pages/403.css"
    "resources/js/auth/login.js" = "public/js/auth/login.js"
    "resources/js/dashboard/dashboard.js" = "public/js/dashboard/dashboard.js"
    "resources/js/layouts/sidebar.js" = "public/js/layouts/sidebar.js"
}

$count = 0
foreach ($source in $files.Keys) {
    $destination = $files[$source]
    if (Test-Path $source) {
        Copy-Item $source $destination -Force
        Write-Host "? Copiado: $source ? $destination" -ForegroundColor Green
        $count++
    }
    else {
        Write-Host "? No encontrado: $source" -ForegroundColor Red
    }
}

Write-Host "===================================" -ForegroundColor Cyan
Write-Host "? Sincronización completada ($count archivos)" -ForegroundColor Green
Write-Host "===================================" -ForegroundColor Cyan
