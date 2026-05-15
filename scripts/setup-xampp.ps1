param(
    [string]$XamppRoot = 'C:\xampp',
    [string]$ProjectName = 'Restaurante',
    [string]$ProjectAlias = '',
    [string]$DbName = 'restaurante',
    [string]$DbUser = 'root',
    [string]$DbPassword = '',
    [string]$AppUrl = '',
    [switch]$UseExistingDestination,
    [switch]$SkipDatabase,
    [switch]$SkipApacheConfig
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Write-Step {
    param([string]$Message)

    Write-Host "[XAMPP] $Message" -ForegroundColor Cyan
}

function Assert-PathExists {
    param(
        [string]$Path,
        [string]$Label
    )

    if (-not (Test-Path -LiteralPath $Path)) {
        throw "$Label no fue encontrado en: $Path"
    }
}

function Set-EnvValue {
    param(
        [string]$FilePath,
        [string]$Key,
        [string]$Value
    )

    $content = Get-Content -LiteralPath $FilePath -Raw
    $pattern = '(?m)^' + [regex]::Escape($Key) + '=.*$'
    $replacement = $Key + '=' + $Value

    if ([regex]::IsMatch($content, $pattern)) {
        $content = [regex]::Replace($content, $pattern, [System.Text.RegularExpressions.MatchEvaluator]{
            param($match)
            $replacement
        }, 1)
    } else {
        $content = $content.TrimEnd("`r", "`n") + "`r`n" + $replacement + "`r`n"
    }

    [System.IO.File]::WriteAllText($FilePath, $content, [System.Text.UTF8Encoding]::new($false))
}

function Invoke-NativeCommand {
    param(
        [string]$FilePath,
        [string[]]$Arguments,
        [string]$ErrorMessage
    )

    & $FilePath @Arguments

    if ($LASTEXITCODE -ne 0) {
        throw $ErrorMessage
    }
}

$sourcePath = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path

if ([string]::IsNullOrWhiteSpace($ProjectAlias)) {
    $ProjectAlias = $ProjectName.ToLowerInvariant()
}

if ([string]::IsNullOrWhiteSpace($AppUrl)) {
    $AppUrl = "http://localhost/$ProjectAlias"
}

if ($ProjectName -notmatch '^[A-Za-z0-9._-]+$') {
    throw 'ProjectName solo puede contener letras, numeros, punto, guion y guion bajo.'
}

if ($ProjectAlias -notmatch '^[A-Za-z0-9._-]+$') {
    throw 'ProjectAlias solo puede contener letras, numeros, punto, guion y guion bajo.'
}

if ($DbName -notmatch '^[A-Za-z0-9_]+$') {
    throw 'DbName solo puede contener letras, numeros y guion bajo.'
}

$destinationPath = Join-Path $XamppRoot ("htdocs\" + $ProjectName)
$phpExe = Join-Path $XamppRoot 'php\php.exe'
$mysqlExe = Join-Path $XamppRoot 'mysql\bin\mysql.exe'
$httpdConf = Join-Path $XamppRoot 'apache\conf\httpd.conf'
$apacheExtraConf = Join-Path $XamppRoot ("apache\conf\extra\" + $ProjectAlias + "-laravel.conf")
$apacheIncludeLine = 'Include conf/extra/' + $ProjectAlias + '-laravel.conf'
$envTemplate = Join-Path $sourcePath '.env.xampp.example'
$envDestination = Join-Path $destinationPath '.env'

Assert-PathExists -Path $sourcePath -Label 'La carpeta del proyecto'
Assert-PathExists -Path $XamppRoot -Label 'La carpeta principal de XAMPP'
Assert-PathExists -Path $phpExe -Label 'PHP de XAMPP'

if (-not $SkipDatabase) {
    Assert-PathExists -Path $mysqlExe -Label 'MySQL de XAMPP'
}

if (-not $SkipApacheConfig) {
    Assert-PathExists -Path $httpdConf -Label 'httpd.conf de Apache'
}

if (Test-Path -LiteralPath $destinationPath) {
    if (-not $UseExistingDestination) {
        throw "La carpeta destino ya existe: $destinationPath. Usa -UseExistingDestination, eliminala o cambia el valor de -ProjectName."
    }

    Assert-PathExists -Path (Join-Path $destinationPath 'artisan') -Label 'La instalacion existente de Laravel en XAMPP'
    Write-Step "Usando la instalacion existente en $destinationPath"
} else {
    Write-Step "Copiando el proyecto a $destinationPath"
    New-Item -ItemType Directory -Path $destinationPath -Force | Out-Null

    $robocopyArguments = @(
        $sourcePath,
        $destinationPath,
        '/E',
        '/R:1',
        '/W:1',
        '/NFL',
        '/NDL',
        '/NJH',
        '/NJS',
        '/XD',
        '.git',
        'node_modules',
        'storage\logs',
        'storage\framework\cache\data',
        'storage\framework\sessions',
        'storage\framework\views',
        '/XF',
        '.env',
        '.env.backup',
        '.phpunit.result.cache',
        'server.log'
    )

    & robocopy.exe @robocopyArguments
    $robocopyExitCode = $LASTEXITCODE

    if ($robocopyExitCode -gt 7) {
        throw 'Robocopy reporto un error al copiar el proyecto.'
    }
}

Assert-PathExists -Path $envTemplate -Label 'La plantilla .env.xampp.example'

Write-Step 'Creando el archivo .env de XAMPP'

if ($UseExistingDestination -and (Test-Path -LiteralPath $envDestination)) {
    $envBackupPath = $envDestination + '.before-xampp-setup.bak'

    if (-not (Test-Path -LiteralPath $envBackupPath)) {
        Copy-Item -LiteralPath $envDestination -Destination $envBackupPath
    }
} else {
    Copy-Item -LiteralPath $envTemplate -Destination $envDestination -Force
}

Set-EnvValue -FilePath $envDestination -Key 'APP_NAME' -Value '"Solomo & Pomo"'
Set-EnvValue -FilePath $envDestination -Key 'APP_URL' -Value $AppUrl
Set-EnvValue -FilePath $envDestination -Key 'DB_CONNECTION' -Value 'mysql'
Set-EnvValue -FilePath $envDestination -Key 'DB_HOST' -Value '127.0.0.1'
Set-EnvValue -FilePath $envDestination -Key 'DB_PORT' -Value '3306'
Set-EnvValue -FilePath $envDestination -Key 'DB_DATABASE' -Value $DbName
Set-EnvValue -FilePath $envDestination -Key 'DB_USERNAME' -Value $DbUser
Set-EnvValue -FilePath $envDestination -Key 'DB_PASSWORD' -Value $DbPassword
Set-EnvValue -FilePath $envDestination -Key 'QUEUE_CONNECTION' -Value 'sync'

foreach ($directory in @(
    'bootstrap\cache',
    'storage\app',
    'storage\app\public',
    'storage\framework\cache',
    'storage\framework\sessions',
    'storage\framework\views',
    'storage\logs'
)) {
    New-Item -ItemType Directory -Path (Join-Path $destinationPath $directory) -Force | Out-Null
}

if (-not $SkipDatabase) {
    Write-Step "Creando la base de datos $DbName si no existe"

    $mysqlArguments = @('-u', $DbUser)

    if (-not [string]::IsNullOrWhiteSpace($DbPassword)) {
        $mysqlArguments += "-p$DbPassword"
    }

    $mysqlArguments += @(
        '-e',
        "CREATE DATABASE IF NOT EXISTS $DbName CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    )

    Invoke-NativeCommand -FilePath $mysqlExe -Arguments $mysqlArguments -ErrorMessage 'No fue posible crear la base de datos en MySQL.'
}

Write-Step 'Generando APP_KEY'
Push-Location $destinationPath

try {
    Invoke-NativeCommand -FilePath $phpExe -Arguments @('artisan', 'key:generate', '--force') -ErrorMessage 'No fue posible generar APP_KEY.'

    Write-Step 'Ejecutando migraciones y seeders'
    Invoke-NativeCommand -FilePath $phpExe -Arguments @('artisan', 'migrate', '--seed', '--force') -ErrorMessage 'No fue posible ejecutar migrate --seed.'

    Write-Step 'Creando el enlace publico de storage'
    Invoke-NativeCommand -FilePath $phpExe -Arguments @('artisan', 'storage:link') -ErrorMessage 'No fue posible crear el storage link.'

    Write-Step 'Limpiando y optimizando caches de Laravel'
    Invoke-NativeCommand -FilePath $phpExe -Arguments @('artisan', 'optimize:clear') -ErrorMessage 'No fue posible limpiar los caches.'
    Invoke-NativeCommand -FilePath $phpExe -Arguments @('artisan', 'optimize') -ErrorMessage 'No fue posible optimizar la aplicacion.'
}
finally {
    Pop-Location
}

if (-not $SkipApacheConfig) {
    Write-Step 'Generando la configuracion de Apache para el alias local'

    $apachePath = $destinationPath.Replace('\', '/')
    $apacheConfig = @"
Alias /$ProjectAlias "$apachePath/public"
AliasMatch ^/$ProjectAlias/(.*)$ "$apachePath/public/`$1"

<Directory "$apachePath/public">
    AllowOverride All
    Require all granted
    DirectoryIndex index.php
    Options FollowSymLinks
</Directory>
"@

    [System.IO.File]::WriteAllText($apacheExtraConf, $apacheConfig, [System.Text.UTF8Encoding]::new($false))

    $httpdContent = Get-Content -LiteralPath $httpdConf -Raw

    $httpdContent = [regex]::Replace(
        $httpdContent,
        '(?m)^\s*#\s*LoadModule rewrite_module modules/mod_rewrite\.so\s*$',
        'LoadModule rewrite_module modules/mod_rewrite.so'
    )

    if ($httpdContent -notmatch [regex]::Escape($apacheIncludeLine)) {
        $httpdContent = $httpdContent.TrimEnd("`r", "`n") + "`r`n`r`n" + $apacheIncludeLine + "`r`n"
    }

    $httpdBackup = $httpdConf + '.codex.bak'
    if (-not (Test-Path -LiteralPath $httpdBackup)) {
        Copy-Item -LiteralPath $httpdConf -Destination $httpdBackup
    }

    [System.IO.File]::WriteAllText($httpdConf, $httpdContent, [System.Text.UTF8Encoding]::new($false))
}

Write-Step 'Proceso terminado'
Write-Host ''
Write-Host "Proyecto copiado en: $destinationPath" -ForegroundColor Green
Write-Host "URL sugerida: $AppUrl/login" -ForegroundColor Green
Write-Host ''
Write-Host 'Reinicia Apache desde XAMPP para tomar la nueva configuracion.' -ForegroundColor Cyan
