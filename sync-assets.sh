#!/usr/bin/env bash
# Script para sincronizar archivos CSS y JS de resources/ a public/
# Uso: ./sync-assets.sh

echo "==================================="
echo "Sincronizando archivos de recursos"
echo "==================================="

# Copiar CSS
cp -v resources/css/auth/login.css public/css/auth/
cp -v resources/css/dashboard/dashboard.css public/css/dashboard/
cp -v resources/css/layouts/app.css public/css/layouts/
cp -v resources/css/layouts/navbar.css public/css/layouts/
cp -v resources/css/layouts/sidebar.css public/css/layouts/
cp -v resources/css/pages/403.css public/css/pages/

# Copiar JS
cp -v resources/js/auth/login.js public/js/auth/
cp -v resources/js/dashboard/dashboard.js public/js/dashboard/
cp -v resources/js/layouts/sidebar.js public/js/layouts/

echo "==================================="
echo "? Sincronización completada"
echo "==================================="
