<?php

use App\Models\FraisScolaire;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Les montants vivaient a deux endroits : sur le niveau (valeurs « par
     * defaut ») et dans la grille frais_scolaires (annee scolaire + niveau).
     * Deux sources pour un meme tarif, donc deux verites possibles.
     *
     * La grille est desormais la seule source : elle porte l'annee scolaire,
     * ce que le niveau ne saura jamais faire. Avant de supprimer les colonnes,
     * on remonte les montants du niveau dans la grille de chaque annee qui n'a
     * pas encore de bareme, pour ne perdre aucun tarif deja saisi.
     */
    public function up(): void
    {
        $this->reporterMontantsVersGrille();

        Schema::table('niveaux', function (Blueprint $table) {
            $table->dropColumn(['montant_inscription', 'montant_mensuel']);
        });
    }

    public function down(): void
    {
        Schema::table('niveaux', function (Blueprint $table) {
            $table->integer('montant_inscription')->default(0);
            $table->integer('montant_mensuel')->default(0);
        });

        // On repeuple depuis le bareme de l'annee en cours, a defaut le plus recent.
        foreach (DB::table('niveaux')->whereNull('deleted_at')->get() as $niveau) {
            $frais = DB::table('frais_scolaires')
                ->where('niveau_id', $niveau->id)
                ->whereNull('deleted_at')
                ->orderByDesc('annee_scolaire_id')
                ->first();

            if ($frais === null) {
                continue;
            }

            DB::table('niveaux')->where('id', $niveau->id)->update([
                'montant_inscription' => $frais->montant_inscription,
                'montant_mensuel'     => $frais->montant_mensualite,
            ]);
        }
    }

    /**
     * Cree un bareme par (annee scolaire, niveau) manquant a partir des
     * montants portes par le niveau. Les baremes deja saisis font foi et ne
     * sont jamais ecrases.
     */
    private function reporterMontantsVersGrille(): void
    {
        if (!Schema::hasColumn('niveaux', 'montant_inscription')) {
            return;
        }

        $niveaux = DB::table('niveaux')->whereNull('deleted_at')->get();
        $annees  = DB::table('annee_scolaires')->whereNull('deleted_at')->get();

        foreach ($annees as $annee) {
            foreach ($niveaux as $niveau) {
                $existe = DB::table('frais_scolaires')
                    ->where('annee_scolaire_id', $annee->id)
                    ->where('niveau_id', $niveau->id)
                    ->exists();

                if ($existe) {
                    continue;
                }

                $inscription = (int) $niveau->montant_inscription;
                $mensualite  = (int) $niveau->montant_mensuel;
                $nombre      = FraisScolaire::NOMBRE_MENSUALITES_DEFAUT;

                DB::table('frais_scolaires')->insert([
                    'annee_scolaire_id'    => $annee->id,
                    'niveau_id'            => $niveau->id,
                    'montant_inscription'  => $inscription,
                    'montant_mensualite'   => $mensualite,
                    'nombre_mensualites'   => $nombre,
                    'frais_annuel'         => $inscription + $mensualite * $nombre,
                    'neuvieme_mois_inclus' => false,
                    'created_at'           => now(),
                    'updated_at'           => now(),
                ]);
            }
        }
    }
};
