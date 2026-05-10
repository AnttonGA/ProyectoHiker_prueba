/**
 * perfil_ia.js — AI Profile tab
 * Uses Bootstrap Icons exclusively (no Font Awesome).
 */

const PerfilIA = {

  activityLabels: {
    hiking:        'Hiking',
    ski_touring:   'Ski Touring',
    climbing:      'Climbing',
    cycle_touring: 'Cycling',
    snowshoeing:   'Snowshoeing',
    via_ferrata:   'Via Ferrata',
  },

  levelLabels: {
    beginner:     { text: 'Beginner',     badge: 'success' },
    intermediate: { text: 'Intermediate', badge: 'warning' },
    advanced:     { text: 'Advanced',     badge: 'danger'  },
  },

  init() {
    fetch('javascript/user_profile.php')
      .then(r => r.json())
      .then(data => {
        if (!data.success) throw new Error(data.error || 'Error loading profile');
        this.render(data);
      })
      .catch(err => {
        document.getElementById('perfil-ia-content').innerHTML =
          `<div class="alert alert-warning">
              <i class="bi bi-exclamation-triangle-fill me-2"></i>
              Could not load AI Profile. ${this.esc(err.message)}
           </div>`;
      });
  },

  render(data) {
    const { user, preferences: p, stats, activity_breakdown, top_zones, activity_by_month, chat_sessions } = data;
    const container = document.getElementById('perfil-ia-content');

    const hasPrefs  = p !== null;
    const actLabel  = (key) => this.activityLabels[key] || key;
    const levelInfo = this.levelLabels[p?.experience_level] ?? { text: 'No data', badge: 'secondary' };

    container.innerHTML = `
      <!-- Profile header -->
      <div class="row g-3 mb-4">
        <div class="col-12">
          <div class="d-flex align-items-center gap-3 flex-wrap">
            <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                 style="width:64px;height:64px;font-size:1.6rem;flex-shrink:0;">
              <i class="bi bi-person-fill"></i>
            </div>
            <div class="flex-grow-1 min-width-0">
              <h4 class="mb-0">${this.esc(user.nombre || user.usuario)}</h4>
              <small class="text-muted">@${this.esc(user.usuario)}</small>
              ${user.ultimo_acceso
                ? `<br><small class="text-muted"><i class="bi bi-clock me-1"></i>Last login: ${this.formatDate(user.ultimo_acceso)}</small>`
                : ''}
            </div>
            <span class="badge bg-${levelInfo.badge} fs-6 flex-shrink-0">
              <i class="bi bi-trophy-fill me-1"></i>${levelInfo.text}
            </span>
          </div>
        </div>
      </div>

      <!-- Quick stats -->
      <div class="row g-3 mb-4">
        ${this.statCard('bi bi-signpost-2-fill', 'primary', 'Routes uploaded',  stats.total_routes)}
        ${this.statCard('bi bi-heart-fill',       'danger',  'Favourites',       stats.total_favorites)}
        ${this.statCard('bi bi-check-circle-fill','success', 'Completed',        stats.total_completed)}
        ${this.statCard('bi bi-fire',             'warning', 'Current streak',   stats.streak_days + ' days')}
      </div>

      <div class="row g-3 mb-4">
        <!-- Preferred activities -->
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-header fw-semibold">
              <i class="bi bi-person-walking me-2 text-primary"></i>Preferred Activities
            </div>
            <div class="card-body">
              ${hasPrefs && p.preferred_activities.length > 0
                ? p.preferred_activities.map(a =>
                    `<span class="badge bg-primary me-1 mb-1 p-2">${actLabel(a)}</span>`
                  ).join('')
                : '<p class="text-muted small mb-0">Not enough data yet. Explore routes and the system will learn your preferences.</p>'
              }
              ${activity_breakdown.length > 0
                ? `<hr class="my-2">
                   <small class="text-muted d-block mb-1">Interaction breakdown:</small>
                   ${this.miniBarChart(activity_breakdown)}`
                : ''
              }
            </div>
          </div>
        </div>

        <!-- Usual zones -->
        <div class="col-md-6">
          <div class="card h-100">
            <div class="card-header fw-semibold">
              <i class="bi bi-geo-alt-fill me-2 text-danger"></i>Usual Zones
            </div>
            <div class="card-body">
              ${hasPrefs && p.preferred_locations.length > 0
                ? p.preferred_locations.map(l =>
                    `<span class="badge bg-secondary me-1 mb-1 p-2">${this.esc(l)}</span>`
                  ).join('')
                : '<p class="text-muted small mb-0">Explore routes in the Pyrenees to let the system detect your usual areas.</p>'
              }
              ${top_zones.length > 0
                ? `<hr class="my-2">
                   <ol class="list-group list-group-numbered list-group-flush">
                      ${top_zones.slice(0, 3).map(z =>
                        `<li class="list-group-item d-flex justify-content-between align-items-start px-0 py-1">
                            <span class="small">${this.esc(z.location || 'Unknown')}</span>
                            <span class="badge bg-primary rounded-pill">${z.cnt}</span>
                         </li>`
                      ).join('')}
                   </ol>`
                : ''
              }
            </div>
          </div>
        </div>
      </div>

      <!-- Average metrics -->
      ${hasPrefs ? `
      <div class="row g-3 mb-4">
        <div class="col-12">
          <div class="card">
            <div class="card-header fw-semibold">
              <i class="bi bi-bar-chart-fill me-2 text-info"></i>Your Typical Route Metrics
            </div>
            <div class="card-body">
              <div class="row text-center">
                <div class="col-4">
                  <div class="display-6 fw-bold text-primary">${p.avg_preferred_distance}</div>
                  <small class="text-muted">avg km</small>
                </div>
                <div class="col-4">
                  <div class="display-6 fw-bold text-success">${p.avg_preferred_duration}</div>
                  <small class="text-muted">avg hours</small>
                </div>
                <div class="col-4">
                  <div class="display-6 fw-bold text-warning">${p.avg_preferred_elevation}</div>
                  <small class="text-muted">avg elevation (m)</small>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      ` : ''}

      <!-- Monthly activity -->
      ${activity_by_month.length > 0 ? `
      <div class="row g-3 mb-4">
        <div class="col-12">
          <div class="card">
            <div class="card-header fw-semibold">
              <i class="bi bi-calendar3 me-2 text-secondary"></i>Activity — Last 6 Months
            </div>
            <div class="card-body">
              ${this.monthlyChart(activity_by_month)}
            </div>
          </div>
        </div>
      </div>
      ` : ''}

      <!-- Recent chat sessions -->
      ${chat_sessions.length > 0 ? `
      <div class="row g-3 mb-4">
        <div class="col-12">
          <div class="card">
            <div class="card-header fw-semibold">
              <i class="bi bi-robot me-2 text-info"></i>Recent Conversations with Kahir
            </div>
            <ul class="list-group list-group-flush">
              ${chat_sessions.map(s => `
                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <span class="small">
                    <i class="bi bi-chat-dots text-muted me-2"></i>
                    ${this.formatDate(s.fecha_inicio)}
                  </span>
                  <span class="badge bg-info rounded-pill">${s.num_mensajes} messages</span>
                </li>
              `).join('')}
            </ul>
          </div>
        </div>
      </div>
      ` : ''}

      <!-- AI Profile status -->
      <div class="alert alert-light border small mt-2">
        <i class="bi bi-info-circle text-primary me-2"></i>
        ${hasPrefs && stats.total_interactions >= 5
          ? `AI Profile active · ${stats.total_interactions} interactions analysed · Updated ${this.formatDate(p.last_updated)}`
          : `The AI Profile activates automatically after 5 route interactions. Explore the catalogue!`
        }
      </div>
    `;
  },

  statCard(icon, color, label, value) {
    return `
      <div class="col-6 col-md-3">
        <div class="card text-center h-100">
          <div class="card-body py-3">
            <i class="${icon} text-${color} mb-2 d-block" style="font-size:2rem"></i>
            <div class="fs-4 fw-bold">${value}</div>
            <small class="text-muted">${label}</small>
          </div>
        </div>
      </div>`;
  },

  miniBarChart(breakdown) {
    const max = Math.max(...breakdown.map(b => b.cnt), 1);
    return `<div class="mt-1">
      ${breakdown.map(b => `
        <div class="d-flex align-items-center gap-2 mb-1">
          <small class="text-muted" style="width:100px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis">
            ${this.esc(this.activityLabels[b.tipo_ruta] || b.tipo_ruta)}
          </small>
          <div class="flex-grow-1 bg-light rounded" style="height:8px">
            <div class="bg-primary rounded" style="height:8px;width:${Math.round(b.cnt / max * 100)}%"></div>
          </div>
          <small class="text-muted">${b.cnt}</small>
        </div>
      `).join('')}
    </div>`;
  },

  monthlyChart(data) {
    const max = Math.max(...data.map(d => d.cnt), 1);
    const monthNames = {
      '01':'Jan','02':'Feb','03':'Mar','04':'Apr','05':'May','06':'Jun',
      '07':'Jul','08':'Aug','09':'Sep','10':'Oct','11':'Nov','12':'Dec',
    };
    return `<div class="d-flex align-items-end gap-2" style="height:80px">
      ${data.map(d => {
        const [, m] = d.mes.split('-');
        const h = Math.max(Math.round(d.cnt / max * 64), 4);
        return `<div class="d-flex flex-column align-items-center flex-grow-1">
          <small class="text-muted">${d.cnt}</small>
          <div class="bg-primary rounded-top w-100" style="height:${h}px"></div>
          <small class="text-muted mt-1">${monthNames[m] || m}</small>
        </div>`;
      }).join('')}
    </div>`;
  },

  formatDate(iso) {
    if (!iso) return '';
    try {
      return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    } catch { return iso; }
  },

  esc(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
  },
};

document.addEventListener('DOMContentLoaded', () => {
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
