<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kahir.AI - Tu Asistente de Montaña</title>
    
    <!-- Meta tags -->
    <meta name="description" content="Asistente inteligente para rutas de montaña y senderismo en los Pirineos">
    <meta name="keywords" content="senderismo, montaña, Pirineos, rutas, AI, asistente virtual">
    
    <!-- Preload -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    
    <!-- Estilos -->
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="landing/landing.css" rel="stylesheet">

    <!-- Scripts -->
    <script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
</head>
<body>
    <!-- Navbar -->
    <div id="menu"></div>

    <!-- Landing Page Root -->
    <div id="landing-root"></div>

    <!-- Footer -->
    <div id="footer"></div>

    <!-- Scripts -->
    <script src="landing/landing.js"></script>
    <script>
        // Inicializar el componente cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            const root = ReactDOM.createRoot(document.getElementById('landing-root'));
            root.render(React.createElement(LandingPage));

            // Detector de intersección para animaciones al hacer scroll
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('visible');
                    }
                });
            }, {
                threshold: 0.1
            });

            // Observar todas las secciones
            document.querySelectorAll('section').forEach(section => {
                observer.observe(section);
            });
        });
    </script>
</body>
</html>