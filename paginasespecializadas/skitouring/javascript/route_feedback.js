/**
 * route_feedback.js — Panel "¿Qué tal te fue?" tras completar una ruta (Fase 1).
 *
 * Aparece cuando la ruta está marcada como completada (evento 'kahir:completed'
 * que lanza bt_completed.js) y guarda la percepción (fácil/adecuada/dura) y las
 * estrellas en javascript/route_feedback.php. El token CSRF lo añade csrf.js.
 */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    const panel = document.getElementById('route-feedback');
    if (!panel) return;

    const routeId = new URLSearchParams(window.location.search).get('id');
    if (!routeId || !/^\d+$/.test(routeId)) return;

    const percepButtons = panel.querySelectorAll('[data-percepcion]');
    const starButtons   = panel.querySelectorAll('[data-star]');
    const saveBtn       = panel.querySelector('#feedback-save');
    const msg           = panel.querySelector('#feedback-msg');

    let percepcion = null;
    let estrellas  = null;
    let loaded     = false;

    function paintPercepcion() {
        percepButtons.forEach(b => {
            const active = b.dataset.percepcion === percepcion;
            b.classList.toggle('active', active);
            b.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        saveBtn.disabled = !percepcion;
    }

    function paintStars() {
        starButtons.forEach(b => {
            const n = Number(b.dataset.star);
            const on = estrellas !== null && n <= estrellas;
            b.querySelector('i').className = on ? 'bi bi-star-fill' : 'bi bi-star';
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function showMessage(text, ok) {
        msg.textContent = text;
        msg.className = 'small mt-2 ' + (ok ? 'text-success' : 'text-danger');
    }

    function loadExisting() {
        if (loaded) return;
        loaded = true;
        fetch(`javascript/route_feedback.php?ruta_id=${encodeURIComponent(routeId)}`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (data && data.success && data.feedback) {
                    percepcion = data.feedback.percepcion;
                    estrellas  = data.feedback.estrellas;
                    paintPercepcion();
                    paintStars();
                    showMessage('Ya valoraste esta ruta. Puedes cambiar tu valoración.', true);
                }
            })
            .catch(() => { /* sin valoración previa */ });
    }

    percepButtons.forEach(b => b.addEventListener('click', () => {
        percepcion = b.dataset.percepcion;
        paintPercepcion();
    }));

    starButtons.forEach(b => b.addEventListener('click', () => {
        const n = Number(b.dataset.star);
        estrellas = (estrellas === n) ? null : n; // pulsar la misma estrella la quita
        paintStars();
    }));

    saveBtn.addEventListener('click', () => {
        if (!percepcion) return;
        saveBtn.disabled = true;
        const token = typeof window.kahirCsrfToken === 'function' ? window.kahirCsrfToken() : '';
        fetch('javascript/route_feedback.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ruta_id: Number(routeId), percepcion, estrellas, csrf_token: token })
        })
            .then(r => r.json().catch(() => ({ success: false })))
            .then(data => {
                if (data.success) {
                    showMessage(data.message || 'Gracias por tu valoración.', true);
                } else {
                    showMessage(data.error || 'No se pudo guardar la valoración. Inténtalo de nuevo.', false);
                }
            })
            .catch(() => showMessage('No se pudo conectar con el servidor. Inténtalo de nuevo.', false))
            .finally(() => { saveBtn.disabled = !percepcion; });
    });

    document.addEventListener('kahir:completed', e => {
        const done = !!(e.detail && e.detail.isCompleted);
        panel.hidden = !done;
        if (done) loadExisting();
    });
});
