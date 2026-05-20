// ============================================================
// Dashboard Prestador - Lógica Principal
// ============================================================
let currentPrestadorData = null;

document.addEventListener('DOMContentLoaded', async () => {
    // Verificar sesión y rol
    try {
        const resp = await fetch('php/auth/check.php');
        const data = await resp.json();
        if (!data.logged_in || data.user.rol !== 'prestador') {
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

    // Cargar datos iniciales
    await loadPrestadorData();
    await loadCitasHoy();
    await loadHorarios();
    await loadFechasBloqueadas();

    // Eventos de formularios
    setupPerfilForm();
    setupServicioForm();
    setupHorariosForm();
    setupPortafolioForm();
    setupBloquearFecha();
    setupAddPlan();
});

function showSection(id) {
    document.querySelectorAll('.dashboard-section').forEach(s => s.style.display = 'none');
    const sec = document.getElementById(`section-${id}`);
    if (sec) sec.style.display = 'block';

    document.querySelectorAll('.sidebar-menu a').forEach(a => a.classList.remove('active'));
    const activeLink = document.querySelector(`.sidebar-menu a[data-section="${id}"]`);
    if (activeLink) activeLink.classList.add('active');

    if (id === 'agenda') loadAgenda();
    if (id === 'servicios') loadServicios();
    if (id === 'portafolio') loadPortafolio();
}

// ============================================================
// Cargar datos del perfil
// ============================================================
async function loadPrestadorData() {
    try {
        const resp = await fetch('php/api/prestador_perfil.php');
        const data = await resp.json();
        if (data.status !== 'success') return;
        currentPrestadorData = data;

        // Resumen
        const servCount = (data.servicios || []).length;
        document.getElementById('resumen-servicios').innerHTML = 
            `<p style="font-size: 2rem; font-weight: 800; color: var(--dash-primary);">${servCount}</p>`;

        // Visibilidad automática
        const activoEtiqueta = data.data?.activo_etiqueta == 1;
        const tienePerfil = data.data && data.data.especialidad && data.data.direccion_fisica;
        const tieneServicios = servCount > 0;
        updateVisibilidadUI(activoEtiqueta, tienePerfil, tieneServicios);

        // Perfil form
        if (data.data) fillPerfilForm(data.data);
    } catch (e) {
        console.error('Error loading prestador data:', e);
    }
}

function updateVisibilidadUI(activo, tienePerfil, tieneServicios) {
    const estadoEl = document.getElementById('estado-visibilidad');
    if (!estadoEl) return;

    if (activo) {
        estadoEl.innerHTML = `
            <p style="color: #2ecc71; font-weight: 700;">
                <i class="fa-solid fa-eye"></i> Visible en búsquedas
            </p>
            <p style="font-size: 0.85rem; color: var(--dash-text-muted); margin-top: 0.3rem;">
                Tus clientes pueden encontrarte.
            </p>
        `;
    } else if (!tienePerfil) {
        estadoEl.innerHTML = `
            <p style="color: #f39c12; font-weight: 600;">
                <i class="fa-solid fa-pen"></i> Completa tu perfil
            </p>
            <p style="font-size: 0.85rem; color: var(--dash-text-muted); margin-top: 0.3rem;">
                Ve a <a href="#" onclick="showSection('perfil');return false;">Mi Perfil</a> y completa los datos.
            </p>
        `;
    } else if (!tieneServicios) {
        estadoEl.innerHTML = `
            <p style="color: #f39c12; font-weight: 600;">
                <i class="fa-solid fa-scissors"></i> Agrega servicios
            </p>
            <p style="font-size: 0.85rem; color: var(--dash-text-muted); margin-top: 0.3rem;">
                Ve a <a href="#" onclick="showSection('servicios');return false;">Mis Servicios</a> y agrega al menos uno.
            </p>
        `;
    } else {
        estadoEl.innerHTML = `
            <p style="color: #95a5a6;">
                <i class="fa-regular fa-eye-slash"></i> No visible
            </p>
            <p style="font-size: 0.85rem; color: var(--dash-text-muted); margin-top: 0.3rem;">
                Asegúrate de tener al menos un servicio activo y perfil completo.
            </p>
        `;
    }
}

// ============================================================
// Perfil Form
// ============================================================
function fillPerfilForm(data) {
    document.getElementById('perfil-nombre').value = data.nombre || '';
    document.getElementById('perfil-telefono').value = data.telefono || '';
    document.getElementById('perfil-tipo-entidad').value = data.tipo_entidad || 'particular';
    document.getElementById('perfil-nombre-legal').value = data.nombre_legal || '';
    document.getElementById('perfil-apellido-legal').value = data.apellido_legal || '';
    document.getElementById('perfil-nombre-empresa').value = data.nombre_empresa || '';
    document.getElementById('perfil-ruc').value = data.ruc || '';
    document.getElementById('perfil-especialidad').value = data.especialidad || '';
    document.getElementById('perfil-categoria').value = data.categoria || '';
    document.getElementById('perfil-direccion').value = data.direccion_fisica || '';
    document.getElementById('perfil-descripcion').value = data.descripcion || '';
    document.getElementById('perfil-sitio-web').value = data.sitio_web || '';
    document.getElementById('perfil-redes').value = data.redes_sociales || '';
    toggleTipoEntidad(data.tipo_entidad || 'particular');
}

function setupPerfilForm() {
    const form = document.getElementById('perfil-form');
    const tipoSelect = document.getElementById('perfil-tipo-entidad');
    
    if (tipoSelect) {
        tipoSelect.addEventListener('change', function() {
            toggleTipoEntidad(this.value);
        });
    }

    if (!form) return;
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const data = Object.fromEntries(fd.entries());

        try {
            const resp = await fetch('php/api/prestador_perfil.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await resp.json();
            if (result.status === 'success') {
                showNotification('Perfil guardado', 'Tu perfil ha sido actualizado correctamente.', 'success');
                await loadPrestadorData();
            } else {
                showNotification('Error', result.message, 'error');
            }
        } catch (e) {
            console.error(e);
            showNotification('Error de conexión', 'No se pudo guardar el perfil. Intenta de nuevo.', 'error');
        }
    });
}

function toggleTipoEntidad(tipo) {
    const particularFields = document.getElementById('perfil-particular-fields');
    const empresaFields = document.getElementById('perfil-empresa-fields');
    if (particularFields) particularFields.style.display = tipo === 'particular' ? 'block' : 'none';
    if (empresaFields) empresaFields.style.display = tipo === 'empresa' ? 'block' : 'none';
}

// ============================================================
// Servicios
// ============================================================
async function loadServicios() {
    try {
        const resp = await fetch('php/api/servicios.php');
        const data = await resp.json();
        if (data.status !== 'success') return;

        const container = document.getElementById('servicios-lista');
        const servicios = data.data || [];

        if (servicios.length === 0) {
            container.innerHTML = '<p style="color: var(--dash-text-muted);">No tienes servicios registrados. Agrega tu primer servicio.</p>';
            return;
        }

        container.innerHTML = servicios.map(s => `
            <div class="servicio-item">
                <div class="servicio-item-header">
                    <h4>${s.nombre_servicio}</h4>
                    <div class="servicio-item-actions">
                        <span class="servicio-status ${s.activo == 1 ? 'activo' : 'inactivo'}">${s.activo == 1 ? 'Activo' : 'Inactivo'}</span>
                        <button class="btn-small" onclick="editarServicio(${s.id})" title="Editar"><i class="fa-solid fa-pen"></i></button>
                        <button class="btn-small btn-danger" onclick="eliminarServicio(${s.id})" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
                    </div>
                </div>
                <p class="servicio-meta">$${parseFloat(s.precio).toFixed(2)} · ${s.duracion_minutos} min · ${s.planes_count || 0} plan(es)</p>
                ${s.descripcion_servicio ? `<p class="servicio-desc">${s.descripcion_servicio}</p>` : ''}
                ${s.planes && s.planes.length > 0 ? `
                    <div class="servicio-planes">
                        ${s.planes.map(p => `<span class="plan-tag">${p.nombre_plan}: $${parseFloat(p.precio).toFixed(2)}</span>`).join('')}
                    </div>
                ` : ''}
            </div>
        `).join('');
    } catch (e) {
        console.error('Error loading servicios:', e);
    }
}

function resetServicioForm() {
    document.getElementById('servicio-form').reset();
    document.getElementById('edit-servicio-id').value = '0';
    document.getElementById('btn-guardar-servicio').textContent = 'Guardar Servicio';
    document.getElementById('btn-cancelar-edicion').style.display = 'none';
    // Eliminar todos los planes excepto el primero (forma segura y compatible)
    const allPlanRows = document.querySelectorAll('.plan-row');
    for (let i = allPlanRows.length - 1; i > 0; i--) {
        allPlanRows[i].remove();
    }
    const firstPlan = document.querySelector('.plan-row');
    if (firstPlan) {
        firstPlan.querySelector('.plan-nombre').value = '';
        firstPlan.querySelector('.plan-desc').value = '';
        firstPlan.querySelector('.plan-precio').value = '';
        firstPlan.querySelector('.plan-duracion').value = '60';
    }
}

async function editarServicio(id) {
    try {
        const resp = await fetch('php/api/servicios.php');
        const data = await resp.json();
        
        if (data.status !== 'success') {
            showNotification('Error', data.message || 'No se pudieron cargar los servicios.', 'error');
            return;
        }

        const serv = (data.data || []).find(s => s.id == id);
        if (!serv) {
            showNotification('Error', 'Servicio no encontrado.', 'error');
            return;
        }

        resetServicioForm();
        document.getElementById('edit-servicio-id').value = id;
        document.getElementById('serv-nombre').value = serv.nombre_servicio || '';
        document.getElementById('serv-precio').value = serv.precio || 0;
        document.getElementById('serv-duracion').value = serv.duracion_minutos || 60;
        document.getElementById('serv-descripcion').value = serv.descripcion_servicio || '';
        document.getElementById('btn-guardar-servicio').textContent = 'Actualizar Servicio';
        document.getElementById('btn-cancelar-edicion').style.display = 'inline-block';

        const planes = serv.planes || [];
        document.querySelectorAll('.plan-row').forEach(el => el.remove());
        if (planes.length === 0) {
            addPlanRow();
        } else {
            planes.forEach(p => {
                addPlanRow(p.nombre_plan, p.descripcion_plan || '', p.precio, p.duracion_minutos);
            });
        }

        showSection('servicios');
        setTimeout(() => {
            const form = document.getElementById('servicio-form');
            if (form) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 100);

    } catch (e) {
        showNotification('Error', 'Ocurrió un error al cargar el servicio.', 'error');
    }
}

async function eliminarServicio(id) {
    if (!confirm('¿Estás seguro de eliminar este servicio?')) return;
    try {
        const resp = await fetch('php/api/servicios.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'eliminar', servicio_id: id })
        });
        const data = await resp.json();
        if (data.status === 'success') {
            showNotification('Servicio eliminado', 'El servicio fue eliminado correctamente.', 'success');
            await loadPrestadorData();
            loadServicios();
        } else {
            alert(data.message);
        }
    } catch (e) {
        console.error(e);
    }
}

function setupServicioForm() {
    const form = document.getElementById('servicio-form');
    if (!form) return;

    document.getElementById('btn-cancelar-edicion')?.addEventListener('click', () => {
        resetServicioForm();
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const data = Object.fromEntries(fd.entries());
        const editId = document.getElementById('edit-servicio-id').value;

        // Get planes
        const planRows = document.querySelectorAll('.plan-row');
        const planes = [];
        planRows.forEach(row => {
            const nombre = row.querySelector('.plan-nombre')?.value?.trim();
            const precio = parseFloat(row.querySelector('.plan-precio')?.value || 0);
            if (nombre && precio > 0) {
                planes.push({
                    nombre_plan: nombre,
                    descripcion_plan: row.querySelector('.plan-desc')?.value || '',
                    precio: precio,
                    duracion_minutos: parseInt(row.querySelector('.plan-duracion')?.value || 60)
                });
            }
        });

        data.planes = planes;

        if (editId && editId !== '0') {
            data.accion = 'actualizar';
            data.servicio_id = parseInt(editId);
        } else {
            data.accion = 'crear';
        }

        try {
            const resp = await fetch('php/api/servicios.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await resp.json();
            if (result.status === 'success') {
                showNotification('Servicio guardado', result.message, 'success');
                resetServicioForm();
                await loadPrestadorData();
                loadServicios();
            } else {
                showNotification('Error', result.message, 'error');
            }
        } catch (e) {
            console.error(e);
        }
    });
}

// ============================================================
// Planes
// ============================================================
let planCounter = 1;

function setupAddPlan() {
    document.getElementById('add-plan-btn')?.addEventListener('click', () => addPlanRow());
}

function addPlanRow(nombre, desc, precio, duracion) {
    const container = document.getElementById('planes-container');
    if (!container) return;
    const rows = container.querySelectorAll('.plan-row');
    if (rows.length >= 10) return;

    const idx = planCounter++;
    const row = document.createElement('div');
    row.className = 'plan-row';
    row.innerHTML = `
        <input type="text" name="planes[${idx}][nombre_plan]" placeholder="Nombre del plan" class="plan-nombre" value="${nombre || ''}">
        <input type="text" name="planes[${idx}][descripcion_plan]" placeholder="Descripción" class="plan-desc" value="${desc || ''}">
        <input type="number" step="0.01" name="planes[${idx}][precio]" placeholder="Precio $" class="plan-precio" value="${precio || ''}">
        <input type="number" name="planes[${idx}][duracion_minutos]" placeholder="Min" value="${duracion || 60}" class="plan-duracion">
        <button type="button" class="btn-remove-plan" title="Eliminar plan"><i class="fa-solid fa-xmark"></i></button>
    `;
    row.querySelector('.btn-remove-plan').addEventListener('click', () => row.remove());
    container.appendChild(row);
}

// ============================================================
// Horarios
// ============================================================
const DIAS = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

function setupHorariosForm() {
    const form = document.getElementById('horarios-form');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const horarios = [];

        document.querySelectorAll('.dia-horario').forEach(div => {
            const checkbox = div.querySelector('.dia-toggle');
            if (!checkbox || !checkbox.checked) return; // Solo días activos
            const dia = parseInt(div.dataset.dia);
            const rows = div.querySelectorAll('.hora-row');
            rows.forEach(row => {
                const inicio = row.querySelector('.hora-inicio')?.value;
                const fin = row.querySelector('.hora-fin')?.value;
                if (inicio && fin) {
                    horarios.push({ dia_semana: dia, hora_inicio: inicio, hora_fin: fin });
                }
            });
        });

        try {
            const resp = await fetch('php/api/horarios.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ accion: 'guardar_horarios', horarios })
            });
            const data = await resp.json();
            if (data.status === 'success') {
                showNotification('Horarios guardados', 'Tus horarios se actualizaron correctamente.', 'success');
            } else {
                showNotification('Error', data.message, 'error');
            }
        } catch (e) {
            console.error(e);
        }
    });
}

async function loadHorarios() {
    try {
        const resp = await fetch('php/api/horarios.php');
        const data = await resp.json();
        if (data.status !== 'success') return;
        renderHorariosGrid(data.horarios || []);
    } catch (e) {
        console.error(e);
    }
}

function renderHorariosGrid(horarios) {
    const grid = document.getElementById('horarios-grid');
    if (!grid) return;

    // Group by day
    const byDay = {};
    horarios.forEach(h => {
        if (!byDay[h.dia_semana]) byDay[h.dia_semana] = [];
        byDay[h.dia_semana].push(h);
    });

    grid.innerHTML = DIAS.map((diaNombre, diaIdx) => {
        const dayHorarios = byDay[diaIdx] || [];
        return `
            <div class="dia-horario" data-dia="${diaIdx}">
                <div class="dia-header">
                    <label class="dia-checkbox">
                        <input type="checkbox" class="dia-toggle" ${dayHorarios.length > 0 ? 'checked' : ''}>
                        <strong>${diaNombre}</strong>
                    </label>
                </div>
                <div class="horas-container" style="${dayHorarios.length > 0 ? '' : 'display: none;'}">
                    ${dayHorarios.length === 0 
                        ? `<div class="hora-row">
                            <input type="time" class="hora-inicio" value="09:00">
                            <span>a</span>
                            <input type="time" class="hora-fin" value="18:00">
                            <button type="button" class="btn-remove-hora" style="visibility:hidden;"><i class="fa-solid fa-xmark"></i></button>
                          </div>`
                        : dayHorarios.map(h => `
                            <div class="hora-row">
                                <input type="time" class="hora-inicio" value="${h.hora_inicio.substring(0, 5)}">
                                <span>a</span>
                                <input type="time" class="hora-fin" value="${h.hora_fin.substring(0, 5)}">
                                <button type="button" class="btn-remove-hora"><i class="fa-solid fa-xmark"></i></button>
                            </div>
                        `).join('')
                    }
                    <button type="button" class="btn-add-hora"><i class="fa-solid fa-plus"></i> Agregar horario</button>
                </div>
            </div>
        `;
    }).join('');

    // Event listeners
    grid.querySelectorAll('.dia-toggle').forEach(cb => {
        cb.addEventListener('change', function() {
            const container = this.closest('.dia-horario').querySelector('.horas-container');
            container.style.display = this.checked ? 'block' : 'none';
        });
    });

    grid.querySelectorAll('.btn-add-hora').forEach(btn => {
        btn.addEventListener('click', function() {
            const container = this.closest('.horas-container');
            const row = document.createElement('div');
            row.className = 'hora-row';
            row.innerHTML = `
                <input type="time" class="hora-inicio" value="09:00">
                <span>a</span>
                <input type="time" class="hora-fin" value="18:00">
                <button type="button" class="btn-remove-hora"><i class="fa-solid fa-xmark"></i></button>
            `;
            row.querySelector('.btn-remove-hora').addEventListener('click', () => row.remove());
            container.insertBefore(row, this);
        });
    });

    grid.querySelectorAll('.btn-remove-hora').forEach(btn => {
        btn.addEventListener('click', function() {
            const container = this.closest('.horas-container');
            const rows = container.querySelectorAll('.hora-row');
            if (rows.length > 1) {
                this.closest('.hora-row').remove();
            }
        });
    });
}

// ============================================================
// Fechas bloqueadas
// ============================================================
function setupBloquearFecha() {
    const btn = document.getElementById('btn-bloquear-fecha');
    if (!btn) return;
    btn.addEventListener('click', async () => {
        const fecha = document.getElementById('bloquear-fecha-input')?.value;
        const motivo = document.getElementById('bloquear-motivo')?.value;
        if (!fecha) return;

        try {
            const resp = await fetch('php/api/horarios.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ accion: 'bloquear_fecha', fecha, motivo })
            });
        const data = await resp.json();
        if (data.status === 'success') {
            showNotification('Fecha bloqueada', 'La fecha ha sido bloqueada.', 'success');
                document.getElementById('bloquear-fecha-input').value = '';
                document.getElementById('bloquear-motivo').value = '';
                loadFechasBloqueadas();
            }
        } catch (e) {
            console.error(e);
        }
    });
}

async function loadFechasBloqueadas() {
    try {
        const resp = await fetch('php/api/horarios.php');
        const data = await resp.json();
        if (data.status !== 'success') return;

        const container = document.getElementById('fechas-bloqueadas');
        const excepciones = data.excepciones || [];

        if (excepciones.length === 0) {
            container.innerHTML = '<p style="color: var(--dash-text-muted);">No hay fechas bloqueadas.</p>';
            return;
        }

        container.innerHTML = excepciones.map(e => `
            <div class="fecha-bloqueada-item">
                <span><strong>${e.fecha}</strong> ${e.motivo ? '- ' + e.motivo : ''}</span>
                <button class="btn-small" onclick="desbloquearFecha('${e.fecha}')"><i class="fa-solid fa-unlock"></i> Desbloquear</button>
            </div>
        `).join('');
    } catch (e) {
        console.error(e);
    }
}

async function desbloquearFecha(fecha) {
    try {
        const resp = await fetch('php/api/horarios.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'desbloquear_fecha', fecha })
        });
        const data = await resp.json();
        if (data.status === 'success') {
            showNotification('Fecha desbloqueada', 'La fecha ya está disponible.', 'success');
            loadFechasBloqueadas();
        }
    } catch (e) {
        console.error(e);
    }
}

// ============================================================
// Agenda
// ============================================================
async function loadAgenda() {
    try {
        const [proxResp, histResp] = await Promise.all([
            fetch('php/api/citas.php?tipo=proximas'),
            fetch('php/api/citas.php?tipo=historial')
        ]);
        const prox = await proxResp.json();
        const hist = await histResp.json();

        renderAgendaLista('agenda-proximas', prox.data || [], 'proximas');
        renderAgendaLista('agenda-historial', hist.data || [], 'historial');
    } catch (e) {
        console.error(e);
    }
}

function renderAgendaLista(id, citas, tipo) {
    const container = document.getElementById(id);
    if (!container) return;

    if (citas.length === 0) {
        container.innerHTML = `<p style="color: var(--dash-text-muted);">No hay citas ${tipo === 'proximas' ? 'próximas' : 'en el historial'}.</p>`;
        return;
    }

    container.innerHTML = citas.map(c => `
        <div class="cita-item ${c.estado}">
            <div class="cita-header">
                <strong>${c.cliente_nombre}</strong>
                <span class="cita-estado estado-${c.estado}">${c.estado}</span>
            </div>
            <div class="cita-body">
                <p><i class="fa-regular fa-clock"></i> ${formatFecha(c.fecha_hora_inicio)}</p>
                <p><i class="fa-solid fa-scissors"></i> ${c.nombre_servicio} ${c.nombre_plan ? '- ' + c.nombre_plan : ''}</p>
                <p><i class="fa-solid fa-phone"></i> ${c.cliente_telefono || ''}</p>
                ${c.costo_final ? `<p><i class="fa-solid fa-dollar-sign"></i> $${parseFloat(c.costo_final).toFixed(2)}</p>` : ''}
            </div>
            ${c.estado === 'pendiente' ? `
                <div class="cita-actions">
                    <button class="btn-small" onclick="confirmarCita(${c.id})">Confirmar</button>
                    <button class="btn-small btn-danger" onclick="cancelarCita(${c.id})">Cancelar</button>
                </div>
            ` : ''}
        </div>
    `).join('');
}

async function loadCitasHoy() {
    try {
        const resp = await fetch('php/api/citas.php?tipo=hoy');
        const data = await resp.json();
        const citas = data.data || [];
        const container = document.getElementById('citas-hoy');
        if (!container) return;

        if (citas.length === 0) {
            container.innerHTML = '<p style="color: var(--dash-text-muted);">No tienes citas agendadas para hoy.</p>';
            return;
        }

        container.innerHTML = citas.map(c => `
            <div class="cita-item ${c.estado}">
                <p><strong>${c.fecha_hora_inicio.substring(11, 16)}</strong> - ${c.cliente_nombre}</p>
                <p style="font-size: 0.85rem; color: var(--dash-text-muted);">${c.nombre_servicio}</p>
            </div>
        `).join('');
    } catch (e) {
        console.error(e);
    }
}

async function confirmarCita(id) {
    try {
        const resp = await fetch('php/api/citas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'confirmar', cita_id: id })
        });
        const data = await resp.json();
        if (data.status === 'success') {
            showNotification('Cita confirmada', 'La cita ha sido confirmada exitosamente.', 'success');
            loadAgenda();
            loadCitasHoy();
        }
    } catch (e) {
        console.error(e);
    }
}

async function cancelarCita(id) {
    if (!confirm('¿Cancelar esta cita?')) return;
    try {
        const resp = await fetch('php/api/citas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'cancelar', cita_id: id })
        });
        const data = await resp.json();
        if (data.status === 'success') {
            showNotification('Cita cancelada', 'La cita ha sido cancelada.', 'success');
            loadAgenda();
            loadCitasHoy();
        }
    } catch (e) {
        console.error(e);
    }
}

// ============================================================
// Portafolio
// ============================================================
function setupPortafolioForm() {
    const form = document.getElementById('portafolio-form');
    if (!form) return;
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        fd.append('accion', 'subir_logo');
        // Actually, portafolio upload uses a different endpoint
        // Let's use the existing portafolio_upload.php
        try {
            const resp = await fetch('php/api/portafolio_upload.php', {
                method: 'POST',
                body: fd
            });
        const data = await resp.json();
        if (data.status === 'success') {
            showNotification('Imagen subida', 'La imagen se subió correctamente.', 'success');
                form.reset();
                loadPortafolio();
            } else {
                alert(data.message);
            }
        } catch (e) {
            console.error(e);
        }
    });
}

async function loadPortafolio() {
    if (!currentPrestadorData?.imagenes) {
        // Reload
        await loadPrestadorData();
    }
    const imagenes = currentPrestadorData?.imagenes || [];
    const grid = document.getElementById('portafolio-grid');
    if (!grid) return;

    if (imagenes.length === 0) {
        grid.innerHTML = '<p style="color: var(--dash-text-muted);">No has subido imágenes aún.</p>';
        return;
    }

    grid.innerHTML = imagenes.map(img => `
        <div class="portafolio-item">
            <img src="${img.ruta_imagen}" alt="Trabajo">
            <button class="btn-small btn-danger delete-img" onclick="deleteImagen(${img.id})"><i class="fa-solid fa-trash"></i></button>
        </div>
    `).join('');
}

async function deleteImagen(id) {
    if (!confirm('¿Eliminar esta imagen?')) return;
    try {
        const resp = await fetch('php/api/portafolio_upload.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'eliminar', imagen_id: id })
        });
        const data = await resp.json();
        if (data.status === 'success') {
            showNotification('Imagen eliminada', 'La imagen fue eliminada.', 'success');
            await loadPrestadorData();
            loadPortafolio();
        }
    } catch (e) {
        console.error(e);
    }
}

// ============================================================
// Utilidades
// ============================================================
// showNotification() y formatFecha() están definidas en dashboard.js
