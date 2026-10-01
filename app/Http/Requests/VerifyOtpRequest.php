<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'challenge_token' => ['required', 'string', 'size:64'],
            // Six chiffres exactement : tout le reste est rejete avant meme
            // d'atteindre le challenge, et ne consomme donc pas d'essai.
            'otp'             => ['required', 'string', 'regex:/^\d{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'challenge_token.required' => 'Session de vérification introuvable.',
            'challenge_token.size'     => 'Session de vérification invalide.',
            'otp.required'             => 'Le code de vérification est obligatoire.',
            'otp.regex'                => 'Le code doit comporter 6 chiffres.',
        ];
    }
}
