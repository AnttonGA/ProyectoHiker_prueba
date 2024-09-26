document.addEventListener('DOMContentLoaded', function() {
    const submenuToggle = document.getElementById('submenuToggle');
    const advancedOptions = document.getElementById('advancedOptions');

    submenuToggle.addEventListener('click', function() {
        // Mostrar u ocultar el submenú con animación
        if (advancedOptions.classList.contains('d-none')) {
            advancedOptions.classList.remove('d-none');
            advancedOptions.classList.add('show');
            submenuToggle.textContent = 'Ocultar opciones';
        } else {
            advancedOptions.classList.add('d-none');
            advancedOptions.classList.remove('show');
            submenuToggle.textContent = 'Opciones avanzadas';
        }
    });
});
