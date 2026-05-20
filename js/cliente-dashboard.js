// ============================================================
// Dashboard Cliente - Lógica Principal
// ============================================================

document.addEventListener('DOMContentLoaded', async () => {
    // Verificar sesión y rol
    try {
        const resp = await fetch('php/auth/check.php');
        const data = await resp.json();
        if (!data.logged_in || data.user.rol !== 'cliente') {
            window.location.href = data.logged_in ? `dashboard_${data.user.rol}.html` : 'login.html';
            return;
        }
        const userNameEl = document.getElementById('user-name');
        if (userNameEl) userNameEl.textContent = data.user.nombre;
    } catch (e) {
        console.error('Auth check failed', e);
    }

    // Sidebar navigation
    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const section = this.dataset.section;
            if (section) showSection(section);
        });
    });

    // Logout
    document.getElementById('logout-btn')?.addEventListener('click', async (e) => {
        e.preventDefault();
        await fetch('php/auth/logout.php');
        window.location.href = 'index.html';
    });

    // Cargar datos
    await loadResumen();
    await loadCitas('proximas');
    await loadPerfil();

    // Setup forms
    setupPerfilForm();
    setupDesactivarCuenta();
    setupCitasTabs();

    // Modal
    document.getElementById('modal-cancel')?.addEventListener('click', closeModal);
    document.getElementById('modal-confirm')?.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) closeModal();
    });
});

function showSection(id) {
    document.querySelectorAll('.dashboard-section').forEach(s => s.style.display = 'none');
    const sec = document.getElementById(`section-${id}`);
    if (sec) sec.style.display = 'block';

    document.querySelectorAll('.sidebar-menu a').forEach(a => a.classList.remove('active'));
    const activeLink = document.querySelector(`.sidebar-menu a[data-section="${id}"]`);
    if (activeLink) activeLink.classList.add('active');

    if (id === 'citas') loadCitas('proximas');
    if (id === 'calendario') renderCalendario();
}

// ============================================================
// Resumen
// ============================================================
async function loadResumen() {
    try {
        const resp = await fetch('php/api/citas.php?tipo=proximas');
        const data = await resp.json();
        const citas = data.data || [];
        const container = document.getElementById('resumen-citas');
        if (!container) return;

        if (citas.length === 0) {
            container.innerHTML = '<p style="color: var(--dash-text-muted);">No tienes próximas citas.</p>';
        } else {
            container.innerHTML = citas.slice(0, 3).map(c => `
                <div class="cita-mini">
                    <p><strong>${c.nombre_servicio}</strong> con ${c.prestador_nombre}</p>
                    <p style="font-size: 0.85rem; color: var(--dash-text-muted);">${formatFecha(c.fecha_hora_inicio)}</p>
                </div>
            `).join('');
            if (citas.length > 3) {
                container.innerHTML += `<p style="font-size: 0.85rem; color: var(--dash-primary);">+${citas.length - 3} cita(s) más</p>`;
            }
        }

        // Perfil resumen
        const perfilResp = await fetch('php/api/cliente_perfil.php');
        const perfilData = await perfilResp.json();
        if (perfilData.status === 'success' && perfilData.data) {
            document.getElementById('resumen-perfil').innerHTML = `
                <p><strong>${perfilData.data.nombre}</strong></p>
                <p style="font-size: 0.85rem; color: var(--dash-text-muted);">${perfilData.data.correo}</p>
            `;
        }
    } catch (e) {
        console.error('Error loading resumen:', e);
    }
}

// ============================================================
// Citas
// ============================================================
function setupCitasTabs() {
    document.querySelectorAll('[data-citas-tab]').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('[data-citas-tab]').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            loadCitas(this.dataset.citasTab);
        });
    });
}

async function loadCitas(tipo) {
    try {
        const resp = await fetch(`php/api/citas.php?tipo=${tipo}`);
        const data = await resp.json();
        renderCitasLista(data.data || [], tipo);
    } catch (e) {
        console.error('Error loading citas:', e);
    }
}

function renderCitasLista(citas, tipo) {
    const container = document.getElementById('citas-lista');
    if (!container) return;

    if (citas.length === 0) {
        container.innerHTML = `<p style="color: var(--dash-text-muted); padding: 1rem 0;">No tienes citas ${tipo === 'proximas' ? 'próximas' : 'en el historial'}.</p>`;
        return;
    }

    container.innerHTML = citas.map(c => `
        <div class="cita-item ${c.estado}">
            <div class="cita-header">
                <strong>${c.nombre_servicio}</strong>
                <span class="cita-estado estado-${c.estado}">${c.estado}</span>
            </div>
            <div class="cita-body">
                <p><i class="fa-regular fa-clock"></i> ${formatFecha(c.fecha_hora_inicio)}</p>
                <p><i class="fa-regular fa-user"></i> ${c.prestador_nombre || 'Profesional'}</p>
                ${c.direccion_fisica ? `<p><i class="fa-solid fa-location-dot"></i> ${c.direccion_fisica}</p>` : ''}
                ${c.costo_final ? `<p><i class="fa-solid fa-dollar-sign"></i> $${parseFloat(c.costo_final).toFixed(2)}</p>` : ''}
            </div>
            ${c.estado !== 'cancelada' && c.estado !== 'completada' ? `
                <div class="cita-actions">
                    <button class="btn-small btn-danger" onclick="cancelarCitaCliente(${c.id})">Cancelar cita</button>
                </div>
            ` : ''}
        </div>
    `).join('');
}

async function cancelarCitaCliente(id) {
    showModal('Cancelar cita', '¿Estás seguro de cancelar esta cita? Recuerda que después de 3 cancelaciones tu cuenta puede ser suspendida.', async () => {
        try {
            const resp = await fetch('php/api/citas.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ accion: 'cancelar', cita_id: id })
            });
            const data = await resp.json();
            if (data.status === 'success') {
                showNotification('Cita cancelada', data.message, 'success');
                closeModal();
                loadCitas('proximas');
                loadResumen();
            } else {
                showNotification('Error', data.message, 'error');
            }
        } catch (e) {
            console.error(e);
        }
    });
}

// ============================================================
// Calendario
// ============================================================
function renderCalendario() {
    const container = document.getElementById('calendario-mini');
    if (!container) return;

    const today = new Date();
    const currentMonth = today.getMonth();
    const currentYear = today.getFullYear();

    const firstDay = new Date(currentYear, currentMonth, 1).getDay();
    const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();

    const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    let html = `
        <div class="cal-header">
            <strong>${monthNames[currentMonth]} ${currentYear}</strong>
        </div>
        <div class="cal-grid">
            <div class="cal-day-header">Dom</div>
            <div class="cal-day-header">Lun</div>
            <div class="cal-day-header">Mar</div>
            <div class="cal-day-header">Mié</div>
            <div class="cal-day-header">Jue</div>
            <div class="cal-day-header">Vie</div>
            <div class="cal-day-header">Sáb</div>
    `;

    for (let i = 0; i < firstDay; i++) {
        html += '<div class="cal-day empty"></div>';
    }

    for (let day = 1; day <= daysInMonth; day++) {
        const isToday = day === today.getDate() && currentMonth === today.getMonth() && currentYear === today.getFullYear();
        html += `<div class="cal-day ${isToday ? 'today' : ''}" data-date="${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}">${day}</div>`;
    }

    html += '</div>';
    container.innerHTML = html;

    // Click on a day
    container.querySelectorAll('.cal-day:not(.empty)').forEach(dayEl => {
        dayEl.addEventListener('click', function() {
            document.querySelectorAll('.cal-day.selected').forEach(d => d.classList.remove('selected'));
            this.classList.add('selected');
            loadCitasDelDia(this.dataset.date);
        });
    });
}

async function loadCitasDelDia(fecha) {
    const container = document.getElementById('citas-del-dia');
    if (!container) return;

    try {
        const resp = await fetch('php/api/citas.php?tipo=proximas');
        const data = await resp.json();
        const citas = (data.data || []).filter(c => c.fecha_hora_inicio.startsWith(fecha));

        if (citas.length === 0) {
            container.innerHTML = `<p style="color: var(--dash-text-muted);">No hay citas para este día.</p>`;
            return;
        }

        container.innerHTML = citas.map(c => `
            <div class="cita-item">
                <p><strong>${c.nombre_servicio}</strong></p>
                <p style="font-size: 0.85rem; color: var(--dash-text-muted);">${c.fecha_hora_inicio.substring(11, 16)} - ${c.prestador_nombre}</p>
            </div>
        `).join('');
    } catch (e) {
        console.error(e);
    }
}

// ============================================================
// Perfil
// ============================================================
async function loadPerfil() {
    try {
        const resp = await fetch('php/api/cliente_perfil.php');
        const data = await resp.json();
        if (data.status !== 'success' || !data.data) return;

        const p = data.data;
        document.getElementById('cli-perfil-nombre').value = p.nombre || '';
        document.getElementById('cli-perfil-correo').value = p.correo || '';
        document.getElementById('cli-perfil-telefono').value = p.telefono || '';
        document.getElementById('cli-perfil-ubicacion').value = p.ubicacion || '';
    } catch (e) {
        console.error(e);
    }
}

function setupPerfilForm() {
    const form = document.getElementById('perfil-cliente-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const data = Object.fromEntries(fd.entries());

        try {
            const resp = await fetch('php/api/cliente_perfil.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await resp.json();
            if (result.status === 'success') {
                showNotification('Perfil guardado', 'Tu perfil se actualizó correctamente.', 'success');
                loadResumen();
            } else {
                showNotification('Error', result.message, 'error');
            }
        } catch (e) {
            console.error(e);
            showNotification('Error de conexión', 'No se pudo guardar el perfil.', 'error');
        }
    });
}

// ============================================================
// Desactivar cuenta
// ============================================================
function setupDesactivarCuenta() {
    document.getElementById('btn-desactivar-cuenta')?.addEventListener('click', () => {
        showModal(
            'Desactivar cuenta',
            '¿Estás seguro de que deseas desactivar tu cuenta? No podrás iniciar sesión hasta que un administrador la reactive. Tus datos no se eliminarán.',
            async () => {
                try {
                    const resp = await fetch('php/api/cliente_perfil.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ accion: 'desactivar_cuenta' })
                    });
                    const data = await resp.json();
                    if (data.status === 'success') {
                        showNotification('Cuenta desactivada', 'Tu cuenta ha sido desactivada. Serás redirigido.', 'success');
                        closeModal();
                        setTimeout(() => {
                            window.location.href = 'index.html';
                        }, 2000);
                    } else {
                        showNotification('Error', data.message, 'error');
                    }
                } catch (e) {
                    console.error(e);
                }
            }
        );
    });
}

// ============================================================
// Modal
// ============================================================
let modalCallback = null;

function showModal(title, message, onConfirm) {
    document.getElementById('modal-title').textContent = title;
    document.getElementById('modal-message').textContent = message;
    document.getElementById('modal-confirm-btn').onclick = onConfirm;
    document.getElementById('modal-confirm').classList.add('show');
    modalCallback = onConfirm;
}

function closeModal() {
    document.getElementById('modal-confirm').classList.remove('show');
}

// ============================================================
// Utilidades
// ============================================================
// showNotification() y formatFecha() están definidas en dashboard.js
