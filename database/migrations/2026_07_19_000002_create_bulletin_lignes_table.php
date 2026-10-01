<?php

use App\Enums\AppreciationEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une ligne de bulletin = une matière du programme de la classe.
 *
 * Les matières sans aucune note sont enregistrées elles aussi, avec
 * `notee = false` : le bulletin papier les imprime avec des tirets (Espagnol,
 * Ed-Civique sur le modèle de référence) et les exclut du total des
 * coefficients. Les distinguer par une colonne plutôt que par des NULL épars
 * évite d'avoir à deviner l'intention à la lecture.
 *
 * `matiere_nom` est figé et fait foi à l'impression ; `matiere_id` n'est
 * conservé que pour la traçabilité et devient nul si la matière disparaît.
 *
 * Les moyennes sont stockées avec trois décimales, et non deux : sur le
 * bulletin de référence, Anglais vaut (10,25 + 8) / 2 = 9,125 et sa
 * contribution au total est 9,125 × 2 = 18,25. Arrondir à 9,13 avant de
 * multiplier donnerait 18,26 et fausserait le total général.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulletin_lignes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bulletin_id')->constrained('bulletins')->cascadeOnDelete();
            $table->foreignId('matiere_id')->nullable()->constrained('matieres')->nullOnDelete();

            $table->string('matiere_nom');
            $table->unsignedSmallInteger('ordre')->default(0);

            $table->decimal('moy_devoirs', 5, 3)->nullable();
            $table->decimal('composition', 5, 3)->nullable();
            $table->decimal('moyenne', 5, 3)->nullable();
            $table->unsignedSmallInteger('coefficient')->nullable();
            $table->decimal('moy_x_coef', 8, 3)->nullable();

            $table->unsignedSmallInteger('rang')->nullable();
            $table->boolean('rang_ex_aequo')->default(false);
            $table->enum('appreciation', array_column(AppreciationEnum::cases(), 'value'))->nullable();

            // Pilote l'affichage en tirets et l'exclusion du total des coefficients.
            $table->boolean('notee')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['bulletin_id', 'ordre']);
        });

        // Une matière ne peut apparaître qu'une fois par bulletin. Index partiel
        // pour la même raison que sur `bulletins` : ne pas compter les lignes
        // soft-deleted lors d'une régénération.
        DB::statement(
            'CREATE UNIQUE INDEX bulletin_lignes_bulletin_matiere_unique
             ON bulletin_lignes (bulletin_id, matiere_id)
             WHERE deleted_at IS NULL AND matiere_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS bulletin_lignes_bulletin_matiere_unique');
        Schema::dropIfExists('bulletin_lignes');
    }
};
