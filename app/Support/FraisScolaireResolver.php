<?php

namespace App\Support;

use App\Models\AnneeScolaire;
use App\Models\FraisScolaire;
use App\Models\Niveau;
use Carbon\CarbonImmutable;

/**
 * Resout le bareme tarifaire applicable a une combinaison (annee scolaire,
 * niveau) et construit l'echeancier des mensualites.
 *
 * La grille frais_scolaires est l'UNIQUE source des montants : le niveau n'en
 * porte plus. Sans bareme saisi pour l'annee, il n'y a donc rien a facturer,
 * et c'est une erreur de configuration, pas un montant a zero.
 */
class FraisScolaireResolver
{
    /** Jour du mois auquel la mensualite est due au plus tard. */
    public const JOUR_ECHEANCE = 5;

    /**
     * Bareme effectif sous forme de tableau normalise :
     *  - montant_inscription
     *  - montant_mensualite
     *  - nombre_mensualites
     *  - frais_annuel
     *  - neuvieme_mois_inclus
     *
     * Echoue en 422 si le barème n'a pas été défini pour cette année scolaire.
     */
    public function baremePour(int|string $anneeScolaireId, int|string $niveauId): array
    {
        $bareme = $this->baremeOuNull($anneeScolaireId, $niveauId);

        if ($bareme === null) {
            $niveau = Niveau::find($niveauId);
            $annee  = AnneeScolaire::find($anneeScolaireId);

            abort(422, sprintf(
                "Aucun barème de frais scolaires n'est défini pour le niveau « %s » sur l'année scolaire « %s ». Renseignez-le dans le module Frais scolaire avant de continuer.",
                $niveau?->nom ?? '?',
                $annee?->nom ?? '?',
            ));
        }

        return $bareme;
    }

    /**
     * Meme resolution, mais rend null au lieu d'echouer : pour les ecrans qui
     * savent afficher « barème non défini » (fiche PDF, previsualisations).
     */
    public function baremeOuNull(int|string $anneeScolaireId, int|string $niveauId): ?array
    {
        $frais = FraisScolaire::query()
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->where('niveau_id', $niveauId)
            ->first();

        if ($frais === null) {
            return null;
        }

        return [
            'montant_inscription'  => $frais->montant_inscription,
            'montant_mensualite'   => $frais->montant_mensualite,
            'nombre_mensualites'   => $frais->nombre_mensualites,
            'frais_annuel'         => $frais->frais_annuel,
            'neuvieme_mois_inclus' => $frais->neuvieme_mois_inclus,
        ];
    }

    /**
     * Echeancier des mensualites : une ligne par mois a partir du debut de
     * l'annee scolaire, chacune due au plus tard le 5 du mois.
     *
     * @return array<int, array{mois:int, annee:int, date_echeance:CarbonImmutable, montant_mensualite:int}>
     */
    public function echeancier(AnneeScolaire $annee, array $bareme): array
    {
        $debut     = CarbonImmutable::parse($annee->date_debut)->startOfMonth();
        $lignes    = [];
        $nombre    = (int) $bareme['nombre_mensualites'];
        $montant   = (int) $bareme['montant_mensualite'];

        for ($i = 0; $i < $nombre; $i++) {
            $mois = $debut->addMonths($i);

            $lignes[] = [
                'mois'               => (int) $mois->format('n'),
                'annee'              => (int) $mois->format('Y'),
                'date_echeance'      => $mois->day(self::JOUR_ECHEANCE),
                'montant_mensualite' => $montant,
            ];
        }

        return $lignes;
    }
}
