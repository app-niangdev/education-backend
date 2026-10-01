<?php

use App\Enums\ModePaiementEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Les tables de paiement ont ete creees quand ModePaiementEnum valait
 * ESPECES/CHEQUE/VIREMENT/MOBILE_MONEY. L'enum expose desormais
 * ESPECES/WAVE/ORANGE_MONEY/FREE_MONEY, mais les contraintes CHECK n'ont
 * jamais suivi : seul ESPECES pouvait etre insere.
 */
return new class extends Migration
{
    private const TABLES = ['paiement_inscriptions', 'paiement_mensualites'];

    private const ANCIENNES_VALEURS = ['ESPECES', 'CHEQUE', 'VIREMENT', 'MOBILE_MONEY'];

    public function up(): void
    {
        $this->appliquer(array_column(ModePaiementEnum::cases(), 'value'));
    }

    public function down(): void
    {
        $this->appliquer(self::ANCIENNES_VALEURS);
    }

    private function appliquer(array $valeurs): void
    {
        $liste = implode(', ', array_map(fn ($v) => "'" . $v . "'", $valeurs));

        foreach (self::TABLES as $table) {
            $contrainte = "{$table}_mode_paiement_check";

            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$contrainte}");
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$contrainte} CHECK (mode_paiement::text IN ({$liste}))");
        }
    }
};
