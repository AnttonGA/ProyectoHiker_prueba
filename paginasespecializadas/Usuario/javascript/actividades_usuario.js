/**
 * actividades_usuario.js — Tarjeta "Which activities do you do?" en Mi cuenta (Fase 1).
 *
 * Opcional: si el usuario no ha declarado actividades se muestra la pregunta
 * (se puede cerrar con "Not now"); si ya las declaró se muestra un resumen con
 * botón para editarlas. Guarda en javascript/user_activities.php.
 */
(function () {
    'use strict';

    const ACTIVIDADES = [
        ['hiking', 'Senderismo', 'bi-signpost-split'],
        ['ski_touring', 'Esquí de travesía', 'bi-snow'],
        ['snowshoeing', 'Raquetas', 'bi-snow2'],
        ['climbing', 'Escalada', 'bi-triangle'],
        ['via_ferrata', 'Vía ferrata', 'bi-link-45deg'],
        ['cycle_touring', 'Cicloturismo', 'bi-bicycle'],
        ['btt', 'BTT', 'bi-bicycle'],
        ['kayaking', 'Kayak', 'bi-water'],
    ];
    const LABEL = Object.fromEntries(ACTIVIDADES.map(([k, l]) => [k, l]));
    const DISMISS_KEY = 'kahir_actividades_dismissed';

    function storageGet(key) { try { return localStorage.getItem(key); } catch (e) { return null; } }
    function storageSet(key, v) { try { localStorage.setItem(key, v); } catch (e) { /* sin almacenamiento */ } }

    function el(tag, attrs, children) {
        const node = document.createElement(tag);
        Object.entries(attrs || {}).forEach(([k, v]) => {
            if (k === 'text') node.textContent = v;
            else if (k === 'class') node.className = v;
            else node.setAttribute(k, v);
        });
        (children || []).forEach(c => node.appendChild(c));
        return node;
    }

    function renderSummary(card, declared) {
        card.replaceChildren();
        const edit = el('button', { type: 'button', class: 'btn btn-link btn-sm acct-acts-summary__edit', text: 'Editar' });
        edit.setAttribute('aria-label', 'Editar tus actividades');
        edit.addEventListener('click', () => renderForm(card, declared, false));
        const chips = el('span', { class: 'acct-acts-summary__chips' },
            declared.map(k => el('span', { class: 'k-chip k-chip-moss', text: LABEL[k] || k })));
        card.appendChild(el('div', { class: 'acct-acts-summary' }, [
            el('span', { class: 'acct-acts-summary__label', text: 'Tus actividades' }),
            chips,
            edit,
        ]));
        card.hidden = false;
    }

    function renderForm(card, selected, firstTime) {
        card.replaceChildren();
        const chosen = new Set(selected || []);
        const box = el('section', { class: 'acct-panel acct-acts', 'aria-labelledby': 'acct-acts-title' });
        const body = el('div', { class: 'acct-panel__body' });

        body.appendChild(el('p', { class: 'acct-eyebrow', text: firstTime ? 'Personaliza Kahir' : 'Tus actividades' }));
        body.appendChild(el('h2', { class: 'acct-panel__title', id: 'acct-acts-title', text: '¿Qué actividades practicas?' }));
        body.appendChild(el('p', { class: 'acct-panel__lead',
            text: 'Kahir les dará prioridad al recomendarte rutas. Puedes cambiarlas cuando quieras.' }));

        const group = el('div', { class: 'acct-toggles', role: 'group', 'aria-label': 'Actividades' });
        ACTIVIDADES.forEach(([key, label, icon]) => {
            const btn = el('button', { type: 'button', class: 'acct-toggle',
                'aria-pressed': chosen.has(key) ? 'true' : 'false' }, [
                el('i', { class: 'bi ' + icon, 'aria-hidden': 'true' }),
                document.createTextNode(label),
            ]);
            btn.addEventListener('click', () => {
                if (chosen.has(key)) chosen.delete(key); else chosen.add(key);
                const on = chosen.has(key);
                btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                save.disabled = chosen.size === 0;
            });
            group.appendChild(btn);
        });
        body.appendChild(group);

        const msg = el('p', { class: 'small mb-0 mt-2', 'aria-live': 'polite' });
        const save = el('button', { type: 'button', class: 'btn btn-primary', text: 'Guardar actividades' });
        save.disabled = chosen.size === 0;
        const later = el('button', { type: 'button', class: 'btn btn-link acct-link-muted', text: firstTime ? 'Ahora no' : 'Cancelar' });

        save.addEventListener('click', () => {
            save.disabled = true;
            const actividades = ACTIVIDADES.map(a => a[0]).filter(k => chosen.has(k));
            const token = typeof window.kahirCsrfToken === 'function' ? window.kahirCsrfToken() : '';
            fetch('javascript/user_activities.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ actividades, csrf_token: token })
            })
                .then(r => r.json().catch(() => ({ success: false })))
                .then(data => {
                    if (data.success) {
                        renderSummary(card, data.declared || actividades);
                    } else {
                        msg.className = 'small mb-0 mt-2 text-danger';
                        msg.textContent = data.error || 'No se pudieron guardar tus actividades. Inténtalo de nuevo.';
                        save.disabled = false;
                    }
                })
                .catch(() => {
                    msg.className = 'small mb-0 mt-2 text-danger';
                    msg.textContent = 'No hay conexión con el servidor. Inténtalo de nuevo.';
                    save.disabled = false;
                });
        });

        later.addEventListener('click', () => {
            if (firstTime) {
                storageSet(DISMISS_KEY, '1');
                card.hidden = true;
            } else {
                renderSummary(card, selected);
            }
        });

        body.appendChild(el('div', { class: 'acct-panel__actions' }, [save, later]));
        body.appendChild(msg);
        box.appendChild(body);
        card.appendChild(box);
        card.hidden = false;
    }

    function init() {
        const card = document.getElementById('activities-card');
        if (!card) return;
        fetch('javascript/user_activities.php', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data || !data.success) return;
                if (Array.isArray(data.declared) && data.declared.length) {
                    renderSummary(card, data.declared);
                } else if (storageGet(DISMISS_KEY) !== '1') {
                    renderForm(card, [], true);
                }
            })
            .catch(() => { /* la tarjeta es opcional */ });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
