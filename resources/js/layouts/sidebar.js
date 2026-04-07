/* Script para: layouts/app.blade.php - Sidebar */

document.addEventListener('DOMContentLoaded', function() {
    // Botn para mostrar/ocultar sidebar en mvil
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }

    // Cerrar sidebar al hacer clic en un enlace
    const sidebarLinks = document.querySelectorAll('.sidebar-menu a');
    sidebarLinks.forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('show');
            }
        });
    });

    // Manejar mens colapsables
    const toggleButtons = document.querySelectorAll('[data-toggle-menu]');
    toggleButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const submenu = this.nextElementSibling;
            if (submenu && submenu.classList.contains('sidebar-submenu')) {
                submenu.classList.toggle('show');
                // Cambiar icono del toggle
                const icon = this.querySelector('.toggle-icon');
                if (icon) {
                    icon.classList.toggle('fa-chevron-down');
                    icon.classList.toggle('fa-chevron-up');
                }
            }
        });
    });

    // Cerrar sidebar cuando se haceclic fuera en mvil
    document.addEventListener('click', function(event) {
        const isClickInsideSidebar = sidebar.contains(event.target);
        const isClickOnToggle = sidebarToggle && sidebarToggle.contains(event.target);
        
        if (!isClickInsideSidebar && !isClickOnToggle && window.innerWidth <= 768) {
            sidebar.classList.remove('show');
        }
    });
});
