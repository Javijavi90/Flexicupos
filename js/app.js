// ============================================================
// FlexiCupos - App Principal (Index)
// ============================================================
let currentUser = null;
let currentSearch = '';
let currentPage = 1;

async function checkAuth() {
    try {
        const resp = await fetch('php/auth/check.php');
        const data = await resp.json();
        if (data.logged_in) {
            currentUser = data.user;
            showLoggedInUI();
        }
    } catch (e) {
        console.warn('Auth check failed', e);
    }
}

function showLoggedInUI() {
    const navGuest = document.getElementById('nav-guest');
    const navUser = document.getElementById('nav-user');
    const userName = document.getElementById('nav-user-name');
    if (navGuest) navGuest.style.display = 'none';
    if (navUser) navUser.style.display = 'flex';
    if (userName && currentUser) userName.textContent = currentUser.nombre;

    // Modo búsqueda: ocultar contenido de marketing y mostrar profesionales
    document.body.classList.add('logged-in');
    // Auto-cargar todos los profesionales disponibles
    setTimeout(() => buscarProfesionales('', 1), 200);
}

function showLoggedOutUI() {
    const navGuest = document.getElementById('nav-guest');
    const navUser = document.getElementById('nav-user');
    if (navGuest) navGuest.style.display = 'flex';
    if (navUser) navUser.style.display = 'none';
    document.body.classList.remove('logged-in');
}

document.addEventListener('DOMContentLoaded', () => {
    checkAuth();

    // --- Search ---
    const searchBtn = document.getElementById('search-btn');
    const searchInput = document.getElementById('search-input');

    function doSearch(q, page) {
        q = q || searchInput.value.trim();
        page = page || 1;
        currentSearch = q;
        currentPage = page;
        buscarProfesionales(q, page);
    }

    if (searchBtn) {
        searchBtn.addEventListener('click', () => doSearch());
    }
    if (searchInput) {
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') doSearch();
        });
    }

    // Trending badges
    document.querySelectorAll('.badge').forEach(badge => {
        badge.addEventListener('click', function() {
            const texto = this.textContent.trim().replace(/^[^\s]+\s/, '').trim();
            if (searchInput) searchInput.value = texto;
            doSearch(texto);
            // Scroll to results
            document.getElementById('search-results')?.scrollIntoView({ behavior: 'smooth' });
        });
    });

    // --- User Dropdown ---
    const dropdownBtn = document.getElementById('user-dropdown-btn');
    const dropdownMenu = document.getElementById('user-dropdown-menu');

    if (dropdownBtn && dropdownMenu) {
        dropdownBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
        });
        document.addEventListener('click', () => dropdownMenu.classList.remove('show'));
    }

    // Dropdown actions
    document.getElementById('dropdown-cerrar-sesion')?.addEventListener('click', async (e) => {
        e.preventDefault();
        await fetch('php/auth/logout.php');
        currentUser = null;
        showLoggedOutUI();
        location.reload();
    });

    document.getElementById('dropdown-dashboard')?.addEventListener('click', (e) => {
        e.preventDefault();
        if (currentUser) {
            if (currentUser.rol === 'prestador') window.location.href = 'dashboard_prestador.html';
            else if (currentUser.rol === 'cliente') window.location.href = 'dashboard_cliente.html';
            else if (currentUser.rol === 'admin') window.location.href = 'dashboard_admin.html';
        }
    });

    // --- Modal ---
    const modal = document.getElementById('profesional-modal');
    const modalClose = document.getElementById('modal-close');
    if (modalClose) {
        modalClose.addEventListener('click', () => modal.classList.remove('show'));
    }
    if (modal) {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.classList.remove('show');
        });
    }

    // Navbar scroll
    window.addEventListener('scroll', () => {
        const nav = document.querySelector('.navbar');
        if (nav) nav.classList.toggle('scrolled', window.scrollY > 40);
    });
});

// ============================================================
// Búsqueda de profesionales
// ============================================================
async function buscarProfesionales(q, pagina) {
    const resultsSection = document.getElementById('search-results');
    const resultsGrid = document.getElementById('results-grid');
    const resultsTitle = document.getElementById('results-title');
    const resultsCount = document.getElementById('results-count');
    const pagination = document.getElementById('results-pagination');

    if (!resultsSection || !resultsGrid) return;

    try {
        let url = `php/api/profesionales.php?pagina=${pagina}&limite=10`;
        if (q) url += `&q=${encodeURIComponent(q)}`;

        const resp = await fetch(url);
        const data = await resp.json();

        if (data.status !== 'success') {
            resultsGrid.innerHTML = '<p style="color: var(--dash-text-muted);">Error al buscar profesionales.</p>';
            return;
        }

        resultsSection.style.display = 'block';
        if (resultsTitle) resultsTitle.textContent = q ? `Resultados para "${q}"` : 'Todos los profesionales';
        if (resultsCount) resultsCount.textContent = `${data.total} profesional${data.total !== 1 ? 'es' : ''} encontrado${data.total !== 1 ? 's' : ''}`;

        if (data.data.length === 0) {
            resultsGrid.innerHTML = '<div class="empty-state"><i class="fa-solid fa-magnifying-glass"></i><h3>No se encontraron profesionales</h3><p>Intenta con otros términos de búsqueda.</p></div>';
            pagination.innerHTML = '';
            return;
        }

        // Render cards
        resultsGrid.innerHTML = data.data.map(prof => 
            renderProfessionalCard(prof)
        ).join('');

        // Pagination
        renderPagination(pagination, data.pagina, data.total_paginas, q);

        // Scroll to results
        resultsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });

    } catch (e) {
        console.error('Search error:', e);
        resultsGrid.innerHTML = '<p style="color: var(--dash-text-muted);">Error de conexión.</p>';
    }
}

function renderProfessionalCard(prof) {
    const nombre = prof.tipo_entidad === 'empresa' && prof.nombre_empresa 
        ? prof.nombre_empresa 
        : [prof.nombre_legal, prof.apellido_legal].filter(Boolean).join(' ') || prof.usuario_nombre;
    
    const servicios = prof.servicios || [];
    const precios = servicios.map(s => s.precio).filter(p => p > 0);
    const precioMin = precios.length > 0 ? Math.min(...precios) : null;
    const imgSrc = prof.imagen_principal || 'img/cat_default.jpg';

    return `
        <div class="professional-card" onclick="openProfessionalModal(${prof.id})">
            <div class="prof-card-img">
                <img src="${imgSrc}" alt="${nombre}">
            </div>
            <div class="prof-card-body">
                <h3>${nombre}</h3>
                <span class="prof-especialidad">${prof.especialidad}</span>
                <p class="prof-desc">${(prof.descripcion || '').substring(0, 80)}${(prof.descripcion || '').length > 80 ? '...' : ''}</p>
                <div class="prof-card-footer">
                    <span class="prof-services-count">${servicios.length} servicio${servicios.length !== 1 ? 's' : ''}</span>
                    ${precioMin !== null ? `<span class="prof-price">Desde $${precioMin.toFixed(2)}</span>` : ''}
                </div>
            </div>
        </div>
    `;
}

function renderPagination(container, current, total, q) {
    if (total <= 1) { container.innerHTML = ''; return; }
    
    let html = '<div class="pagination-controls">';
    for (let i = 1; i <= total; i++) {
        html += `<button class="page-btn ${i === current ? 'active' : ''}" onclick="buscarProfesionales('${q || ''}', ${i})">${i}</button>`;
    }
    html += '</div>';
    container.innerHTML = html;
}

// ============================================================
// Modal de detalle del profesional
// ============================================================
async function openProfessionalModal(prestadorPerfilId) {
    const modal = document.getElementById('profesional-modal');
    const modalBody = document.getElementById('modal-body');
    if (!modal || !modalBody) return;

    modalBody.innerHTML = '<p style="text-align: center; padding: 2rem;">Cargando...</p>';
    modal.classList.add('show');

    try {
        const resp = await fetch(`php/api/profesionales.php?detalle_id=${prestadorPerfilId}`);
        const detailData = await resp.json();

        if (detailData.status !== 'success' || !detailData.data) {
            modalBody.innerHTML = '<p style="text-align: center; padding: 2rem; color: var(--dash-text-muted);">Profesional no encontrado.</p>';
            return;
        }

        const prof = detailData.data;

        const nombre = prof.tipo_entidad === 'empresa' && prof.nombre_empresa 
            ? prof.nombre_empresa 
            : [prof.nombre_legal, prof.apellido_legal].filter(Boolean).join(' ') || prof.usuario_nombre;
        
        const servicios = prof.servicios || [];
        
        modalBody.innerHTML = `
            <div class="profesional-detalle">
                <div class="detalle-header">
                    <div class="detalle-avatar">
                        <img src="${prof.imagen_principal || 'img/cat_default.jpg'}" alt="${nombre}">
                    </div>
                    <div class="detalle-info">
                        <h2>${nombre}</h2>
                        <span class="detalle-especialidad">${prof.especialidad}${prof.categoria ? ' · ' + prof.categoria : ''}</span>
                        <p class="detalle-descripcion">${prof.descripcion || ''}</p>
                        <div class="detalle-contacto">
                            <p><i class="fa-solid fa-location-dot"></i> ${prof.direccion_fisica || ''}</p>
                            ${prof.sitio_web ? `<p><i class="fa-solid fa-globe"></i> <a href="${prof.sitio_web}" target="_blank">${prof.sitio_web}</a></p>` : ''}
                        </div>
                    </div>
                </div>
                
                <h3 style="margin-top: 2rem;">Servicios disponibles</h3>
                <div class="detalle-servicios">
                    ${servicios.length === 0 
                        ? '<p style="color: var(--dash-text-muted);">Este profesional aún no tiene servicios registrados.</p>'
                        : servicios.map(s => renderServicioDetalle(s, prof.id, prof.direccion_fisica)).join('')
                    }
                </div>
            </div>
        `;
    } catch (e) {
        console.error('Modal error:', e);
        modalBody.innerHTML = '<p style="text-align: center; padding: 2rem; color: #e74c3c;">Error al cargar la información.</p>';
    }
}

function renderServicioDetalle(servicio, prestadorPerfilId, direccion) {
    const planes = servicio.planes || [];
    
    return `
        <div class="servicio-detalle-card">
            <div class="servicio-detalle-header">
                <h4>${servicio.nombre_servicio}</h4>
                <span class="servicio-precio">${servicio.precio > 0 ? '$' + parseFloat(servicio.precio).toFixed(2) : 'Consultar'}</span>
            </div>
            ${servicio.descripcion_servicio ? `<p class="servicio-desc">${servicio.descripcion_servicio}</p>` : ''}
            <p class="servicio-duracion"><i class="fa-regular fa-clock"></i> ${servicio.duracion_minutos} min</p>
            
            ${planes.length > 0 ? `
                <div class="planes-lista">
                    <h5>Planes disponibles:</h5>
                    ${planes.map(p => `
                        <div class="plan-item">
                            <div class="plan-info">
                                <strong>${p.nombre_plan}</strong>
                                ${p.descripcion_plan ? `<span>${p.descripcion_plan}</span>` : ''}
                            </div>
                            <div class="plan-precio-info">
                                <span>$${parseFloat(p.precio).toFixed(2)}</span>
                                <small>${p.duracion_minutos} min</small>
                            </div>
                        </div>
                    `).join('')}
                </div>
            ` : ''}
            
            <button class="btn-primary agendar-btn" onclick="agendarCita(${prestadorPerfilId}, ${servicio.id}, ${servicio.duracion_minutos}, ${servicio.precio})">
                <i class="fa-regular fa-calendar-plus"></i> Agendar ${servicio.nombre_servicio}
            </button>
        </div>
    `;
}

// ============================================================
// Agendar cita
// ============================================================
function agendarCita(prestadorPerfilId, servicioId, duracion, precio) {
    if (!currentUser) {
        window.location.href = 'login.html';
        return;
    }
    if (currentUser.rol !== 'cliente') {
        alert('Solo los clientes pueden agendar citas.');
        return;
    }
    
    // Obtener datos del profesional del modal abierto
    const modalBody = document.getElementById('modal-body');
    const detalleInfo = modalBody?.querySelector('.detalle-info');
    const detalleContacto = modalBody?.querySelector('.detalle-contacto');
    const direccion = detalleContacto?.querySelector('p')?.textContent?.replace('📍', '')?.trim() || '';
    const nombreProf = detalleInfo?.querySelector('h2')?.textContent || 'Profesional';

    // Crear un mini flujo de agendamiento inline
    const agendarHTML = `
        <div class="profesional-detalle">
            <h2 style="margin-bottom: 1rem;">Agendar cita</h2>
            <div class="servicio-detalle-card">
                <p><strong>Profesional:</strong> ${nombreProf}</p>
                <p><strong>Dirección:</strong> ${direccion || 'Por confirmar'}</p>
                <p><strong>Costo:</strong> ${precio > 0 ? '$' + parseFloat(precio).toFixed(2) : 'A consultar'}</p>
                <p><strong>Duración:</strong> ${duracion} min</p>
            </div>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">
                Selecciona una fecha y hora disponible:
            </p>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1rem;">
                <input type="date" id="fecha-cita" min="${new Date().toISOString().split('T')[0]}" class="search-input" style="flex: 1; border: 1px solid var(--border); border-radius: 12px; padding: 0.75rem;">
                <input type="time" id="hora-cita" class="search-input" style="flex: 1; border: 1px solid var(--border); border-radius: 12px; padding: 0.75rem;">
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <button class="btn-outline" onclick="reabrirModalDetalle()">← Atrás</button>
                <button class="btn-primary" onclick="confirmarAgendamiento(${prestadorPerfilId}, ${servicioId}, ${precio})">Confirmar cita</button>
            </div>
            <p style="margin-top: 1rem; font-size: 0.85rem; color: var(--text-muted);">
                <i class="fa-regular fa-circle-check" style="color: var(--primary);"></i>
                Al confirmar, tu cita quedará registrada y el profesional se comunicará contigo para confirmar.
            </p>
        </div>
    `;

    // Guardar el HTML del modal actual para volver atrás
    if (!window._modalHistory) window._modalHistory = [];
    window._modalHistory.push(document.getElementById('modal-body').innerHTML);
    document.getElementById('modal-body').innerHTML = agendarHTML;
}

function reabrirModalDetalle() {
    const modalBody = document.getElementById('modal-body');
    if (window._modalHistory && window._modalHistory.length > 0) {
        modalBody.innerHTML = window._modalHistory.pop();
    }
}

async function confirmarAgendamiento(prestadorPerfilId, servicioId, precio) {
    const fecha = document.getElementById('fecha-cita')?.value;
    const hora = document.getElementById('hora-cita')?.value;
    
    if (!fecha || !hora) {
        alert('Por favor selecciona una fecha y hora.');
        return;
    }

    const fechaHoraInicio = `${fecha} ${hora}:00`;
    const duracionMinutos = 60; // por defecto 60 min

    try {
        const resp = await fetch('php/api/citas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                accion: 'crear',
                prestador_id: prestadorPerfilId,
                servicio_id: servicioId,
                fecha_hora_inicio: fechaHoraInicio,
                duracion_minutos: duracionMinutos
            })
        });
        const data = await resp.json();
        
        const modalBody = document.getElementById('modal-body');
        if (data.status === 'success') {
            modalBody.innerHTML = `
                <div class="profesional-detalle" style="text-align: center; padding: 2rem 0;">
                    <i class="fa-regular fa-circle-check" style="font-size: 3rem; color: #2ecc71; margin-bottom: 1rem;"></i>
                    <h2 style="margin-bottom: 0.75rem;">¡Cita agendada con éxito!</h2>
                    <p style="color: var(--text-muted); margin-bottom: 0.5rem;">
                        Tu servicio ha sido registrado para el <strong>${fecha}</strong> a las <strong>${hora}</strong>.
                    </p>
                    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">
                        El profesional se comunicará contigo para confirmar los detalles.
                    </p>
                    <button class="btn-primary" onclick="document.getElementById('profesional-modal').classList.remove('show')">Entendido</button>
                </div>
            `;
            window._modalHistory = [];
        } else {
            modalBody.innerHTML = `
                <div style="text-align: center; padding: 2rem 0;">
                    <i class="fa-regular fa-circle-xmark" style="font-size: 3rem; color: #dc2626; margin-bottom: 1rem;"></i>
                    <h2 style="margin-bottom: 0.5rem;">Error al agendar</h2>
                    <p style="color: var(--text-muted); margin-bottom: 1rem;">${data.message || 'Intenta de nuevo más tarde.'}</p>
                    <button class="btn-primary" onclick="openProfessionalModal(${prestadorPerfilId})">Volver</button>
                </div>
            `;
        }
    } catch (e) {
        console.error('Error al agendar:', e);
        alert('Error de conexión. Intenta de nuevo.');
    }
}


