<?php

namespace App\Models;

use App\Enums\LienParenteEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Responsable financier et administratif de l'eleve : recoit les bulletins,
 * paie la scolarite, sert d'interlocuteur pour les alertes de l'ecole.
 */
class Tuteur extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tuteurs';

    protected $fillable = [
        'user_id',
        'lien_parente',
        'nom',
        'prenom',
        'nin',
        'telephone_principal',
        'telephone_secondaire',
        'email',
        'profession',
        'adresse',
    ];

    protected $casts = [
        'lien_parente' => LienParenteEnum::class,
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $appends = [
        'nom_complet',
    ];

    public function getNomCompletAttribute(): string
    {
        return trim(($this->prenom ?? '') . ' ' . ($this->nom ?? ''));
    }

    public function eleves(): HasMany
    {
        return $this->hasMany(Eleve::class);
    }

    /**
     * Le compte de connexion, quand il existe. Facultatif : la plupart des
     * tuteurs n'en ont pas — voir la migration add_user_id_to_tuteurs_table.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Les fils de discussion ouverts avec l'etablissement. */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /** Vrai si le tuteur peut ouvrir une session (et donc utiliser le chat). */
    public function aUnCompte(): bool
    {
        return $this->user_id !== null;
    }

    /**
     * Ramene un numero senegalais a sa forme locale a 9 chiffres (77xxxxxxx).
     *
     * Le telephone sert d'identifiant de connexion aux familles : il doit se
     * comparer a l'identique, quelle que soit la facon dont il a ete saisi.
     * « 77 123 45 67 », « +221 77 123 45 67 » et « 00221771234567 » designent
     * la meme ligne, et un tuteur enregistre sous une forme doit pouvoir se
     * connecter sous une autre.
     *
     * L'indicatif 221 n'est retire que devant un numero de 9 chiffres : le
     * couper aveuglement mutilerait un numero etranger, qu'on prefere laisser
     * intact plutot que de le rendre meconnaissable.
     */
    public static function normaliserTelephone(?string $numero): ?string
    {
        if ($numero === null) {
            return null;
        }

        // Ne garder que les chiffres : espaces, points, tirets et parentheses
        // sont des habitudes d'ecriture, pas des donnees.
        $chiffres = preg_replace('/\D+/', '', $numero) ?? '';

        if ($chiffres === '') {
            return null;
        }

        // 00221771234567 -> 221771234567
        if (str_starts_with($chiffres, '00221')) {
            $chiffres = substr($chiffres, 2);
        }

        // 221771234567 -> 771234567
        if (str_starts_with($chiffres, '221') && strlen($chiffres) === 12) {
            $chiffres = substr($chiffres, 3);
        }

        return $chiffres;
    }
}
