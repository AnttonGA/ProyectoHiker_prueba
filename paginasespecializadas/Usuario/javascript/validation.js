/**
 * validation.js — Validación y auto-detección GPX para subir_ruta.html
 *
 * Paso 1: el usuario sube el GPX → se parsea en el cliente → se auto-rellena la zona
 *         y se muestra un preview de distancia / desnivel / duración estimada.
 * Paso 2: detalles de la ruta (nombre, tipo, dificultad, zona editable, descripción,
 *         notas opcionales). Se valida al pasar al paso 3 y otra vez al enviar.
 * Paso 3: fotos opcionales y publicar.
 */

document.addEventListener('DOMContentLoaded', function () {

    // ── Elementos ─────────────────────────────────────────────────────────────
    const form        = document.getElementById('formularioRuta');
    const dropZone    = document.getElementById('gpxDropZone');
    const fileInput   = document.getElementById('archivo_gpx');
    const nextBtn     = document.getElementById('nextStep1');
    const prevBtn     = document.getElementById('prevStep1');
    const nextBtn2    = document.getElementById('nextStep2');
    const prevBtn2    = document.getElementById('prevStep2');
    const photoInput  = document.getElementById('imagenes');
    const photoLabel  = document.getElementById('photoDropLabel');
    const photoDrop   = document.getElementById('photoDrop');
    const PHOTO_LABEL = photoLabel ? photoLabel.textContent : '';
    const gpxPreview  = document.getElementById('gpxPreview');
    const gpxError    = document.getElementById('gpx-error');

    // ── Navegación entre pasos ────────────────────────────────────────────────
    nextBtn.addEventListener('click', function () {
        if (!fileInput.files.length) {
            showGpxError('Selecciona un archivo GPX antes de continuar.');
            return;
        }
        goToStep(2);
    });

    prevBtn.addEventListener('click', () => goToStep(1));

    if (nextBtn2) {
        nextBtn2.addEventListener('click', function () {
            if (validateStep2()) goToStep(3);
        });
    }
    if (prevBtn2) prevBtn2.addEventListener('click', () => goToStep(2));

    function goToStep(n) {
        [1, 2, 3].forEach(function (i) {
            const panel = document.getElementById('step' + i);
            const ind   = document.getElementById('ind-step' + i);
            if (panel) panel.style.display = n === i ? 'block' : 'none';
            if (ind) {
                ind.className = 'step' + (i === n ? ' active' : (i < n ? ' done' : ''));
                if (i === n) ind.setAttribute('aria-current', 'step');
                else ind.removeAttribute('aria-current');
            }
        });
        const title = document.getElementById('step' + n + '-title');
        if (title) {
            title.setAttribute('tabindex', '-1');
            title.focus({ preventScroll: true });
        }
        const indicator = document.querySelector('.step-indicator');
        if (indicator && indicator.getBoundingClientRect().top < 0) {
            indicator.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    // ── Fotos (paso 3): mostrar cuántas se han elegido ────────────────────────
    if (photoInput && photoLabel) {
        photoInput.addEventListener('change', function () {
            const n = photoInput.files.length;
            photoLabel.textContent = n === 0 ? PHOTO_LABEL
                : (n === 1 ? photoInput.files[0].name : n + ' fotos seleccionadas');
            if (photoDrop) photoDrop.classList.toggle('has-file', n > 0);
        });
        if (photoDrop) {
            photoInput.addEventListener('dragenter', () => photoDrop.classList.add('drag-over'));
            photoInput.addEventListener('dragleave', () => photoDrop.classList.remove('drag-over'));
            photoInput.addEventListener('drop', () => photoDrop.classList.remove('drag-over'));
        }
    }

    // ── Zona de drop / clic ───────────────────────────────────────────────────
    dropZone.addEventListener('click', () => fileInput.click());
    dropZone.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            fileInput.click();
        }
    });

    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        // Sin esto el archivo soltado no llega al <input> ni al POST
        try { fileInput.files = e.dataTransfer.files; } catch (err) { /* navegador antiguo */ }
        if (file) handleGPXFile(file);
    });

    fileInput.addEventListener('change', () => {
        if (fileInput.files[0]) handleGPXFile(fileInput.files[0]);
    });

    // ── Procesar archivo GPX seleccionado ─────────────────────────────────────
    function handleGPXFile(file) {
        hideGpxError();

        // Validar extensión
        if (!file.name.toLowerCase().endsWith('.gpx')) {
            showGpxError('El archivo debe tener extensión .gpx. Elige otro archivo.');
            resetGpxUI();
            return;
        }
        // Validar tamaño (10 MB)
        if (file.size > 10 * 1024 * 1024) {
            showGpxError('El archivo supera los 10 MB permitidos. Elige uno más ligero.');
            resetGpxUI();
            return;
        }

        // Leer y parsear
        const reader = new FileReader();
        reader.onload = function (e) {
            try {
                const result = parseGPX(e.target.result);
                if (result.error) {
                    showGpxError(result.error);
                    resetGpxUI();
                    return;
                }
                applyGPXResult(file.name, result);
            } catch (err) {
                showGpxError('No se pudo leer el GPX: ' + err.message);
                resetGpxUI();
            }
        };
        reader.readAsText(file);
    }

    function applyGPXResult(filename, result) {
        // Actualizar zona de drop
        dropZone.classList.add('has-file');
        dropZone.querySelector('.gpx-icon').innerHTML = '<i class="bi bi-check-circle-fill"></i>';
        document.getElementById('gpxDropLabel').textContent = filename;

        // Mostrar preview de stats
        document.getElementById('gpxFileName').textContent = filename;
        document.getElementById('statDist').textContent = fmtNum(result.distance, 1);
        document.getElementById('statElev').textContent = fmtNum(result.elevation, 0);
        document.getElementById('statDur').textContent  = fmtNum(result.duration, 1);
        document.getElementById('statZone').textContent = locationLabel(result.location);
        gpxPreview.style.display = 'block';

        // Auto-seleccionar zona en el paso 2
        const sel = document.getElementById('location_ruta');
        if (result.location) {
            sel.value = result.location;
            document.getElementById('locationHint').innerHTML =
                '<i class="bi bi-magic me-1" aria-hidden="true"></i>' +
                'Zona detectada por las coordenadas del GPX. Puedes cambiarla si lo necesitas.';
        }

        // Habilitar botón Siguiente
        nextBtn.disabled = false;
    }

    function resetGpxUI() {
        dropZone.classList.remove('has-file');
        dropZone.querySelector('.gpx-icon').innerHTML = '<i class="bi bi-signpost-split"></i>';
        document.getElementById('gpxDropLabel').textContent =
            'Arrastra aquí tu archivo GPX o haz clic para seleccionarlo';
        gpxPreview.style.display = 'none';
        nextBtn.disabled = true;
    }

    // ── Parser GPX (cliente) ──────────────────────────────────────────────────
    function parseGPX(xmlText) {
        const parser = new DOMParser();
        const xml    = parser.parseFromString(xmlText, 'text/xml');

        // Comprobar errores de parse
        const parseErr = xml.querySelector('parsererror');
        if (parseErr) return { error: 'El archivo no es un XML válido. Comprueba que sea un GPX.' };

        const root = xml.documentElement;
        if (root.tagName.toLowerCase() !== 'gpx') {
            return { error: 'El archivo no tiene estructura GPX (elemento raíz: ' + root.tagName + ').' };
        }

        // Recoger puntos: trkpt > rtept > wpt
        let pts = Array.from(xml.querySelectorAll('trkpt'));
        if (pts.length === 0) pts = Array.from(xml.querySelectorAll('rtept'));
        if (pts.length === 0) pts = Array.from(xml.querySelectorAll('wpt'));

        if (pts.length < 2) {
            return { error: 'El GPX debe contener al menos 2 puntos de track.' };
        }

        // Punto de inicio → zona
        const startLat = parseFloat(pts[0].getAttribute('lat') || '0');
        const startLon = parseFloat(pts[0].getAttribute('lon') || '0');
        const location = lonToZone(startLon);

        // Calcular distancia y desnivel
        let totalDist = 0, totalAscent = 0;
        let prev = null;

        for (const pt of pts) {
            const lat = parseFloat(pt.getAttribute('lat') || '0');
            const lon = parseFloat(pt.getAttribute('lon') || '0');
            const eleEl = pt.querySelector('ele');
            const ele   = eleEl ? parseFloat(eleEl.textContent) : null;

            if (prev) {
                totalDist += haversine(prev.lat, prev.lon, lat, lon);
                if (ele !== null && prev.ele !== null) {
                    const diff = ele - prev.ele;
                    if (diff > 0) totalAscent += diff;
                }
            }
            prev = { lat, lon, ele };
        }

        // Duración estimada (Naismith: 5 km/h + 1 h por cada 600 m de desnivel)
        const durationH = totalDist / 5 + totalAscent / 600;

        return {
            distance:  Math.round(totalDist  * 10) / 10,
            elevation: Math.round(totalAscent),
            duration:  Math.round(durationH  * 10) / 10,
            location:  location,
            points:    pts.length,
        };
    }

    /** Fórmula haversine → distancia en km */
    function haversine(lat1, lon1, lat2, lon2) {
        const R    = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a    = Math.sin(dLat / 2) ** 2
                   + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
                   * Math.sin(dLon / 2) ** 2;
        return R * 2 * Math.asin(Math.sqrt(a)) * 1.02; // ×1.02 para irregularidades
    }

    /**
     * Detecta la zona del Pirineo según la longitud del punto de inicio.
     *   Pirineo Occidental  → lon < 0.0°  (País Vasco, Navarra, Huesca oeste)
     *   Pirineo Central     → 0.0°–2.0°   (Huesca centro, Lleida oeste)
     *   Pirineo Oriental    → lon > 2.0°  (Lleida este, Girona, Andorra)
     */
    function lonToZone(lon) {
        if (lon < 0.0)  return 'Western_Pyrenees';
        if (lon < 2.0)  return 'Central_Pyrenees';
        return 'Eastern_Pyrenees';
    }

    function locationLabel(zone) {
        const labels = {
            Western_Pyrenees: 'Pirineo occidental',
            Central_Pyrenees: 'Pirineo central',
            Eastern_Pyrenees: 'Pirineo oriental',
            Other:            'Otras zonas',
        };
        return labels[zone] || zone || '—';
    }

    /** Número en formato español para mostrar (no se envía al servidor) */
    function fmtNum(v, dec) {
        const x = Number(v);
        if (v === null || v === undefined || !Number.isFinite(x)) return '—';
        return x.toLocaleString('es-ES', { maximumFractionDigits: dec });
    }

    // ── Error GPX ─────────────────────────────────────────────────────────────
    function showGpxError(msg) {
        gpxError.textContent = msg;
        gpxError.style.removeProperty('display');
        fileInput.classList.add('is-invalid');
    }
    function hideGpxError() {
        gpxError.textContent = '';
        gpxError.style.setProperty('display', 'none', 'important');
        fileInput.classList.remove('is-invalid');
    }

    // ── Validación Paso 2 al enviar ───────────────────────────────────────────
    form.addEventListener('submit', function (event) {
        if (!validateStep2()) {
            event.preventDefault();
            goToStep(2);   // los errores están en el paso 2
        }
    });

    function validateStep2() {
        let ok = true;

        // Nombre
        const nombre = document.getElementById('nombre').value.trim();
        if (!nombre) {
            showFieldError('nombre', 'El nombre de la ruta es obligatorio.');
            ok = false;
        } else {
            hideFieldError('nombre');
        }

        // Tipo de ruta
        const tipo = document.getElementById('tipo_ruta').value;
        if (!tipo) {
            showFieldError('tipo_ruta', 'Selecciona el tipo de actividad.');
            ok = false;
        } else {
            hideFieldError('tipo_ruta');
        }

        // Dificultad
        const dif = document.getElementById('dificultad').value;
        if (!dif) {
            showFieldError('dificultad', 'Selecciona la dificultad.');
            ok = false;
        } else {
            hideFieldError('dificultad');
        }

        // Zona
        const loc = document.getElementById('location_ruta').value;
        if (!loc) {
            showFieldError('location_ruta', 'Selecciona o confirma la zona.');
            ok = false;
        } else {
            hideFieldError('location_ruta');
        }

        // Descripción
        const desc = document.getElementById('descripcion').value.trim();
        if (!desc) {
            showFieldError('descripcion', 'Añade una descripción de la ruta.');
            ok = false;
        } else {
            hideFieldError('descripcion');
        }

        return ok;
    }

    // ── Helpers de error de campo ─────────────────────────────────────────────
    function showFieldError(fieldId, msg) {
        const field = document.getElementById(fieldId);
        const fb    = field.nextElementSibling;
        if (fb) { fb.textContent = msg; fb.style.display = 'block'; }
        field.classList.add('is-invalid');
        setTimeout(() => hideFieldError(fieldId), 4000);
    }

    function hideFieldError(fieldId) {
        const field = document.getElementById(fieldId);
        const fb    = field.nextElementSibling;
        if (fb) { fb.textContent = ''; fb.style.display = 'none'; }
        field.classList.remove('is-invalid');
    }

});
