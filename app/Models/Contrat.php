<?php

namespace App\Models;

use App\Enums\ModeRemunerationEnum;
use App\Enums\StatutContratEnum;
use App\Enums\TypeContratEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Le contrat de travail d'un membre du personnel.
 *
 * Il porte les conditions d'engagement (dates, remuneration, fonction) que les
 * tables de profil detenaient auparavant. Un employe peut en avoir plusieurs
 * dans le temps : un seul est en cours, les autres racontent son historique.
 */
#[Fillable([
    'contractable_type', 'contractable_id',
    'numero_contrat', 'code_verification', 'type_contrat', 'statut',
    'date_debut', 'date_fin', 'duree_periode_essai',
    'salaire_base', 'mode_remuneration',
    'fonction', 'lieu_travail', 'volume_horaire_hebdo',
    'date_resiliation', 'motif_resiliation',
    'contrat_parent_id', 'observations',
])]
class Contrat extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'contrats';

    protected $appends = [
        'remuneration_libelle',
        'type_contrat_libelle',
        'statut_libelle',
        'en_periode_essai',
        'jours_avant_echeance',
    ];

    protected function casts(): array
    {
        return [
            'date_debut'           => 'date',
            'date_fin'             => 'date',
            'date_resiliation'     => 'date',
            'duree_periode_essai'  => 'integer',
            'salaire_base'         => 'integer',
            'volume_horaire_hebdo' => 'integer',
            'type_contrat'         => TypeContratEnum::class,
            'statut'               => StatutContratEnum::class,
            'mode_remuneration'    => ModeRemunerationEnum::class,
        ];
    }

    /**
     * Tout contrat nait verifiable.
     *
     * Le code est pose ici plutot que dans le service : le contrat initial, le
     * contrat manuel et le renouvellement passent par des chemins differents,
     * et un seul d'entre eux qui l'oublierait produirait un PDF sans QR.
     */
    protected static function booted(): void
    {
        static::creating(function (self $contrat) {
            $contrat->code_verification ??= Str::lower(Str::random(40));
        });
    }

    /** L'employe engage : Enseignant, Tresorier ou Surveillant. */
    public function contractable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * L'adresse imprimee dans le QR code du contrat.
     *
     * Elle vise la SPA, pas l'API : un telephone qui scanne doit tomber sur une
     * page lisible, pas sur du JSON.
     */
    public function lienVerification(): ?string
    {
        if (!$this->code_verification) {
            return null;
        }

        return rtrim(config('app.frontend_url'), '/')
            . '/verification-contrat/' . $this->code_verification;
    }

    /** Le contrat que celui-ci renouvelle ou amende, s'il y en a un. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'contrat_parent_id');
    }

    /** Les contrats qui prolongent celui-ci. */
    public function renouvellements(): HasMany
    {
        return $this->hasMany(self::class, 'contrat_parent_id');
    }

    /**
     * Les contrats liant encore l'employe a l'etablissement.
     *
     * On ne filtre pas sur la date : un contrat echu mais non encore bascule en
     * EXPIRE reste ici, sans quoi un employe paraitrait sans contrat le
     * lendemain de son terme, avant meme qu'on ait tranche son renouvellement.
     */
    public function scopeEnCours(Builder $query): Builder
    {
        return $query->whereIn('statut', [
            StatutContratEnum::ACTIF->value,
            StatutContratEnum::SUSPENDU->value,
        ]);
    }

    /** Les contrats arrives a terme mais encore marques actifs. */
    public function scopeEchus(Builder $query): Builder
    {
        return $query->where('statut', StatutContratEnum::ACTIF->value)
                     ->whereNotNull('date_fin')
                     ->whereDate('date_fin', '<', now());
    }

    /**
     * Le montant avec son unite : « 5 000 FCFA/h » ne se confond pas avec
     * « 250 000 FCFA/mois ». Null tant qu'aucun montant n'est saisi.
     */
    public function getRemunerationLibelleAttribute(): ?string
    {
        if ($this->salaire_base === null) {
            return null;
        }

        $mode = $this->mode_remuneration ?? ModeRemunerationEnum::MENSUEL;

        return number_format($this->salaire_base, 0, ',', ' ') . ' ' . $mode->unite();
    }

    public function getTypeContratLibelleAttribute(): ?string
    {
        return $this->type_contrat?->libelle();
    }

    public function getStatutLibelleAttribute(): ?string
    {
        return $this->statut?->libelle();
    }

    /**
     * Vrai tant que la periode d'essai court : c'est la fenetre pendant laquelle
     * chaque partie peut rompre sans preavis, d'ou son affichage sur la fiche.
     */
    public function getEnPeriodeEssaiAttribute(): bool
    {
        if (!$this->duree_periode_essai || !$this->date_debut) {
            return false;
        }

        if (!$this->statut?->estEnCours()) {
            return false;
        }

        return now()->lessThan(
            $this->date_debut->copy()->addMonths($this->duree_periode_essai)
        );
    }

    /**
     * Combien de jours avant le terme. Negatif si le terme est depasse, null
     * pour un contrat sans fin ou deja clos : rien a annoncer dans ces cas.
     */
    public function getJoursAvantEcheanceAttribute(): ?int
    {
        if (!$this->date_fin || $this->statut?->estClos()) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->date_fin, false);
    }
}
