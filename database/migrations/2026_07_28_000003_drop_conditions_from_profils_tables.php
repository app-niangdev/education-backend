<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retire des profils les conditions d'engagement, desormais portees par le
 * contrat (voir la migration de reprise qui precede).
 *
 * Les garder aurait laisse deux salaires possibles pour un meme employe, sans
 * regle disant lequel fait foi. Ce qui reste sur le profil decrit la personne
 * et son poste (matricule, diplomes, zone de surveillance, acces caisse), pas
 * les termes de son engagement.
 *
 * Le `down()` recree les colonnes vides : c'est la migration de reprise, annulee
 * juste apres, qui y reversera les valeurs depuis les contrats.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enseignants', function (Blueprint $table) {
            $table->dropColumn(['type_contrat', 'date_embauche', 'salaire_base', 'mode_remuneration']);
        });

        Schema::table('tresoriers', function (Blueprint $table) {
            $table->dropColumn(['type_contrat', 'date_embauche', 'salaire_base']);
        });

        Schema::table('surveillants', function (Blueprint $table) {
            $table->dropColumn(['type_contrat', 'date_embauche', 'salaire_base']);
        });
    }

    public function down(): void
    {
        Schema::table('enseignants', function (Blueprint $table) {
            $table->enum('type_contrat', ['permanent', 'vacataire', 'stagiaire'])->default('permanent');
            $table->date('date_embauche')->nullable();
            $table->integer('salaire_base')->nullable();
            $table->enum('mode_remuneration', ['MENSUEL', 'HORAIRE'])->default('MENSUEL');
        });

        Schema::table('tresoriers', function (Blueprint $table) {
            $table->enum('type_contrat', ['permanent', 'vacataire', 'stagiaire'])->default('permanent');
            $table->date('date_embauche')->nullable();
            $table->integer('salaire_base')->nullable();
        });

        Schema::table('surveillants', function (Blueprint $table) {
            $table->enum('type_contrat', ['permanent', 'vacataire', 'stagiaire'])->default('permanent');
            $table->date('date_embauche')->nullable();
            $table->integer('salaire_base')->nullable();
        });
    }
};
