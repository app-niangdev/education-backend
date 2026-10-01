<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activez votre compte — {{ config('app.name') }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #1a56db; padding: 32px 40px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; }
        .body { padding: 40px; color: #374151; }
        .body p { line-height: 1.7; margin: 0 0 16px; }
        .btn-wrap { text-align: center; margin: 32px 0 8px; }
        .btn { display: inline-block; background: #1a56db; color: #ffffff !important; text-decoration: none; padding: 14px 32px; border-radius: 6px; font-size: 15px; font-weight: 600; }
        .fallback { font-size: 12px; color: #6b7280; word-break: break-all; background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px 16px; margin-top: 24px; }
        .meta { text-align: center; font-size: 13px; color: #6b7280; margin-top: 12px; }
        .identifiant { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 14px 18px; margin-top: 8px; font-size: 14px; }
        .warning { background: #fffbeb; border-left: 4px solid #f59e0b; padding: 14px 18px; border-radius: 4px; margin-top: 24px; font-size: 13px; color: #92400e; }
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

            <p>
                Un compte vient d'être ouvert à votre nom sur {{ config('app.name') }}.
                Pour y accéder, choisissez votre mot de passe en cliquant sur le
                bouton ci-dessous.
            </p>

            <div class="identifiant">
                Votre identifiant de connexion : <strong>{{ $user->email }}</strong>
            </div>

            <div class="btn-wrap">
                <a href="{{ $activationUrl }}" class="btn">Activer mon compte</a>
                <div class="meta">
                    Ce lien est valable {{ $validiteJours }} jours et ne peut servir qu'une fois.
                </div>
            </div>

            <div class="fallback">
                Si le bouton ne fonctionne pas, copiez cette adresse dans votre navigateur :<br>
                {{ $activationUrl }}
            </div>

            <div class="warning">
                Ce message ne contient aucun mot de passe : vous seul choisirez le
                vôtre. Si vous n'attendiez pas l'ouverture d'un compte, ignorez ce
                message et prévenez l'administration de l'établissement.
            </div>
        </div>
        <div class="footer">
            Message automatique — merci de ne pas y répondre.<br>
            &copy; {{ date('Y') }} {{ config('app.name') }}
        </div>
    </div>
</body>
</html>
