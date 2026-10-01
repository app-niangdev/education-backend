<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Fiche de renseignement - {{ $inscription->numero_inscription }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #1f2937; font-size: 11px; }

        .header {
            border-bottom: 2px solid {{ $couleur }};
            padding-bottom: 10px;
            margin-bottom: 16px;
        }
        .header .inspection { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: .3px; }
        .header .etab { font-size: 16px; font-weight: bold; color: {{ $couleur }}; margin-top: 4px; }
        .header .slogan { font-size: 10px; color: #6b7280; font-style: italic; }
        .header .titre { font-size: 14px; font-weight: bold; margin-top: 8px; }
        .header .meta { font-size: 10px; color: #4b5563; margin-top: 2px; }

        /* Logo de l'établissement, à côté de son nom dans l'en-tête. */
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { border: none; padding: 0; vertical-align: middle; }
        .logo-cell { width: 40px; padding-right: 8px !important; }
        .logo { max-width: 36px; max-height: 36px; }

        .eleve-infos { margin-bottom: 8px; }
        .eleve-infos .ligne { margin: 2px 0; font-size: 11px; }
        .eleve-infos .cle { display: inline-block; min-width: 130px; font-weight: bold; color: {{ $couleur_foncee }}; }

        h2 {
            font-size: 12px;
            color: {{ $couleur_foncee }};
            margin: 18px 0 8px;
            text-transform: uppercase;
        }

        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
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
        td.label { background: {{ $couleur_claire }}; font-weight: bold; color: {{ $couleur_foncee }}; width: 40%; }
        td.montant, th.montant { text-align: right; white-space: nowrap; }
        tfoot td { font-weight: bold; background: #f3f4f6; }
        .note {
            margin: 6px 0 0;
            font-size: 10px;
            color: #6b7280;
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
@php
    $moisFr = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];
    $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ') . ' FCFA';
    $statutsFr = ['PAYE' => 'Payé', 'PARTIEL' => 'Partiel', 'NON_PAYE' => 'Non payé'];
    $totalMensualites = collect($echeancier)->sum('montant');
@endphp
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                @if($logo ?? null)
                    <td class="logo-cell"><img src="{{ $logo }}" class="logo" alt="Logo"></td>
                @endif
                <td>
                    @if($etablissement)
                        @if($etablissement->inspection_academique)
                            <div class="inspection">{{ $etablissement->inspection_academique }}</div>
                        @endif
                        @if($etablissement->inspection_education_formation)
                            <div class="inspection">{{ $etablissement->inspection_education_formation }}</div>
                        @endif
                        <div class="etab">{{ $etablissement->nom }}</div>
                        @if($etablissement->slogan)
                            <div class="slogan">{{ $etablissement->slogan }}</div>
                        @endif
                    @endif
                </td>
            </tr>
        </table>
        <div class="titre">Fiche de renseignement — {{ $inscription->numero_inscription }}</div>
        <div class="meta">
            @if($annee)Année scolaire {{ $annee->nom }} &bull; @endif
            Éditée le {{ now()->format('d/m/Y') }}
        </div>
    </div>

    <h2>Élève</h2>
    <div class="eleve-infos">
        <div class="ligne"><span class="cle">Nom &amp; prénom</span> {{ $eleve?->nom_complet ?? '—' }}</div>
        <div class="ligne"><span class="cle">Matricule</span> {{ $eleve?->matricule ?? '—' }}</div>
        <div class="ligne">
            <span class="cle">Classe</span>
            {{ $classe?->nom ?? '—' }}@if($classe?->niveau) — {{ $classe->niveau->nom }}@endif
        </div>
        <div class="ligne"><span class="cle">Date d'inscription</span> {{ $inscription->date_inscription?->format('d/m/Y') ?? '—' }}</div>
    </div>

    <h2>Frais de scolarité</h2>
    @if($bareme)
        <table>
            <tr>
                <td class="label">Frais d'inscription</td>
                <td class="montant">{{ $fmt($bareme['montant_inscription']) }}</td>
            </tr>
            <tr>
                <td class="label">Mensualité</td>
                <td class="montant">{{ $fmt($bareme['montant_mensualite']) }}</td>
            </tr>
            <tr>
                <td class="label">Nombre de mensualités</td>
                <td class="montant">{{ $bareme['nombre_mensualites'] }}</td>
            </tr>
            <tr>
                <td class="label">Frais annuel</td>
                <td class="montant">{{ $fmt($bareme['frais_annuel']) }}</td>
            </tr>
        </table>
        @if($bareme['neuvieme_mois_inclus'])
            <p class="note">
                Le 9ᵉ mois est intégré aux frais d'inscription : l'élève règle
                l'inscription puis {{ $bareme['nombre_mensualites'] }} mensualités.
            </p>
        @endif
    @else
        <p class="note">Aucun barème tarifaire n'est défini pour ce niveau.</p>
    @endif

    <h2>Mois à payer durant l'année scolaire</h2>
    @if(empty($echeancier))
        <p class="note">Aucun mois de mensualité défini pour cette inscription.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 40px;">N°</th>
                    <th>Mois</th>
                    <th class="montant">Montant</th>
                    <th style="width: 80px;">Statut</th>
                </tr>
            </thead>
            <tbody>
                @foreach($echeancier as $i => $ligne)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ ($moisFr[$ligne['mois']] ?? $ligne['mois']) . ' ' . $ligne['annee'] }}</td>
                        <td class="montant">{{ $fmt($ligne['montant']) }}</td>
                        <td>{{ $ligne['statut'] ? ($statutsFr[$ligne['statut']] ?? $ligne['statut']) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2">Total des mensualités ({{ count($echeancier) }} mois)</td>
                    <td class="montant">{{ $fmt($totalMensualites) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endif

    <div class="footer">
        Document généré le {{ now()->format('d/m/Y à H:i') }}
    </div>
</body>
</html>
