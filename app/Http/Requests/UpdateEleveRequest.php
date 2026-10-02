<?php

namespace App\Http\Requests;

use App\Enums\AptitudeSportiveEnum;
use App\Enums\GroupeSanguinEnum;
use App\Enums\LienParenteEnum;
use App\Enums\SexeEnum;
use App\Enums\StatutInscriptionEleveEnum;
use App\Models\Eleve;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEleveRequest extends FormRequest
{
    private ?Eleve $eleve = null;

    /**
     * Le surveillant tient la fiche eleve a jour : c'est lui qui recoit les
     * corrections du quotidien (changement de telephone du tuteur, adresse,
     * consignes medicales). Il modifie donc au meme titre que l'admin et le
     * manager. La suppression, elle, reste hors de sa portee.
     *
     * Le tresorier consulte la fiche sans la modifier : une coordonnee a
     * corriger passe par la scolarite.
     */
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager', 'supervisor']);
    }

    public function rules(): array
    {
        $eleveId = $this->route('id');

        return [
            'eleve.matricule'      => ['sometimes', 'string', 'max:50', "unique:eleves,matricule,{$eleveId}"],
            'eleve.nom'            => ['sometimes', 'required', 'string', 'max:255'],
            'eleve.prenom'         => ['sometimes', 'required', 'string', 'max:255'],
            'eleve.date_naissance' => ['sometimes', 'required', 'date', 'before:today'],
            'eleve.lieu_naissance' => ['nullable', 'string', 'max:255'],
            'eleve.sexe'           => ['sometimes', 'required', Rule::enum(SexeEnum::class)],
            'eleve.nationalite'    => ['nullable', 'string', 'max:100'],
            'eleve.adresse'        => ['nullable', 'string', 'max:255'],
            'eleve.telephone'      => ['nullable', 'string', 'max:20'],
            'eleve.photo'          => ['nullable', 'string', 'max:255'],

            'eleve.groupe_sanguin'      => ['nullable', Rule::enum(GroupeSanguinEnum::class)],
            'eleve.allergies'           => ['nullable', 'string', 'max:2000'],
            'eleve.maladies_chroniques' => ['nullable', 'string', 'max:2000'],
            'eleve.aptitude_sportive'   => ['nullable', Rule::enum(AptitudeSportiveEnum::class)],
            'eleve.consignes_urgence'   => ['nullable', 'string', 'max:2000'],

            'eleve.statut_inscription' => ['sometimes', 'required', Rule::enum(StatutInscriptionEleveEnum::class)],

            'eleve.etablissement_origine' => [
                Rule::requiredIf(fn () => $this->statutEffectif() === StatutInscriptionEleveEnum::TRANSFERE->value),
                'nullable',
                'string',
                'max:255',
            ],

            'eleve.nom_pere'        => ['nullable', 'string', 'max:255'],
            'eleve.prenom_pere'     => ['nullable', 'string', 'max:255'],
            'eleve.telephone_pere'  => ['nullable', 'string', 'max:20'],
            'eleve.profession_pere' => ['nullable', 'string', 'max:255'],
            'eleve.adresse_pere'    => ['nullable', 'string', 'max:255'],

            'eleve.nom_mere'        => ['nullable', 'string', 'max:255'],
            'eleve.prenom_mere'     => ['nullable', 'string', 'max:255'],
            'eleve.telephone_mere'  => ['nullable', 'string', 'max:20'],
            'eleve.profession_mere' => ['nullable', 'string', 'max:255'],
            'eleve.adresse_mere'    => ['nullable', 'string', 'max:255'],

            // Bloc tuteur facultatif en modification : absent, la fiche
            // rattachee reste inchangee.
            //
            // Present, seul « lien_parente » est exige dans tous les cas :
            // c'est lui qui dit quel bloc fait foi. Quand le tuteur est le
            // pere ou la mere, ses coordonnees viennent du bloc parent et le
            // service les recopie (EleveService::completerDepuisParent) : les
            // exiger ici aussi rejetterait une modification parfaitement
            // valide, le formulaire ne les affichant meme pas.
            'tuteur'                      => ['sometimes', 'array'],
            'tuteur.id'                   => ['nullable', 'integer', 'exists:tuteurs,id'],
            'tuteur.lien_parente'         => ['required_with:tuteur', Rule::enum(LienParenteEnum::class)],

            // Ces quatre colonnes sont NOT NULL en base, mais le vide reste
            // tolere ici : quand le tuteur est le pere ou la mere, le
            // formulaire masque ces champs et les transmet vides, leurs
            // valeurs venant du bloc parent (completerDepuisParent). Les
            // exiger rejetterait une modification parfaitement valide.
            //
            // C'est EleveService::champsSoumis() qui ecarte ce qui resterait
            // vide, pour ne jamais ramener une colonne requise a null.
            'tuteur.nom' => [
                Rule::requiredIf(fn () => $this->tuteurEstUnTiers()),
                'nullable', 'string', 'max:255',
            ],
            'tuteur.prenom' => [
                Rule::requiredIf(fn () => $this->tuteurEstUnTiers()),
                'nullable', 'string', 'max:255',
            ],
            'tuteur.telephone_principal' => [
                Rule::requiredIf(fn () => $this->tuteurEstUnTiers() || $this->tuteurParentSansTelephone()),
                'nullable', 'string', 'max:20',
            ],
            'tuteur.adresse' => [
                Rule::requiredIf(fn () => $this->tuteurEstUnTiers()),
                'nullable', 'string', 'max:255',
            ],

            'tuteur.telephone_secondaire' => ['nullable', 'string', 'max:20'],
            'tuteur.email'                => ['nullable', 'email', 'max:255'],
            'tuteur.profession'           => ['nullable', 'string', 'max:255'],
            // Le NIN est unique en base. En modification, la fiche visee est
            // deja identifiee (par « tuteur.id » ou par celle rattachee a
            // l'eleve) : on l'exclut de l'unicite, sinon reenregistrer un
            // tuteur sans toucher a son NIN echouerait sur lui-meme. Un NIN
            // appartenant a un AUTRE tuteur reste refuse, avec un message
            // clair plutot qu'une violation de contrainte.
            'tuteur.nin'                  => [
                Rule::requiredIf(fn () => $this->tuteurEstUnTiers()),
                'nullable',
                'string',
                'max:30',
                Rule::unique('tuteurs', 'nin')->ignore($this->tuteurVise()),
            ],
        ];
    }

    /**
     * Une modification partielle peut basculer le statut en TRANSFERE sans
     * renvoyer l'etablissement d'origine, ou l'inverse : on confronte donc la
     * requete a la valeur deja persistee.
     */
    private function statutEffectif(): ?string
    {
        return $this->input('eleve.statut_inscription')
            ?? $this->eleve()?->statut_inscription?->value;
    }

    /**
     * La fiche tuteur que cette requete va mettre a jour : celle designee
     * explicitement, sinon celle deja rattachee a l'eleve. Sert a exclure la
     * fiche d'elle-meme lors du controle d'unicite du NIN.
     */
    private function tuteurVise(): ?int
    {
        return $this->input('tuteur.id') ?? $this->eleve()?->tuteur_id;
    }

    private function tuteurEstUnTiers(): bool
    {
        $lien = $this->input('tuteur.lien_parente');

        return $lien !== null && !in_array($lien, [
            LienParenteEnum::PERE->value,
            LienParenteEnum::MERE->value,
        ], true);
    }

    /**
     * Vrai lorsque le pere ou la mere devient tuteur sans qu'aucun numero ne
     * puisse lui etre attribue : ni dans le bloc parent (requete ou fiche deja
     * en base), ni sur une fiche tuteur existante, dont le telephone est
     * toujours renseigne. Le numero doit alors etre saisi dans le bloc tuteur.
     */
    private function tuteurParentSansTelephone(): bool
    {
        $champ = match ($this->input('tuteur.lien_parente')) {
            LienParenteEnum::PERE->value => 'telephone_pere',
            LienParenteEnum::MERE->value => 'telephone_mere',
            default => null,
        };

        if ($champ === null || $this->tuteurVise() !== null) {
            return false;
        }

        return blank($this->input("eleve.{$champ}") ?? $this->eleve()?->{$champ});
    }

    private function eleve(): ?Eleve
    {
        return $this->eleve ??= Eleve::find($this->route('id'));
    }

    public function messages(): array
    {
        return [
            'eleve.nom.required'            => "Le nom de l'élève est obligatoire.",
            'eleve.prenom.required'         => "Le prénom de l'élève est obligatoire.",
            'eleve.date_naissance.before'   => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'eleve.matricule.unique'        => 'Ce matricule est déjà attribué.',
            'eleve.groupe_sanguin.enum'     => 'Le groupe sanguin est invalide.',
            'eleve.aptitude_sportive.enum'  => "L'aptitude sportive est invalide.",
            'eleve.statut_inscription.enum' => "Le statut doit être : Nouveau, Redoublant, Réinscrit ou Transféré.",
            'eleve.etablissement_origine.required' => "L'établissement d'origine est obligatoire pour un élève transféré.",

            'tuteur.lien_parente.required_with'   => 'Le lien de parenté du tuteur est obligatoire.',
            'tuteur.nom.required'                 => "Le nom du tuteur est obligatoire lorsqu'il n'est ni le père ni la mère.",
            'tuteur.prenom.required'              => "Le prénom du tuteur est obligatoire lorsqu'il n'est ni le père ni la mère.",
            'tuteur.telephone_principal.required' => 'Le téléphone du tuteur est obligatoire.',
            'tuteur.adresse.required'             => "L'adresse du tuteur est obligatoire lorsqu'il n'est ni le père ni la mère.",
            'tuteur.email.email'                       => "L'adresse email du tuteur n'est pas valide.",
            'tuteur.nin.required'                      => "Le NIN du tuteur est obligatoire lorsque celui-ci n'est ni le père ni la mère.",
            'tuteur.id.exists'                         => 'Le tuteur sélectionné est invalide.',
            'tuteur.nin.unique'                        => 'Ce NIN est déjà attribué à un autre tuteur.',
        ];
    }
}
