{{-- Le bulletin d'un seul élève. --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletin - {{ $bulletin->nom_complet }}</title>
    @include('pdf.partials.bulletin-styles')
</head>
<body>
    @include('pdf.partials.bulletin-corps', ['bulletin' => $bulletin])

    <div class="footer">
        Document généré le {{ now()->format('d/m/Y à H:i') }}
    </div>
</body>
</html>
