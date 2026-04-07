# Limpieza de Encoding y Caracteres UTF-8 - RestaurantePOS

## Problema Resuelto ?

Se corrigieron **caracteres raros** que aparecían como:
- "Electr??ón?ico" ? "Electrónico"
- "???" ? caracteres correctos
- Caracteres de control ocultos

## Soluciones Implementadas

### 1?? Conversión a UTF-8 Puro
Todos los archivos fueron convertidos a **UTF-8 sin BOM** (Byte Order Mark):

```
? resources/views/**/*.blade.php
? resources/css/**/*.css
? resources/js/**/*.js
? app/Models/**/*.php
? app/Http/Controllers/**/*.php
```

### 2?? Limpieza de Caracteres Especiales
- Eliminados caracteres de control `[\x00-\x08, \x0B-\x0C, \x0E-\x1F, \x7F]`
- Eliminados zero-width characters (caracteres invisibles)
- Normalizados saltos de línea

### 3?? Sincronización resources/ ? public/

Los archivos en **resources/** deben ser copiados a **public/** para que el navegador los cargue.

#### En PowerShell (Windows):
```powershell
.\sync-assets.ps1
```

#### En Bash/Linux/Mac:
```bash
chmod +x sync-assets.sh
./sync-assets.sh
```

#### Manualmente:
```bash
# Copiar CSS
Copy-Item "resources/css/*" "public/css/" -Recurse -Force

# Copiar JS
Copy-Item "resources/js/*" "public/js/" -Recurse -Force
```

## Archivos Sincronizados

```
resources/css/auth/login.css           ? public/css/auth/login.css
resources/css/dashboard/dashboard.css  ? public/css/dashboard/dashboard.css
resources/css/layouts/app.css          ? public/css/layouts/app.css
resources/css/layouts/navbar.css       ? public/css/layouts/navbar.css
resources/css/layouts/sidebar.css      ? public/css/layouts/sidebar.css
resources/css/pages/403.css            ? public/css/pages/403.css

resources/js/auth/login.js             ? public/js/auth/login.js
resources/js/dashboard/dashboard.js    ? public/js/dashboard/dashboard.js
resources/js/layouts/sidebar.js        ? public/js/layouts/sidebar.js
```

## Workflow Recomendado

### Cuando edites CSS o JS:

```
1. Edita en resources/css/ o resources/js/
2. Ejecuta: .\sync-assets.ps1  (o ./sync-assets.sh)
3. Recarga el navegador (Ctrl+Shift+R para limpiar caché)
4. Commit a git: git add -A && git commit -m "..."
```

### Configuración Git Automática

Se agregó `.gitattributes` para:
- Normalizar line endings a LF (Unix-style)
- Forzar UTF-8 en todos los archivos de texto
- Interpretar binarios correctamente

Esto evita problemas cuando trabajas entre Windows/Linux/Mac.

## Prevención de Futuros Problemas

### En VS Code, configura:

**settings.json:**
```json
{
    "files.encoding": "utf8",
    "files.endOfLine": "lf",
    "editor.renderWhitespace": "all",
    "[php]": {
        "editor.formatOnSave": true,
        "editor.defaultFormatter": "bmewburn.vscode-intelephense-client"
    },
    "[blade]": {
        "editor.formatOnSave": true
    }
}
```

### En la terminal, antes de clonar repositorio:

```bash
git config --global core.safecrlf warn
git config --global core.autocrlf input
```

## Verificación

Para verificar que los archivos estén en UTF-8 correcto:

**En PowerShell:**
```powershell
[System.IO.File]::ReadAllText("resources/views/auth/login.blade.php", [System.Text.Encoding]::UTF8) | Select-String "Electrónico"
```

**En Linux/Mac:**
```bash
file resources/views/auth/login.blade.php
# Debería mostrar: UTF-8 Unicode text
```

## Commit Realizado

```
Commit: 5d57dbb
fix: Limpiar caracteres raros y convertir todos los archivos a UTF-8

- Convertir todos los archivos a UTF-8 sin BOM
- Eliminar caracteres de control y caracteres raros
- Asegurar codificación consistente en vistas, CSS y JS
- Sincronizar archivos de resources/ a public/
- Soluciona display de caracteres como 'Electrónico' y otros
```

## Notas Importantes

1. **Siempre copia a public/** después de editar CSS/JS
2. **Usa los scripts sync-assets** para automatizar
3. **Recarga el navegador** con Ctrl+Shift+R (Cmd+Shift+R en Mac) para limpiar caché
4. **Verifica en Herramientas del Navegador** (F12) ? Pestaña Network para confirmar que los archivos se cargan

---

*Última actualización: Abril 7, 2026*
