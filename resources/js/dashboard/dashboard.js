/* Script para: dashboard.blade.php */

document.addEventListener('DOMContentLoaded', function() {
    cargarDatosUsuario();
});

function cargarDatosUsuario() {
    // Obtener información del usuario autenticado
    fetch('/api/user', {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        if (!response.ok) throw new Error('Error al obtener usuario');
        return response.json();
    })
    .then(data => {
        if (data && data.user) {
            mostrarRoles(data.user.roles || []);
            mostrarPermisos(data.user.permissions || []);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('roles-container').innerHTML = 
            '<p class="text-danger"><i class="fas fa-exclamation-circle"></i> Error al cargar información</p>';
    });
}

function mostrarRoles(roles) {
    const rolesContainer = document.getElementById('roles-container');
    
    if (!rolesContainer) return;
    
    if (!roles || roles.length === 0) {
        rolesContainer.innerHTML = '<p class="text-muted">Sin roles asignados</p>';
        return;
    }

    let html = '';
    roles.forEach(role => {
        const roleDesc = role.description ? 
            `<small class="text-muted d-block">${role.description}</small>` : 
            '';
        html += `
            <span class="badge-role">
                <i class="fas fa-user-shield"></i> ${role.name}
                ${roleDesc}
            </span>
        `;
    });

    rolesContainer.innerHTML = html || '<p class="text-muted">Sin roles asignados</p>';
    document.getElementById('permissions-count').textContent = 
        roles.reduce((sum, role) => sum + (role.permissions ? role.permissions.length : 0), 0);
}

function mostrarPermisos(permisos) {
    const permissionsContainer = document.getElementById('permissions-container');
    
    if (!permissionsContainer) return;
    
    if (!permisos || permisos.length === 0) {
        permissionsContainer.innerHTML = '<p class="text-muted">Sin permisos asignados</p>';
        return;
    }

    // Agrupar permisos por categoría (users, roles, orders, etc.)
    const gruposPorCategoria = {};
    
    permisos.forEach(permission => {
        // Extraer categoría del nombre del permiso (ej: users.view -> users)
        const categoria = permission.name.split('.')[0];
        if (!gruposPorCategoria[categoria]) {
            gruposPorCategoria[categoria] = [];
        }
        gruposPorCategoria[categoria].push(permission);
    });

    let html = '<div class="row">';
    
    Object.entries(gruposPorCategoria).forEach(([categoria, permisos]) => {
        html += `
            <div class="col-md-6 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-lock"></i> ${categoria.charAt(0).toUpperCase() + categoria.slice(1)}
                        </h6>
                    </div>
                    <div class="card-body">
        `;

        permisos.forEach(permission => {
            const accion = permission.name.split('.')[1] || '';
            html += `
                <span class="badge-permission ${categoria}">
                    <i class="fas fa-check-circle"></i> ${accion}
                </span>
            `;
        });

        html += `
                    </div>
                </div>
            </div>
        `;
    });

    html += '</div>';
    permissionsContainer.innerHTML = html;
}
