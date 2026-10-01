<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Le code qui rend un contrat imprime verifiable.
 *
 * Le PDF du contrat porte un QR code menant a une page publique : quiconque
 * detient le papier peut confirmer qu'il correspond bien a un engagement
 * enregistre. Ce code est la cle de cette page.
 *
 * Il est stocke plutot que derive d'une signature : un contrat imprime circule
 * des annees, et une rotation d'`APP_KEY` invaliderait d'un coup tous les
 * documents deja remis. Il est aussi assez long pour qu'on ne puisse pas
 * tomber dessus au hasard — la page publique n'est protegee que par lui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            $table->string('code_verification', 64)->nullable()->unique()->after('numero_contrat');
        });

        // Les contrats deja en base doivent pouvoir etre imprimes eux aussi :
        // sans code, leur PDF n'aurait pas de QR.
        DB::table('contrats')->whereNull('code_verification')->orderBy('id')
            ->each(function ($contrat) {
                DB::table('contrats')
                    ->where('id', $contrat->id)
                    ->update(['code_verification' => Str::lower(Str::random(40))]);
            });
    }

    public function down(): void
    {
        Schema::table('contrats', function (Blueprint $table) {
            $table->dropColumn('code_verification');
        });
    }
};
