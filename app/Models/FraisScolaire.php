<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraisScolaire extends Model
{
    use HasFactory;

    /** Nombre de mensualites retenu quand rien n'est precise (annee de 9 mois). */
    public const NOMBRE_MENSUALITES_DEFAUT = 9;

    protected $table = 'frais_scolaires';

    protected $fillable = [
        'annee_scolaire_id',
        'niveau_id',
        'montant_inscription',
        'montant_mensualite',
        'nombre_mensualites',
        'frais_annuel',
        'neuvieme_mois_inclus',
    ];

    protected $casts = [
        'montant_inscription'  => 'integer',
        'montant_mensualite'   => 'integer',
        'nombre_mensualites'   => 'integer',
        'frais_annuel'         => 'integer',
        'neuvieme_mois_inclus' => 'boolean',
    ];

    protected $hidden = [
        'created_at',
        'updated_at',
    ];

    /**
     * Le frais annuel est toujours coherent avec les montants saisis :
     * inscription + mensualite x nombre de mensualites. On le recalcule a
     * chaque sauvegarde plutot que de faire confiance a la valeur envoyee.
     */
    protected static function booted(): void
    {
        static::saving(function (FraisScolaire $frais): void {
            $frais->frais_annuel = $frais->calculerFraisAnnuel();
        });
    }

    public function calculerFraisAnnuel(): int
    {
        return (int) $this->montant_inscription
            + ((int) $this->montant_mensualite * (int) $this->nombre_mensualites);
    }

    /**
     * `withTrashed` sur les deux relations : le niveau et l'annee sont en
     * SoftDeletes, et la cascade en base ne se declenche jamais sur un
     * soft-delete. Sans cela, supprimer un niveau laisserait ses baremes
     * affiches sans nom plutot que rattaches a leur niveau supprime.
     */
    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class)->withTrashed();
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class)->withTrashed();
    }
}
