document.addEventListener('DOMContentLoaded', () => {
    const forms = document.querySelectorAll('form[data-validate="password"]');

    forms.forEach((form) => {
        form.addEventListener('submit', (e) => {
            // Password puede llamarse distinto según formulario (register vs change password)
            const pass =
                form.querySelector('input[name="password"]') ||
                form.querySelector('input[name="new_password"]');

            // Confirm puede llamarse distinto según formulario
            const confirm =
                form.querySelector('input[name="password_confirm"]') ||
                form.querySelector('input[name="confirm_password"]') ||
                form.querySelector('input[name="new_password_confirm"]');

            if (!pass || !confirm) return;

            // Reset estilo en cada submit
            confirm.style.borderColor = '';

            if (pass.value !== confirm.value) {
                e.preventDefault();

                // UX mínima sin frameworks
                alert('Las contraseñas no coinciden.');
                confirm.focus();
                confirm.style.borderColor = 'red';
            }
        });
    });
});