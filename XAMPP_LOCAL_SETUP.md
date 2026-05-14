# XAMPP Local

Este proyecto ya puede ejecutarse con Apache de XAMPP sin depender de `php artisan serve`.

## Que hace el ajuste

- Copia el proyecto a `C:\xampp\htdocs\Restaurante`
- Crea un `.env` listo para XAMPP usando MySQL
- Deja `QUEUE_CONNECTION=sync` para no depender de una consola con `queue:work`
- Ejecuta `key:generate`, `migrate --seed`, `storage:link`, `optimize:clear` y `optimize`
- Genera una configuracion de Apache con alias local para servir `public`

## Requisitos

- XAMPP instalado en `C:\xampp`
- Servicios de Apache y MySQL activos
- El proyecto fuente debe conservar la carpeta `vendor`

## Ejecucion

Desde la raiz del repo:

```powershell
.\scripts\setup-xampp.cmd
```

O si prefieres PowerShell directo:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\setup-xampp.ps1
```

## Resultado esperado

- Proyecto copiado a `C:\xampp\htdocs\Restaurante`
- Alias local disponible en `http://localhost/restaurante`
- Login disponible en `http://localhost/restaurante/login`

Credenciales sembradas por defecto:

- `admin@restaurante.com`
- `password123`

## Parametros utiles

```powershell
.\scripts\setup-xampp.ps1 -ProjectName RestaurantePOS -ProjectAlias restaurantepos
.\scripts\setup-xampp.ps1 -DbName restaurante_local -DbUser root -DbPassword 12345
.\scripts\setup-xampp.ps1 -UseExistingDestination
.\scripts\setup-xampp.ps1 -SkipDatabase
.\scripts\setup-xampp.ps1 -SkipApacheConfig
```

## Notas

- El script se detiene si la carpeta destino ya existe, para no sobrescribir una instalacion activa por accidente.
- Si la carpeta `C:\xampp\htdocs\Restaurante` ya existe y quieres reutilizarla, ejecuta el script con `-UseExistingDestination`.
- Se crea un respaldo de `httpd.conf` en `httpd.conf.codex.bak` la primera vez que se ajusta Apache.
- Si el `.env` ya existe en XAMPP y usas `-UseExistingDestination`, se crea un respaldo en `.env.before-xampp-setup.bak`.
- Si luego quieres un comportamiento mas parecido a produccion, puedes cambiar en el `.env` copiado:

```env
APP_ENV=production
APP_DEBUG=false
QUEUE_CONNECTION=database
```

Si activas `QUEUE_CONNECTION=database`, ahi si necesitarias un worker de cola como servicio aparte.
