<?php

namespace App\Support;

use Carbon\Carbon;
use Throwable;

/**
 * Conversões dos campos que a Sponte devolve no XML.
 *
 * Elemento vazio no XML vira array vazio depois do json_encode do SimpleXML,
 * por isso todo campo escalar passa por aqui.
 */
class SponteData
{
    /** Texto do campo, ou null se vier vazio/estruturado. */
    public static function str(mixed $value): ?string
    {
        if (is_array($value) || $value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** ID positivo, ou null (a Sponte usa 0 e valores negativos para "sem vínculo"). */
    public static function id(mixed $value): ?int
    {
        $value = self::str($value);

        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    /** Inteiro (pode ser 0), ou null. */
    public static function int(mixed $value): ?int
    {
        $value = self::str($value);

        return $value !== null && is_numeric($value) ? (int) $value : null;
    }

    /** Data d/m/Y da Sponte convertida para Y-m-d, ou null se vazia/inválida. */
    public static function date(mixed $value): ?string
    {
        $value = self::str($value);

        if ($value === null) {
            return null;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', substr($value, 0, 10))->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
}
