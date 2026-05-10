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
    icon:   'bi-map-fill',
    bg:     'rgba(13,110,253,.1)',
    color:  '#0d6efd',
    title:  'Upload a Route',
    desc:   'Share your adventures with the Kahir community.',
    cta:    'Upload',
    href:   'upload_route.php',
    action: null,
  },
  {
    key:    'favorites',
    icon:   'bi-heart-fill',
    bg:     'rgba(220,53,69,.1)',
    color:  '#dc3545',
    title:  'Save a Favourite',
    desc:   'Heart a route to keep it in your collection.',
    cta:    'Browse',
    href:   '../skitouring/skitouring.html',
    action: null,
  },
  {
    key:    'lists',
    icon:   'bi-bookmark-fill',
    bg:     'rgba(111,66,193,.1)',
    color:  '#6f42c1',
    title:  'Create a List',
    desc:   'Organise routes into custom collections.',
    cta:    'New List',
    href:   null,
    action: () => {
      document.getElementById('lists-tab')?.click();
      setTimeout(() => document.getElementById('createListBtn')?.click(), 350);
    },
  },
  {
    key:     'ai',
    icon:    'bi-graph-up-arrow',
    bg:      'rgba(13,202,240,.1)',
    color:   '#0dcaf0',
    title:   'Explore AI Profile',
    desc:    'See your personalised hiking stats and preferences.',
    cta:     'View Profile',
    href:    null,
    action:  () => document.getElementById('perfil-ia-tab')?.click(),
    localKey: 'kahir_onb_ai',
  },
  {
    key:    'downloads',
    icon:   'bi-cloud-arrow-down-fill',
    bg:     'rgba(25,135,84,.1)',
    color:  '#198754',
    title:  'Download a GPX',
    desc:   'Take a route offline for your next adventure.',
    cta:    'Find Route',
    href:   '../skitouring/skitouring.html',
    action: null,
  },
];

/* ── AI chat step (featured, full-width) ──────────────── */
const AI_STEP = {
  key:   'chat',
  color: '#6f42c1',
  prompts: [
    { icon: 'bi-signpost-2',       text: 'Recommend a ski touring route for intermediate level' },
    { icon: 'bi-person-walking',   text: 'Best beginner hiking routes in the Pyrenees?' },
    { icon: 'bi-calendar3',        text: 'Plan a 3-day hiking weekend near Barcelona' },
    { icon: 'bi-shield-check',     text: 'What safety gear do I need for ski touring?' },
    { icon: 'bi-cloud-snow',       text: 'How do I read an avalanche forecast?' },
    { icon: 'bi-trophy',           text: 'How can I improve my mountain fitness?' },
  ],
};

/* Keys are user-specific so dismissing on one account doesn't affect another */
const _user        = document.getElementById('onboarding-section')?.dataset.user ?? 'default';
const LS_DISMISSED = `kahir_onb_dismissed_${_user}`;
const LS_AI_DONE   = `kahir_onb_ai_${_user}`;
const CHAT_URL     = '../../chat.php';
const LS_PREFILL   = 'kahir_chat_prefill';

let _completed = new Set();

/* ── Regular step card ────────────────────────────────── */
function buildCard(step, done) {
  const cls = done ? 'onb-card onb-card--done' : 'onb-card';

  const ctaHtml = done
    ? `<span class="onb-done-badge"><i class="bi bi-check-circle-fill"></i> Done</span>`
    : step.href
      ? `<a href="${step.href}" class="onb-cta"
            style="color:${step.color};border-color:${step.color}40">
           ${step.cta} <i class="bi bi-arrow-right-short"></i>
         </a>`
      : `<button class="onb-cta" data-step="${step.key}"
               style="color:${step.color};border-color:${step.color}40">
           ${step.cta} <i class="bi bi-arrow-right-short"></i>
         </button>`;

  return `
    <div class="${cls}" data-step="${step.key}">
      <div class="onb-icon" style="background:${done ? '#f0f0f0' : step.bg};
                                   color:${done ? '#adb5bd' : step.color}">
        <i class="bi ${step.icon}"></i>
        ${done ? `<span class="onb-tick"><i class="bi bi-check-lg"></i></span>` : ''}
      </div>
      <p class="onb-title">${step.title}</p>
      <p class="onb-desc">${step.desc}</p>
      ${ctaHtml}
    </div>`;
}

/* ── Featured AI chat card ────────────────────────────── */
function buildAiCard(done) {
  const promptsHtml = AI_STEP.prompts.map(p => `
    <button class="onb-prompt-chip" data-prompt="${p.text}" title="Click to use this prompt">
      <i class="bi ${p.icon}"></i>
      <span>${p.text}</span>
    </button>`).join('');

  const ctaHtml = done
    ? `<div class="onb-ai-done">
         <i class="bi bi-check-circle-fill text-success"></i>
         <span>First conversation done!</span>
         <a href="${CHAT_URL}" class="onb-ai-cta onb-ai-cta--done">Chat again</a>
       </div>`
    : `<a href="${CHAT_URL}" class="onb-ai-cta">
         <i class="bi bi-chat-dots-fill me-1"></i>Start chatting
         <i class="bi bi-arrow-right-short"></i>
       </a>`;

  return `
    <div class="onb-ai-card ${done ? 'onb-ai-card--done' : ''}" data-step="chat">
      <div class="onb-ai-left">
        <div class="onb-ai-icon">
          <i class="bi bi-robot"></i>
          ${done ? `<span class="onb-tick"><i class="bi bi-check-lg"></i></span>` : ''}
        </div>
        <div>
          <p class="onb-ai-title">Ask Kahir AI</p>
          <p class="onb-ai-subtitle">
            Your personal mountain guide — get route recommendations,<br>
            safety tips, training advice and more.
          </p>
        </div>
      </div>
      <div class="onb-ai-prompts">
        <p class="onb-ai-prompts__label">
          <i class="bi bi-lightbulb-fill text-warning me-1"></i>
          Try asking:
        </p>
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
        <i class="bi bi-trophy-fill" style="color:#ffc107;font-size:1.3rem"></i>
        <div>
          <strong>You're all set!</strong>
          <span class="text-muted ms-1 small">You've discovered all Kahir features.</span>
        </div>
        <button class="btn-close ms-auto onb-dismiss-btn" title="Dismiss"></button>
      </div>`;
    section.style.display = '';
    section.querySelector('.onb-dismiss-btn')?.addEventListener('click', dismiss);
    return;
  }

  section.innerHTML = `
    <div class="onb-header">
      <span class="onb-header__title">
        <i class="bi bi-stars text-warning me-1"></i>Getting started
      </span>
      <div class="onb-progress">
        <div class="onb-progress__track">
          <div class="onb-progress__fill" style="width:${pct}%"></div>
        </div>
        <span class="onb-progress__label">${done} / ${total}</span>
      </div>
      <button class="btn-close btn-close-sm onb-dismiss-btn" title="Hide for now"></button>
    </div>

    <!-- Regular 5-step row -->
    <div class="onb-steps">
      ${STEPS.map(s => buildCard(s, completed.has(s.key))).join('')}
    </div>

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
