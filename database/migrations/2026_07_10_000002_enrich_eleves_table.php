<?php

use App\Enums\AptitudeSportiveEnum;
use App\Enums\GroupeSanguinEnum;
use App\Enums\StatutInscriptionEleveEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Enrichit la fiche eleve : antecedents medicaux, etat civil, scolarite,
 * coordonnees des parents, et rattachement au tuteur legal.
 *
 * Les colonnes nom_parent / telephone_parent / email_parent sont retirees :
 * le tuteur legal (table « tuteurs ») remplit desormais exactement ce role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            // --- Etat civil -------------------------------------------------
            $table->string('nationalite')->default('Sénégalaise')->after('sexe');

            // --- Antecedents medicaux ---------------------------------------
            $table->enum('groupe_sanguin', array_column(GroupeSanguinEnum::cases(), 'value'))
                ->nullable()
                ->after('photo');
            $table->text('allergies')->nullable()->after('groupe_sanguin');
            $table->text('maladies_chroniques')->nullable()->after('allergies');
            $table->enum('aptitude_sportive', array_column(AptitudeSportiveEnum::cases(), 'value'))
                ->default(AptitudeSportiveEnum::APTE->value)
                ->after('maladies_chroniques');
            $table->text('consignes_urgence')->nullable()->after('aptitude_sportive');

            // --- Scolarite --------------------------------------------------
            // Denormalisation : maintenue par InscriptionService pour eviter
            // une jointure sur inscriptions a chaque affichage de liste.
            $table->foreignId('classe_actuelle_id')
                ->nullable()
                ->after('consignes_urgence')
                ->constrained('classes')
                ->nullOnDelete();

            $table->enum('statut_inscription', array_column(StatutInscriptionEleveEnum::cases(), 'value'))
                ->default(StatutInscriptionEleveEnum::NOUVEAU->value)
                ->after('classe_actuelle_id');

            // Obligatoire uniquement lorsque statut_inscription = TRANSFERE :
            // la regle est portee par la validation, pas par le schema.
            $table->string('etablissement_origine')->nullable()->after('statut_inscription');

            // Date de la premiere inscription dans l'etablissement. Pilotee
            // par le backend, jamais par le client. useCurrent() couvre les
            // lignes deja presentes lors de la migration.
            $table->date('date_inscription')->useCurrent()->after('etablissement_origine');

            // --- Pere -------------------------------------------------------
            $table->string('nom_pere')->nullable()->after('date_inscription');
            $table->string('prenom_pere')->nullable()->after('nom_pere');
            $table->string('telephone_pere')->nullable()->after('prenom_pere');
            $table->string('profession_pere')->nullable()->after('telephone_pere');
            $table->string('adresse_pere')->nullable()->after('profession_pere');

            // --- Mere (nom de jeune fille) ----------------------------------
            $table->string('nom_mere')->nullable()->after('adresse_pere');
            $table->string('prenom_mere')->nullable()->after('nom_mere');
            $table->string('telephone_mere')->nullable()->after('prenom_mere');
            $table->string('profession_mere')->nullable()->after('telephone_mere');
            $table->string('adresse_mere')->nullable()->after('profession_mere');

            // --- Tuteur legal -----------------------------------------------
            $table->foreignId('tuteur_id')
                ->nullable()
                ->after('adresse_mere')
                ->constrained('tuteurs')
                ->nullOnDelete();

            $table->dropColumn(['nom_parent', 'telephone_parent', 'email_parent']);
        });
    }

    public function down(): void
    {
        Schema::table('eleves', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tuteur_id');
            $table->dropConstrainedForeignId('classe_actuelle_id');

            $table->dropColumn([
                'nationalite',
                'groupe_sanguin',
                'allergies',
                'maladies_chroniques',
                'aptitude_sportive',
                'consignes_urgence',
                'statut_inscription',
                'etablissement_origine',
                'date_inscription',
                'nom_pere', 'prenom_pere', 'telephone_pere', 'profession_pere', 'adresse_pere',
                'nom_mere', 'prenom_mere', 'telephone_mere', 'profession_mere', 'adresse_mere',
            ]);

            $table->string('nom_parent')->nullable();
            $table->string('telephone_parent')->nullable();
            $table->string('email_parent')->nullable();
        });
    }
};
