document.addEventListener('DOMContentLoaded', function () {
  if (typeof L === 'undefined') {
    console.error('Leaflet (L) is not defined.');
    return;
  }

  const idParam = new URLSearchParams(window.location.search).get('id');
  let map, routeMarkers, shelterMarkers, allRoutes = [], shelterLayer;

  // ── Activity colours & icons ──────────────────────────────────
  const ACTIVITY_STYLE = {
    hiking:        { color: '#28a745', icon: 'bi-person-walking' },
    ski_touring:   { color: '#0d6efd', icon: 'bi-snow3' },
    btt:           { color: '#fd7e14', icon: 'bi-bicycle' },
    cycle_touring: { color: '#6f42c1', icon: 'bi-bicycle' },
    snowshoeing:   { color: '#17a2b8', icon: 'bi-snow' },
    climbing:      { color: '#dc3545', icon: 'bi-activity' },
    via_ferrata:   { color: '#e83e8c', icon: 'bi-lightning-charge' },
    kayaking:      { color: '#20c997', icon: 'bi-water' },
  };

  const ACTIVITY_LABEL = {
    hiking: 'Hiking', ski_touring: 'Ski Touring', btt: 'MTB',
    cycle_touring: 'Cycle Touring', snowshoeing: 'Snowshoeing',
    climbing: 'Climbing', via_ferrata: 'Via Ferrata', kayaking: 'Kayaking',
  };

  const DIFFICULTY_LABEL = {
    beginner: 'Easy', intermediate: 'Moderate', advanced: 'Hard',
  };

  const DIFFICULTY_COLOR = {
    beginner: '#28a745', intermediate: '#fd7e14', advanced: '#dc3545',
  };

  // ── Icon factory ──────────────────────────────────────────────
  function createActivityIcon(tipo) {
    const style = ACTIVITY_STYLE[tipo] || { color: '#6c757d', icon: 'bi-map' };
    return L.divIcon({
      className: '',
      html: `<div style="
        background:${style.color};
        border:2px solid #fff;
        border-radius:50%;
        width:28px; height:28px;
        display:flex; align-items:center; justify-content:center;
        box-shadow:0 2px 6px rgba(0,0,0,.35);">
        <i class="bi ${style.icon}" style="font-size:13px;color:#fff;"></i>
      </div>`,
      iconSize: [28, 28],
      iconAnchor: [14, 14],
      popupAnchor: [0, -16],
    });
  }

  function createBootstrapIcon(iconClass) {
    return L.divIcon({
      className: 'leaflet-bootstrap-icon',
      html: `<i class="bi ${iconClass}" style="font-size:24px;"></i>`,
      iconSize: [24, 24],
      iconAnchor: [12, 12],
      popupAnchor: [0, -12],
    });
  }

  // ── Init map ──────────────────────────────────────────────────
  function initMap(lat, lng, zoom) {
    map = L.map('map').setView([lat, lng], zoom);

    L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.opentopomap.org/copyright">OpenTopoMap</a>'
    }).addTo(map);

    L.control.fullscreen().addTo(map);

    routeMarkers = L.markerClusterGroup({
      disableClusteringAtZoom: 12,
      spiderfyOnMaxZoom: true,
      showCoverageOnHover: false,
      zoomToBoundsOnClick: true,
      iconCreateFunction(cluster) {
        const count = cluster.getChildCount();
        const size = count < 10 ? 32 : count < 50 ? 38 : 44;
        return L.divIcon({
          html: `<div style="background:#0d6efd;color:#fff;border-radius:50%;width:${size}px;height:${size}px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.8rem;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3);">${count}</div>`,
          className: '', iconSize: [size, size],
        });
      }
    });

    shelterMarkers = L.markerClusterGroup({
      disableClusteringAtZoom: 12,
      spiderfyOnMaxZoom: true,
      showCoverageOnHover: false,
      zoomToBoundsOnClick: true,
    });

    map.addLayer(routeMarkers);
    cargarRutas();
  }

  if (idParam === '1') {
    navigator.geolocation?.getCurrentPosition(
      pos => initMap(pos.coords.latitude, pos.coords.longitude, 12),
      ()  => initMap(42.5, 1.5, 7)
    );
  } else {
    initMap(42.5, 1.5, 7);
  }

  // ── Escape helpers (datos de la BD / GeoJSON antes de meterlos en HTML) ──
  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  // Solo enlaces http(s); cualquier otro esquema (javascript:, data:...) se descarta
  function safeHttpUrl(url) {
    try {
      const parsed = new URL(String(url || ''), window.location.href);
      return (parsed.protocol === 'http:' || parsed.protocol === 'https:') ? parsed.href : '';
    } catch (e) {
      return '';
    }
  }

  // ── Popup builder ─────────────────────────────────────────────
  function buildPopup(ruta) {
    const actStyle = ACTIVITY_STYLE[ruta.tipo_ruta] || { color: '#6c757d' };
    const actLabel = ACTIVITY_LABEL[ruta.tipo_ruta] || ruta.tipo_ruta;
    const diffLabel = DIFFICULTY_LABEL[ruta.dificultad?.toLowerCase()] || ruta.dificultad || '—';
    const diffColor = DIFFICULTY_COLOR[ruta.dificultad?.toLowerCase()] || '#6c757d';

    const elev = ruta.elevacion_ganada ? `+${parseFloat(ruta.elevacion_ganada).toLocaleString('es-ES', {maximumFractionDigits:0})} m` : '—';
    const dist = ruta.distancia_km    ? `${parseFloat(ruta.distancia_km).toFixed(1)} km` : '—';
    const dur  = ruta.duracion_horas  ? `${parseFloat(ruta.duracion_horas).toFixed(1)} h`  : '—';

    return `
      <div style="min-width:220px;font-family:inherit;">
        <div style="background:${actStyle.color};color:#fff;padding:8px 12px;margin:-1px -1px 0;border-radius:4px 4px 0 0;">
          <strong style="font-size:.9rem;">${escapeHtml(ruta.titulo || 'Sin título')}</strong>
        </div>
        <div style="padding:10px 12px 12px;line-height:1.5;">
          <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:8px;">
            <span style="background:${diffColor};color:#fff;border-radius:20px;padding:2px 8px;font-size:.7rem;font-weight:600;">${escapeHtml(diffLabel)}</span>
            <span style="background:${actStyle.color};color:#fff;border-radius:20px;padding:2px 8px;font-size:.7rem;font-weight:600;">${escapeHtml(actLabel)}</span>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
            <span style="background:#f0f4f8;border-radius:20px;padding:2px 9px;font-size:.72rem;font-weight:600;">↑ ${elev}</span>
            <span style="background:#f0f4f8;border-radius:20px;padding:2px 9px;font-size:.72rem;font-weight:600;">⟷ ${dist}</span>
            <span style="background:#f0f4f8;border-radius:20px;padding:2px 9px;font-size:.72rem;font-weight:600;">⏱ ${dur}</span>
          </div>
          ${ruta.ubicacion ? `<div style="font-size:.75rem;color:#6c757d;margin-bottom:10px;">📍 ${escapeHtml(ruta.ubicacion)}</div>` : ''}
          <a href="ruta.html?id=${encodeURIComponent(ruta.id)}" style="display:block;text-align:center;background:#0d6efd;color:#fff;border-radius:8px;padding:6px 0;font-size:.8rem;font-weight:600;text-decoration:none;">Ver ruta →</a>
        </div>
      </div>`;
  }

  // ── Load routes ───────────────────────────────────────────────
  function cargarRutas() {
    fetch('javascript/obtener_rutas.php')
      .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
      .then(rutas => {
        allRoutes = rutas.map(ruta => ({ ...ruta, marker: null, loadedLayer: null }));
        mostrarMarcadores(allRoutes);
      })
      .catch(err => console.error('Error loading routes:', err));
  }

  function mostrarMarcadores(rutas) {
    routeMarkers.clearLayers();

    // Clear any open GPX overlays
    allRoutes.forEach(r => {
      if (r.loadedLayer && map.hasLayer(r.loadedLayer)) {
        map.removeLayer(r.loadedLayer);
        r.loadedLayer = null;
      }
    });

    rutas.forEach(ruta => {
      const lat = parseFloat(ruta.start_lat);
      const lng = parseFloat(ruta.start_lng);
      if (isNaN(lat) || isNaN(lng)) return;

      // Reuse existing marker if same route
      if (!ruta.marker) {
        const icon = createActivityIcon(ruta.tipo_ruta);
        const marker = L.marker([lat, lng], { icon });
        marker.bindPopup(buildPopup(ruta), { maxWidth: 260 });

        marker.on('popupopen', () => {
          if (!ruta.loadedLayer) ruta.loadedLayer = cargarGPX(ruta);
        });
        marker.on('popupclose', () => {
          if (ruta.loadedLayer) {
            map.removeLayer(ruta.loadedLayer);
            ruta.loadedLayer = null;
          }
        });

        ruta.marker = marker;
      }

      routeMarkers.addLayer(ruta.marker);
    });
  }

  function cargarGPX(ruta) {
    const actStyle = ACTIVITY_STYLE[ruta.tipo_ruta] || { color: '#0d6efd' };
    return new L.GPX(`rutas_st/${ruta.ruta_carpeta}/${ruta.gpx_path}`, {
      async: true,
      polyline_options: {
        color: actStyle.color,
        weight: 4,
        opacity: 0.8,
        lineCap: 'round',
      },
      marker_options: {
        startIcon: createBootstrapIcon('bi-geo-alt-fill'),
        endIcon:   createBootstrapIcon('bi-flag-fill'),
      },
    }).on('loaded', e => {
      // Remove duplicate start marker if it overlaps our cluster marker
      const layers = e.target.getLayers();
      if (layers.length > 0 && layers[0].getLatLng?.()) {
        const ll = layers[0].getLatLng();
        if (Math.abs(ll.lat - parseFloat(ruta.start_lat)) < 0.0001 &&
            Math.abs(ll.lng - parseFloat(ruta.start_lng)) < 0.0001) {
          e.target.removeLayer(layers[0]);
        }
      }
    }).addTo(map);
  }

  // ── Filters ───────────────────────────────────────────────────
  function getFilters() {
    return {
      diff:        document.getElementById('difficultyFilter')?.value?.toLowerCase() ?? 'all',
      minElev:     parseFloat(document.getElementById('minElevation')?.value)  || 0,
      maxDur:      parseFloat(document.getElementById('maxDuration')?.value)   || Infinity,
      maxDist:     parseFloat(document.getElementById('maxDistance')?.value)   || Infinity,
      activities:  Array.from(document.querySelectorAll('#mapActivityFilter input:checked')).map(i => i.value),
    };
  }

  function filtrarYMostrarRutas() {
    const { diff, minElev, maxDur, maxDist, activities } = getFilters();

    const filtered = allRoutes.filter(r => {
      if (diff !== 'all' && (r.dificultad || '').toLowerCase() !== diff) return false;
      if (minElev > 0 && (parseFloat(r.elevacion_ganada) || 0) < minElev) return false;
      if (maxDur < Infinity && (parseFloat(r.duracion_horas) || 0) > maxDur) return false;
      if (maxDist < Infinity && (parseFloat(r.distancia_km) || 0) > maxDist) return false;
      if (activities.length && !activities.includes(r.tipo_ruta)) return false;
      return true;
    });

    mostrarMarcadores(filtered);
  }

  document.getElementById('filterButton')?.addEventListener('click', filtrarYMostrarRutas);

  document.getElementById('clearMapFilters')?.addEventListener('click', () => {
    document.getElementById('difficultyFilter').value = 'all';
    document.getElementById('minElevation').value = '';
    document.getElementById('maxDuration').value = '';
    document.getElementById('maxDistance').value = '';
    document.querySelectorAll('#mapActivityFilter input').forEach(cb => cb.checked = false);
    mostrarMarcadores(allRoutes);
  });

  // ── Shelter filter ────────────────────────────────────────────
  function getShelterColor(type) {
    const map = {
      'cabane ouverte':                                            '#337DFF',
      'cabane fermee':                                             '#FF5733',
      'cabane ouverte mais ocupee par le berger l ete':            '#FFA500',
      'orri toue abri en pierre':                                  '#FFFF00',
      'refuge garde l ete partie hivernal':                        '#00FF00',
      'refuge garde toute l annee':                                '#FF1493',
      'ruine':                                                     '#8C8C8C',
    };
    return map[type] || '#198754';
  }

  function createShelterPopup(props) {
    props = props || {};
    const url = props.url ? safeHttpUrl(props.url) : '';
    return `
      <div style="min-width:200px;">
        <div style="background:#0d6efd;color:#fff;padding:8px 12px;margin:-1px -1px 0;border-radius:4px 4px 0 0;">
          <strong>${escapeHtml(props.name || 'Shelter')}</strong>
        </div>
        <div style="padding:10px 12px;line-height:1.6;font-size:.82rem;">
          ${props.region   ? `<div>📍 ${escapeHtml(props.region)}</div>` : ''}
          ${props.altitude ? `<div>⛰️ Alt: ${escapeHtml(props.altitude)} m</div>` : ''}
          ${props.cap_ete  ? `<div>☀️ Summer capacity: ${escapeHtml(props.cap_ete)}</div>` : ''}
          ${props.cap_hiver? `<div>❄️ Winter capacity: ${escapeHtml(props.cap_hiver)}</div>` : ''}
          ${url            ? `<div style="margin-top:8px;"><a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer" style="color:#0d6efd;font-weight:600;">More info →</a></div>` : ''}
        </div>
      </div>`;
  }

  function loadShelters(selectedType) {
    fetch('javascript/pyrenees.geojson')
      .then(r => { if (!r.ok) throw new Error('GeoJSON load failed'); return r.json(); })
      .then(data => {
        if (shelterLayer) {
          map.removeLayer(shelterLayer);
          shelterMarkers.clearLayers();
        }
        shelterLayer = L.geoJSON(data, {
          filter: f => selectedType === 'all' ||
            f.properties.type_hebergement?.toLowerCase() === selectedType.toLowerCase(),
          pointToLayer: (feature, latlng) => {
            const fillColor = getShelterColor(feature.properties.type_hebergement?.toLowerCase());
            return L.circleMarker(latlng, {
              radius: 8, fillColor, color: '#000',
              weight: 1, opacity: 1, fillOpacity: 0.85,
            }).bindPopup(createShelterPopup(feature.properties));
          }
        });
        shelterMarkers.addLayer(shelterLayer);
        map.addLayer(shelterMarkers);
      })
      .catch(err => console.error('Shelter load error:', err));
  }

  document.getElementById('aplicarFiltro')?.addEventListener('click', () => {
    const selected = document.getElementById('filtroRefugio').value;
    if (selected !== 'none') loadShelters(selected);
    else if (shelterLayer) {
      map.removeLayer(shelterLayer);
      shelterMarkers.clearLayers();
    }
  });
});
