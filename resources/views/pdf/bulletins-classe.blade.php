{{-- Les bulletins de toute une classe, une page par élève. --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Bulletins - {{ $classe->nom }} - {{ $periode->libelle }}</title>
    @include('pdf.partials.bulletin-styles')
</head>
<body>
    @foreach($bulletins as $bulletin)
        @include('pdf.partials.bulletin-corps', ['bulletin' => $bulletin])

        {{-- Pas de saut après le dernier : il produirait une page blanche. --}}
        @if(!$loop->last)
            <div style="page-break-after: always;"></div>
        @endif
    @endforeach

    <div class="footer">
        {{ $classe->nom }} — {{ $periode->libelle }} —
        Document généré le {{ now()->format('d/m/Y à H:i') }}
    </div>
</body>
</html>
