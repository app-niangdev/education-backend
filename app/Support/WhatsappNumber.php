<?php

namespace App\Support;

/**
 * Numero au format attendu par WhatsApp : indicatif pays + numero, chiffres
 * seuls, sans « + » ni « 00 ».
 */
final class WhatsappNumber
{
    public static function normalize(?string $phone): ?string
    {
        $raw = trim((string) $phone);

        if ($raw === '') {
            return null;
        }

        $digits        = preg_replace('/\D+/', '', $raw) ?? '';
        $international = str_starts_with($raw, '+') || str_starts_with($digits, '00');
        $digits        = ltrim(str_starts_with($digits, '00') ? substr($digits, 2) : $digits, '0');

        // Un numero national (771234567) recoit l'indicatif de l'etablissement.
        if (!$international && strlen($digits) === config('services.waha.national_number_length')) {
            $digits = config('services.waha.country_code') . $digits;
        }

        // E.164 : 15 chiffres au plus ; en dessous de 10, il manque
        // l'indicatif ou des chiffres.
        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
    }
}
