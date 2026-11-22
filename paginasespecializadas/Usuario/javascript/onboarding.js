/**
 * onboarding.js — Feature discovery cards for my_account.php
 *
 * 5 regular steps  +  1 featured "Ask Kahir AI" card.
 * Each step: done (green tick) or pending (CTA button).
 * Dismissible permanently via localStorage.
 */

/* ── Regular steps ────────────────────────────────────── */
const STEPS = [
  {
    key:    'routes',
    icon:   'bi-signpost-split',
    bg:     'rgba(13,110,253,.1)',
    color:  '#0d6efd',
    title:  'Subir ruta',
    desc:   'Comparte tus salidas con la comunidad de Kahir.',
    cta:    'Subir',
    href:   'subir_ruta.html',
    action: null,
  },
  {
    key:    'favorites',
    icon:   'bi-heart',
    bg:     'rgba(220,53,69,.1)',
    color:  '#dc3545',
    title:  'Guardar una favorita',
    desc:   'Toca el corazón de una ruta para guardarla en tu colección.',
    cta:    'Explorar',
    href:   '../skitouring/skitouring.html',
    action: null,
  },
  {
    key:    'lists',
    icon:   'bi-bookmarks',
    bg:     'rgba(111,66,193,.1)',
    color:  '#6f42c1',
    title:  'Crear una lista',
    desc:   'Organiza las rutas en tus propias colecciones.',
    cta:    'Nueva lista',
    href:   null,
    action: () => {
      document.getElementById('lists-tab')?.click();
      setTimeout(() => document.getElementById('createListBtn')?.click(), 350);
    },
  },
  {
    key:     'ai',
    icon:    'bi-person-badge',
    bg:      'rgba(13,202,240,.1)',
    color:   '#0dcaf0',
    title:   'Ver tu perfil IA',
    desc:    'Consulta tus estadísticas y preferencias de montaña.',
    cta:     'Ver perfil',
    href:    null,
    action:  () => document.getElementById('perfil-ia-tab')?.click(),
    localKey: 'kahir_onb_ai',
  },
  {
    key:    'downloads',
    icon:   'bi-download',
    bg:     'rgba(25,135,84,.1)',
    color:  '#198754',
    title:  'Descargar un GPX',
    desc:   'Llévate una ruta sin conexión para tu próxima salida.',
    cta:    'Buscar ruta',
    href:   '../skitouring/skitouring.html',
    action: null,
  },
];

/* ── AI chat step (featured, full-width) ──────────────── */
const AI_STEP = {
  key:   'chat',
  color: '#6f42c1',
  prompts: [
    { icon: 'bi-signpost-2',       text: 'Recomiéndame una ruta de esquí de travesía de nivel moderado' },
    { icon: 'bi-person-walking',   text: '¿Qué rutas fáciles de senderismo hay en el Pirineo?' },
    { icon: 'bi-calendar3',        text: 'Planifica un fin de semana de 3 días de senderismo cerca de Barcelona' },
    { icon: 'bi-shield-check',     text: '¿Qué material de seguridad necesito para el esquí de travesía?' },
    { icon: 'bi-cloud-snow',       text: '¿Cómo se lee un boletín de aludes?' },
    { icon: 'bi-trophy',           text: '¿Cómo puedo mejorar mi forma física para la montaña?' },
  ],
};

/* Keys are user-specific so dismissing on one account doesn't affect another */
const _user        = document.getElementById('onboarding-section')?.dataset.user ?? 'default';
const LS_DISMISSED = `kahir_onb_dismissed_${_user}`;
const LS_AI_DONE   = `kahir_onb_ai_${_user}`;
const CHAT_URL     = '../../index.php';
const LS_PREFILL   = 'kahir_chat_prefill';

let _completed = new Set();

/* ── Regular step card ────────────────────────────────── */
/* step.bg / step.color are kept in STEPS for reference but the cards now
   use the shared moss palette from css/my_account.css. */
function buildCard(step, done, index) {
  const cls = done ? 'onb-card onb-card--done' : 'onb-card';

  const ctaHtml = done
    ? `<span class="onb-done-badge"><i class="bi bi-check2" aria-hidden="true"></i> Hecho</span>`
    : step.href
      ? `<a href="${step.href}" class="onb-cta">
           ${step.cta} <i class="bi bi-arrow-right-short" aria-hidden="true"></i>
         </a>`
      : `<button type="button" class="onb-cta" data-step="${step.key}">
           ${step.cta} <i class="bi bi-arrow-right-short" aria-hidden="true"></i>
         </button>`;

  return `
    <li class="${cls}" data-step="${step.key}">
      <div class="onb-card__top">
        <span class="onb-icon" aria-hidden="true">
          <i class="bi ${done ? 'bi-check-lg' : step.icon}"></i>
        </span>
        <span class="onb-num">${done ? '<span class="visually-hidden">Hecho: </span>' : ''}Paso ${index + 1}</span>
      </div>
      <p class="onb-title">${step.title}</p>
      <p class="onb-desc">${step.desc}</p>
      ${ctaHtml}
    </li>`;
}

/* ── Featured AI chat card ────────────────────────────── */
function buildAiCard(done) {
  const promptsHtml = AI_STEP.prompts.map(p => `
    <button type="button" class="onb-prompt-chip" data-prompt="${p.text}" title="Preguntárselo a Kahir">
      <i class="bi ${p.icon}" aria-hidden="true"></i>
      <span>${p.text}</span>
    </button>`).join('');

  const ctaHtml = done
    ? `<div class="onb-ai-done">
         <span><i class="bi bi-check2" aria-hidden="true"></i> Primera conversación hecha</span>
         <a href="${CHAT_URL}" class="btn btn-outline-primary btn-sm">Volver a hablar</a>
       </div>`
    : `<a href="${CHAT_URL}" class="btn btn-primary onb-ai-cta">
         Empezar a hablar <i class="bi bi-arrow-right-short" aria-hidden="true"></i>
       </a>`;

  return `
    <div class="onb-ai-card ${done ? 'onb-ai-card--done' : ''}" data-step="chat">
      <div class="onb-ai-left">
        <span class="onb-ai-icon" aria-hidden="true"><i class="bi bi-chat-text"></i></span>
        <div>
          <p class="onb-ai-title">Pregunta a Kahir</p>
          <p class="onb-ai-subtitle">
            Tu compañero de montaña para buscar ideas de rutas, resolver dudas de seguridad y preparar tus salidas.
          </p>
        </div>
      </div>
      <div class="onb-ai-prompts">
        <p class="onb-ai-prompts__label">Prueba a preguntar</p>
        <div class="onb-ai-chips">${promptsHtml}</div>
      </div>
      <div class="onb-ai-footer">
        ${ctaHtml}
      </div>
    </div>`;
}

/* ── Full section render ──────────────────────────────── */
function render(completed) {
  const section = document.getElementById('onboarding-section');
  if (!section || localStorage.getItem(LS_DISMISSED)) return;

  const total = STEPS.length + 1; // +1 for AI chat
  const done  = completed.size;
  const pct   = Math.round(done / total * 100);

  if (done === total) {
    section.innerHTML = `
      <div class="onb-complete">
        <span class="onb-icon" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
        <div>
          <strong>Ya te mueves por Kahir como en casa.</strong>
          <span class="text-muted ms-1 small">Has completado todos los primeros pasos.</span>
        </div>
        <button type="button" class="btn-close ms-auto onb-dismiss-btn" title="Cerrar" aria-label="Cerrar"></button>
      </div>`;
    section.style.display = '';
    section.querySelector('.onb-dismiss-btn')?.addEventListener('click', dismiss);
    return;
  }

  section.innerHTML = `
    <div class="onb-header">
      <div class="onb-header__text">
        <p class="acct-eyebrow mb-1">Primeros pasos</p>
        <h2 class="onb-header__title">Haz tuyo Kahir</h2>
      </div>
      <div class="onb-progress">
        <div class="onb-progress__track" role="progressbar" aria-label="Progreso de primeros pasos"
             aria-valuemin="0" aria-valuemax="${total}" aria-valuenow="${done}">
          <div class="onb-progress__fill" style="width:${pct}%"></div>
        </div>
        <span class="onb-progress__label">${done} de ${total} hechos</span>
      </div>
      <button type="button" class="btn-close onb-dismiss-btn" title="Ocultar por ahora" aria-label="Ocultar primeros pasos"></button>
    </div>

    <!-- Regular 5-step row -->
    <ol class="onb-steps">
      ${STEPS.map((s, i) => buildCard(s, completed.has(s.key), i)).join('')}
    </ol>

    <!-- Featured AI chat card -->
    ${buildAiCard(completed.has('chat'))}`;

  section.style.display = '';

  /* Dismiss */
  section.querySelector('.onb-dismiss-btn')?.addEventListener('click', dismiss);

  /* Action-only CTAs (no href) */
  section.querySelectorAll('.onb-cta[data-step]').forEach(btn => {
    const step = STEPS.find(s => s.key === btn.dataset.step);
    if (step?.action) btn.addEventListener('click', e => { e.preventDefault(); step.action(); });
  });

  /* Prompt chips — store in localStorage then navigate to chat */
  section.querySelectorAll('.onb-prompt-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      localStorage.setItem(LS_PREFILL, chip.dataset.prompt);
      window.location.href = CHAT_URL;
    });
  });
}

/* ── Dismiss ──────────────────────────────────────────── */
function dismiss() {
  localStorage.setItem(LS_DISMISSED, '1');
  const section = document.getElementById('onboarding-section');
  if (!section) return;
  section.style.transition = 'opacity .3s ease, max-height .4s ease, margin .4s ease, padding .4s ease';
  section.style.opacity    = '0';
  section.style.maxHeight  = '0';
  section.style.overflow   = 'hidden';
  section.style.marginBottom = '0';
  section.style.padding    = '0';
  setTimeout(() => section.remove(), 450);
}

/* ── Mark a step done and re-render ──────────────────── */
function markDone(key) {
  if (_completed.has(key)) return;
  _completed.add(key);
  render(_completed);
}

/* ── Init ─────────────────────────────────────────────── */
async function initOnboarding() {
  if (localStorage.getItem(LS_DISMISSED)) return;

  try {
    const data = await fetch('javascript/user_onboarding.php').then(r => r.json());
    if (!data.success) return;

    _completed = new Set();
    const s = data.steps;
    if (s.routes)    _completed.add('routes');
    if (s.favorites) _completed.add('favorites');
    if (s.lists)     _completed.add('lists');
    if (s.downloads) _completed.add('downloads');
    if (s.chat)      _completed.add('chat');
    if (localStorage.getItem(LS_AI_DONE)) _completed.add('ai');

    render(_completed);

    /* AI profile tab visit → mark done */
    document.getElementById('perfil-ia-tab')?.addEventListener('shown.bs.tab', () => {
      localStorage.setItem(LS_AI_DONE, '1');
      markDone('ai');
    });

    /* List created → mark done */
    document.addEventListener('kahir:listCreated', () => markDone('lists'));

  } catch (err) {
    console.warn('[Onboarding] Failed to load:', err.message);
  }
}

document.addEventListener('DOMContentLoaded', initOnboarding);
