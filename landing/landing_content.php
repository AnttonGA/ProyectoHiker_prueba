<?php
/**
 * Portada para visitantes sin sesión (la incluye index.php).
 * Solo cifras reales del catálogo: nada de usuarios, testimonios ni funciones que no existen.
 * Requiere $conn (mysqli) y e() / root_path() de includes/functions.php.
 */

// Rutas útiles por actividad (mismos criterios que el asistente: sin tramos minúsculos ni rutas OSM sin nombre)
$landingCounts = ['hiking' => 0, 'ski_touring' => 0, 'btt' => 0, 'cycle_touring' => 0];
$landingExample = null;
try {
    $res = $conn->query(
        "SELECT tipo_ruta, COUNT(*) AS n FROM rutas
          WHERE estado = 'publicada' AND distancia_km >= 1.5 AND titulo NOT LIKE 'Ruta OSM %'
          GROUP BY tipo_ruta"
    );
    foreach ($res->fetch_all(MYSQLI_ASSOC) as $row) {
        if (isset($landingCounts[$row['tipo_ruta']])) $landingCounts[$row['tipo_ruta']] = (int) $row['n'];
    }
    // Ruta real para la conversación de ejemplo
    $st = $conn->prepare(
        "SELECT id, titulo, distancia_km, elevacion_ganada, horas_mide, esfuerzo, start_lat, start_lng
           FROM rutas WHERE estado = 'publicada' AND titulo LIKE ? LIMIT 1"
    );
    $like = 'Tramacastilla de Tena%';
    $st->bind_param('s', $like);
    $st->execute();
    $landingExample = $st->get_result()->fetch_assoc() ?: null;
} catch (\Throwable $e) {
    error_log('landing: ' . $e->getMessage());
}
$landingTotal = array_sum($landingCounts);
// Distancia real del inicio de la ruta de ejemplo a Panticosa (42.72, -0.28)
$landingKm = null;
if ($landingExample && $landingExample['start_lat'] !== null) {
    $dLat = deg2rad((float) $landingExample['start_lat'] - 42.72);
    $dLon = deg2rad((float) $landingExample['start_lng'] + 0.28);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad(42.72)) * cos(deg2rad((float) $landingExample['start_lat'])) * sin($dLon / 2) ** 2;
    $landingKm = (int) round(12742 * asin(min(1, sqrt($a))));
}
$fmt = fn($n) => number_format((int) $n, 0, ',', '.');
$fmtDec = fn($n) => number_format((float) $n, 1, ',', '.');
$hours = function ($h) {
    $h = (float) $h; $hh = (int) floor($h); $mm = (int) round(($h - $hh) * 60);
    return $mm ? "{$hh} h {$mm} min" : "{$hh} h";
};
$signup = root_path('paginasespecializadas/inicio_sesion/gestion.php');
$routes = root_path('paginasespecializadas/skitouring/skitouring.html');
$huts   = root_path('paginasespecializadas/huts/huts.html');
?>
<main class="kl">

  <!-- ── Portada ─────────────────────────────────────────────────────────── -->
  <section class="kl-hero">
    <img class="kl-hero-photo" src="<?= e(root_path('imagenes/GR_route.jpg')) ?>" alt="" aria-hidden="true" fetchpriority="high">
    <div class="kl-hero-shade" aria-hidden="true"></div>
    <div class="container kl-hero-inner">
      <div class="kl-hero-copy">
        <p class="k-eyebrow kl-eyebrow-light"><i class="bi bi-compass" aria-hidden="true"></i> Asistente de montaña</p>
        <h1>Tu próxima salida, bien pensada.</h1>
        <p class="kl-lead">
          Pregunta como hablarías con alguien que conoce el monte. Kahir busca rutas reales cerca de donde quieres ir,
          mira el tiempo del día de tu salida y te dice con claridad lo que no sabe.
        </p>
        <div class="kl-cta">
          <a class="btn btn-primary btn-lg" href="<?= e($signup) ?>">Empezar a planear <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
          <a class="btn btn-lg kl-btn-ghost" href="<?= e($routes) ?>">Ver el mapa de rutas</a>
        </div>
      </div>

      <!-- Conversación de ejemplo (con una ruta real del catálogo) -->
      <figure class="kl-demo" aria-label="Ejemplo de conversación con Kahir">
        <figcaption class="kl-demo-tag">Ejemplo</figcaption>
        <div class="kl-msg kl-msg-user">Busco una ruta fácil por Panticosa para el sábado</div>
        <div class="kl-msg kl-msg-bot">
          <img src="<?= e(root_path('assets/img/kahir-mark.svg')) ?>" alt="" width="28" height="28" class="kl-msg-avatar">
          <div>
            <p>Te miro la previsión del sábado en el valle y te propongo esta, corta y con poco desnivel:</p>
            <?php if ($landingExample): ?>
              <a class="kl-route" href="<?= e(root_path('paginasespecializadas/skitouring/ruta.html?id=' . (int) $landingExample['id'])) ?>">
                <span class="kl-route-title"><?= e(str_replace('_', ' ', $landingExample['titulo'])) ?></span>
                <span class="kl-route-chips">
                  <span class="k-chip"><i class="bi bi-signpost" aria-hidden="true"></i> <?= e($fmtDec($landingExample['distancia_km'])) ?> km</span>
                  <span class="k-chip"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> +<?= e($fmt($landingExample['elevacion_ganada'])) ?> m</span>
                  <?php if (!empty($landingExample['horas_mide'])): ?>
                    <span class="k-chip"><i class="bi bi-clock" aria-hidden="true"></i> <?= e($hours($landingExample['horas_mide'])) ?></span>
                  <?php endif; ?>
                  <?php if (!empty($landingExample['esfuerzo'])): ?>
                    <span class="k-chip k-chip-moss">Esfuerzo <?= (int) $landingExample['esfuerzo'] ?>/5</span>
                  <?php endif; ?>
                </span>
                <?php if ($landingKm !== null): ?>
                  <span class="kl-route-why"><i class="bi bi-check2" aria-hidden="true"></i> <?= $landingKm < 3 ? 'Sale desde Panticosa' : 'Empieza a ' . $landingKm . ' km de Panticosa' ?></span>
                <?php endif; ?>
              </a>
            <?php endif; ?>
          </div>
        </div>
      </figure>
    </div>
  </section>

  <!-- ── Cifras reales ───────────────────────────────────────────────────── -->
  <section class="kl-facts" aria-label="El catálogo en cifras">
    <div class="container">
      <dl class="kl-facts-grid">
        <div><dt><?= e($fmt($landingTotal)) ?></dt><dd>rutas con traza GPX</dd></div>
        <div><dt>16 días</dt><dd>de previsión meteorológica</dd></div>
        <div><dt>MIDE</dt><dd>horas y esfuerzo de cada ruta</dd></div>
        <div><dt>Oficial</dt><dd>boletín de aludes de AEMET y Météo-France</dd></div>
      </dl>
    </div>
  </section>

  <!-- ── Qué hace ────────────────────────────────────────────────────────── -->
  <section class="kl-section" id="como-ayuda">
    <div class="container">
      <div class="kl-section-head">
        <p class="k-eyebrow">Qué hace Kahir</p>
        <h2>Menos pestañas abiertas antes de salir</h2>
      </div>
      <div class="row g-4">
        <div class="col-md-4">
          <article class="kl-feature">
            <span class="kl-feature-icon"><i class="bi bi-geo-alt" aria-hidden="true"></i></span>
            <h3>Rutas cerca de donde vas</h3>
            <p>Busca por distancia real al punto de inicio, dentro de una región o a tu medida: “unas 6 horas”,
               “menos de 500 metros de desnivel”, “algo fácil para ir con niños”.</p>
          </article>
        </div>
        <div class="col-md-4">
          <article class="kl-feature">
            <span class="kl-feature-icon kl-feature-icon-lake"><i class="bi bi-cloud-sun" aria-hidden="true"></i></span>
            <h3>El tiempo del día que sales</h3>
            <p>Si dices “el sábado” o “este finde”, mira la previsión de esos días en esa zona y te dice cuál es mejor,
               avisando cuando a tantos días vista solo es orientativa.</p>
          </article>
        </div>
        <div class="col-md-4">
          <article class="kl-feature">
            <span class="kl-feature-icon kl-feature-icon-rust"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
            <h3>Honesto con la seguridad</h3>
            <p>El peligro de aludes solo lo da el boletín oficial. Si no está disponible, lo dice. Nunca se inventa una ruta,
               una cifra ni un acceso.</p>
          </article>
        </div>
      </div>
    </div>
  </section>

  <!-- ── Actividades ─────────────────────────────────────────────────────── -->
  <section class="kl-section kl-section-alt">
    <div class="container">
      <div class="kl-section-head">
        <p class="k-eyebrow">El catálogo</p>
        <h2>Pirineos, País Vasco y más allá</h2>
        <p class="kl-section-lead">Rutas de España, Andorra y el sur de Francia, con su traza, desnivel y dificultad.</p>
      </div>
      <div class="row g-4">
        <div class="col-md-4">
          <a class="kl-tile" href="<?= e($routes) ?>">
            <img src="<?= e(root_path('imagenes/hiking.jpg')) ?>" alt="Dos senderistas cruzan un puente bajo una pared del Pirineo" loading="lazy">
            <span class="kl-tile-body">
              <span class="kl-tile-title">Senderismo</span>
              <span class="kl-tile-count"><?= e($fmt($landingCounts['hiking'])) ?> rutas</span>
            </span>
          </a>
        </div>
        <div class="col-md-4">
          <a class="kl-tile" href="<?= e($routes) ?>">
            <img src="<?= e(root_path('imagenes/cycletouring.jpg')) ?>" alt="Ciclista por una carretera de montaña" loading="lazy">
            <span class="kl-tile-body">
              <span class="kl-tile-title">BTT y cicloturismo</span>
              <span class="kl-tile-count"><?= e($fmt($landingCounts['btt'] + $landingCounts['cycle_touring'])) ?> rutas</span>
            </span>
          </a>
        </div>
        <div class="col-md-4">
          <a class="kl-tile" href="<?= e($routes) ?>">
            <img src="<?= e(root_path('imagenes/skitouring.jpg')) ?>" alt="Esquiador de travesía en una ladera nevada" loading="lazy">
            <span class="kl-tile-body">
              <span class="kl-tile-title">Esquí de travesía</span>
              <span class="kl-tile-count"><?= e($fmt($landingCounts['ski_touring'])) ?> rutas</span>
            </span>
          </a>
        </div>
      </div>
      <p class="kl-more"><a href="<?= e($huts) ?>"><i class="bi bi-house" aria-hidden="true"></i> También refugios, en el mapa</a></p>
    </div>
  </section>

  <!-- ── Cómo funciona ───────────────────────────────────────────────────── -->
  <section class="kl-section">
    <div class="container">
      <div class="kl-section-head">
        <p class="k-eyebrow">Cómo funciona</p>
        <h2>Tres pasos y al monte</h2>
      </div>
      <ol class="kl-steps">
        <li>
          <span class="kl-step-n">1</span>
          <h3>Crea tu cuenta</h3>
          <p>Dinos qué actividades haces y tu nivel. Con eso ya te propone rutas a tu medida.</p>
        </li>
        <li>
          <span class="kl-step-n">2</span>
          <h3>Pregunta</h3>
          <p>Dónde, cuándo y cómo de largo. Puedes afinar: “¿y algo más corto?”, “compara las dos primeras”.</p>
        </li>
        <li>
          <span class="kl-step-n">3</span>
          <h3>Guarda y valora</h3>
          <p>Apunta la salida en tus listas y, al volver, valora el esfuerzo: las siguientes propuestas se ajustan a ti.</p>
        </li>
      </ol>
    </div>
  </section>

  <!-- ── Preguntas ───────────────────────────────────────────────────────── -->
  <section class="kl-section kl-section-alt" id="preguntas">
    <div class="container kl-narrow">
      <div class="kl-section-head">
        <p class="k-eyebrow">Preguntas</p>
        <h2>Antes de empezar</h2>
      </div>
      <div class="kl-faq">
        <details>
          <summary>¿De dónde salen las rutas?</summary>
          <p>Del catálogo de Kahir: rutas importadas de OpenStreetMap y rutas que suben los usuarios, todas con su traza GPX.
             Las horas y el esfuerzo se calculan con el método MIDE a partir de la distancia y el desnivel.</p>
        </details>
        <details>
          <summary>¿Qué zonas cubre?</summary>
          <p>España, Andorra y el sur de Francia, sobre todo Pirineos y País Vasco. Si preguntas por una zona fuera
             (los Alpes, por ejemplo), te lo dirá en vez de inventar rutas.</p>
        </details>
        <details>
          <summary>¿Sustituye al parte meteorológico o al boletín de aludes?</summary>
          <p>No. Kahir te ayuda a planear, pero antes de salir revisa siempre el parte y el boletín oficial,
             y decide sobre el terreno.</p>
        </details>
        <details>
          <summary>¿Qué nivel necesito?</summary>
          <p>Ninguno en concreto: desde paseos para ir con niños hasta jornadas largas de alta montaña.
             Las propuestas se ajustan a tu nivel y a cómo valoras las rutas que haces.</p>
        </details>
      </div>
    </div>
  </section>

  <!-- ── Llamada final ───────────────────────────────────────────────────── -->
  <section class="kl-final">
    <div class="container">
      <img src="<?= e(root_path('assets/img/kahir-mark.svg')) ?>" alt="" width="48" height="48" class="kl-final-mark">
      <h2>¿Adónde vas este fin de semana?</h2>
      <p>Crea tu cuenta y pregúntaselo a Kahir.</p>
      <a class="btn btn-lg kl-btn-light" href="<?= e($signup) ?>">Empezar a planear <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </div>
  </section>
</main>
