<?php

namespace App\Http\Requests;

use App\Enums\AptitudeSportiveEnum;
use App\Enums\GroupeSanguinEnum;
use App\Enums\LienParenteEnum;
use App\Enums\SexeEnum;
use App\Enums\StatutInscriptionEleveEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reprise de donnees : import en masse des eleves deja scolarises.
 *
 * Le fichier Excel est lu et mis en forme par le navigateur ; ce qui arrive
 * ici est deja du JSON, une ligne du tableur par entree de « lignes ».
 *
 * La validation reste volontairement plus souple que StoreEleveRequest sur
 * deux points, parce qu'un import n'est pas une saisie au guichet :
 *
 *  - Pas de « unique:eleves,matricule ». Un matricule deja connu n'est pas
 *    une erreur de saisie a corriger mais un eleve deja repris : le service
 *    passe la ligne et le signale dans le rapport (voir EleveService::importer).
 *    La regle unique ferait echouer tout le lot pour un cas prevu.
 *
 *  - Le bloc tuteur est facultatif. Les anciens registres ne le portent pas
 *    toujours ; l'eleve est alors cree sans responsable rattache, et l'ecole
 *    completera la fiche. Refuser la ligne bloquerait la reprise entiere sur
 *    une donnee que le fichier d'origine n'avait pas.
 *
 * Ce qui reste exige, c'est ce sans quoi la fiche ne vaut rien : le nom, le
 * prenom, la date de naissance et le sexe.
 */
class ImportElevesRequest extends FormRequest
{
    /**
     * Une reprise de donnees engage tout le fichier d'un coup : elle reste
     * entre les mains de l'admin et du manager. Le surveillant et le tresorier
     * creent au guichet, fiche par fiche — c'est un autre geste.
     */
    public function authorize(): bool
    {
        return in_array($this->user()->role?->name, ['admin', 'manager'], true);
    }

    public function rules(): array
    {
        return [
            'lignes'   => ['required', 'array', 'min:1', 'max:' . self::MAX_LIGNES],
            'lignes.*' => ['array'],

            // Numero de la ligne dans le tableur d'origine. Sans lui, un
            // rapport d'erreur renverrait l'agent a un index de tableau que
            // rien ne relie a ce qu'il a sous les yeux dans Excel.
            'lignes.*.ligne' => ['nullable', 'integer', 'min:1'],

            // --- Identite ---------------------------------------------------
            'lignes.*.eleve'                => ['required', 'array'],
            'lignes.*.eleve.matricule'      => ['nullable', 'string', 'max:50'],
            'lignes.*.eleve.nom'            => ['required', 'string', 'max:255'],
            'lignes.*.eleve.prenom'         => ['required', 'string', 'max:255'],
            'lignes.*.eleve.date_naissance' => ['required', 'date', 'before:today'],
            'lignes.*.eleve.lieu_naissance' => ['nullable', 'string', 'max:255'],
            'lignes.*.eleve.sexe'           => ['required', Rule::enum(SexeEnum::class)],
            'lignes.*.eleve.nationalite'    => ['nullable', 'string', 'max:100'],
            'lignes.*.eleve.adresse'        => ['nullable', 'string', 'max:255'],
            'lignes.*.eleve.telephone'      => ['nullable', 'string', 'max:20'],

            // --- Antecedents medicaux ---------------------------------------
            'lignes.*.eleve.groupe_sanguin'      => ['nullable', Rule::enum(GroupeSanguinEnum::class)],
            'lignes.*.eleve.allergies'           => ['nullable', 'string', 'max:2000'],
            'lignes.*.eleve.maladies_chroniques' => ['nullable', 'string', 'max:2000'],
            'lignes.*.eleve.aptitude_sportive'   => ['nullable', Rule::enum(AptitudeSportiveEnum::class)],
            'lignes.*.eleve.consignes_urgence'   => ['nullable', 'string', 'max:2000'],

            // --- Scolarite --------------------------------------------------
            'lignes.*.eleve.statut_inscription'    => ['nullable', Rule::enum(StatutInscriptionEleveEnum::class)],
            'lignes.*.eleve.etablissement_origine' => ['nullable', 'string', 'max:255'],

            // --- Pere / Mere ------------------------------------------------
            'lignes.*.eleve.nom_pere'        => ['nullable', 'string', 'max:255'],
            'lignes.*.eleve.prenom_pere'     => ['nullable', 'string', 'max:255'],
            'lignes.*.eleve.telephone_pere'  => ['nullable', 'string', 'max:20'],
            'lignes.*.eleve.profession_pere' => ['nullable', 'string', 'max:255'],
            'lignes.*.eleve.adresse_pere'    => ['nullable', 'string', 'max:255'],

            'lignes.*.eleve.nom_mere'        => ['nullable', 'string', 'max:255'],
            'lignes.*.eleve.prenom_mere'     => ['nullable', 'string', 'max:255'],
            'lignes.*.eleve.telephone_mere'  => ['nullable', 'string', 'max:20'],
            'lignes.*.eleve.profession_mere' => ['nullable', 'string', 'max:255'],
            'lignes.*.eleve.adresse_mere'    => ['nullable', 'string', 'max:255'],

            // --- Tuteur legal -----------------------------------------------
            //
            // Facultatif, mais des qu'il est present le lien de parente est
            // exige : c'est lui qui dit ou le service doit chercher les
            // coordonnees — bloc pere, bloc mere, ou le bloc tuteur lui-meme.
            'lignes.*.tuteur'              => ['nullable', 'array'],
            'lignes.*.tuteur.lien_parente' => [
                'required_with:lignes.*.tuteur',
                Rule::enum(LienParenteEnum::class),
            ],
            'lignes.*.tuteur.nom'                  => ['nullable', 'string', 'max:255'],
            'lignes.*.tuteur.prenom'               => ['nullable', 'string', 'max:255'],
            'lignes.*.tuteur.telephone_principal'  => ['nullable', 'string', 'max:20'],
            'lignes.*.tuteur.telephone_secondaire' => ['nullable', 'string', 'max:20'],
            'lignes.*.tuteur.email'                => ['nullable', 'email', 'max:255'],
            'lignes.*.tuteur.profession'           => ['nullable', 'string', 'max:255'],
            'lignes.*.tuteur.adresse'              => ['nullable', 'string', 'max:255'],

            // Pas de « unique » : un NIN deja connu designe le tuteur d'une
            // fratrie, que le service reutilise au lieu de le dupliquer.
            'lignes.*.tuteur.nin' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * Plafond par envoi. Chaque ligne ouvre sa propre transaction et peut
     * creer un tuteur : au-dela, la requete tiendrait la connexion trop
     * longtemps. Le navigateur decoupe les gros fichiers en plusieurs lots.
     */
    public const MAX_LIGNES = 500;

    public function messages(): array
    {
        return [
            'lignes.required' => 'Aucune ligne à importer.',
            'lignes.min'      => 'Aucune ligne à importer.',
            'lignes.max'      => 'Un import ne peut dépasser ' . self::MAX_LIGNES . ' lignes à la fois.',

            'lignes.*.eleve.required'               => "Les informations de l'élève sont obligatoires.",
            'lignes.*.eleve.nom.required'           => "Le nom de l'élève est obligatoire.",
            'lignes.*.eleve.prenom.required'        => "Le prénom de l'élève est obligatoire.",
            'lignes.*.eleve.date_naissance.required' => 'La date de naissance est obligatoire.',
            'lignes.*.eleve.date_naissance.date'    => "La date de naissance n'est pas une date valide.",
            'lignes.*.eleve.date_naissance.before'  => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'lignes.*.eleve.sexe.required'          => 'Le sexe est obligatoire.',
            'lignes.*.eleve.sexe.enum'              => 'Le sexe doit être M ou F.',
            'lignes.*.eleve.groupe_sanguin.enum'    => 'Le groupe sanguin est invalide.',
            'lignes.*.eleve.aptitude_sportive.enum' => "L'aptitude sportive est invalide.",
            'lignes.*.eleve.statut_inscription.enum' => 'Le statut doit être : Nouveau, Redoublant, Réinscrit ou Transféré.',

            'lignes.*.tuteur.lien_parente.required_with' => 'Le lien de parenté du tuteur est obligatoire.',
            'lignes.*.tuteur.lien_parente.enum'          => 'Le lien de parenté est invalide.',
            'lignes.*.tuteur.email.email'                => "L'adresse email du tuteur n'est pas valide.",
        ];
    }

    /**
     * Les messages ci-dessus nomment le champ, mais pas la ligne du tableur.
     * On la prefixe ici : « Ligne 12 : le sexe doit être M ou F. » est
     * exploitable tel quel, « lignes.11.eleve.sexe » ne l'est pas.
     *
     * L'index du tableau part de 0 et ignore l'en-tete du fichier : on lui
     * prefere le numero que le navigateur a transmis avec la ligne, qui est
     * celui affiche dans Excel. A defaut, +2 le reconstitue (index 0 =
     * ligne 2, l'en-tete occupant la premiere).
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        $erreurs = [];

        foreach ($validator->errors()->messages() as $champ => $messages) {
            if (!preg_match('/^lignes\.(\d+)/', $champ, $correspondance)) {
                $erreurs[$champ] = $messages;

                continue;
            }

            $index  = (int) $correspondance[1];
            $numero = $this->input("lignes.{$index}.ligne") ?? $index + 2;

            $erreurs[$champ] = array_map(
                fn (string $message) => "Ligne {$numero} : " . lcfirst($message),
                $messages,
            );
        }

        throw new \Illuminate\Validation\ValidationException(
            $validator,
            response()->json([
                'status'  => 422,
                'message' => "Le fichier comporte des lignes invalides : aucun élève n'a été importé.",
                'errors'  => $erreurs,
            ], 422),
        );
    }
}
