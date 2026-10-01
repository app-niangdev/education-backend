<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restructure la gestion pédagogique :
     *
     *  - classe_matiere : le PROGRAMME. Le coefficient et le volume horaire
     *    d'une matière dépendent de la classe (ex. Math coeff 6 en TS2,
     *    coeff 2 en TL2). C'est une donnée du programme, indépendante du prof.
     *
     *  - affectations : le RH. Rattache UN enseignant à un couple
     *    (classe × matière). Un enseignant peut avoir plusieurs affectations
     *    (donc plusieurs matières / classes).
     *
     * L'ancienne table `cours` mélangeait ces deux responsabilités : ses
     * données sont migrées, puis elle est supprimée. Le coefficient global
     * de `matieres` est retiré (trompeur : le vrai coefficient vit sur
     * classe_matiere).
     */
    public function up(): void
    {
        Schema::create('classe_matiere', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('matiere_id')->constrained('matieres')->cascadeOnDelete();
            $table->unsignedTinyInteger('coefficient')->default(1);
            $table->unsignedSmallInteger('volume_horaire')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['classe_id', 'matiere_id']);
        });

        Schema::create('affectations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enseignant_id')->constrained('enseignants')->cascadeOnDelete();
            $table->foreignId('classe_matiere_id')->constrained('classe_matiere')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Un seul enseignant par (classe × matière).
            $table->unique('classe_matiere_id');
        });

        // Reprise des données existantes de `cours` (si présentes).
        if (Schema::hasTable('cours')) {
            $cours = DB::table('cours')->whereNull('deleted_at')->get();

            foreach ($cours as $c) {
                $classeMatiereId = DB::table('classe_matiere')->insertGetId([
                    'classe_id'      => $c->classe_id,
                    'matiere_id'     => $c->matiere_id,
                    'coefficient'    => $c->coefficient ?? 1,
                    'volume_horaire' => $c->volume_horaire,
                    'created_at'     => $c->created_at,
                    'updated_at'     => $c->updated_at,
                ]);

                if (!empty($c->enseignant_id)) {
                    DB::table('affectations')->insert([
                        'enseignant_id'     => $c->enseignant_id,
                        'classe_matiere_id' => $classeMatiereId,
                        'created_at'        => $c->created_at,
                        'updated_at'        => $c->updated_at,
                    ]);
                }
            }

            Schema::dropIfExists('cours');
        }

        if (Schema::hasColumn('matieres', 'coefficient')) {
            Schema::table('matieres', function (Blueprint $table) {
                $table->dropColumn('coefficient');
            });
        }
    }

    public function down(): void
    {
        Schema::table('matieres', function (Blueprint $table) {
            $table->unsignedTinyInteger('coefficient')->default(1);
        });

        Schema::create('cours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('matiere_id')->constrained('matieres');
            $table->foreignId('enseignant_id')->constrained('enseignants');
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->unsignedTinyInteger('coefficient')->nullable();
            $table->unsignedSmallInteger('volume_horaire')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['matiere_id', 'classe_id']);
        });

        Schema::dropIfExists('affectations');
        Schema::dropIfExists('classe_matiere');
    }
};
