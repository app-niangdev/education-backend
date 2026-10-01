<?php

namespace Database\Seeders;

use App\Enums\TypePeriodeEnum;
use App\Models\AnneeScolaire;
use App\Models\Periode;
use Illuminate\Database\Seeder;

/**
 * Les trois trimestres de l'annee scolaire 1 (2026-2027).
 *
 * Le decoupage suit l'annee telle que la pose AnneeScolaireSeeder : du
 * 1er octobre 2026 au 30 juin 2027. Les trimestres s'enchainent sans trou ni
 * chevauchement, le premier ouvrant le jour de la rentree et le troisieme se
 * fermant le dernier jour de l'annee — une periode qui deborderait laisserait
 * des evaluations rattachees a une annee close.
 *
 * `date_fin_saisie_notes` est posee une semaine apres la fin de chaque
 * trimestre : les enseignants corrigent les compositions apres la derniere
 * seance, et fermer la saisie le jour meme rendrait le trimestre inachevable.
 * Passe cette date, `Periode::saisieNotesFermee()` verrouille la saisie.
 *
 * Idempotent : la cle de reconnaissance est le couple (annee, ordre), et non
 * le libelle, qu'un etablissement peut vouloir renommer sans creer pour autant
 * un quatrieme trimestre a chaque passage.
 */
class PeriodeSeeder extends Seeder
{
    /** L'annee visee, conformement a la demande. */
    private const ANNEE_SCOLAIRE_ID = 1;

    public function run(): void
    {
        $annee = AnneeScolaire::find(self::ANNEE_SCOLAIRE_ID);

        // Sans l'annee, les periodes n'ont nulle part ou se rattacher : la
        // cle etrangere refuserait l'insertion. On le dit plutot que de
        // laisser tomber une erreur SQL.
        if (! $annee) {
            $this->command?->warn(
                'PeriodeSeeder : année scolaire #' . self::ANNEE_SCOLAIRE_ID
                . ' introuvable. Lancez AnneeScolaireSeeder d\'abord.'
            );

            return;
        }

        foreach ($this->trimestres() as $trimestre) {
            Periode::updateOrCreate(
                [
                    'annee_scolaire_id' => self::ANNEE_SCOLAIRE_ID,
                    'ordre'             => $trimestre['ordre'],
                ],
                $trimestre + ['type' => TypePeriodeEnum::TRIMESTRE],
            );
        }

        $this->command?->info(
            'PeriodeSeeder : 3 trimestres posés sur l\'année « ' . $annee->nom . ' ».'
        );
    }

    /**
     * Le decoupage, dates comprises.
     *
     * @return array<int, array<string, mixed>>
     */
    private function trimestres(): array
    {
        return [
            [
                'libelle'               => '1er Trimestre',
                'ordre'                 => 1,
                'date_debut'            => '2026-10-01',
                'date_fin'              => '2026-12-31',
                'date_fin_saisie_notes' => '2027-01-07',
            ],
            [
                'libelle'               => '2ème Trimestre',
                'ordre'                 => 2,
                'date_debut'            => '2027-01-01',
                'date_fin'              => '2027-03-31',
                'date_fin_saisie_notes' => '2027-04-07',
            ],
            [
                'libelle'               => '3ème Trimestre',
                'ordre'                 => 3,
                'date_debut'            => '2027-04-01',
                'date_fin'              => '2027-06-30',
                // Derniere periode : la saisie reste ouverte un peu plus
                // longtemps, les bulletins de fin d'annee se preparant apres
                // la sortie des classes.
                'date_fin_saisie_notes' => '2027-07-10',
            ],
        ];
    }
}
