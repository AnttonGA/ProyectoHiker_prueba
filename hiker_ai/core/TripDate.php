<?php
namespace KahirAI\Core;

/**
 * Resuelve la fecha de la salida a partir del mensaje ("este sábado", "mañana",
 * "el finde", "pasado mañana", "el 5 de diciembre"…) en zona horaria de Madrid.
 *
 * Devuelve ['dates' => ['Y-m-d', …], 'label' => 'sábado 28 de noviembre', 'days_ahead' => int]
 * o null si el mensaje no habla de ninguna fecha.
 */
final class TripDate {

    private const DAYS = ['domingo' => 0, 'lunes' => 1, 'martes' => 2, 'miercoles' => 3, 'jueves' => 4, 'viernes' => 5, 'sabado' => 6];
    private const DAYS_EN = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
    private const MONTHS = ['enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6, 'julio' => 7,
                            'agosto' => 8, 'septiembre' => 9, 'setiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12];
    private const DAY_NAMES = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    private const MONTH_NAMES = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    public static function resolve(string $query, ?\DateTimeImmutable $now = null): ?array {
        $now   = ($now ?? new \DateTimeImmutable('now', new \DateTimeZone('Europe/Madrid')))->setTime(0, 0);
        $q     = strtr(mb_strtolower($query, 'UTF-8'), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
        $dates = null;

        if (preg_match('/\bpasado\s+manana\b/u', $q)) {
            $dates = [$now->modify('+2 day')];
        } elseif (preg_match('/\bhoy\b|\besta\s+(tarde|manana|noche)\b|\btoday\b/u', $q)) {
            $dates = [$now];
        } elseif (preg_match('/\bmanana\b|\btomorrow\b/u', $q)) {
            $dates = [$now->modify('+1 day')];
        } elseif (preg_match('/\b(fin\s+de\s+semana|finde|weekend)\b/u', $q)) {
            // Fin de semana: sábado y domingo próximos (si hoy es domingo, solo hoy; si es sábado, hoy y mañana)
            $dow = (int) $now->format('w');
            if ($dow === 0) {
                $dates = [$now];
            } elseif ($dow === 6) {
                $dates = [$now, $now->modify('+1 day')];
            } else {
                $sat = $now->modify('+' . (6 - $dow) . ' day');
                $dates = [$sat, $sat->modify('+1 day')];
            }
            if (preg_match('/\b(proximo|que\s+viene|siguiente)\b/u', $q) && $dow === 0) {
                $sat = $now->modify('+6 day');
                $dates = [$sat, $sat->modify('+1 day')];
            }
        } elseif (preg_match('/\b(\d{1,2})\s+de\s+(' . implode('|', array_keys(self::MONTHS)) . ')\b/u', $q, $m)) {
            $month = self::MONTHS[$m[2]];
            $year  = (int) $now->format('Y');
            $d = $now->setDate($year, $month, (int) $m[1]);
            if ($d < $now) $d = $d->setDate($year + 1, $month, (int) $m[1]);
            $dates = [$d];
        } else {
            foreach (self::DAYS + self::DAYS_EN as $name => $dow) {
                if (preg_match('/\b' . $name . '\b/u', $q)) {
                    $diff = ($dow - (int) $now->format('w') + 7) % 7;
                    // "el sábado que viene" / "el próximo sábado" desde el propio sábado → dentro de una semana
                    if ($diff === 0 && preg_match('/\b(proximo|que\s+viene|siguiente|next)\b/u', $q)) $diff = 7;
                    $dates = [$now->modify("+{$diff} day")];
                    break;
                }
            }
        }

        if (!$dates) return null;
        $ymd = array_map(fn($d) => $d->format('Y-m-d'), $dates);
        return [
            'dates'      => $ymd,
            'label'      => implode(' y ', array_map([self::class, 'label'], $ymd)),
            'days_ahead' => (int) $now->diff($dates[count($dates) - 1])->days,
        ];
    }

    /** "2026-11-28" → "sábado 28 de noviembre" */
    public static function label(string $ymd): string {
        $d = new \DateTimeImmutable($ymd, new \DateTimeZone('Europe/Madrid'));
        return self::DAY_NAMES[(int) $d->format('w')] . ' ' . (int) $d->format('j') . ' de ' . self::MONTH_NAMES[(int) $d->format('n')];
    }
}
