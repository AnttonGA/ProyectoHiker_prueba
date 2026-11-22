<?php
namespace KahirAI\Core;

/**
 * Resuelve la fecha de la salida a partir del mensaje ("este sábado", "mañana",
 * "el finde", "pasado mañana", "el 5 de diciembre", "entre el sábado y el domingo"…)
 * en zona horaria de Madrid.
 *
 * Devuelve ['dates' => ['Y-m-d', …], 'label' => 'sábado 28 de noviembre', 'days_ahead' => int]
 * o null si el mensaje no habla de ninguna fecha (o la fecha no existe, p. ej. "31 de febrero").
 */
final class TripDate {

    private const DAYS = ['domingo' => 0, 'lunes' => 1, 'martes' => 2, 'miercoles' => 3, 'jueves' => 4, 'viernes' => 5, 'sabado' => 6,
                          'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
    private const MONTHS = ['enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6, 'julio' => 7,
                            'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12];
    private const DAY_NAMES = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    private const MONTH_NAMES = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    public static function resolve(string $query, ?\DateTimeImmutable $now = null): ?array {
        $now = ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))->setTime(0, 0);
        $q   = strtr(mb_strtolower($query, 'UTF-8'), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        // "esta mañana" es hoy; "por/de/a/en la mañana" y "las mañanas" son una franja del día,
        // no "mañana" (el día siguiente). Se neutralizan antes de buscar el día.
        $today = (bool) preg_match('/\bhoy\b|\besta\s+(tarde|manana|noche)\b|\btoday\b|\bthis\s+(morning|afternoon|evening)\b|\btonight\b/u', $q);
        $q = preg_replace('/\b(esta|por\s+la|de\s+la|a\s+la|en\s+la|las|una)\s+mananas?\b/u', ' ', $q);

        $dates = null;
        if (preg_match('/\bpasado\s+manana\b/u', $q)) {
            $dates = [$now->modify('+2 day')];
        } elseif (preg_match('/\bmanana\b|\btomorrow\b/u', $q)) {
            $dates = [$now->modify('+1 day')];
        } elseif ($today) {
            $dates = [$now];
        } elseif (preg_match('/\b(fin\s+de\s+semana|finde|weekend)\b/u', $q)) {
            // Fin de semana: sábado y domingo próximos (si hoy es sábado, hoy y mañana; si es domingo, hoy,
            // salvo "el próximo / que viene", que es el siguiente)
            $dow = (int) $now->format('w');
            $next = (bool) preg_match('/\b(proximo|que\s+viene|siguiente|next)\b/u', $q);
            if ($dow === 0 && !$next) {
                $dates = [$now];
            } elseif ($dow === 6 && !$next) {
                $dates = [$now, $now->modify('+1 day')];
            } else {
                $sat = $now->modify('+' . (((6 - $dow + 7) % 7) ?: 7) . ' day');
                $dates = [$sat, $sat->modify('+1 day')];
            }
        } elseif (preg_match('/\b(\d{1,2})\s+de\s+(' . implode('|', array_keys(self::MONTHS)) . ')\b/u', $q, $m)) {
            $day   = (int) $m[1];
            $month = self::MONTHS[$m[2]];
            $year  = (int) $now->format('Y');
            if (!checkdate($month, $day, $year)) {
                // 29 de febrero puede existir el año que viene; cualquier otra fecha imposible se descarta
                if (!checkdate($month, $day, $year + 1)) return null;
                $year++;
            }
            $d = $now->setDate($year, $month, $day);
            if ($d < $now) {
                if (!checkdate($month, $day, $year + 1)) return null;
                $d = $d->setDate($year + 1, $month, $day);
            }
            $dates = [$d];
        } else {
            // Días de la semana en el orden en que aparecen; cada uno a partir del anterior
            // ("entre el sábado y el domingo" → sábado y el domingo siguiente)
            preg_match_all('/\b(' . implode('|', array_keys(self::DAYS)) . ')\b/u', $q, $mm, PREG_OFFSET_CAPTURE);
            $next = (bool) preg_match('/\b(proximo|que\s+viene|siguiente|next)\b/u', $q);
            $prev = $now;
            foreach ($mm[1] as $i => [$name, $_pos]) {
                $dow  = self::DAYS[$name];
                $diff = ($dow - (int) $prev->format('w') + 7) % 7;
                // "el sábado que viene" dicho un sábado → dentro de una semana
                if ($i === 0 && $diff === 0 && $next) $diff = 7;
                $d = $prev->modify("+{$diff} day");
                $dates[] = $d;
                $prev = $d;
            }
        }

        if (!$dates) return null;
        $ymd = array_values(array_unique(array_map(fn($d) => $d->format('Y-m-d'), $dates)));
        sort($ymd);
        $last = new \DateTimeImmutable(end($ymd), new \DateTimeZone('Europe/Madrid'));
        return [
            'dates'      => $ymd,
            'label'      => implode(' y ', array_map([self::class, 'label'], $ymd)),
            'days_ahead' => (int) $now->diff($last)->days,
        ];
    }

    /** "2026-11-28" → "sábado 28 de noviembre" */
    public static function label(string $ymd): string {
        $d = new \DateTimeImmutable($ymd, new \DateTimeZone('Europe/Madrid'));
        return self::DAY_NAMES[(int) $d->format('w')] . ' ' . (int) $d->format('j') . ' de ' . self::MONTH_NAMES[(int) $d->format('n')];
    }
}
