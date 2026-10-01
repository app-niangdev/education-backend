<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code de vérification — {{ config('app.name') }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #1a56db; padding: 32px 40px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; }
        .body { padding: 40px; color: #374151; }
        .body p { line-height: 1.7; margin: 0 0 16px; }
        .code-wrap { text-align: center; margin: 32px 0; }
        .code { display: inline-block; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 20px 32px; font-size: 34px; font-weight: 700; letter-spacing: 10px; color: #111827; }
        .meta { text-align: center; font-size: 13px; color: #6b7280; margin-top: 12px; }
        .warning { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px 18px; border-radius: 4px; margin-top: 24px; font-size: 13px; color: #92400e; }
        .alert { background: #fef2f2; border-left: 4px solid #ef4444; padding: 14px 18px; border-radius: 4px; margin-top: 24px; font-size: 13px; color: #991b1b; }
        .footer { background: #f9fafb; padding: 24px 40px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{ config('app.name') }}</h1>
        </div>
        <div class="body">
            <p>Bonjour <strong>{{ $user->full_name }}</strong>,</p>

            @if($raison === 'IP_PREVIOUSLY_BLOCKED')
                <p>
                    Plusieurs tentatives de connexion infructueuses ont été enregistrées
                    récemment depuis votre réseau. Par précaution, cette connexion doit
                    être confirmée par le code ci-dessous.
                </p>
            @else
                <p>
                    La double authentification est activée sur votre compte. Saisissez le
                    code ci-dessous pour terminer votre connexion.
                </p>
            @endif

            <div class="code-wrap">
                <div class="code">{{ $code }}</div>
                <div class="meta">Ce code expire dans {{ $validiteMinutes }} minute{{ $validiteMinutes > 1 ? 's' : '' }}.</div>
            </div>

            @if($raison === 'IP_PREVIOUSLY_BLOCKED')
            <div class="alert">
                Si vous n'êtes pas à l'origine de cette connexion, changez votre mot de
                passe sans attendre et prévenez l'administrateur de l'établissement.
            </div>
            @endif

            <div class="warning">
                Ce code est strictement personnel. Aucun membre de
                {{ config('app.name') }} ne vous le demandera, ni par téléphone, ni par
                message.
            </div>
        </div>
        <div class="footer">
            Message automatique — merci de ne pas y répondre.<br>
            &copy; {{ date('Y') }} {{ config('app.name') }}
        </div>
    </div>
</body>
</html>
