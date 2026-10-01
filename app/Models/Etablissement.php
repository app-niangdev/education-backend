<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['logo','nom','nom_court','slogan','adresse',
    'email','telephone_principal','telephone_secondaire',
    'site_web','lien_facebook','lien_instagram','inspection_academique','inspection_education_formation'
])]
#[Hidden(['created_at','updated_at','deleted_at'])]
class Etablissement extends Model implements HasMedia
{
    use SoftDeletes, InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->useDisk('logo_etablissement');
    }

    /**
     * Le logo encodé en data URI, prêt à être injecté dans un <img> de PDF.
     *
     * DomPDF ne peut pas fiablement aller chercher un fichier via son disque/URL
     * (chroot, remote désactivé) : on l'embarque donc directement dans le HTML.
     */
    public function logoDataUri(): ?string
    {
        $media = $this->getFirstMedia('logo');

        if (! $media || ! is_file($media->getPath())) {
            return null;
        }

        $contenu = file_get_contents($media->getPath());

        return 'data:'.$media->mime_type.';base64,'.base64_encode($contenu);
    }
}
