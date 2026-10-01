<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Matières de spécialité de l'enseignant.
     *
     * À distinguer des `affectations`, qui rattachent un enseignant à un couple
     * (classe × matière) : c'est le planning, « qui enseigne quoi, où ».
     * Ici on décrit la COMPÉTENCE : les matières que l'enseignant est qualifié
     * à enseigner, indépendamment de toute classe.
     *
     * Remplace la colonne texte `enseignants.specialite`, qui ne permettait
     * aucune requête fiable (« quels profs sont qualifiés en Physique ? ») et
     * n'était reliée à rien.
     */
    public function up(): void
    {
        Schema::create('enseignant_matiere', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enseignant_id')->constrained('enseignants')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->timestamps();

            // Une matière ne peut être déclarée qu'une fois par enseignant.
            $table->unique(['enseignant_id', 'matiere_id']);
        });

        $this->migrateSpecialites();

        if (Schema::hasColumn('enseignants', 'specialite')) {
            Schema::table('enseignants', function (Blueprint $table) {
                $table->dropColumn('specialite');
            });
        }
    }

    /**
     * Reprend les spécialités saisies en texte libre : chaque valeur est
     * rapprochée d'une matière par son nom ou son code (comparaison insensible
     * à la casse et aux espaces). Les textes sans correspondance sont ignorés —
     * ils seront à ressaisir depuis la fiche enseignant.
     */
    private function migrateSpecialites(): void
    {
        if (!Schema::hasColumn('enseignants', 'specialite') || !Schema::hasTable('matieres')) {
            return;
        }

        $matieres = DB::table('matieres')->whereNull('deleted_at')->get();

        if ($matieres->isEmpty()) {
            return;
        }

        // Index nom/code normalisés -> id, pour un rapprochement en mémoire.
        $index = [];
        foreach ($matieres as $matiere) {
            $index[$this->normalize($matiere->nom)]  = $matiere->id;
            $index[$this->normalize($matiere->code)] = $matiere->id;
        }

        $enseignants = DB::table('enseignants')
            ->whereNotNull('specialite')
            ->where('specialite', '<>', '')
            ->get(['id', 'specialite']);

        $now  = now();
        $rows = [];

        foreach ($enseignants as $enseignant) {
            // Une spécialité pouvait lister plusieurs matières : « Maths, Physique ».
            foreach (preg_split('/[,;\/]+/', $enseignant->specialite) as $libelle) {
                $cle = $this->normalize($libelle);

                if ($cle === '' || !isset($index[$cle])) {
                    continue;
                }

                $rows[$enseignant->id . '-' . $index[$cle]] = [
                    'enseignant_id' => $enseignant->id,
                    'matiere_id'    => $index[$cle],
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }
        }

        if ($rows !== []) {
            DB::table('enseignant_matiere')->insert(array_values($rows));
        }
    }

    /**
     * Réduit un libellé à ses seules lettres/chiffres en minuscules, accents
     * repliés : « Mathématiques », « mathematiques » et « Maths-Sciences » se
     * comparent alors de façon fiable. On n'utilise pas iconv//TRANSLIT, qui
     * produit des artefacts selon la locale (« é » -> « 'e »).
     */
    private function normalize(?string $valeur): string
    {
        $valeur = mb_strtolower(trim((string) $valeur));

        if ($valeur === '') {
            return '';
        }

        $accents = [
            'à'=>'a','á'=>'a','â'=>'a','ã'=>'a','ä'=>'a','å'=>'a',
            'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
            'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
            'ò'=>'o','ó'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
            'ç'=>'c','ñ'=>'n','ÿ'=>'y',
        ];

        $valeur = strtr($valeur, $accents);

        // Tirets, espaces et ponctuation ne doivent pas empêcher la correspondance.
        return preg_replace('/[^a-z0-9]/', '', $valeur) ?? '';
    }

    public function down(): void
    {
        if (!Schema::hasColumn('enseignants', 'specialite')) {
            Schema::table('enseignants', function (Blueprint $table) {
                $table->string('specialite')->nullable();
            });

            // Restitution : on reconcatène les matières liées en texte libre.
            if (Schema::hasTable('enseignant_matiere')) {
                $libelles = DB::table('enseignant_matiere')
                    ->join('matieres', 'matieres.id', '=', 'enseignant_matiere.matiere_id')
                    ->select('enseignant_matiere.enseignant_id', 'matieres.nom')
                    ->get()
                    ->groupBy('enseignant_id');

                foreach ($libelles as $enseignantId => $lignes) {
                    DB::table('enseignants')
                        ->where('id', $enseignantId)
                        ->update(['specialite' => $lignes->pluck('nom')->implode(', ')]);
                }
            }
        }

        Schema::dropIfExists('enseignant_matiere');
    }
};
