document.addEventListener('DOMContentLoaded', function() {
    const submenuToggle = document.getElementById('submenuToggle');
    const advancedOptions = document.getElementById('advancedOptions');

    submenuToggle.addEventListener('click', function(e) {
        e.preventDefault();
        advancedOptions.classList.toggle('d-none');
        advancedOptions.classList.toggle('show');
        
        // Asegurarse de que el botón siempre muestre el icono
        submenuToggle.innerHTML = '<i class="fas fa-cog"></i>';
    });

    // Cerrar el menú si se hace clic fuera de él
    document.addEventListener('click', function(e) {
        if (!submenuToggle.contains(e.target) && !advancedOptions.contains(e.target)) {
            advancedOptions.classList.add('d-none');
            advancedOptions.classList.remove('show');
        }
    });
});