<?php

namespace App\Support;

use App\Services\ParametreService;
use Illuminate\Support\Str;

class Fmt
{
    private static function compta(): array
    {
        try {
            return app(ParametreService::class)->get('compta.general');
        } catch (\Throwable) {
            return ParametreService::DEFAUTS['compta.general']; // base indisponible : valeurs par défaut
        }
    }

    /** Montant dans la devise paramétrée (Comptabilité › Général), ex. « 1 250 000 Ar ». */
    public static function ar(null|int|float|string $v): string
    {
        $c = self::compta();

        return number_format((float) $v, (int) $c['decimales'], ',', ' ') . ' ' . $c['devise_symbole'];
    }

    /** Neutralise l'injection de formules dans les exports CSV/Excel. */
    public static function csv(mixed $v): string
    {
        $s = (string) $v;

        return $s !== '' && str_contains('=+-@', $s[0]) ? "'" . $s : $s;
    }

    /** Numéro de pièce : préfixe paramétrable + date + suffixe aléatoire. */
    public static function reference(string $prefix): string
    {
        $prefixe = self::compta()['prefixes'][$prefix] ?? $prefix;

        return $prefixe . '-' . now()->format('ymd') . '-' . strtoupper(Str::random(5));
    }
}
