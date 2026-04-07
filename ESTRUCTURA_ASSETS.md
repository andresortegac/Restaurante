# Estructura de Recursos (Assets) - Sistema RestaurantePOS

## Organización de Directorios

La estructura de recursos ha sido reorganizada para mejorar la mantenibilidad y claridad. Cada vista tiene sus propios estilos y scripts asociados.

```
resources/
??? css/
?   ??? app.css                      # Estilos globales (variables y reset)
?   ??? auth/
?   ?   ??? login.css               # Estilos específicos del login
?   ??? dashboard/
?   ?   ??? dashboard.css           # Estilos del dashboard y contenido principal
?   ??? layouts/
?   ?   ??? app.css                 # Variables y estilos globales del layout
?   ?   ??? navbar.css              # Estilos de la barra de navegación
?   ?   ??? sidebar.css             # Estilos del menú lateral
?   ??? pages/
?       ??? 403.css                 # Estilos de página de error 403
?
??? js/
?   ??? app.js                      # Entry point principal (bootstrap)
?   ??? auth/
?   ?   ??? login.js                # Scripts del formulario de login
?   ??? dashboard/
?   ?   ??? dashboard.js            # Scripts de carga de datos del dashboard
?   ??? layouts/
?       ??? sidebar.js              # Scripts del menú lateral (toggle, submenu, etc)
?
??? images/
?   ??? auth/                       # Imágenes para la sección de autenticación
?   ??? common/                     # Imágenes comunes (logos, iconos, etc)
?   ??? dashboard/                  # Imágenes para el dashboard
?
??? views/
    ??? auth/
    ?   ??? login.blade.php
    ??? components/
    ?   ??? alerts.blade.php        # Componente de alertas SweetAlert2
    ??? dashboard.blade.php
    ??? errors/
    ?   ??? 403.blade.php
    ??? layouts/
        ??? app.blade.php           # Layout base
```

## Guía de Referenciación

### En las vistas Blade:

#### Para CSS:
```blade
<!-- Archivos CSS específicos de la vista -->
<link rel="stylesheet" href="{{ asset('css/auth/login.css') }}">
<link rel="stylesheet" href="{{ asset('css/dashboard/dashboard.css') }}">
<link rel="stylesheet" href="{{ asset('css/layouts/navbar.css') }}">
<link rel="stylesheet" href="{{ asset('css/layouts/sidebar.css') }}">
```

#### Para JavaScript:
```blade
<!-- Scripts específicos -->
<script src="{{ asset('js/auth/login.js') }}"></script>
<script src="{{ asset('js/dashboard/dashboard.js') }}"></script>
<script src="{{ asset('js/layouts/sidebar.js') }}"></script>
```

#### Para Imágenes:
```blade
<!-- Imágenes en las vistas -->
<img src="{{ asset('images/auth/logo.png') }}" alt="Logo">
<img src="{{ asset('images/common/icon.png') }}" alt="Icono">
<img src="{{ asset('images/dashboard/chart.png') }}" alt="Gráfico">
```

## Descripción de Archivos CSS

### `css/auth/login.css`
- **Vista asociada:** `resources/views/auth/login.blade.php`
- **Contenido:** Estilos del formulario de login, contenedor, campos de entrada, botones
- **Responsivo:** Sí (media queries para dispositivos móviles)

### `css/dashboard/dashboard.css`
- **Vista asociada:** `resources/views/dashboard.blade.php`
- **Contenido:** Estilos del área principal, tarjetas de estadísticas, tabla de permisos, grillas
- **Responsivo:** Sí (grid responsive, media queries)

### `css/layouts/app.css`
- **Vista asociada:** `resources/views/layouts/app.blade.php`
- **Contenido:** Variables CSS globales (--primary-color, --sidebar-bg, etc.), reset y estilos base
- **Uso:** Define el sistema de variables de color y dimensiones

### `css/layouts/navbar.css`
- **Vista asociada:** `resources/views/layouts/app.blade.php`
- **Contenido:** Barra de navegación superior, dropdown de usuario, botones
- **Responsivo:** Sí (media queries para tablet y móvil)

### `css/layouts/sidebar.css`
- **Vista asociada:** `resources/views/layouts/app.blade.php`
- **Contenido:** Menú lateral, ítems de menú, submenús colapsables, scrollbar personalizado
- **Responsivo:** Sí (sidebar oculto en móvil, aparece con toggle)

### `css/pages/403.css`
- **Vista asociada:** `resources/views/errors/403.blade.php`
- **Contenido:** Página de error 403, iconos, botones de acción
- **Responsivo:** Sí (optimizado para todos los dispositivos)

## Descripción de Archivos JavaScript

### `js/auth/login.js`
- **Vista asociada:** `resources/views/auth/login.blade.php`
- **Contenido:**
  - Mostrar errores con SweetAlert2
  - Mostrar mensajes de éxito
  - Validación de formulario antes de envío
- **Dependencias:** SweetAlert2 (CDN)

### `js/dashboard/dashboard.js`
- **Vista asociada:** `resources/views/dashboard.blade.php`
- **Contenido:**
  - Obtener datos del usuario autenticado vía API
  - Mostrar roles del usuario
  - Mostrar permisos agrupados por categoría
  - Actualizar contador de permisos
- **Dependencias:** Fetch API, DOM API
- **Endpoints API:** `/api/user`

### `js/layouts/sidebar.js`
- **Vista asociada:** `resources/views/layouts/app.blade.php`
- **Contenido:**
  - Toggle del sidebar en dispositivos móviles
  - Manejo de menús colapsables
  - Cerrar sidebar al hacer clic en enlace (móvil)
  - Cerrar sidebar al hacer clic fuera (móvil)
- **Dependencias:** DOM API

## Estructura de Carpetas para Imágenes

### `images/auth/`
Para guardar imágenes relacionadas con:
- Logos de aplicación alternos
- Iconografía de login
- Elementos visuales de autenticación

### `images/common/`
Para guardar imágenes compartidas:
- Logo principal de la aplicación
- Iconos genéricos
- Elementos visuales reutilizables
- Favicons

### `images/dashboard/`
Para guardar imágenes del dashboard:
- Gráficos estáticos
- Imágenes de estadísticas
- Iconos de módulos
- Elementos visuales específicos

## Cómo Añadir Nuevos Módulos

Cuando necesites agregar un nuevo módulo (ej: Módulo de Inventario):

1. **Crea la estructura de carpetas:**
   ```
   resources/
   ??? css/inventario/
   ?   ??? inventario.css
   ??? js/inventario/
   ?   ??? inventario.js
   ??? images/inventario/
   ```

2. **Agregar referencias en la vista:**
   ```blade
   @extends('layouts.app')
   
   @section('content')
       <!-- Contenido aquí -->
       <script src="{{ asset('js/inventario/inventario.js') }}"></script>
   @endsection
   ```

3. **Agregar enlace en el sidebar** (en `layouts/app.blade.php`):
   ```blade
   <li>
       <a href="{{ route('inventario.index') }}">
           <i class="fas fa-box"></i> Inventario
       </a>
   </li>
   ```

## Convenciones de Nombres

- **Archivos CSS:** `nombre-modulo.css` (minúsculas, guiones)
- **Archivos JS:** `nombre-modulo.js` (minúsculas, guiones)
- **Carpetas:** `nombre-modulo/` (minúsculas, guiones)
- **Variables CSS:** `--primary-color`, `--text-dark`, `--shadow` (guiones, descriptivo)
- **Clases CSS:** `.card-header`, `.sidebar-menu`, `.btn-primary` (minúsculas, guiones)
- **IDs en HTML:** `sidebarToggle`, `loginForm`, `permissions-container` (camelCase)

## Variables CSS Globales

Los siguientes variables están disponibles en todos los CSS (definidas en `css/layouts/app.css`):

```css
--primary-color: #667eea;           /* Morado principal */
--secondary-color: #764ba2;         /* Morado secundario */
--sidebar-bg: #2c3e50;              /* Fondo del sidebar */
--sidebar-hover: #34495e;           /* Hover del sidebar */
--text-light: #ecf0f1;              /* Texto claro */
--text-dark: #333;                  /* Texto oscuro */
--bg-light: #f5f7fa;                /* Fondo claro */
--border-color: #ddd;               /* Color de bordes */
--shadow: 0 2px 10px rgba(...);     /* Sombra pequeña */
--shadow-lg: 0 5px 20px rgba(...);  /* Sombra grande */
```

## Notas Importantes

1. **Siempre usar `{{ asset() }}`** para las rutas de archivos estáticos
2. **No incrustar `<style>` o `<script>` tags** en las vistas, usar archivos externos
3. **UTF-8 charset:** Asegurar que en el `<head>` exista `<meta charset="UTF-8">`
4. **SweetAlert2:** Se carga desde CDN en el layout base
5. **Bootstrap:** Se carga desde CDN en el layout base
6. **Font Awesome:** Se carga desde CDN en el layout base

## Edición de Estilos

Para editar estilos de una vista específica:

1. Localiza el archivo CSS correspondiente
2. Abre el archivo desde: `resources/css/[modulo]/[nombre].css`
3. Realiza los cambios
4. Guarda el archivo (sin necesidad de recompilar si usas un servidor local)

Ejemplo: Para modificar el color del login:
- Abre `resources/css/auth/login.css`
- Modifica los valores en `.login-container` o `.btn-login`
- Guarda y recarga el navegador

## Solución de Problemas

**Los estilos no se ven:**
- Verifica que `{{ asset() }}` esté correctamente formado
- Limpia cache del navegador (Ctrl+Shift+R)
- Comprueba la consola del navegador para errores 404

**Los scripts no funcionan:**
- Verifica que las dependencias (SweetAlert2, etc) estén cargadas
- Abre la consola del navegador (F12) para ver errores
- Comprueba que los IDs del HTML coincidan con los selectores en JS

---

*Última actualización: Abril 7, 2026*
