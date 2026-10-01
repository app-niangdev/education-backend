<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $estSolde ? 'Facture acquittée' : 'Décharge' }} - {{ $facture->numero_facture }}</title>
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
        .header .coords { font-size: 9px; color: #6b7280; margin-top: 3px; }

        /* Logo de l'établissement, à côté de son nom dans l'en-tête. */
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { border: none; padding: 0; vertical-align: middle; }
        .logo-cell { width: 40px; padding-right: 8px !important; }
        .logo { max-width: 36px; max-height: 36px; }

        .titre-bloc {
            background: {{ $couleur_claire }};
            border: 1px solid {{ $couleur_claire_intense }};
            padding: 10px 12px;
            margin-bottom: 14px;
        }
        .titre-bloc .titre {
            font-size: 16px;
            font-weight: bold;
            color: {{ $couleur_foncee }};
            text-transform: uppercase;
            letter-spacing: .5px;
        }
        .titre-bloc .numero { font-size: 11px; color: #4b5563; margin-top: 3px; }

        h2 {
            font-size: 12px;
            color: {{ $couleur_foncee }};
            margin: 16px 0 6px;
            text-transform: uppercase;
        }

        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td {
            border: 1px solid #d1d5db;
            padding: 6px 8px;
            text-align: left;
            vertical-align: top;
        }
        th {
            background: {{ $couleur_claire }};
            font-size: 10px;
            color: {{ $couleur_foncee }};
            text-transform: uppercase;
            letter-spacing: .3px;
        }
        td.label {
            background: {{ $couleur_claire }};
            font-weight: bold;
            color: {{ $couleur_foncee }};
            width: 40%;
        }
        td.montant, th.montant { text-align: right; white-space: nowrap; }
        td.centre, th.centre { text-align: center; }
        tr.total td {
            font-weight: bold;
            font-size: 13px;
            background: {{ $couleur_claire_intense }};
        }
        tr.reste td { font-weight: bold; color: #b91c1c; }

        .badge {
            display: inline-block;
            padding: 1px 6px;
            font-size: 9px;
            font-weight: bold;
            border-radius: 8px;
        }
        /* Meme parti pris que pour le reste du document (cf. recu-paiement) :
           le statut se lit dans le texte du badge, pas dans une couleur de
           statut distincte — les deux restent dans la couleur de l'etablissement. */
        .badge-solde, .badge-partiel { background: {{ $couleur_claire_intense }}; color: {{ $couleur_foncee }}; }

        .mention {
            margin: 12px 0 0;
            padding: 8px 10px;
            background: #f9fafb;
            border-left: 3px solid {{ $couleur }};
            font-size: 10px;
            color: #374151;
        }

        .signatures { width: 100%; margin-top: 28px; }
        .signatures td {
            border: none;
            width: 50%;
            font-size: 10px;
            color: #4b5563;
            vertical-align: top;
        }
        .signatures .ligne-signature {
            border-bottom: 1px solid #9ca3af;
            height: 42px;
            margin-bottom: 4px;
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
    $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ') . ' FCFA';
    $modesFr = [
        'ESPECES'      => 'Espèces',
        'WAVE'         => 'Wave',
        'ORANGE_MONEY' => 'Orange Money',
        'FREE_MONEY'   => 'Free Money',
    ];
    $modeValeur = $facture->mode_paiement instanceof BackedEnum
        ? $facture->mode_paiement->value
        : $facture->mode_paiement;
    $nbMois = count($lignes);
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
                        <div class="coords">
                            @if($etablissement->adresse){{ $etablissement->adresse }}@endif
                            @if($etablissement->telephone_principal) &bull; Tél. {{ $etablissement->telephone_principal }}@endif
                            @if($etablissement->telephone_secondaire) / {{ $etablissement->telephone_secondaire }}@endif
                            @if($etablissement->email) &bull; {{ $etablissement->email }}@endif
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="titre-bloc">
        <div class="titre">{{ $estSolde ? 'Facture acquittée' : 'Décharge' }}</div>
        <div class="numero">
            N° {{ $facture->numero_facture }}
            &bull; Mensualités — {{ $nbMois }} mois
            @if($annee) &bull; Année scolaire {{ $annee->nom }}@endif
        </div>
    </div>

    <h2>Élève</h2>
    <table>
        <tr>
            <td class="label">Nom &amp; prénom</td>
            <td>{{ $eleve?->nom_complet ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Matricule</td>
            <td>{{ $eleve?->matricule ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Classe</td>
            <td>
                {{ $classe?->nom ?? '—' }}@if($classe?->niveau) — {{ $classe->niveau->nom }}@endif
            </td>
        </tr>
        <tr>
            <td class="label">N° d'inscription</td>
            <td>{{ $inscription?->numero_inscription ?? '—' }}</td>
        </tr>
    </table>

    <h2>Détail des mois réglés</h2>
    <table>
        <thead>
            <tr>
                <th>Mois</th>
                <th class="montant">Montant dû</th>
                <th class="montant">Montant réglé</th>
                <th class="montant">Reste</th>
                <th class="centre">État</th>
            </tr>
        </thead>
        <tbody>
            @foreach($lignes as $ligne)
                <tr>
                    <td>{{ $ligne['libelle'] }}</td>
                    <td class="montant">{{ $fmt($ligne['montant_du']) }}</td>
                    <td class="montant">{{ $fmt($ligne['montant']) }}</td>
                    <td class="montant">{{ $fmt($ligne['reste']) }}</td>
                    <td class="centre">
                        <span class="badge {{ $ligne['solde'] ? 'badge-solde' : 'badge-partiel' }}">
                            {{ $ligne['solde'] ? 'Soldé' : 'Avance' }}
                        </span>
                    </td>
                </tr>
            @endforeach
            <tr class="total">
                <td>Total versé</td>
                <td class="montant">—</td>
                <td class="montant">{{ $fmt($facture->montant_total) }}</td>
                <td class="montant">—</td>
                <td class="centre">—</td>
            </tr>
        </tbody>
    </table>

    <h2>Versement</h2>
    <table>
        <tr>
            <td class="label">Date du versement</td>
            <td>{{ $facture->date_paiement?->format('d/m/Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Mode de paiement</td>
            <td>{{ $modesFr[$modeValeur] ?? $modeValeur }}</td>
        </tr>
        @if($facture->numero_transaction)
            <tr>
                <td class="label">N° de transaction</td>
                <td>{{ $facture->numero_transaction }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Encaissé par</td>
            <td>{{ $facture->utilisateur?->full_name ?? '—' }}</td>
        </tr>
        <tr class="total">
            <td class="label">Montant total versé</td>
            <td class="montant">{{ $fmt($facture->montant_total) }}</td>
        </tr>
    </table>

    <h2>Situation annuelle des mensualités</h2>
    <table>
        <tr>
            <td class="label">Total dû sur l'année</td>
            <td class="montant">{{ $fmt($montantDu) }}</td>
        </tr>
        <tr>
            <td class="label">Total réglé à ce jour</td>
            <td class="montant">{{ $fmt($totalPaye) }}</td>
        </tr>
        <tr class="{{ $reste > 0 ? 'reste' : '' }}">
            <td class="label">Reste à payer sur l'année</td>
            <td class="montant">{{ $fmt($reste) }}</td>
        </tr>
    </table>

    <div class="mention">
        @if($estSolde)
            Je soussigné(e), responsable de la trésorerie de l'établissement, reconnais avoir
            reçu de l'élève désigné ci-dessus la somme de <strong>{{ $fmt($facture->montant_total) }}</strong>
            au titre des <strong>{{ $nbMois }} mensualité(s)</strong> détaillée(s) ci-dessus.
            Chacun de ces mois est intégralement réglé :
            <strong>le présent document vaut reçu de paiement définitif pour ces mois.</strong>
            @if($reste > 0)
                Un solde de <strong>{{ $fmt($reste) }}</strong> reste dû au titre des mois non encore échus.
            @endif
        @else
            Je soussigné(e), responsable de la trésorerie de l'établissement, reconnais avoir
            reçu de l'élève désigné ci-dessus la somme de <strong>{{ $fmt($facture->montant_total) }}</strong>
            au titre des mensualités détaillées ci-dessus. Un des mois couverts n'étant pas
            intégralement réglé, <strong>le présent document constitue une décharge et non un
            reçu de paiement définitif</strong> : un reste de <strong>{{ $fmt($reste) }}</strong>
            demeure exigible.
        @endif
    </div>

    <table class="signatures">
        <tr>
            <td>
                <div>Le/la déclarant(e) (parent ou tuteur)</div>
                <div class="ligne-signature"></div>
                <div>Nom, date et signature</div>
            </td>
            <td>
                <div>Le/la trésorier(ère)</div>
                <div class="ligne-signature"></div>
                <div>{{ $facture->utilisateur?->full_name ?? '' }}</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ $etablissement?->nom }} — Document généré le {{ now()->format('d/m/Y à H:i') }}
        &bull; {{ $facture->numero_facture }}
    </div>
</body>
</html>
