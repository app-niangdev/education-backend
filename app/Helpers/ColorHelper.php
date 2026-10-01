<?php

namespace App\Helpers;

/**
 * Dérive des teintes cohérentes (foncée / claire) à partir d'une couleur
 * hexadécimale de base, pour les thèmes générés dynamiquement (PDF, etc.).
 */
class ColorHelper
{
    /** Couleur de repli si aucune couleur (ou une couleur invalide) n'est configurée. */
    public const DEFAUT = '#4f46e5';

    /** Valide une couleur hexadécimale, avec repli sur DEFAUT si absente ou invalide. */
    public static function normalise(?string $hex): string
    {
        if ($hex && preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $hex)) {
            return $hex;
        }

        return self::DEFAUT;
    }

    /** Assombrit une couleur en la mélangeant avec du noir. */
    public static function darken(string $hex, float $pourcentage): string
    {
        return self::melange($hex, 0, 0, 0, $pourcentage);
    }

    /** Éclaircit une couleur en la mélangeant avec du blanc. */
    public static function lighten(string $hex, float $pourcentage): string
    {
        return self::melange($hex, 255, 255, 255, $pourcentage);
    }

    /**
     * Palette utilisée dans les gabarits PDF : la couleur de l'établissement,
     * une variante foncée (texte sur fond clair), une variante claire (fond
     * derrière du texte foncé) et une variante claire plus soutenue (bordures,
     * fonds de mise en avant) — dans l'esprit du thème indigo d'origine.
     *
     * @return array{couleur: string, couleur_foncee: string, couleur_claire: string, couleur_claire_intense: string}
     */
    public static function palette(?string $hex): array
    {
        $couleur = self::normalise($hex);

        return [
            'couleur' => $couleur,
            'couleur_foncee' => self::darken($couleur, 0.15),
            'couleur_claire' => self::lighten($couleur, 0.92),
            'couleur_claire_intense' => self::lighten($couleur, 0.65),
        ];
    }

    private static function melange(string $hex, int $r2, int $g2, int $b2, float $pourcentage): string
    {
        $pourcentage = max(0, min(1, $pourcentage));
        [$r, $g, $b] = self::hexVersRgb($hex);

        $melange = fn (int $c1, int $c2): int => (int) round($c1 + ($c2 - $c1) * $pourcentage);

        return self::rgbVersHex($melange($r, $r2), $melange($g, $g2), $melange($b, $b2));
    }

    /** @return array{0: int, 1: int, 2: int} */
    private static function hexVersRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private static function rgbVersHex(int $r, int $g, int $b): string
    {
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
