<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fait du contrat la seule source de verite des conditions d'engagement.
 *
 * Chaque profil existant (enseignant, tresorier, surveillant) recoit un contrat
 * reprenant ses colonnes actuelles ; la migration suivante supprime alors ces
 * colonnes des tables de profil. La conversion est faite ici, avant la
 * suppression, pour qu'aucune donnee ne soit perdue.
 *
 * La date de debut retombe sur la date de creation du profil quand
 * `date_embauche` est vide : `date_debut` ne peut pas etre nulle, et la date
 * d'enregistrement reste l'approximation la plus honnete dont on dispose.
 */
return new class extends Migration
{
    /** Table de profil => modele polymorphe correspondant. */
    private const PROFILS = [
        'enseignants'  => \App\Models\Enseignant::class,
        'tresoriers'   => \App\Models\Tresorier::class,
        'surveillants' => \App\Models\Surveillant::class,
    ];

    /** La fonction inscrite au contrat, deduite du profil d'origine. */
    private const FONCTIONS = [
        'enseignants'  => 'Enseignant',
        'tresoriers'   => 'Trésorier',
        'surveillants' => 'Surveillant',
    ];

    public function up(): void
    {
        $now       = now();
        $compteurs = [];

        foreach (self::PROFILS as $table => $modele) {
            // `mode_remuneration` n'existe que sur les enseignants : les autres
            // profils n'ont jamais porte la distinction mensuel / horaire.
            $aModeRemuneration = $table === 'enseignants';

            DB::table($table)->orderBy('id')->each(function ($profil) use (
                $table, $modele, $aModeRemuneration, $now, &$compteurs
            ) {
                $debut = $profil->date_embauche
                    ? Carbon::parse($profil->date_embauche)
                    : Carbon::parse($profil->created_at ?? $now);

                // Le numero suit l'annee du contrat, pas l'annee courante :
                // deux contrats de 2022 et 2026 ne se marchent pas dessus.
                $annee   = $debut->year;
                $rang    = ($compteurs[$annee] = ($compteurs[$annee] ?? 0) + 1);
                $numero  = sprintf('CTR-%d-%04d', $annee, $rang);

                $typeContrat = $profil->type_contrat ?? 'permanent';

                DB::table('contrats')->insert([
                    'contractable_type'   => $modele,
                    'contractable_id'     => $profil->id,
                    'numero_contrat'      => $numero,
                    'type_contrat'        => $typeContrat,
                    // Le profil est vivant : son contrat l'est aussi. Un profil
                    // archive (soft delete) laisse un contrat resilie.
                    'statut'              => $profil->deleted_at ? 'RESILIE' : 'ACTIF',
                    'date_debut'          => $debut->toDateString(),
                    // Aucune date de fin n'etait connue jusqu'ici, y compris pour
                    // les contrats bornes : elle sera saisie a la prochaine mise
                    // a jour plutot qu'inventee ici.
                    'date_fin'            => null,
                    'salaire_base'        => $profil->salaire_base,
                    'mode_remuneration'   => $aModeRemuneration
                        ? ($profil->mode_remuneration ?? 'MENSUEL')
                        : 'MENSUEL',
                    'fonction'            => self::FONCTIONS[$table],
                    'date_resiliation'    => $profil->deleted_at
                        ? Carbon::parse($profil->deleted_at)->toDateString()
                        : null,
                    'motif_resiliation'   => $profil->deleted_at
                        ? 'Profil archivé avant la mise en place du suivi des contrats.'
                        : null,
                    'observations'        => 'Contrat reconstitué automatiquement à partir de la fiche du personnel.',
                    'created_at'          => $now,
                    'updated_at'          => $now,
                    'deleted_at'          => null,
                ]);
            });
        }
    }

    /**
     * Renvoie les conditions dans les tables de profil. Les colonnes sont
     * recreees par le `down()` de la migration suivante, qui s'execute avant
     * celui-ci : elles sont donc bien presentes ici, mais vides.
     */
    public function down(): void
    {
        foreach (self::PROFILS as $table => $modele) {
            $contrats = DB::table('contrats')
                ->where('contractable_type', $modele)
                ->whereNull('deleted_at')
                ->orderBy('date_debut')
                ->get();

            foreach ($contrats as $contrat) {
                $valeurs = [
                    'type_contrat'  => $contrat->type_contrat,
                    'date_embauche' => $contrat->date_debut,
                    'salaire_base'  => $contrat->salaire_base,
                ];

                if ($table === 'enseignants') {
                    $valeurs['mode_remuneration'] = $contrat->mode_remuneration;
                }

                DB::table($table)->where('id', $contrat->contractable_id)->update($valeurs);
            }
        }

        DB::table('contrats')->delete();
    }
};
