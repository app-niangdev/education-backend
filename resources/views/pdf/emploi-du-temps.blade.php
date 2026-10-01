<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Emploi du temps - {{ $classe->nom }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #1f2937; font-size: 11px; }

        .header {
            border-bottom: 2px solid {{ $couleur }};
            padding-bottom: 10px;
            margin-bottom: 16px;
        }
        .header .etab { font-size: 16px; font-weight: bold; color: {{ $couleur }}; }
        .header .slogan { font-size: 10px; color: #6b7280; font-style: italic; }
        .header .titre { font-size: 14px; font-weight: bold; margin-top: 8px; }
        .header .meta { font-size: 10px; color: #4b5563; margin-top: 2px; }

        /* Logo de l'établissement, à côté de son nom dans l'en-tête. */
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { border: none; padding: 0; vertical-align: middle; }
        .logo-cell { width: 40px; padding-right: 8px !important; }
        .logo { max-width: 36px; max-height: 36px; }

        table { width: 100%; border-collapse: collapse; }
        th, td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }
        thead th {
            background: {{ $couleur }};
            color: #fff;
            font-size: 10px;
            text-transform: uppercase;
        }
        .jour-cell {
            background: {{ $couleur_claire }};
            font-weight: bold;
            color: {{ $couleur_foncee }};
            width: 90px;
            white-space: nowrap;
        }
        .heure { font-weight: bold; white-space: nowrap; }
        .matiere { font-weight: bold; }
        .enseignant { color: #4b5563; font-size: 10px; }
        .salle { color: #6b7280; font-size: 10px; }

        .empty {
            text-align: center;
            padding: 30px;
            color: #9ca3af;
            font-style: italic;
        }
        .footer {
            position: fixed;
            bottom: -20px; left: 0; right: 0;
            text-align: center;
            font-size: 9px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                @if($logo ?? null)
                    <td class="logo-cell"><img src="{{ $logo }}" class="logo" alt="Logo"></td>
                @endif
                <td>
                    @if($etablissement)
                        <div class="etab">{{ $etablissement->nom }}</div>
                        @if($etablissement->slogan)
                            <div class="slogan">{{ $etablissement->slogan }}</div>
                        @endif
                    @endif
                </td>
            </tr>
        </table>
        <div class="titre">Emploi du temps — Classe {{ $classe->nom }}</div>
        <div class="meta">
            @if($classe->niveau){{ $classe->niveau->nom }} &bull; @endif
            @if($classe->anneeScolaire)Année scolaire {{ $classe->anneeScolaire->nom }}@endif
        </div>
    </div>

    @if(empty($parJour))
        <div class="empty">Aucun cours planifié pour cette classe.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 90px;">Jour</th>
                    <th style="width: 110px;">Horaire</th>
                    <th>Matière</th>
                    <th>Enseignant</th>
                    <th style="width: 80px;">Salle</th>
                </tr>
            </thead>
            <tbody>
                @foreach($parJour as $jour => $creneaux)
                    @foreach($creneaux as $i => $c)
                        <tr>
                            @if($i === 0)
                                <td class="jour-cell" rowspan="{{ count($creneaux) }}">{{ $jour }}</td>
                            @endif
                            <td class="heure">
                                {{ \Illuminate\Support\Str::substr($c->heure_debut, 0, 5) }}
                                –
                                {{ \Illuminate\Support\Str::substr($c->heure_fin, 0, 5) }}
                            </td>
                            <td class="matiere">
                                {{ $c->affectation?->classeMatiere?->matiere?->nom ?? '—' }}
                            </td>
                            <td class="enseignant">
                                @php $u = $c->affectation?->enseignant?->user; @endphp
                                {{ $u ? trim($u->first_name . ' ' . $u->last_name) : '—' }}
                            </td>
                            <td class="salle">{{ $c->salle ?? '—' }}</td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        Document généré le {{ now()->format('d/m/Y à H:i') }}
    </div>
</body>
</html>
