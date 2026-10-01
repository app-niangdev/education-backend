<?php

namespace Database\Seeders;

use App\Enums\ModePaiementEnum;
use App\Enums\SexeEnum;
use App\Enums\StatutInscriptionEnum;
use App\Enums\StatutPaiementEnum;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\Mensualite;
use App\Models\PaiementInscription;
use App\Models\PaiementMensualite;
use App\Models\User;
use App\Support\FraisScolaireResolver;
use Illuminate\Database\Seeder;

/**
 * Quelques élèves de démonstration, inscrits (inscription validée) dans la
 * première classe pour l'année en cours, avec le paiement de leur inscription
 * enregistré et leurs mensualités générées (dont quelques versements partiels),
 * afin qu'ils apparaissent dans la grille des notes ET dans l'écran trésorier
 * « Mensualités à encaisser ». Idempotent : rejouable sans doublon (matricule
 * unique, unicité élève/année et mois/inscription, numéro de reçu unique).
 */
class EleveSeeder extends Seeder
{
    public function run(): void
    {
        $annee = AnneeScolaire::where('en_cours', true)->first()
            ?? AnneeScolaire::orderBy('date_debut')->first();

        $classe = Classe::with('niveau')->orderBy('id')->first();

        if (!$annee || !$classe) {
            $this->command?->warn('EleveSeeder : année en cours ou classe manquante, rien à faire.');
            return;
        }

        $resolver = app(FraisScolaireResolver::class);
        $bareme   = $resolver->baremeOuNull($annee->id, $classe->niveau_id);

        if ($bareme === null) {
            $this->command?->warn("EleveSeeder : aucun barème pour « {$classe->niveau?->nom} » sur « {$annee->nom} », rien à faire.");
            return;
        }

        $echeancier = $resolver->echeancier($annee, $bareme);

        // Montant de l'inscription, réglé intégralement en démo.
        $montantInscription = (int) $bareme['montant_inscription'];

        // Caissier fictif : le premier trésorier, sinon un admin (nullable en base).
        $caissier = User::where('role_id', 4)->first() ?? User::where('role_id', 1)->first();

        $demo = [
            ['matricule' => 'ELV-2026-001', 'nom' => 'Diallo',  'prenom' => 'Awa',     'sexe' => SexeEnum::FEMININ,  'date_naissance' => '2014-03-12', 'lieu_naissance' => 'Dakar'],
            ['matricule' => 'ELV-2026-002', 'nom' => 'Ndiaye',  'prenom' => 'Moussa',   'sexe' => SexeEnum::MASCULIN, 'date_naissance' => '2014-07-05', 'lieu_naissance' => 'Thiès'],
            ['matricule' => 'ELV-2026-003', 'nom' => 'Sow',     'prenom' => 'Fatou',    'sexe' => SexeEnum::FEMININ,  'date_naissance' => '2013-11-23', 'lieu_naissance' => 'Saint-Louis'],
        ];

        foreach ($demo as $i => $data) {
            $eleve = Eleve::firstOrCreate(
                ['matricule' => $data['matricule']],
                [
                    'nom'            => $data['nom'],
                    'prenom'         => $data['prenom'],
                    'sexe'           => $data['sexe'],
                    'date_naissance' => $data['date_naissance'],
                    'lieu_naissance' => $data['lieu_naissance'],
                ],
            );

            // Inscription validée dans la classe pour l'année en cours : c'est
            // ce qui rend l'élève visible dans la grille des évaluations.
            $inscription = Inscription::firstOrCreate(
                ['eleve_id' => $eleve->id, 'annee_scolaire_id' => $annee->id],
                [
                    'numero_inscription' => sprintf('INS-%d-%03d', $annee->id, $i + 1),
                    'classe_id'          => $classe->id,
                    'utilisateur_id'     => $caissier?->id,
                    'montant_inscription'=> $montantInscription,
                    'statut_inscription' => StatutInscriptionEnum::VALIDEE,
                    'statut_paiement'    => StatutPaiementEnum::PAYE,
                    'date_inscription'   => $annee->date_debut->toDateString(),
                ],
            );

            // Paiement de l'inscription : réglée intégralement en espèces.
            PaiementInscription::firstOrCreate(
                ['inscription_id' => $inscription->id],
                [
                    'utilisateur_id'     => $caissier?->id,
                    'numero_recu'        => sprintf('RECU-%d-%03d', $annee->id, $i + 1),
                    'montant'            => $montantInscription,
                    'mode_paiement'      => ModePaiementEnum::ESPECES,
                    'date_paiement'      => $annee->date_debut->toDateString(),
                ],
            );

            // Mensualités de l'année : mêmes mois/montants que le service (via
            // le résolveur). firstOrCreate + unicité (inscription, mois, année)
            // garantissent l'absence de doublon au rejeu du seeder.
            foreach ($echeancier as $ligne) {
                Mensualite::firstOrCreate(
                    [
                        'inscription_id' => $inscription->id,
                        'mois'           => $ligne['mois'],
                        'annee'          => $ligne['annee'],
                    ],
                    [
                        'date_echeance'      => $ligne['date_echeance']->toDateString(),
                        'montant_mensualite' => $ligne['montant_mensualite'],
                    ],
                );
            }

            // Profils de paiement de démo, un par élève, pour illustrer les
            // trois statuts (PAYE / PARTIEL / NON_PAYE) dans l'écran trésorier.
            $this->seedVersementsDemo($inscription, $i, $caissier);

            // Denormalisation reprise par InscriptionService en temps normal.
            $eleve->forceFill(['classe_actuelle_id' => $classe->id])->save();
        }

        $this->command?->info(
            "EleveSeeder : " . count($demo) . " élèves inscrits (validés) et payés dans « {$classe->nom} » pour « {$annee->nom} », mensualités générées."
        );
    }

    /**
     * Quelques versements de mensualité de démo, différents pour chaque élève,
     * afin de peupler l'écran « Mensualités à encaisser » avec des lignes dans
     * les trois statuts. Idempotent : un numéro de reçu déjà présent n'est pas
     * réinséré, et le statut de la mensualité est resynchronisé à partir des
     * paiements réels.
     */
    private function seedVersementsDemo(Inscription $inscription, int $index, ?User $caissier): void
    {
        // [rang de la mensualité (0 = 1er mois), fraction du montant à verser]
        $profils = [
            0 => [[0, 1.0], [1, 0.5]], // 1er mois soldé, 2e partiel
            1 => [[0, 0.4]],           // 1er mois partiel
            2 => [],                   // aucun versement (tout NON_PAYE)
        ];

        $plan        = $profils[$index] ?? [];
        $mensualites = $inscription->mensualites()->orderBy('annee')->orderBy('mois')->get();
        $compteur    = 0;

        foreach ($plan as [$rang, $fraction]) {
            $mensualite = $mensualites[$rang] ?? null;
            if (!$mensualite) {
                continue;
            }

            $montant = (int) round($mensualite->montant_mensualite * $fraction);
            if ($montant <= 0) {
                continue;
            }

            PaiementMensualite::firstOrCreate(
                ['numero_recu' => sprintf('REM-DEMO-%d-%03d', $inscription->id, ++$compteur)],
                [
                    'mensualite_id'  => $mensualite->id,
                    'utilisateur_id' => $caissier?->id,
                    'montant'        => $montant,
                    'mode_paiement'  => ModePaiementEnum::ESPECES,
                    'date_paiement'  => $mensualite->date_echeance?->toDateString()
                        ?? now()->toDateString(),
                ],
            );

            // Statut dérivé des paiements réels (NON_PAYE / PARTIEL / PAYE).
            $mensualite->load('paiements');
            $mensualite->synchroniserStatut();
        }
    }
}
