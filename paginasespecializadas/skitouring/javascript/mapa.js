document.addEventListener('DOMContentLoaded', function() {
  if (typeof L === 'undefined') {
    console.error('Leaflet (L) is not defined. Make sure to load Leaflet before this script.');
    return;
  }

  const idParam = new URLSearchParams(window.location.search).get('id');
  let map, markers, allRoutes = [];

  function initMap(lat, lng, zoom) {
    map = L.map('map').setView([lat, lng], zoom);
    
    L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.opentopomap.org/copyright">OpenTopoMap</a> contributors'
    }).addTo(map);

    L.control.fullscreen().addTo(map);

    markers = L.markerClusterGroup({
      disableClusteringAtZoom: 12,
      spiderfyOnMaxZoom: true,
      showCoverageOnHover: false,
      zoomToBoundsOnClick: true
    });

    map.addLayer(markers);
    
    cargarRutas();
  }

  if (idParam === '1') {
    navigator.geolocation?.getCurrentPosition(
      position => initMap(position.coords.latitude, position.coords.longitude, 12),
      error => {
        console.error('Error getting user location:', error);
        initMap(42.5, 1.5, 7);
      }
    );
  } else {
    initMap(42.5, 1.5, 7);
  }

  const createBootstrapIcon = iconClass => L.divIcon({
    className: 'leaflet-bootstrap-icon',
    html: `<i class="bi ${iconClass}" style="font-size: 24px;"></i>`,
    iconSize: [24, 24],
    iconAnchor: [12, 12],
    popupAnchor: [0, -12]
  });

  function cargarRutas() {
    fetch('javascript/obtener_rutas.php')
      .then(response => {
        if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
        return response.json();
      })
      .then(rutas => {
        allRoutes = rutas.map(ruta => ({ ...ruta, marker: null, loadedLayer: null }));
        mostrarMarcadorInicio(allRoutes);
      })
      .catch(error => {
        console.error('Error loading routes:', error);
        // Add code here to show an error message to the user
      });
  }

  const difficultyColors = {
    beginner: 'green',
    intermediate: 'blue',
    advanced: 'yellow'
  };

  function mostrarMarcadorInicio(rutas) {
    rutas.forEach(ruta => {
      const startIcon = createBootstrapIcon('bi-geo-alt-fill');
      const marker = L.marker([parseFloat(ruta.startLat), parseFloat(ruta.startLng)], { icon: startIcon });
      const popupContent = `
        <div class="popup-content">
          <h3>${ruta.title}</h3>
          <p><strong>Elevation:</strong> ${ruta.elevation} m</p>
          <p><strong>Duration:</strong> ${ruta.duration} hours</p>
          <p><strong>Difficulty:</strong> ${ruta.difficulty}</p>
          <p><strong>Location:</strong> ${ruta.location}</p>
          <p><strong>Activity:</strong> ${ruta.tipo_ruta}</p>
          <a href="ruta.html?id=${ruta.id}" class="btn btn-primary text-dark mt-2 more-info-btn">More Info</a>
        </div>
      `;
      marker.bindPopup(popupContent);

      marker.on('popupopen', () => {
        if (!ruta.loadedLayer) {
          ruta.loadedLayer = cargarRutaCompleta(ruta);
        }
      });

      marker.on('popupclose', () => {
        if (ruta.loadedLayer) {
          map.removeLayer(ruta.loadedLayer);
          ruta.loadedLayer = null;
        }
      });

      ruta.marker = marker;
      markers.addLayer(marker);
    });
  }

  function cargarRutaCompleta(ruta) {
    const color = difficultyColors[ruta.difficulty.toLowerCase()] || 'black';
    return new L.GPX(`rutas_st/${ruta.ruta_carpeta}/${ruta.filename}`, {
      async: true,
      polyline_options: {
        color: color,
        weight: 4,
        opacity: 0.75,
        lineCap: 'round'
      },
      marker_options: {
        startIcon: createBootstrapIcon('bi-geo-alt-fill'),
        endIcon: createBootstrapIcon('bi-flag-fill')
      }
    }).on('loaded', e => {
      const layers = e.target.getLayers();
      if (layers.length > 0 && layers[0].getLatLng?.().equals([parseFloat(ruta.startLat), parseFloat(ruta.startLng)])) {
        e.target.removeLayer(layers[0]);
      }
    }).addTo(map);
  }

  window.cargarRutas = cargarRutas;
  window.mostrarMarcadorInicio = mostrarMarcadorInicio;

  document.getElementById('filterButton').addEventListener('click', filtrarYMostrarRutas);

  function filtrarYMostrarRutas() {
    const difficultyFilter = document.getElementById('difficultyFilter').value.toLowerCase();
    const minElevation = parseInt(document.getElementById('minElevation').value) || 0;
    const maxDuration = parseInt(document.getElementById('maxDuration').value) || Infinity;
    const activityFilters = Array.from(document.querySelectorAll('#activityFilter input:checked')).map(input => input.value);

    markers.clearLayers();

    allRoutes.forEach(ruta => {
      if (ruta.loadedLayer && map.hasLayer(ruta.loadedLayer)) {
        map.removeLayer(ruta.loadedLayer);
        ruta.loadedLayer = null;
      }
    });

    const rutasFiltradas = allRoutes.filter(ruta => 
      (!difficultyFilter || ruta.difficulty.toLowerCase() === difficultyFilter) &&
      (parseFloat(ruta.elevation) >= minElevation) &&
      (parseFloat(ruta.duration) <= maxDuration) &&
      (activityFilters.length === 0 || activityFilters.includes(ruta.tipo_ruta))
    );

    rutasFiltradas.forEach(ruta => {
      if (!ruta.marker) {
        const startIcon = createBootstrapIcon('bi-geo-alt-fill');
        ruta.marker = L.marker([parseFloat(ruta.startLat), parseFloat(ruta.startLng)], { icon: startIcon });
        const popupContent = `
          <div class="popup-content">
            <h3>${ruta.title}</h3>
            <p><strong>Elevation:</strong> ${ruta.elevation} m</p>
            <p><strong>Duration:</strong> ${ruta.duration} hours</p>
            <p><strong>Difficulty:</strong> ${ruta.difficulty}</p>
            <p><strong>Location:</strong> ${ruta.location}</p>
            <p><strong>Activity:</strong> ${ruta.tipo_ruta}</p>
            <a href="ruta.html?id=${ruta.id}" class="btn btn-primary text-dark mt-2 more-info-btn">More Info</a>
          </div>
        `;
        ruta.marker.bindPopup(popupContent);
      }
      markers.addLayer(ruta.marker);
    });
  }
});