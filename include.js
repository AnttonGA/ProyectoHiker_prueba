document.addEventListener("DOMContentLoaded", function() {

    function loadHTML(elementId, file) {
        console.log(`Loading ${file} into #${elementId}`);
        const xhttp = new XMLHttpRequest();
        xhttp.onreadystatechange = function() {
            if (this.readyState === 4) {
                if (this.status === 200) {
                    document.getElementById(elementId).innerHTML = this.responseText;
                } else {
                    console.error(`Failed to load ${file}: ${this.status} - ${this.statusText}`);
                }
            }
        };
        xhttp.open("GET", file, true);
        xhttp.send();
    }

    // Determinar la ruta base dependiendo del archivo HTML actual para el servidor 2 para el local 4
    const pathArray = window.location.pathname.split('/');
    let basePath = '';
    for (let i = 0; i < pathArray.length - 4; i++) {
        basePath += '../';
    }
    basePath += 'includes/';

    console.log(`Base path: ${basePath}`);
    loadHTML("menu", basePath + "menu.php");
    loadHTML("footer", basePath + "footer.html");

});


