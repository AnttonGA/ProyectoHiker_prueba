/**
 * perfil_ia.js — AI Profile tab
 * Uses Bootstrap Icons exclusively (no Font Awesome).
 */

const PerfilIA = {

  activityLabels: {
    hiking:        'Senderismo',
    ski_touring:   'Esquí de travesía',
    climbing:      'Escalada',
    cycle_touring: 'Cicloturismo',
    snowshoeing:   'Raquetas',
    via_ferrata:   'Vía ferrata',
    mtb:           'BTT',
    btt:           'BTT',
    kayaking:      'Kayak',
  },

  levelLabels: {
    beginner:     { text: 'Nivel fácil',    badge: 'success' },
    intermediate: { text: 'Nivel moderado', badge: 'warning' },
    advanced:     { text: 'Nivel difícil',  badge: 'danger'  },
  },

  /* Single shared request: used by the AI Profile tab and the header stats */
  _promise: null,
  load() {
    if (!this._promise) {
      this._promise = fetch('javascript/user_profile.php').then(r => r.json());
    }
    return this._promise;
  },

  init() {
    this.load()
      .then(data => {
        if (!data.success) throw new Error(data.error || 'No se pudo cargar tu perfil. Recarga la página.');
        this.render(data);
      })
      .catch(err => {
        document.getElementById('perfil-ia-content').innerHTML =
          `<div class="acct-empty acct-empty--error">
              <span class="acct-empty__icon" aria-hidden="true"><i class="bi bi-exclamation-circle"></i></span>
              <h3 class="acct-empty__title">No se pudo cargar tu perfil IA</h3>
              <p class="acct-empty__text">${this.esc(err.message)}</p>
           </div>`;
      });
  },

  render(data) {
    const { user, preferences: p, stats, activity_breakdown, top_zones, activity_by_month, chat_sessions } = data;
    const container = document.getElementById('perfil-ia-content');

    const hasPrefs  = p !== null;
    const actLabel  = (key) => this.esc(this.activityLabels[key] || key);
    const levelInfo = this.levelLabels[p?.experience_level] ?? { text: 'Nivel aún sin definir', badge: 'secondary' };
    const n         = (v) => (Number.isFinite(Number(v)) ? Number(v) : 0);
    const locLabel  = (l) => this.esc(l === 'Other' ? 'Otras zonas' : ((typeof LOC_LABEL !== 'undefined' && LOC_LABEL[l]) || l));
    const num1      = (v) => (v === null || v === undefined || v === '' || !Number.isFinite(Number(v)))
      ? this.esc(v)
      : Number(v).toLocaleString('es-ES', { maximumFractionDigits: 1 });
    const initial   = this.esc(String(user.nombre || user.usuario || '?').trim().charAt(0).toUpperCase());

    container.innerHTML = `
      <!-- Profile header -->
      <div class="ia-head">
        <span class="k-avatar ia-head__avatar" aria-hidden="true">${initial}</span>
        <div class="ia-head__text">
          <h2 class="ia-head__name">${this.esc(user.nombre || user.usuario)}</h2>
          <p class="ia-head__meta">
            @${this.esc(user.usuario)}
            ${user.ultimo_acceso
              ? `<span class="ia-dot" aria-hidden="true">·</span>Última visita: ${this.esc(this.formatDate(user.ultimo_acceso))}`
              : ''}
          </p>
        </div>
        <span class="k-chip ${p?.experience_level ? 'k-chip-moss' : ''} ia-head__level">
          <i class="bi bi-bar-chart-steps" aria-hidden="true"></i>${this.esc(levelInfo.text)}
        </span>
      </div>

      <!-- Quick stats -->
      <div class="ia-stats">
        ${this.statCard('bi-signpost-split', 'Rutas subidas',  n(stats.total_routes))}
        ${this.statCard('bi-heart',          'Favoritas',      n(stats.total_favorites))}
        ${this.statCard('bi-flag',           'Completadas',    n(stats.total_completed))}
        ${this.statCard('bi-calendar-check', 'Días seguidos',  n(stats.streak_days))}
      </div>

      <div class="row g-3 mb-3">
        <!-- Preferred activities -->
        <div class="col-md-6">
          <section class="acct-panel h-100">
            <div class="acct-panel__body">
              <h3 class="ia-card-title">Actividades preferidas</h3>
              ${hasPrefs && p.preferred_activities.length > 0
                ? `<div class="ia-chips">${p.preferred_activities.map(a =>
                    `<span class="k-chip k-chip-moss">${actLabel(a)}</span>`
                  ).join('')}</div>`
                : '<p class="ia-note">Kahir lo irá aprendiendo a medida que explores rutas. Abre algunas que te interesen.</p>'
              }
              ${activity_breakdown.length > 0
                ? `<p class="ia-sublabel">Interacciones por actividad</p>
                   ${this.miniBarChart(activity_breakdown)}`
                : ''
              }
            </div>
          </section>
        </div>

        <!-- Usual zones -->
        <div class="col-md-6">
          <section class="acct-panel h-100">
            <div class="acct-panel__body">
              <h3 class="ia-card-title">Zonas habituales</h3>
              ${hasPrefs && p.preferred_locations.length > 0
                ? `<div class="ia-chips">${p.preferred_locations.map(l =>
                    `<span class="k-chip"><i class="bi bi-geo-alt" aria-hidden="true"></i>${locLabel(l)}</span>`
                  ).join('')}</div>`
                : '<p class="ia-note">Explora rutas por el Pirineo y Kahir sabrá adónde te gusta ir.</p>'
              }
              ${top_zones.length > 0
                ? `<p class="ia-sublabel">Más visitadas</p>
                   <ol class="ia-rank">
                      ${top_zones.slice(0, 3).map(z =>
                        `<li>
                            <span>${z.location ? locLabel(z.location) : 'Sin zona'}</span>
                            <span class="ia-rank__count">${n(z.cnt)}</span>
                         </li>`
                      ).join('')}
                   </ol>`
                : ''
              }
            </div>
          </section>
        </div>
      </div>

      <!-- Average metrics -->
      ${hasPrefs ? `
      <section class="acct-panel mb-3">
        <div class="acct-panel__body">
          <h3 class="ia-card-title">Tu ruta típica</h3>
          <div class="ia-metrics">
            <div class="ia-metric">
              <span class="ia-metric__value">${num1(p.avg_preferred_distance)}</span>
              <span class="ia-metric__label">km de media</span>
            </div>
            <div class="ia-metric">
              <span class="ia-metric__value">${num1(p.avg_preferred_duration)}</span>
              <span class="ia-metric__label">horas de media</span>
            </div>
            <div class="ia-metric">
              <span class="ia-metric__value">${num1(p.avg_preferred_elevation)}</span>
              <span class="ia-metric__label">m de desnivel de media</span>
            </div>
          </div>
        </div>
      </section>
      ` : ''}

      <!-- Monthly activity -->
      ${activity_by_month.length > 0 ? `
      <section class="acct-panel mb-3">
        <div class="acct-panel__body">
          <h3 class="ia-card-title">Actividad en los últimos 6 meses</h3>
          ${this.monthlyChart(activity_by_month)}
        </div>
      </section>
      ` : ''}

      <!-- Recent chat sessions -->
      ${chat_sessions.length > 0 ? `
      <section class="acct-panel mb-3">
        <div class="acct-panel__body pb-2">
          <h3 class="ia-card-title">Conversaciones recientes con Kahir</h3>
          <ul class="ia-chats">
            ${chat_sessions.map(s => `
              <li>
                <span><i class="bi bi-chat-text" aria-hidden="true"></i>${this.esc(this.formatDate(s.fecha_inicio))}</span>
                <span class="k-chip">${n(s.num_mensajes).toLocaleString('es-ES')} ${n(s.num_mensajes) === 1 ? 'mensaje' : 'mensajes'}</span>
              </li>
            `).join('')}
          </ul>
        </div>
      </section>
      ` : ''}

      <!-- AI Profile status -->
      <p class="ia-status">
        <i class="bi bi-info-circle" aria-hidden="true"></i>
        ${hasPrefs && stats.total_interactions >= 5
          ? `Perfil IA activo · ${n(stats.total_interactions).toLocaleString('es-ES')} interacciones analizadas · Actualizado el ${this.esc(this.formatDate(p.last_updated))}`
          : `Tu perfil IA se activa tras 5 interacciones con rutas. Explora el catálogo para ponerlo en marcha.`
        }
      </p>
    `;
  },

  statCard(icon, label, value) {
    return `
      <div class="ia-stat">
        <i class="bi ${icon} ia-stat__icon" aria-hidden="true"></i>
        <span class="ia-stat__value">${Number(value).toLocaleString('es-ES')}</span>
        <span class="ia-stat__label">${label}</span>
      </div>`;
  },

  miniBarChart(breakdown) {
    const max = Math.max(...breakdown.map(b => Number(b.cnt) || 0), 1);
    return `<div class="ia-bars">
      ${breakdown.map(b => `
        <div class="ia-bars__row">
          <span class="ia-bars__label">
            ${this.esc(this.activityLabels[b.tipo_ruta] || b.tipo_ruta)}
          </span>
          <span class="ia-bars__track">
            <span class="ia-bars__fill" style="width:${Math.round((Number(b.cnt) || 0) / max * 100)}%"></span>
          </span>
          <span class="ia-bars__count">${(Number(b.cnt) || 0).toLocaleString('es-ES')}</span>
        </div>
      `).join('')}
    </div>`;
  },

  monthlyChart(data) {
    const max = Math.max(...data.map(d => Number(d.cnt) || 0), 1);
    const monthNames = {
      '01':'ene','02':'feb','03':'mar','04':'abr','05':'may','06':'jun',
      '07':'jul','08':'ago','09':'sep','10':'oct','11':'nov','12':'dic',
    };
    return `<div class="ia-months">
      ${data.map(d => {
        const [, m] = String(d.mes ?? '').split('-');
        const cnt = Number(d.cnt) || 0;
        const h = Math.max(Math.round(cnt / max * 72), 4);
        return `<div class="ia-months__col">
          <span class="ia-months__count">${cnt.toLocaleString('es-ES')}</span>
          <span class="ia-months__bar" style="height:${h}px"></span>
          <span class="ia-months__label">${this.esc(monthNames[m] || m)}</span>
        </div>`;
      }).join('')}
    </div>`;
  },

  formatDate(iso) {
    if (!iso) return '';
    try {
      return new Date(iso).toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
    } catch { return iso; }
  },

  esc(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  },
};

/* Header summary stats (my_account.php #acct-stats) — optional, hidden on failure */
function fillAccountStats() {
  const box = document.getElementById('acct-stats');
  if (!box) return;
  PerfilIA.load()
    .then(data => {
      if (!data?.success || !data.stats) return;
      const map = {
        routes:    data.stats.total_routes,
        favorites: data.stats.total_favorites,
        completed: data.stats.total_completed,
      };
      box.querySelectorAll('[data-stat]').forEach(el => {
        const v = Number(map[el.dataset.stat]);
        el.textContent = Number.isFinite(v) ? v.toLocaleString('es-ES') : '0';
      });
      box.hidden = false;
    })
    .catch(() => { /* the stats strip is optional */ });
}

document.addEventListener('DOMContentLoaded', () => {
  fillAccountStats();
  const tab = document.getElementById('perfil-ia-tab');
  if (!tab) return;

  let loaded = false;
  tab.addEventListener('shown.bs.tab', () => {
    if (!loaded) {
      loaded = true;
      PerfilIA.init();
    }
  });
});
