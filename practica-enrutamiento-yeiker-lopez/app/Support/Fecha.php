<?php

namespace App\Support;

use Carbon\CarbonInterface;

final class Fecha
{
    /** Fecha en UTC con milisegundos, formato ISO 8601: "2026-11-18T22:15:03.412Z". */
    public static function iso(?CarbonInterface $fecha): ?string
    {
        return $fecha?->copy()->utc()->format('Y-m-d\TH:i:s.v\Z');
    }
}
