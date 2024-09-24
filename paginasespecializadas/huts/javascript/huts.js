document.addEventListener('DOMContentLoaded', function() {
    var map = L.map('map').setView([42.5, 1.5], 8);

    L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: 'Map data &copy; <a href="https://www.opentopomap.org/copyright">OpenTopoMap</a> contributors'
    }).addTo(map);

    var geojsonUrl = 'javascript/pyrenees.geojson';
    var geojsonLayer;
    var originalData; // Variable para guardar los datos originales

    function cargarDatosEnMapa(data) {
        geojsonLayer = L.geoJSON(data, {
            pointToLayer: function(feature, latlng) {
                var fillColor;
                switch (feature.properties.type_hebergement.toLowerCase()) {
                    case 'cabane ouverte':
                        fillColor = '#337DFF'; // Azul para cabane ouverte
                        break;
                    case 'cabane fermee':
                        fillColor = '#FF5733'; // Rojo para cabane fermee
                        break;
                    case 'cabane ouverte mais ocupee par le berger l ete':
                        fillColor = '#FFA500'; // Naranja para cabane ouverte ocupada por el pastor en verano
                        break;
                    case 'orri toue abri en pierre':
                        fillColor = '#FFFF00'; // Amarillo para orri, toue, abri en pierre
                        break;
                    case 'refuge garde l ete partie hivernal':
                        fillColor = '#00FF00'; // Verde para refuge guardé l'été, parte invernal
                        break;
                    case 'refuge garde toute l annee':
                        fillColor = '#FF1493'; // Rosa intenso para refuge guardé toute l'année
                        break;
                    case 'ruine':
                        fillColor = '#8C8C8C'; // Gris para ruine
                        break;
                    default:
                        fillColor = '#198754'; // Verde por defecto
                        break;
                }
                return L.circleMarker(latlng, {
                    radius: 8,
                    fillColor: fillColor,
                    color: "#000",
                    weight: 1,
                    opacity: 1,
                    fillOpacity: 0.8
                }).bindPopup(function(layer) {
                    var props = layer.feature.properties;
                    var popupContent = `
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
                    return popupContent;
                });
            }
        }).addTo(map);
    }

    // Cargar datos originales al inicio
    fetch(geojsonUrl)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error al cargar el archivo GeoJSON');
            }
            return response.json();
        })
        .then(data => {
            originalData = data; // Guardar los datos originales
            cargarDatosEnMapa(data); // Cargar los datos en el mapa al inicio
        })
        .catch(error => {
            console.error('Error al cargar el archivo GeoJSON:', error);
            var errorMessage = document.createElement('div');
            errorMessage.className = 'alert alert-danger';
            errorMessage.textContent = 'Error al cargar el archivo GeoJSON. Por favor, inténtelo de nuevo.';
            document.getElementById('map').appendChild(errorMessage);
        });

    // Aplicar filtro al hacer clic en el botón
    document.getElementById('aplicarFiltro').addEventListener('click', function() {
        var selectedType = document.getElementById('filtroRefugio').value;

        if (selectedType === 'all') {
            // Mostrar todos los datos originales
            map.eachLayer(function(layer) {
                if (layer instanceof L.Marker || layer instanceof L.CircleMarker) {
                    map.removeLayer(layer);
                }
            });
            cargarDatosEnMapa(originalData); // Restaurar datos originales
        } else {
            // Filtrar datos y mostrar solo el tipo seleccionado
            var filteredData = originalData.features.filter(function(feature) {
                return feature.properties.type_hebergement.toLowerCase() === selectedType.toLowerCase();
            });
            map.eachLayer(function(layer) {
                if (layer instanceof L.Marker || layer instanceof L.CircleMarker) {
                    map.removeLayer(layer);
                }
            });
            cargarDatosEnMapa({
                type: 'FeatureCollection',
                features: filteredData
            });
        }
    });
});
