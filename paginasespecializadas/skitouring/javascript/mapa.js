document.addEventListener('DOMContentLoaded', function () {
  if (typeof L === 'undefined') {
    console.error('Leaflet (L) is not defined.');
    return;
  }

  const idParam = new URLSearchParams(window.location.search).get('id');
  let map, routeMarkers, shelterMarkers, allRoutes = [], shelterLayer;

  // ── Activity colours & icons ──────────────────────────────────
  // Paleta terrosa (misma en carrusel.js / lista.js y en los puntos de los filtros)
  const ACTIVITY_STYLE = {
    hiking:        { color: '#5F7444', icon: 'bi-person-walking' },
    ski_touring:   { color: '#3F6B7A', icon: 'bi-snow3' },
    btt:           { color: '#B5653A', icon: 'bi-bicycle' },
    cycle_touring: { color: '#7A5C7E', icon: 'bi-bicycle' },
    snowshoeing:   { color: '#5E8F9E', icon: 'bi-snow' },
    climbing:      { color: '#A6402C', icon: 'bi-activity' },
    via_ferrata:   { color: '#B8862B', icon: 'bi-lightning-charge' },
    kayaking:      { color: '#3E7F74', icon: 'bi-water' },
  };

  const ACTIVITY_LABEL = {
    hiking: 'Senderismo', ski_touring: 'Esquí de travesía', btt: 'BTT',
    cycle_touring: 'Cicloturismo', snowshoeing: 'Raquetas',
    climbing: 'Escalada', via_ferrata: 'Vía ferrata', kayaking: 'Kayak',
  };

  const LOCATION_LABEL = {
    Western_Pyrenees: 'Pirineo occidental',
    Central_Pyrenees: 'Pirineo central',
    Eastern_Pyrenees: 'Pirineo oriental',
    Other: 'Otras zonas',
  };

  const DIFFICULTY_LABEL = {
    beginner: 'Fácil', intermediate: 'Moderada', advanced: 'Difícil',
  };

  const DIFFICULTY_CLASS = {
    beginner: 'diff--easy', intermediate: 'diff--moderate', advanced: 'diff--hard',
  };

  // ── Icon factory ──────────────────────────────────────────────
  function createActivityIcon(tipo) {
    const style = ACTIVITY_STYLE[tipo] || { color: '#7A7466', icon: 'bi-map' };
    return L.divIcon({
      className: 'k-marker-wrap',
      html: `<div class="k-marker" style="--act:${style.color};"><i class="bi ${style.icon}" aria-hidden="true"></i></div>`,
      iconSize: [30, 30],
      iconAnchor: [15, 15],
      popupAnchor: [0, -16],
    });
  }

  function createBootstrapIcon(iconClass) {
    return L.divIcon({
      className: 'leaflet-bootstrap-icon',
      html: `<i class="bi ${iconClass}" aria-hidden="true"></i>`,
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
          html: `<div class="k-cluster" style="width:${size}px;height:${size}px;">${count}</div>`,
          className: 'k-cluster-wrap', iconSize: [size, size],
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
    const actStyle = ACTIVITY_STYLE[ruta.tipo_ruta] || { color: '#7A7466', icon: 'bi-map' };
    const actLabel = ACTIVITY_LABEL[ruta.tipo_ruta] || ruta.tipo_ruta || 'Ruta';
    const diffKey  = ruta.dificultad?.toLowerCase();
    const diffLabel = DIFFICULTY_LABEL[diffKey] || ruta.dificultad || '';
    const diffCls   = DIFFICULTY_CLASS[diffKey] || '';

    const elev = ruta.elevacion_ganada ? `+${parseFloat(ruta.elevacion_ganada).toLocaleString('es-ES', {maximumFractionDigits:0})} m` : '';
    const dist = ruta.distancia_km    ? `${parseFloat(ruta.distancia_km).toLocaleString('es-ES', {minimumFractionDigits:1, maximumFractionDigits:1})} km` : '';
    const dur  = ruta.duracion_horas  ? `${parseFloat(ruta.duracion_horas).toLocaleString('es-ES', {minimumFractionDigits:1, maximumFractionDigits:1})} h`  : '';
    const effort = (ruta.esfuerzo && ruta.horas_mide) ? parseInt(ruta.esfuerzo, 10) : NaN;
    const place = ruta.ubicacion ? (LOCATION_LABEL[ruta.ubicacion] || String(ruta.ubicacion).replace(/_/g, ' ')) : '';

    const chips = [
      dist ? `<li class="k-chip"><i class="bi bi-arrows-expand" aria-hidden="true"></i>${dist}</li>` : '',
      elev ? `<li class="k-chip"><i class="bi bi-arrow-up-right" aria-hidden="true"></i>${elev}</li>` : '',
      dur  ? `<li class="k-chip"><i class="bi bi-clock" aria-hidden="true"></i>${dur}</li>` : '',
      diffLabel ? `<li class="k-chip k-chip-diff ${diffCls}"><span class="k-dot" aria-hidden="true"></span>${escapeHtml(diffLabel)}</li>` : '',
      !isNaN(effort) ? `<li class="k-chip k-chip-moss">Esfuerzo ${effort}/5</li>` : '',
    ].join('');

    return `
      <div class="k-popup">
        <p class="k-popup__eyebrow"><span class="k-dot" style="--act:${actStyle.color};" aria-hidden="true"></span>${escapeHtml(actLabel)}</p>
        <h3 class="k-popup__title">${escapeHtml(ruta.titulo || 'Ruta sin nombre')}</h3>
        ${place ? `<p class="k-popup__place"><i class="bi bi-geo-alt" aria-hidden="true"></i>${escapeHtml(place)}</p>` : ''}
        ${chips ? `<ul class="k-popup__chips">${chips}</ul>` : ''}
        <a href="ruta.html?id=${encodeURIComponent(ruta.id)}" class="btn btn-primary btn-sm w-100 k-popup__cta">Ver ruta <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
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
        marker.bindPopup(buildPopup(ruta), { maxWidth: 280, minWidth: 220 });

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
    const actStyle = ACTIVITY_STYLE[ruta.tipo_ruta] || { color: '#5F7444' };
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
  // Misma paleta que huts.js y la leyenda (.color-example en estilos2.css)
  function getShelterColor(type) {
    const map = {
      'cabane ouverte':                                            '#3F6B7A',
      'cabane fermee':                                             '#B5653A',
      'cabane ouverte mais ocupee par le berger l ete':            '#D49A3A',
      'orri toue abri en pierre':                                  '#C9B27C',
      'refuge garde l ete partie hivernal':                        '#7E9A55',
      'refuge garde toute l annee':                                '#7A4E6E',
      'ruine':                                                     '#9A968C',
    };
    return map[type] || '#5F7444';
  }

  function createShelterPopup(props) {
    props = props || {};
    const url = props.url ? safeHttpUrl(props.url) : '';
    const facts = [
      props.altitude  ? `<dt>Altitud</dt><dd>${escapeHtml(isNaN(Number(props.altitude)) ? props.altitude : Number(props.altitude).toLocaleString('es-ES'))} m</dd>` : '',
      props.cap_ete   ? `<dt>Plazas en verano</dt><dd>${escapeHtml(props.cap_ete)}</dd>` : '',
      props.cap_hiver ? `<dt>Plazas en invierno</dt><dd>${escapeHtml(props.cap_hiver)}</dd>` : '',
    ].join('');
    return `
      <div class="k-popup">
        <p class="k-popup__eyebrow"><i class="bi bi-house-door" aria-hidden="true"></i>Refugio</p>
        <h3 class="k-popup__title">${escapeHtml(props.name || 'Refugio')}</h3>
        ${props.region ? `<p class="k-popup__place"><i class="bi bi-geo-alt" aria-hidden="true"></i>${escapeHtml(props.region)}</p>` : ''}
        ${facts ? `<dl class="k-popup__facts">${facts}</dl>` : ''}
        ${url ? `<a href="${escapeHtml(url)}" class="k-popup__link" target="_blank" rel="noopener noreferrer">Más información <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>` : ''}
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
              radius: 7, fillColor, color: '#FFFDF8',
              weight: 2, opacity: 1, fillOpacity: 0.95,
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
