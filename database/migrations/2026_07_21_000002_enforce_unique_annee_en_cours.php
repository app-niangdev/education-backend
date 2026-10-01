<?php

use App\Enums\StatutAnneeScolaire;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Garantit qu'une seule année scolaire porte `en_cours = true`.
 *
 * `en_cours` est le pivot de toute l'application : treize requêtes de
 * repositories et les trois tableaux de bord s'en servent pour déterminer
 * « l'année courante » (classes, inscriptions, finances, bulletins,
 * assiduité, évaluations). Deux années actives simultanément corrompraient
 * silencieusement l'ensemble — et rien, jusqu'ici, ne l'empêchait : la
 * colonne était un simple booléen écrit par le client, l'unicité n'étant
 * assurée que dans le seeder.
 *
 * L'index partiel porte la garantie au niveau du moteur : quel que soit le
 * chemin d'écriture — service, tinker, requête SQL directe — la base refuse
 * la seconde ligne. C'est la seule protection qui ne dépende pas de la
 * discipline du code.
 *
 * Corollaire : `en_cours` sort de $fillable et des FormRequests. La bascule
 * ne peut plus se faire que par la transaction de clôture, qui ferme l'année
 * courante AVANT d'ouvrir la suivante — l'ordre inverse violerait l'index.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Normaliser avant de contraindre : s'il existe déjà plusieurs années
        // actives, ne conserver que la plus récente ayant le statut ENCOURS.
        $active = DB::table('annee_scolaires')
            ->whereNull('deleted_at')
            ->where('en_cours', true)
            ->orderByRaw("CASE WHEN statut = ? THEN 0 ELSE 1 END", [StatutAnneeScolaire::ENCOURS->value])
            ->orderByDesc('date_debut')
            ->first();

        if ($active !== null) {
            DB::table('annee_scolaires')
                ->where('id', '!=', $active->id)
                ->where('en_cours', true)
                ->update(['en_cours' => false]);
        }

        // Index sur une expression constante avec prédicat : au plus une ligne
        // peut satisfaire `en_cours = true`.
        DB::statement(
            'CREATE UNIQUE INDEX annee_scolaires_en_cours_unique
             ON annee_scolaires ((en_cours))
             WHERE en_cours = true AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS annee_scolaires_en_cours_unique');
    }
};
