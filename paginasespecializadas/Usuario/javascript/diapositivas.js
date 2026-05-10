document.addEventListener("DOMContentLoaded", function() {
    const isFirstVisit = !localStorage.getItem('hasVisitedBefore');

    fetch('javascript/contenidos.json')
        .then(response => response.json())
        .then(data => {
            let diapositivasAMostrar;

            if (isFirstVisit) {
                // En la primera visita, mostrar las primeras 4 diapositivas
                diapositivasAMostrar = data.slice(0, 6);
                localStorage.setItem('hasVisitedBefore', 'true');
            } else {
                // En visitas posteriores, seleccionar 4 diapositivas aleatorias
                diapositivasAMostrar = data.sort(() => 0.5 - Math.random()).slice(0, 4);
            }

            let carouselInner = document.getElementById('carouselSlides');
            let isActive = true; // Para marcar la primera diapositiva como activa

            diapositivasAMostrar.forEach(funcionalidad => {
                let slide = document.createElement('div');
                slide.classList.add('carousel-item');
                if (isActive) {
                    slide.classList.add('active');
                    isActive = false;
                }

                slide.innerHTML = `
                    <div class="content" style="background-color: ${funcionalidad.colorFondo};">
                        <h2 style="font-family: ${funcionalidad.tipografia};">${funcionalidad.titulo}</h2>
                        <p>${funcionalidad.descripcion}</p>
                        <a href="${funcionalidad.enlace}" class="btn btn-light">Ver más</a>
                    </div>
                `;

                carouselInner.appendChild(slide);
            });

            // Inicializar el carrusel
            new bootstrap.Carousel(document.getElementById('funcionalidadesCarrusel'), {
                interval: 4000, // Cambia de diapositiva cada 5 segundos
                pause: 'hover' // Pausa el carrusel al pasar el mouse por encima
            });
        });

    // Lógica para cerrar la presentación
    document.getElementById('cerrarPresentacion').addEventListener('click', function() {
        document.getElementById('presentacionContainer').style.display = 'none';
    });

    // Mostrar la presentación cada vez que se carga la página
    document.getElementById('presentacionContainer').style.display = 'block';
});