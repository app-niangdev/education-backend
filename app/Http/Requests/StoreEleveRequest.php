<?php

namespace App\Http\Requests;

use App\Enums\AptitudeSportiveEnum;
use App\Enums\GroupeSanguinEnum;
use App\Enums\LienParenteEnum;
use App\Enums\SexeEnum;
use App\Enums\StatutInscriptionEleveEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEleveRequest extends FormRequest
{
    /**
     * Le surveillant inscrit les eleves : c'est lui qui recoit les familles au
     * guichet et saisit la fiche. Il cree donc au meme titre que l'admin et le
     * manager, comme il la modifie deja. La suppression, elle, reste hors de
     * sa portee (voir EleveController::destroy).
     *
     * Le tresorier, lui, ne fait que consulter : la fiche eleve releve de la
     * scolarite, pas de la caisse.
     */
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager', 'supervisor']);
    }

    public function rules(): array
    {
        return [
            // --- Identite ---------------------------------------------------
            'eleve.matricule'      => ['nullable', 'string', 'max:50', 'unique:eleves,matricule'],
            'eleve.nom'            => ['required', 'string', 'max:255'],
            'eleve.prenom'         => ['required', 'string', 'max:255'],
            'eleve.date_naissance' => ['required', 'date', 'before:today'],
            'eleve.lieu_naissance' => ['nullable', 'string', 'max:255'],
            'eleve.sexe'           => ['required', Rule::enum(SexeEnum::class)],
            'eleve.nationalite'    => ['nullable', 'string', 'max:100'],
            'eleve.adresse'        => ['nullable', 'string', 'max:255'],
            'eleve.telephone'      => ['nullable', 'string', 'max:20'],
            'eleve.photo'          => ['nullable', 'string', 'max:255'],

            // --- Antecedents medicaux ---------------------------------------
            'eleve.groupe_sanguin'      => ['nullable', Rule::enum(GroupeSanguinEnum::class)],
            'eleve.allergies'           => ['nullable', 'string', 'max:2000'],
            'eleve.maladies_chroniques' => ['nullable', 'string', 'max:2000'],
            'eleve.aptitude_sportive'   => ['nullable', Rule::enum(AptitudeSportiveEnum::class)],
            'eleve.consignes_urgence'   => ['nullable', 'string', 'max:2000'],

            // --- Scolarite --------------------------------------------------
            'eleve.statut_inscription' => ['nullable', Rule::enum(StatutInscriptionEleveEnum::class)],

            // Obligatoire des lors que l'eleve arrive d'un autre etablissement.
            'eleve.etablissement_origine' => [
                Rule::requiredIf(fn () => $this->statutEleve() === StatutInscriptionEleveEnum::TRANSFERE->value),
                'nullable',
                'string',
                'max:255',
            ],

            // --- Pere -------------------------------------------------------
            //
            // Le bloc devient obligatoire lorsque le pere est designe comme
            // tuteur : c'est alors la seule source des coordonnees du
            // responsable legal, et une fiche tuteur sans nom ni telephone
            // serait inutilisable (ni recouvrement, ni messagerie).
            'eleve.nom_pere' => [
                Rule::requiredIf(fn () => $this->lienTuteur() === LienParenteEnum::PERE->value),
                'nullable', 'string', 'max:255',
            ],
            'eleve.prenom_pere' => [
                Rule::requiredIf(fn () => $this->lienTuteur() === LienParenteEnum::PERE->value),
                'nullable', 'string', 'max:255',
            ],
            // Le telephone du tuteur est exige dans tous les cas. Celui du
            // parent designe en tient lieu ; a defaut, l'agent l'a saisi sous
            // « tuteur.telephone_principal » et le bloc parent peut rester vide.
            'eleve.telephone_pere' => [
                Rule::requiredIf(fn () => $this->telephoneParentRequis(LienParenteEnum::PERE)),
                'nullable', 'string', 'max:20',
            ],
            'eleve.profession_pere' => ['nullable', 'string', 'max:255'],
            'eleve.adresse_pere'    => ['nullable', 'string', 'max:255'],

            // --- Mere -------------------------------------------------------
            'eleve.nom_mere' => [
                Rule::requiredIf(fn () => $this->lienTuteur() === LienParenteEnum::MERE->value),
                'nullable', 'string', 'max:255',
            ],
            'eleve.prenom_mere' => [
                Rule::requiredIf(fn () => $this->lienTuteur() === LienParenteEnum::MERE->value),
                'nullable', 'string', 'max:255',
            ],
            'eleve.telephone_mere' => [
                Rule::requiredIf(fn () => $this->telephoneParentRequis(LienParenteEnum::MERE)),
                'nullable', 'string', 'max:20',
            ],
            'eleve.profession_mere' => ['nullable', 'string', 'max:255'],
            'eleve.adresse_mere'    => ['nullable', 'string', 'max:255'],

            // --- Tuteur legal -----------------------------------------------
            //
            // Quand le tuteur est le pere ou la mere, ses coordonnees sont
            // DEJA saisies dans le bloc parent correspondant : les redemander
            // ici ferait saisir deux fois la meme chose, avec le risque que
            // les deux versions divergent. Le service recopie alors le bloc
            // parent vers la fiche tuteur (voir EleveService::resoudreTuteur).
            //
            // Seul « lien_parente » reste exige dans tous les cas : c'est lui
            // qui dit quel bloc fait foi.
            'tuteur'                      => ['required', 'array'],

            // Exige, sauf quand une fiche existante est rattachee : le lien
            // est alors deja porte par le tuteur enregistre.
            'tuteur.lien_parente'         => [
                Rule::requiredIf(fn () => !$this->rattacheUnTuteurExistant()),
                'nullable',
                Rule::enum(LienParenteEnum::class),
            ],

            // Ces quatre colonnes sont NOT NULL en base, mais le vide reste
            // tolere ici : quand le tuteur est le pere ou la mere — y compris
            // une fiche existante rattachee — le formulaire masque ces champs
            // et les transmet vides, leurs valeurs venant du bloc parent.
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
                Rule::requiredIf(fn () => $this->tuteurEstUnTiers()),
                'nullable', 'string', 'max:20',
            ],
            'tuteur.adresse' => [
                Rule::requiredIf(fn () => $this->tuteurEstUnTiers()),
                'nullable', 'string', 'max:255',
            ],

            'tuteur.telephone_secondaire' => ['nullable', 'string', 'max:20'],
            'tuteur.email'                => ['nullable', 'email', 'max:255'],
            'tuteur.profession'           => ['nullable', 'string', 'max:255'],

            // Le NIN scelle les engagements financiers : il devient
            // indispensable quand le responsable n'est pas un parent direct.
            // Pas de regle « unique » : un NIN deja connu identifie le tuteur
            // d'une fratrie, que le service reutilise au lieu de le dupliquer.
            'tuteur.nin' => [
                Rule::requiredIf(fn () => $this->tuteurEstUnTiers()),
                'nullable',
                'string',
                'max:30',
            ],

            // Reutilisation explicite d'une fiche tuteur existante.
            'tuteur.id' => ['nullable', 'integer', 'exists:tuteurs,id'],
        ];
    }

    protected function statutEleve(): ?string
    {
        return $this->input('eleve.statut_inscription');
    }

    /** Le lien declare entre le tuteur et l'eleve. */
    protected function lienTuteur(): ?string
    {
        return $this->input('tuteur.lien_parente');
    }

    /**
     * Vrai lorsque le responsable n'est ni le pere ni la mere : l'eleve vit
     * alors chez un tiers, et le bloc tuteur est la seule source de ses
     * coordonnees — il doit donc etre saisi entierement.
     *
     * Sauf lorsqu'une fiche existante est rattachee (« tuteur.id ») : les
     * coordonnees sont deja en base, les redemander interdirait justement le
     * rattachement d'une fratrie a son tuteur connu.
     */
    protected function tuteurEstUnTiers(): bool
    {
        if ($this->rattacheUnTuteurExistant()) {
            return false;
        }

        $lien = $this->lienTuteur();

        return $lien !== null && !in_array($lien, [
            LienParenteEnum::PERE->value,
            LienParenteEnum::MERE->value,
        ], true);
    }

    /**
     * Vrai lorsque le telephone du parent designe comme tuteur est la seule
     * source possible du numero du responsable : aucun n'a ete saisi dans le
     * bloc tuteur. Un tuteur sans telephone est injoignable, et prive de
     * l'identifiant qui lui ouvre l'espace famille.
     */
    protected function telephoneParentRequis(LienParenteEnum $parent): bool
    {
        return $this->lienTuteur() === $parent->value
            && blank($this->input('tuteur.telephone_principal'));
    }

    /** L'agent a choisi un tuteur deja enregistre dans l'annuaire. */
    protected function rattacheUnTuteurExistant(): bool
    {
        return filled($this->input('tuteur.id'));
    }

    public function messages(): array
    {
        return [
            'eleve.nom.required'            => "Le nom de l'élève est obligatoire.",
            'eleve.prenom.required'         => "Le prénom de l'élève est obligatoire.",
            'eleve.date_naissance.required' => 'La date de naissance est obligatoire.',
            'eleve.date_naissance.before'   => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'eleve.sexe.required'           => 'Le sexe est obligatoire.',
            'eleve.matricule.unique'        => 'Ce matricule est déjà attribué.',
            'eleve.groupe_sanguin.enum'     => 'Le groupe sanguin est invalide.',
            'eleve.aptitude_sportive.enum'  => "L'aptitude sportive est invalide.",
            'eleve.statut_inscription.enum' => "Le statut doit être : Nouveau, Redoublant, Réinscrit ou Transféré.",
            'eleve.etablissement_origine.required' => "L'établissement d'origine est obligatoire pour un élève transféré.",

            // Le bloc parent fait foi quand le tuteur est le père ou la mère :
            // les messages le disent, pour que l'agent sache où corriger.
            'eleve.nom_pere.required'       => "Le nom du père est obligatoire : il est désigné comme tuteur légal.",
            'eleve.prenom_pere.required'    => "Le prénom du père est obligatoire : il est désigné comme tuteur légal.",
            'eleve.telephone_pere.required' => "Le téléphone du tuteur est obligatoire : renseignez celui du père, désigné comme tuteur légal.",
            'eleve.nom_mere.required'       => "Le nom de la mère est obligatoire : elle est désignée comme tutrice légale.",
            'eleve.prenom_mere.required'    => "Le prénom de la mère est obligatoire : elle est désignée comme tutrice légale.",
            'eleve.telephone_mere.required' => "Le téléphone du tuteur est obligatoire : renseignez celui de la mère, désignée comme tutrice légale.",

            'tuteur.required'                     => 'Les informations du tuteur légal sont obligatoires.',
            'tuteur.lien_parente.required'        => 'Le lien de parenté du tuteur est obligatoire.',
            'tuteur.lien_parente.enum'            => 'Le lien de parenté est invalide.',
            'tuteur.nom.required'                 => "Le nom du tuteur est obligatoire lorsqu'il n'est ni le père ni la mère.",
            'tuteur.prenom.required'              => "Le prénom du tuteur est obligatoire lorsqu'il n'est ni le père ni la mère.",
            'tuteur.telephone_principal.required' => 'Le téléphone du tuteur est obligatoire.',
            'tuteur.adresse.required'             => "L'adresse du tuteur est obligatoire lorsqu'il n'est ni le père ni la mère.",
            'tuteur.email.email'                  => "L'adresse email du tuteur n'est pas valide.",
            'tuteur.nin.required'                 => "Le NIN du tuteur est obligatoire lorsque celui-ci n'est ni le père ni la mère.",
            'tuteur.id.exists'                    => 'Le tuteur sélectionné est invalide.',
        ];
    }
}
