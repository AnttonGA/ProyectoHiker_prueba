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
        ['hiking', 'Hiking', 'bi-signpost-split'],
        ['ski_touring', 'Ski touring', 'bi-snow'],
        ['snowshoeing', 'Snowshoeing', 'bi-snow2'],
        ['climbing', 'Climbing', 'bi-triangle'],
        ['via_ferrata', 'Via ferrata', 'bi-link-45deg'],
        ['cycle_touring', 'Cycle touring', 'bi-bicycle'],
        ['btt', 'Mountain biking', 'bi-bicycle'],
        ['kayaking', 'Kayaking', 'bi-water'],
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
        const names = declared.map(k => LABEL[k] || k).join(', ');
        const edit = el('button', { type: 'button', class: 'btn btn-link btn-sm p-0 ms-2', text: 'Edit' });
        edit.addEventListener('click', () => renderForm(card, declared, false));
        card.appendChild(el('div', { class: 'd-flex align-items-center flex-wrap small text-muted' }, [
            el('i', { class: 'bi bi-person-check me-2 text-success' }),
            el('span', { text: 'Your activities: ' }),
            el('strong', { class: 'ms-1 text-body', text: names }),
            edit,
        ]));
        card.hidden = false;
    }

    function renderForm(card, selected, firstTime) {
        card.replaceChildren();
        const chosen = new Set(selected || []);
        const box = el('div', { class: 'card shadow-sm' });
        const body = el('div', { class: 'card-body' });

        body.appendChild(el('h5', { class: 'card-title mb-1', text: 'Which activities do you do?' }));
        body.appendChild(el('p', { class: 'card-text small text-muted mb-3',
            text: 'Kahir will prioritise these when recommending routes. You can change them any time.' }));

        const group = el('div', { class: 'd-flex flex-wrap gap-2 mb-3', role: 'group', 'aria-label': 'Activities' });
        ACTIVIDADES.forEach(([key, label, icon]) => {
            const btn = el('button', { type: 'button', class: 'btn btn-sm ' + (chosen.has(key) ? 'btn-primary' : 'btn-outline-primary'),
                'aria-pressed': chosen.has(key) ? 'true' : 'false' }, [
                el('i', { class: 'bi ' + icon + ' me-1' }),
                document.createTextNode(label),
            ]);
            btn.addEventListener('click', () => {
                if (chosen.has(key)) chosen.delete(key); else chosen.add(key);
                const on = chosen.has(key);
                btn.className = 'btn btn-sm ' + (on ? 'btn-primary' : 'btn-outline-primary');
                btn.setAttribute('aria-pressed', on ? 'true' : 'false');
                save.disabled = chosen.size === 0;
            });
            group.appendChild(btn);
        });
        body.appendChild(group);

        const msg = el('p', { class: 'small mb-0 mt-2', 'aria-live': 'polite' });
        const save = el('button', { type: 'button', class: 'btn btn-success btn-sm', text: 'Save' });
        save.disabled = chosen.size === 0;
        const later = el('button', { type: 'button', class: 'btn btn-link btn-sm text-muted', text: firstTime ? 'Not now' : 'Cancel' });

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
                        msg.textContent = data.error || 'Could not save your activities.';
                        save.disabled = false;
                    }
                })
                .catch(() => {
                    msg.className = 'small mb-0 mt-2 text-danger';
                    msg.textContent = 'Could not reach the server.';
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

        body.appendChild(el('div', {}, [save, later]));
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
