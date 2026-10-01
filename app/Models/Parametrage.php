<?php

namespace App\Models;

use App\Helpers\ColorHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;

#[Fillable(['telephone_transaction', 'statut', 'en_maintenance', 'code_couleur', 'bareme_defaut'])]
#[Hidden(['created_at', 'updated_at'])]
class Parametrage extends Model
{
    /** Barème retenu si aucun paramétrage n'est trouvé en base. */
    public const BAREME_DEFAUT = 20;

    protected function casts(): array
    {
        return [
            'telephone_transaction' => 'encrypted',
            'statut'                => 'boolean',
            'en_maintenance'        => 'boolean',
            'bareme_defaut'         => 'integer',
        ];
    }

    /** Le barème par défaut configuré, ou la constante de repli. */
    public static function baremeDefaut(): int
    {
        return (int) (self::query()->value('bareme_defaut') ?? self::BAREME_DEFAUT);
    }

    /** La couleur configurée pour l'établissement, ou la couleur de repli si absente/invalide. */
    public static function couleurPrincipale(): string
    {
        return ColorHelper::normalise(self::query()->value('code_couleur'));
    }
}
