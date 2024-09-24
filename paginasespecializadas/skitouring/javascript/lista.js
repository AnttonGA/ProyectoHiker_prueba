document.addEventListener('DOMContentLoaded', function() {
  // Configuration
  const ITEMS_PER_PAGE = 50;
  let currentPage = 1;
  let allRoutes = [];
  let filteredRoutes = [];

  // DOM elements
  const routesList = document.getElementById('routes-list');
  const pagination = document.getElementById('pagination');
  const filterForm = document.getElementById('filter-form');

  // Load routes
  async function loadRoutes() {
    try {
      const response = await fetch('javascript/obtener_rutas.php');
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      const text = await response.text();
      if (!text.trim()) {
        throw new Error('Empty response from server');
      }
      allRoutes = JSON.parse(text);
      
      if (!Array.isArray(allRoutes)) {
        throw new Error('Received data is not an array');
      }
      filteredRoutes = [...allRoutes];
      displayRoutes();
      updatePagination();
    } catch (error) {
      console.error('Error:', error);
      routesList.innerHTML = `<p class="col-12">Error loading routes: ${error.message}. Please try again later.</p>`;
    }
  }

  // Display routes
  function displayRoutes() {
    const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
    const endIndex = startIndex + ITEMS_PER_PAGE;
    const routesToDisplay = filteredRoutes.slice(startIndex, endIndex);

    routesList.innerHTML = routesToDisplay.map(route => {
      const imageUrl = route.imagenes && route.imagenes.length > 0 ? route.imagenes[0] : 'path/to/default/image.jpg';
      return `
        <div class="col">
          <div class="card h-100">
            <img class="card-img-top" src="${imageUrl}" alt="Image of ${route.title}" onerror="this.src='path/to/default/image.jpg';">
            <div class="card-body d-flex flex-column">
              <h5 class="card-title">${route.title || 'No Title'}</h5>
              <ul class="list-unstyled">
                <li><i class="fas fa-mountain"></i> ${route.elevation || 'N/A'} m</li>
                <li><i class="far fa-clock"></i> ${route.duration || 'N/A'} hours</li>
                <li><i class="fas fa-signal"></i> ${route.difficulty || 'N/A'}</li>
                <li><i class="fas fa-map-marker-alt"></i> ${route.location || 'N/A'}</li>
                <li><i class="fas fa-hiking"></i> ${route.tipo_ruta || 'N/A'}</li>
              </ul>
              <a href="ruta.html?id=${route.id}" class="btn btn-primary mt-auto">More Info</a>
            </div>
          </div>
        </div>
      `;
    }).join('');
  }

  // Update pagination
  function updatePagination() {
    const totalPages = Math.ceil(filteredRoutes.length / ITEMS_PER_PAGE);
    let paginationHTML = '';

    for (let i = 1; i <= totalPages; i++) {
      paginationHTML += `<button class="btn btn-secondary mx-1 mb-2" onclick="goToPage(${i})" ${i === currentPage ? 'disabled' : ''}>${i}</button>`;
    }

    pagination.innerHTML = paginationHTML;
  }

  // Go to a specific page
  window.goToPage = function(page) {
    currentPage = page;
    displayRoutes();
    updatePagination();
  }

  // Filter and display routes
  function filtrarYMostrarRutas(event) {
    event.preventDefault();
    
    const difficultyFilter = document.getElementById('filter-difficulty').value.toLowerCase();
    const minElevation = parseInt(document.getElementById('filter-min-elevation').value) || 0;
    const maxDuration = parseInt(document.getElementById('filter-max-duration').value) || Infinity;
    const activityFilters = Array.from(document.querySelectorAll('#activityFilter input:checked')).map(input => input.value);

    filteredRoutes = allRoutes.filter(ruta => {
      const meetsActivity = activityFilters.length === 0 || activityFilters.includes(ruta.tipo_ruta);
      const meetsDifficulty = difficultyFilter === 'all' || (ruta.difficulty && ruta.difficulty.toLowerCase() === difficultyFilter);
      const meetsElevation = !ruta.elevation || parseFloat(ruta.elevation) >= minElevation;
      const meetsDuration = maxDuration === Infinity || (ruta.duration && parseFloat(ruta.duration) <= maxDuration);

      return meetsActivity && meetsDifficulty && meetsElevation && meetsDuration;
    });

    currentPage = 1;
    displayRoutes();
    updatePagination();
  }

  // Event listener for the filter form
  filterForm.addEventListener('submit', filtrarYMostrarRutas);

  // Initialization
  loadRoutes();
});