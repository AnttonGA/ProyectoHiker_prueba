document.addEventListener('DOMContentLoaded', function() {
  if (typeof L === 'undefined') {
    console.error('Leaflet (L) is not defined. Make sure to load Leaflet before this script.');
    return;
  }

  const idParam = new URLSearchParams(window.location.search).get('id');
  let map, routeMarkers, shelterMarkers, allRoutes = [], shelterLayer;

  function initMap(lat, lng, zoom) {
    map = L.map('map').setView([lat, lng], zoom);
    
    L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.opentopomap.org/copyright">OpenTopoMap</a> contributors'
    }).addTo(map);

    L.control.fullscreen().addTo(map);

    routeMarkers = L.markerClusterGroup({
      disableClusteringAtZoom: 12,
      spiderfyOnMaxZoom: true,
      showCoverageOnHover: false,
      zoomToBoundsOnClick: true
    });

    shelterMarkers = L.markerClusterGroup({
      disableClusteringAtZoom: 12,
      spiderfyOnMaxZoom: true,
      showCoverageOnHover: false,
      zoomToBoundsOnClick: true
    });

    map.addLayer(routeMarkers);
    
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
      routeMarkers.addLayer(marker);
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

  // Shelter related functions
  function loadShelters(selectedType) {
    fetch('javascript/pyrenees.geojson')
      .then(response => {
        if (!response.ok) {
          throw new Error('Error loading the GeoJSON file');
        }
        return response.json();
      })
      .then(data => {
        if (shelterLayer) {
          map.removeLayer(shelterLayer);
          shelterMarkers.clearLayers();
        }
        shelterLayer = L.geoJSON(data, {
          filter: function(feature) {
            return selectedType === 'all' || feature.properties.type_hebergement.toLowerCase() === selectedType.toLowerCase();
          },
          pointToLayer: function(feature, latlng) {
            var fillColor = getShelterColor(feature.properties.type_hebergement.toLowerCase());
            return L.circleMarker(latlng, {
              radius: 8,
              fillColor: fillColor,
              color: "#000",
              weight: 1,
              opacity: 1,
              fillOpacity: 0.8
            }).bindPopup(createShelterPopup(feature.properties));
          }
        });
        shelterMarkers.addLayer(shelterLayer);
        map.addLayer(shelterMarkers);
      })
      .catch(error => {
        console.error('Error loading the GeoJSON file:', error);
      });
  }

  function getShelterColor(type) {
    switch (type) {
      case 'cabane ouverte': return '#337DFF';
      case 'cabane fermee': return '#FF5733';
      case 'cabane ouverte mais ocupee par le berger l ete': return '#FFA500';
      case 'orri toue abri en pierre': return '#FFFF00';
      case 'refuge garde l ete partie hivernal': return '#00FF00';
      case 'refuge garde toute l annee': return '#FF1493';
      case 'ruine': return '#8C8C8C';
      default: return '#198754';
    }
  }

  function createShelterPopup(props) {
    return `
      <div class="card border-0">
        <div class="card-header bg-primary text-dark">
          <h5 class="card-title">${props.name || 'N/A'}</h5>
        </div>
        <div class="card-body">
          <p class="card-text"><b>Region:</b> ${props.region || 'N/A'}</p>
          <p class="card-text"><b>Altitude:</b> ${props.altitude || 'N/A'}</p>
          <p class="card-text"><b>Capacity (summer):</b> ${props.cap_ete || 'N/A'}</p>
          <p class="card-text"><b>Capacity (winter):</b> ${props.cap_hiver || 'N/A'}</p>
          <a href="${props.url}" class="btn btn-primary text-dark" target="_blank">More info</a>
        </div>
      </div>`;
  }

  // Event Listeners
  document.getElementById('filterButton').addEventListener('click', filtrarYMostrarRutas);
  document.getElementById('aplicarFiltro').addEventListener('click', function() {
    var selectedType = document.getElementById('filtroRefugio').value;
    loadShelters(selectedType);
  });

  function filtrarYMostrarRutas() {
    const difficultyFilter = document.getElementById('difficultyFilter').value.toLowerCase();
    const minElevation = parseInt(document.getElementById('minElevation').value) || 0;
    const maxDuration = parseInt(document.getElementById('maxDuration').value) || Infinity;
    const activityFilters = Array.from(document.querySelectorAll('#activityFilter input:checked')).map(input => input.value);

    routeMarkers.clearLayers();

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
      routeMarkers.addLayer(ruta.marker);
    });
  }
});