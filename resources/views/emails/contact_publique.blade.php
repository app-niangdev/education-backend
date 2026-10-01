<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouveau message depuis le site — {{ config('app.name') }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.08); }
        .header { background: #1a56db; padding: 32px 40px; text-align: center; }
        .header h1 { color: #ffffff; margin: 0; font-size: 24px; }
        .body { padding: 40px; color: #374151; }
        .body p { line-height: 1.7; margin: 0 0 16px; }
        .champ { margin-bottom: 20px; }
        .champ-label { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #9ca3af; margin: 0 0 4px; }
        .champ-valeur { font-size: 15px; color: #111827; font-weight: 600; margin: 0; }
        .message { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 16px 18px; white-space: pre-wrap; line-height: 1.7; font-size: 14px; color: #374151; }
        .footer { background: #f9fafb; padding: 24px 40px; text-align: center; font-size: 12px; color: #9ca3af; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Nouveau message depuis le site</h1>
        </div>
        <div class="body">
            <div class="champ">
                <p class="champ-label">Expéditeur</p>
                <p class="champ-valeur"><a href="mailto:{{ $expediteurEmail }}">{{ $expediteurEmail }}</a></p>
            </div>

            <div class="champ">
                <p class="champ-label">Titre</p>
                <p class="champ-valeur">{{ $titre }}</p>
            </div>

            <div class="champ">
                <p class="champ-label">Message</p>
                <div class="message">{{ $messageContact }}</div>
            </div>
        </div>
        <div class="footer">
            Répondre à ce message écrit directement à {{ $expediteurEmail }}.<br>
            &copy; {{ date('Y') }} {{ config('app.name') }}
        </div>
    </div>
</body>
</html>
