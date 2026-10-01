<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Comment se lit `salaire_base` : au mois ou a l'heure.
 *
 * Un vacataire est souvent paye a l'heure, un permanent au mois. Le meme
 * champ portait donc tantot un salaire mensuel tantot un taux horaire, sans
 * qu'aucune donnee ne permette de les distinguer : un montant de 5 000 etait
 * illisible hors contexte.
 *
 * Le mode est explicite plutot que deduit du type de contrat : un vacataire
 * peut etre paye au forfait mensuel, et l'inverse se rencontre aussi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enseignants', function (Blueprint $table) {
            $table->enum('mode_remuneration', ['MENSUEL', 'HORAIRE'])
                  ->default('MENSUEL')
                  ->after('salaire_base');
        });

        // Les vacataires existants sont les seuls dont le montant designait
        // vraisemblablement un taux horaire : on les bascule, le reste garde
        // la valeur par defaut (mensuel).
        DB::table('enseignants')
            ->where('type_contrat', 'vacataire')
            ->update(['mode_remuneration' => 'HORAIRE']);
    }

    public function down(): void
    {
        Schema::table('enseignants', function (Blueprint $table) {
            $table->dropColumn('mode_remuneration');
        });
    }
};
