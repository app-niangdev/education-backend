<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>{{ $estSolde ? 'Reçu de paiement' : 'Décharge' }} - {{ $paiement->numero_recu }}</title>
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
        td.label {
            background: {{ $couleur_claire }};
            font-weight: bold;
            color: {{ $couleur_foncee }};
            width: 40%;
        }
        td.montant { text-align: right; white-space: nowrap; }
        tr.total td {
            font-weight: bold;
            font-size: 13px;
            background: {{ $couleur_claire_intense }};
        }
        tr.reste td { font-weight: bold; color: #b91c1c; }

        .mention {
            margin: 12px 0 0;
            padding: 8px 10px;
            background: #f9fafb;
            border-left: 3px solid {{ $couleur }};
            font-size: 10px;
            color: #374151;
        }
        .montant-lettres { font-size: 10px; color: #4b5563; font-style: italic; margin: 6px 0 0; }

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
    $moisFr = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];
    $fmt = fn ($n) => number_format((int) $n, 0, ',', ' ') . ' FCFA';
    $modesFr = [
        'ESPECES'      => 'Espèces',
        'WAVE'         => 'Wave',
        'ORANGE_MONEY' => 'Orange Money',
        'FREE_MONEY'   => 'Free Money',
    ];
    $modeValeur = $paiement->mode_paiement instanceof BackedEnum
        ? $paiement->mode_paiement->value
        : $paiement->mode_paiement;
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
        <div class="titre">{{ $estSolde ? 'Reçu de paiement' : 'Décharge' }}</div>
        <div class="numero">
            N° {{ $paiement->numero_recu }}
            &bull; {{ $libelleObjet }}
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

    <h2>Versement</h2>
    <table>
        <tr>
            <td class="label">Objet du paiement</td>
            <td>{{ $libelleObjet }}</td>
        </tr>
        <tr>
            <td class="label">Date du versement</td>
            <td>{{ $paiement->date_paiement?->format('d/m/Y') ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Mode de paiement</td>
            <td>{{ $modesFr[$modeValeur] ?? $modeValeur }}</td>
        </tr>
        @if($paiement->numero_transaction)
            <tr>
                <td class="label">N° de transaction</td>
                <td>{{ $paiement->numero_transaction }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Encaissé par</td>
            <td>{{ $paiement->utilisateur?->full_name ?? '—' }}</td>
        </tr>
        <tr class="total">
            <td class="label">Montant versé</td>
            <td class="montant">{{ $fmt($paiement->montant) }}</td>
        </tr>
    </table>

    <h2>Situation du compte</h2>
    <table>
        <tr>
            <td class="label">Montant total dû</td>
            <td class="montant">{{ $fmt($montantDu) }}</td>
        </tr>
        <tr>
            <td class="label">Total réglé à ce jour</td>
            <td class="montant">{{ $fmt($totalPaye) }}</td>
        </tr>
        <tr class="{{ $estSolde ? '' : 'reste' }}">
            <td class="label">Reste à payer</td>
            <td class="montant">{{ $fmt($reste) }}</td>
        </tr>
    </table>

    <div class="mention">
        @if($estSolde)
            Je soussigné(e), responsable de la trésorerie de l'établissement, reconnais avoir
            reçu de l'élève désigné ci-dessus la somme de <strong>{{ $fmt($paiement->montant) }}</strong>
            au titre de « {{ $libelleObjet }} ». Ce versement solde intégralement le montant dû :
            <strong>le présent document vaut reçu de paiement définitif.</strong>
        @else
            Je soussigné(e), responsable de la trésorerie de l'établissement, reconnais avoir
            reçu de l'élève désigné ci-dessus un versement partiel de
            <strong>{{ $fmt($paiement->montant) }}</strong> au titre de « {{ $libelleObjet }} ».
            Le solde n'étant pas intégralement réglé, <strong>le présent document constitue une
            décharge et non un reçu de paiement</strong> : un reste de
            <strong>{{ $fmt($reste) }}</strong> demeure exigible.
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
                <div>{{ $paiement->utilisateur?->full_name ?? '' }}</div>
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ $etablissement?->nom }} — Document généré le {{ now()->format('d/m/Y à H:i') }}
        &bull; {{ $paiement->numero_recu }}
    </div>
</body>
</html>
