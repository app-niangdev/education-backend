<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Mail\ContactPubliqueMail;
use App\Models\Etablissement;
use App\Models\Parametrage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class EtablissementController extends Controller
{
    public function index(): JsonResponse
    {
        $etablissement = Etablissement::with('media')->first();

        if (!$etablissement) {
            return ApiResponse::error("Aucun établissement trouvé.", 404);
        }

        $data = $etablissement->toArray();
        $data['logo'] = $etablissement->getFirstMediaUrl('logo');

        $data['code_couleur'] = Parametrage::value('code_couleur');
        $data['en_maintenance'] = (bool) Parametrage::value('en_maintenance');

        return ApiResponse::success($data, 'Informations de l\'établissement récupérées avec succès');
    }

    /**
     * Enregistre l'établissement initial.
     *
     * L'application ne gère qu'un seul établissement (cf. index() qui fait un
     * first()) : on refuse la création s'il en existe déjà un, la mise à jour
     * devant alors passer par update().
     */
    public function store(Request $request): JsonResponse
    {
        if (Etablissement::exists()) {
            return ApiResponse::error("Un établissement est déjà enregistré.", 409);
        }

        $validator = Validator::make($request->all(), [
            'nom'                           => 'required|string|max:255',
            'nom_court'                     => 'nullable|string|max:100',
            'slogan'                        => 'nullable|string|max:255',
            'adresse'                       => 'nullable|string|max:255',
            'email'                         => 'nullable|email|unique:etablissements,email',
            'telephone_principal'           => 'required|string|max:20',
            'telephone_secondaire'          => 'required|string|max:20',
            'site_web'                      => 'required|string|max:255',
            'lien_facebook'                 => 'nullable|url|max:255',
            'lien_instagram'                => 'nullable|url|max:255',
            'inspection_academique'         => 'required|string|max:255',
            'inspection_education_formation'=> 'required|string|max:255',
            'logo'                          => 'nullable|image|mimes:jpeg,png,jpg,svg,webp|max:2048',
        ], [
            'nom.required'                            => 'Le nom de l\'établissement est obligatoire.',
            'nom.max'                                 => 'Le nom ne peut pas dépasser 255 caractères.',
            'nom_court.max'                           => 'Le nom court ne peut pas dépasser 100 caractères.',
            'slogan.max'                              => 'Le slogan ne peut pas dépasser 255 caractères.',
            'adresse.max'                             => 'L\'adresse ne peut pas dépasser 255 caractères.',
            'email.email'                             => 'L\'adresse e-mail n\'est pas valide.',
            'email.unique'                            => 'Cette adresse e-mail est déjà utilisée.',
            'telephone_principal.required'            => 'Le téléphone principal est obligatoire.',
            'telephone_principal.max'                 => 'Le téléphone principal ne peut pas dépasser 20 caractères.',
            'telephone_secondaire.required'           => 'Le téléphone secondaire est obligatoire.',
            'telephone_secondaire.max'                => 'Le téléphone secondaire ne peut pas dépasser 20 caractères.',
            'site_web.required'                       => 'Le site web est obligatoire.',
            'site_web.max'                            => 'Le site web ne peut pas dépasser 255 caractères.',
            'lien_facebook.url'                       => 'Le lien Facebook n\'est pas une URL valide.',
            'lien_instagram.url'                      => 'Le lien Instagram n\'est pas une URL valide.',
            'inspection_academique.required'          => 'L\'inspection académique est obligatoire.',
            'inspection_academique.max'               => 'L\'inspection académique ne peut pas dépasser 255 caractères.',
            'inspection_education_formation.required' => 'L\'inspection de l\'éducation et de la formation est obligatoire.',
            'inspection_education_formation.max'      => 'L\'inspection de l\'éducation et de la formation ne peut pas dépasser 255 caractères.',
            'logo.image'                              => 'Le logo doit être une image.',
            'logo.mimes'                              => 'Le logo doit être au format jpeg, png, jpg, svg ou webp.',
            'logo.max'                                => 'Le logo ne peut pas dépasser 2 Mo.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Erreur de validation.', 422, $validator->errors()->toArray());
        }

        $etablissement = Etablissement::create($validator->safe()->except('logo'));

        if ($request->hasFile('logo')) {
            $etablissement->addMediaFromRequest('logo')
                ->toMediaCollection('logo');
        }

        $data = $etablissement->fresh('media')->toArray();
        $data['logo'] = $etablissement->getFirstMediaUrl('logo');

        return ApiResponse::success($data, 'Établissement enregistré avec succès', 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $etablissement = Etablissement::findOrFail($id);

        if (!$etablissement) {
            return ApiResponse::error("Établissement introuvable.", 404);
        }

        $validator = Validator::make($request->all(), [
            'nom'                           => 'sometimes|required|string|max:255',
            'nom_court'                     => 'nullable|string|max:100',
            'slogan'                        => 'nullable|string|max:255',
            'adresse'                       => 'nullable|string|max:255',
            'email'                         => 'nullable|email|unique:etablissements,email,' . $id,
            'telephone_principal'           => 'sometimes|required|string|max:20',
            'telephone_secondaire'          => 'sometimes|required|string|max:20',
            'site_web'                      => 'sometimes|required|string|max:255',
            'lien_facebook'                 => 'nullable|url|max:255',
            'lien_instagram'                => 'nullable|url|max:255',
            'inspection_academique'         => 'sometimes|required|string|max:255',
            'inspection_education_formation'=> 'sometimes|required|string|max:255',
            'logo'                          => 'nullable|image|mimes:jpeg,png,jpg,svg,webp|max:2048',
        ], [
            'nom.required'                            => 'Le nom de l\'établissement est obligatoire.',
            'nom.string'                              => 'Le nom doit être une chaîne de caractères.',
            'nom.max'                                 => 'Le nom ne peut pas dépasser 255 caractères.',
            'nom_court.string'                        => 'Le nom court doit être une chaîne de caractères.',
            'nom_court.max'                           => 'Le nom court ne peut pas dépasser 100 caractères.',
            'slogan.string'                           => 'Le slogan doit être une chaîne de caractères.',
            'slogan.max'                              => 'Le slogan ne peut pas dépasser 255 caractères.',
            'adresse.string'                          => 'L\'adresse doit être une chaîne de caractères.',
            'adresse.max'                             => 'L\'adresse ne peut pas dépasser 255 caractères.',
            'email.email'                             => 'L\'adresse e-mail n\'est pas valide.',
            'email.unique'                            => 'Cette adresse e-mail est déjà utilisée.',
            'telephone_principal.required'            => 'Le téléphone principal est obligatoire.',
            'telephone_principal.string'              => 'Le téléphone principal doit être une chaîne de caractères.',
            'telephone_principal.max'                 => 'Le téléphone principal ne peut pas dépasser 20 caractères.',
            'telephone_secondaire.required'           => 'Le téléphone secondaire est obligatoire.',
            'telephone_secondaire.string'             => 'Le téléphone secondaire doit être une chaîne de caractères.',
            'telephone_secondaire.max'                => 'Le téléphone secondaire ne peut pas dépasser 20 caractères.',
            'site_web.required'                       => 'Le site web est obligatoire.',
            'site_web.string'                         => 'Le site web doit être une chaîne de caractères.',
            'site_web.max'                            => 'Le site web ne peut pas dépasser 255 caractères.',
            'lien_facebook.url'                       => 'Le lien Facebook n\'est pas une URL valide.',
            'lien_facebook.max'                       => 'Le lien Facebook ne peut pas dépasser 255 caractères.',
            'lien_instagram.url'                      => 'Le lien Instagram n\'est pas une URL valide.',
            'lien_instagram.max'                      => 'Le lien Instagram ne peut pas dépasser 255 caractères.',
            'inspection_academique.required'          => 'L\'inspection académique est obligatoire.',
            'inspection_academique.string'            => 'L\'inspection académique doit être une chaîne de caractères.',
            'inspection_academique.max'               => 'L\'inspection académique ne peut pas dépasser 255 caractères.',
            'inspection_education_formation.required' => 'L\'inspection de l\'éducation et de la formation est obligatoire.',
            'inspection_education_formation.string'   => 'L\'inspection de l\'éducation et de la formation doit être une chaîne de caractères.',
            'inspection_education_formation.max'      => 'L\'inspection de l\'éducation et de la formation ne peut pas dépasser 255 caractères.',
            'logo.image'                              => 'Le logo doit être une image.',
            'logo.mimes'                              => 'Le logo doit être au format jpeg, png, jpg, svg ou webp.',
            'logo.max'                                => 'Le logo ne peut pas dépasser 2 Mo.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Erreur de validation.', 422, $validator->errors()->toArray());
        }

        $etablissement->update($validator->safe()->except('logo'));

        if ($request->hasFile('logo')) {
            $etablissement->addMediaFromRequest('logo')
                ->toMediaCollection('logo');
        }

        $data = $etablissement->fresh('media')->toArray();
        $data['logo'] = $etablissement->getFirstMediaUrl('logo');

        return ApiResponse::success($data, 'Établissement mis à jour avec succès');
    }

    public function updateLogo(Request $request, string $id): JsonResponse
    {
        $etablissement = Etablissement::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'logo' => 'required|image|mimes:jpeg,png,jpg,svg,webp|max:2048',
        ], [
            'logo.required' => 'Le logo est obligatoire.',
            'logo.image'    => 'Le logo doit être une image.',
            'logo.mimes'    => 'Le logo doit être au format jpeg, png, jpg, svg ou webp.',
            'logo.max'      => 'Le logo ne peut pas dépasser 2 Mo.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Erreur de validation.', 422, $validator->errors()->toArray());
        }

        // La collection est en singleFile() : l'ancien logo est remplacé automatiquement.
        $etablissement->addMediaFromRequest('logo')
            ->toMediaCollection('logo');

        $data = $etablissement->fresh('media')->toArray();
        $data['logo'] = $etablissement->getFirstMediaUrl('logo');

        return ApiResponse::success($data, 'Logo mis à jour avec succès');
    }

    /**
     * Formulaire de contact de la page d'accueil.
     *
     * Route publique : le visiteur n'a pas de compte. Le destinataire n'est
     * jamais choisi par la requête, il est lu sur l'établissement — sans quoi
     * l'endpoint deviendrait un relais de courrier ouvert. L'adresse saisie
     * n'est là que pour identifier l'expéditeur et lui répondre.
     */
    public function contact(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'   => 'required|email|max:255',
            'titre'   => 'required|string|min:3|max:150',
            'message' => 'required|string|min:10|max:5000',
        ], [
            'email.required'   => 'L\'adresse email est obligatoire.',
            'email.email'      => 'L\'adresse email n\'est pas valide.',
            'email.max'        => 'L\'adresse email ne peut pas dépasser 255 caractères.',
            'titre.required'   => 'Le titre est obligatoire.',
            'titre.min'        => 'Le titre doit contenir au moins 3 caractères.',
            'titre.max'        => 'Le titre ne peut pas dépasser 150 caractères.',
            'message.required' => 'Le message est obligatoire.',
            'message.min'      => 'Le message doit contenir au moins 10 caractères.',
            'message.max'      => 'Le message ne peut pas dépasser 5000 caractères.',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('Erreur de validation.', 422, $validator->errors()->toArray());
        }

        $etablissement = Etablissement::first();

        if (!$etablissement || !$etablissement->email) {
            return ApiResponse::error(
                "Aucune adresse de contact n'est configurée pour l'établissement.",
                503
            );
        }

        $valides = $validator->validated();

        try {
            Mail::to($etablissement->email)->send(new ContactPubliqueMail(
                expediteurEmail: $valides['email'],
                titre:           $valides['titre'],
                messageContact:  $valides['message'],
            ));
        } catch (\Throwable $e) {
            Log::error('Echec envoi du formulaire de contact.', [
                'expediteur' => $valides['email'],
                'erreur'     => $e->getMessage(),
            ]);

            return ApiResponse::error(
                "L'envoi du message a échoué. Merci de réessayer plus tard.",
                500
            );
        }

        return ApiResponse::success(null, 'Votre message a bien été envoyé.');
    }
}
