document.addEventListener('DOMContentLoaded', () => {
    
    // Función genérica para mostrar errores
    const showError = (msg) => {
        const errorDiv = document.getElementById('error-message');
        if (errorDiv) {
            errorDiv.textContent = msg;
            errorDiv.style.display = 'block';
        } else {
            alert(msg);
        }
    };

    // FORMULARIO DE LOGIN
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(loginForm);
            const data = Object.fromEntries(formData.entries());
            const submitBtn = loginForm.querySelector('button[type="submit"]');
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Iniciando...';

            try {
                const response = await fetch('php/auth/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    // Redirección según el rol
                    if (result.role === 'admin') window.location.href = 'dashboard_admin.html';
                    else if (result.role === 'prestador') window.location.href = 'dashboard_prestador.html';
                    else window.location.href = 'dashboard_cliente.html';
                } else {
                    showError(result.message);
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Ingresar';
                }
            } catch (error) {
                console.error(error);
                showError('Error de red al intentar iniciar sesión.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Ingresar';
            }
        });
    }

    // FORMULARIO DE REGISTRO
    const registerForm = document.getElementById('register-form');
    if (registerForm) {
        registerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(registerForm);
            const data = Object.fromEntries(formData.entries());
            const submitBtn = registerForm.querySelector('button[type="submit"]');
            
            submitBtn.disabled = true;
            submitBtn.textContent = 'Registrando...';

            try {
                const response = await fetch('php/auth/register.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.status === 'success') {
                    alert('Registro exitoso. Ahora puedes iniciar sesión.');
                    window.location.href = 'login.html';
                } else {
                    showError(result.message);
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Registrarme';
                }
            } catch (error) {
                console.error(error);
                showError('Error de red al intentar registrar.');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Registrarme';
            }
        });
    }
});

// Funcionalidad de Pestañas (Registro)
function setTipo(tipo) {
    const tipoInput = document.getElementById('tipo_usuario');
    if (!tipoInput) return;
    
    tipoInput.value = tipo;
    
    const tabs = document.querySelectorAll('.tab');
    tabs[0].classList.toggle('active', tipo === 'cliente');
    tabs[1].classList.toggle('active', tipo === 'prestador');

    const fCliente = document.getElementById('fields_cliente');
    const fPrestador = document.getElementById('fields_prestador');
    const btnSubmit = document.getElementById('btn_submit');

    if (tipo === 'cliente') {
        fCliente.style.display = 'block';
        fPrestador.style.display = 'none';
        btnSubmit.innerText = 'Registrarme como Cliente';
        
        document.getElementById('especialidad').removeAttribute('required');
        document.getElementById('direccion').removeAttribute('required');
    } else {
        fCliente.style.display = 'none';
        fPrestador.style.display = 'block';
        btnSubmit.innerText = 'Registrarme como Prestador';
        
        document.getElementById('especialidad').setAttribute('required', 'required');
        document.getElementById('direccion').setAttribute('required', 'required');
    }
}
