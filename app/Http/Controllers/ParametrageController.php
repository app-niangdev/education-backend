<?php

namespace App\Http\Controllers;

use App\Helpers\ApiResponse;
use App\Http\Requests\UpdateParametrageRequest;
use App\Models\Parametrage;
use Illuminate\Http\JsonResponse;

class ParametrageController extends Controller
{
   public function infos(): JsonResponse
    {
        $parametrage = Parametrage::first();

        if (!$parametrage) {
            return ApiResponse::error("Aucun paramétrage trouvé.", 404);
        }

        return ApiResponse::success(
            $parametrage,
            "Paramétrage de l'établissement récupéré avec succès"
        );
    }

    public function update(UpdateParametrageRequest $request): JsonResponse
    {
        $parametrage = Parametrage::first();

        if (!$parametrage) {
            return ApiResponse::error("Aucun paramétrage trouvé.", 404);
        }

        $parametrage->update($request->validated());

        return ApiResponse::success($parametrage, "Paramétrage mis à jour avec succès.");
    }
}
