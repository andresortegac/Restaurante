/* Script para: auth/login.blade.php */

document.addEventListener('DOMContentLoaded', function() {
    // Mostrar errores con SweetAlert si existen
    const errors = window.loginErrors || [];
    if (errors.length > 0) {
        const errorMessage = errors.join('\n');
        
        Swal.fire({
            icon: 'error',
            title: 'Error en la autenticación',
            html: '<pre style="text-align: left; margin: 10px 0;">' + errorMessage + '</pre>',
            confirmButtonColor: '#667eea',
            confirmButtonText: 'Entendido'
        });
    }

    // Mostrar mensaje de éxito si existe
    const successMessage = window.successMessage;
    if (successMessage) {
        Swal.fire({
            icon: 'success',
            title: 'Éxito',
            text: successMessage,
            confirmButtonColor: '#667eea',
            confirmButtonText: 'Continuar'
        });
    }

    // Validar formulario antes de enviar
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();

            if (!email || !password) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Campos requeridos',
                    text: 'Por favor, completa todos los campos de inicio de sesión.',
                    confirmButtonColor: '#667eea'
                });
            }
        });
    }
});
