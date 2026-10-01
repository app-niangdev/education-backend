{{--
    La fiche d'assiduité d'un élève, à remettre aux parents ou à présenter
    en conseil de discipline.

    Le total et la part non justifiée sont mis en avant : c'est la seconde
    qui motive une convocation, pas le volume brut — un élève souvent malade
    avec certificats n'est pas un élève qui décroche.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Absences - {{ $eleve->nom_complet }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #1f2937; font-size: 11px; }

        /* DomPDF ignore flexbox : les mises en colonnes sont des tables nues. */
        table.entete { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        table.entete td { border: none; padding: 0; vertical-align: top; font-size: 11px; }
        .entete-gauche { width: 60%; }
        .entete-droite { width: 40%; text-align: right; }
        .entete .etab { font-weight: bold; color: {{ $couleur }}; }

        /* Logo de l'établissement, à côté de son nom dans l'en-tête. */
        table.entete-logo td { border: none; padding: 0; vertical-align: middle; }
        .logo-cell { width: 40px; padding-right: 8px !important; }
        .logo { max-width: 36px; max-height: 36px; }

        .titre {
            border-top: 2px solid {{ $couleur }};
            border-bottom: 2px solid {{ $couleur }};
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 1px;
            padding: 4px 0;
            margin-bottom: 8px;
        }

        table.identite { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.identite td { border: none; padding: 2px 4px; font-size: 11px; white-space: nowrap; }
        table.identite .lib { color: #4b5563; width: 18%; }
        table.identite .val { font-weight: bold; width: 32%; }

        /* Les totaux : quatre cases, la part non justifiée en évidence. */
        table.totaux { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.totaux td {
            border: 1px solid #d1d5db;
            padding: 8px 6px;
            text-align: center;
            width: 25%;
        }
        table.totaux .valeur { font-size: 18px; font-weight: bold; }
        table.totaux .libelle { font-size: 9px; color: #6b7280; text-transform: uppercase; }
        .alerte { color: #b91c1c; }
        .rassurant { color: #047857; }

        h2 {
            font-size: 12px;
            color: {{ $couleur }};
            margin: 14px 0 6px;
            text-transform: uppercase;
        }

        table.detail { width: 100%; border-collapse: collapse; }
        table.detail th, table.detail td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            vertical-align: middle;
        }
        table.detail thead th {
            background: {{ $couleur }};
            color: #fff;
            font-size: 10px;
            text-transform: uppercase;
            text-align: left;
        }
        table.detail td.centre { text-align: center; white-space: nowrap; }
        .statut { font-weight: bold; }
        .justifiee { color: #047857; }
        .non-justifiee { color: #b91c1c; }

        .vide {
            text-align: center;
            padding: 24px;
            color: #6b7280;
            font-style: italic;
            border: 1px solid #d1d5db;
        }

        table.signature { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table.signature td {
            border: none;
            width: 50%;
            font-size: 11px;
            font-weight: bold;
            /* Réserve la place de la signature manuscrite. */
            padding-bottom: 50px;
        }
        table.signature td:last-child { text-align: right; }

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
    @php
        /** Les heures s'affichent à la française, avec une décimale. */
        $heures = fn ($valeur) => number_format((float) $valeur, 1, ',', '') . ' h';

        $periode = $periode_id
            ? \App\Models\Periode::find($periode_id)
            : null;
    @endphp

    <table class="entete">
        <tr>
            <td class="entete-gauche">
                <table class="entete-logo">
                    <tr>
                        @if($logo ?? null)
                            <td class="logo-cell"><img src="{{ $logo }}" class="logo" alt="Logo"></td>
                        @endif
                        <td>
                            @if($etablissement?->inspection_academique)
                                <div>{{ $etablissement->inspection_academique }}</div>
                            @endif
                            @if($etablissement?->inspection_education_formation)
                                <div>{{ $etablissement->inspection_education_formation }}</div>
                            @endif
                            @if($etablissement?->nom)
                                <div class="etab">{{ $etablissement->nom }}</div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
            <td class="entete-droite">
                <div>{{ $periode?->libelle ?? "Année scolaire complète" }}</div>
                <div>Édité le {{ now()->format('d/m/Y') }}</div>
            </td>
        </tr>
    </table>

    <div class="titre">RELEVÉ D'ABSENCES ET DE RETARDS</div>

    <table class="identite">
        <tr>
            <td class="lib">Prénoms</td>
            <td class="val">{{ $eleve->prenom ?? '—' }}</td>
            <td class="lib">Nom</td>
            <td class="val">{{ $eleve->nom ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lib">Matricule</td>
            <td class="val">{{ $eleve->matricule ?? '—' }}</td>
            <td class="lib">Classe</td>
            <td class="val">{{ $eleve->classeActuelle?->nom ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lib">Né(e) le</td>
            <td class="val">{{ $eleve->date_naissance?->format('d/m/Y') ?? '—' }}</td>
            <td class="lib">à</td>
            <td class="val">{{ $eleve->lieu_naissance ?? '—' }}</td>
        </tr>
    </table>

    {{-- Les quatre totaux de la période --}}
    <table class="totaux">
        <tr>
            <td>
                <div class="valeur">{{ $heures($totaux['heures_absence']) }}</div>
                <div class="libelle">Absences</div>
            </td>
            <td>
                <div class="valeur {{ $totaux['heures_absence_non_justifiee'] > 0 ? 'alerte' : 'rassurant' }}">
                    {{ $heures($totaux['heures_absence_non_justifiee']) }}
                </div>
                <div class="libelle">Dont non justifiées</div>
            </td>
            <td>
                <div class="valeur">{{ $totaux['retards'] }}</div>
                <div class="libelle">Retards</div>
            </td>
            <td>
                <div class="valeur">{{ $totaux['renvois'] }}</div>
                <div class="libelle">Renvois de cours</div>
            </td>
        </tr>
    </table>

    <h2>Détail des absences et retards</h2>

    @forelse($presences as $presence)
        @if($loop->first)
            <table class="detail">
                <thead>
                    <tr>
                        <th style="width: 15%;">Date</th>
                        <th style="width: 13%;">Horaire</th>
                        <th style="width: 22%;">Matière</th>
                        <th style="width: 16%;">Statut</th>
                        <th style="width: 12%;">Justifiée</th>
                        <th>Motif</th>
                    </tr>
                </thead>
                <tbody>
        @endif

        <tr>
            <td class="centre">{{ $presence->seance?->date_seance?->format('d/m/Y') ?? '—' }}</td>
            <td class="centre">
                {{ \Illuminate\Support\Str::substr($presence->seance?->heure_debut ?? '', 0, 5) }}
                –
                {{ \Illuminate\Support\Str::substr($presence->seance?->heure_fin ?? '', 0, 5) }}
            </td>
            <td>{{ $presence->seance?->affectation?->classeMatiere?->matiere?->nom ?? '—' }}</td>
            <td class="statut">
                {{ $presence->statut_libelle }}
                @if($presence->minutes_retard)
                    ({{ $presence->minutes_retard }} min)
                @endif
            </td>
            <td class="centre {{ $presence->justifie ? 'justifiee' : 'non-justifiee' }}">
                {{ $presence->justifie ? 'Oui' : 'Non' }}
            </td>
            <td>{{ $presence->motif ?? '—' }}</td>
        </tr>

        @if($loop->last)
                </tbody>
            </table>
        @endif
    @empty
        <div class="vide">
            Aucune absence ni retard enregistré sur la période.
        </div>
    @endforelse

    <table class="signature">
        <tr>
            <td>Le Surveillant Général</td>
            <td>Signature du parent ou tuteur</td>
        </tr>
    </table>

    <div class="footer">
        Document généré le {{ now()->format('d/m/Y à H:i') }}
    </div>
</body>
</html>
