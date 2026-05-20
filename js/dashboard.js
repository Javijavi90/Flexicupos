// ============================================================
// Dashboard - Autenticación Compartida
// ============================================================

document.addEventListener('DOMContentLoaded', async () => {
    // 1. Verificar Sesión
    try {
        const response = await fetch('php/auth/check.php');
        const data = await response.json();

        if (!data.logged_in) {
            window.location.href = 'login.html';
            return;
        }

        // Mostrar nombre y rol
        const userNameEl = document.getElementById('user-name');
        const userRoleEl = document.getElementById('user-role');
        
        if (userNameEl) userNameEl.textContent = data.user.nombre;
        if (userRoleEl) userRoleEl.textContent = data.user.rol;

        // Protección de rutas simple en frontend
        const currentPath = window.location.pathname;
        if (currentPath.includes('dashboard_cliente') && data.user.rol !== 'cliente') window.location.href = `dashboard_${data.user.rol}.html`;
        if (currentPath.includes('dashboard_prestador') && data.user.rol !== 'prestador') window.location.href = `dashboard_${data.user.rol}.html`;
        if (currentPath.includes('dashboard_admin') && data.user.rol !== 'admin') window.location.href = `dashboard_${data.user.rol}.html`;

    } catch (error) {
        console.error('Error al verificar sesión', error);
    }

    // 2. Lógica de Cerrar Sesión
    const logoutBtn = document.getElementById('logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            await fetch('php/auth/logout.php');
            window.location.href = 'login.html';
        });
    }
});

// ============================================================
// Notificaciones - Modal Compartido
// ============================================================

function showNotification(title, message, type) {
    const overlay = document.getElementById('notif-modal');
    const titleEl = document.getElementById('notif-title');
    const msgEl = document.getElementById('notif-message');
    const iconEl = document.getElementById('notif-icon');
    if (!overlay || !titleEl || !msgEl) return;

    titleEl.textContent = title || '';
    msgEl.textContent = message || '';
    overlay.className = 'modal-overlay notif-modal show';

    if (type === 'success') {
        iconEl.className = 'fa-regular fa-circle-check notif-icon success';
    } else if (type === 'error') {
        iconEl.className = 'fa-regular fa-circle-xmark notif-icon error';
    } else {
        iconEl.className = 'fa-regular fa-circle-info notif-icon info';
    }

    // Click en cualquier botón cierra
    overlay.querySelectorAll('.btn-primary, .btn-outline').forEach(btn => {
        btn.onclick = () => overlay.classList.remove('show');
    });
    overlay.onclick = (e) => {
        if (e.target === overlay) overlay.classList.remove('show');
    };
}

function formatFecha(datetime) {
    const d = new Date(datetime.replace(' ', 'T') + 'Z');
    const options = { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' };
    return d.toLocaleDateString('es-ES', options);
}
