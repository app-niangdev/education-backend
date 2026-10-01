<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Contrat de travail - {{ $contrat->numero_contrat }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { margin: 0; color: #1f2937; font-size: 11px; line-height: 1.5; }

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

        .titre-doc {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 4px 0 2px;
        }
        .sous-titre {
            text-align: center;
            font-size: 11px;
            color: #4b5563;
            margin-bottom: 14px;
        }
        .sous-titre .numero { font-weight: bold; color: {{ $couleur_foncee }}; }

        h2 {
            font-size: 11px;
            color: {{ $couleur_foncee }};
            margin: 16px 0 6px;
            text-transform: uppercase;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 3px;
        }

        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        td {
            border: 1px solid #d1d5db;
            padding: 5px 8px;
            text-align: left;
            vertical-align: top;
        }
        td.label { background: {{ $couleur_claire }}; font-weight: bold; color: {{ $couleur_foncee }}; width: 32%; }

        .clause { margin: 0 0 7px; text-align: justify; }
        .clause .num { font-weight: bold; color: {{ $couleur_foncee }}; }

        .mention {
            background: #fef3c7;
            border-left: 3px solid #f59e0b;
            padding: 6px 8px;
            margin: 8px 0;
            font-size: 10px;
        }

        /* Le bloc d'authenticite : QR a gauche, explication a droite. La mise en
           page passe par un tableau, DomPDF ne suivant pas flexbox. */
        .authenticite {
            margin-top: 14px;
            border: 1px solid {{ $couleur_claire_intense }};
            background: {{ $couleur_claire }};
            padding: 8px;
        }
        .authenticite td { border: none; padding: 0 8px 0 0; vertical-align: middle; }
        .authenticite .qr { width: 92px; }
        .authenticite .qr img { width: 88px; height: 88px; display: block; }
        .authenticite .titre { font-weight: bold; color: {{ $couleur_foncee }}; font-size: 10px; text-transform: uppercase; }
        .authenticite .texte { font-size: 9px; color: #4b5563; margin-top: 3px; }
        .authenticite .url {
            font-size: 8px;
            color: {{ $couleur_foncee }};
            word-break: break-all;
            margin-top: 4px;
        }
        .authenticite .code { font-size: 9px; color: #4b5563; margin-top: 3px; }

        .signatures { width: 100%; margin-top: 18px; border-collapse: collapse; }
        .signatures td {
            border: none;
            width: 50%;
            text-align: center;
            font-size: 10px;
            padding: 0 10px;
            vertical-align: top;
        }
        .signatures .role { font-weight: bold; color: {{ $couleur_foncee }}; }
        .signatures .mention-manuscrite { font-size: 8px; color: #6b7280; font-style: italic; }
        .signatures .trait {
            border-bottom: 1px solid #9ca3af;
            height: 46px;
            margin-top: 4px;
        }

        .fait-a { font-size: 10px; margin-top: 12px; text-align: right; color: #4b5563; }

        .footer {
            position: fixed;
            bottom: -24px; left: 0; right: 0;
            text-align: center;
            font-size: 8px;
            color: #9ca3af;
        }
    </style>
</head>
@php
    /**
     * Le contrat imprime doit se lire seul, sans le logiciel. Les valeurs
     * absentes deviennent donc des pointilles a completer a la main plutot que
     * des cases vides, qu'on lirait comme une clause sans objet.
     */
    $vide = '…………………………';
    $dateFr = fn ($d) => $d ? $d->format('d/m/Y') : $vide;
    $fmt = fn ($n) => $n !== null ? number_format((int) $n, 0, ',', ' ') . ' FCFA' : null;

    $nomEmploye = trim("{$employe?->first_name} {$employe?->last_name}") ?: $vide;
    $fonction   = $contrat->fonction ?: $vide;
    $lieu       = $contrat->lieu_travail ?: ($etablissement?->adresse ?: $vide);
    $nomEtab    = $etablissement?->nom ?: "L'établissement";

    // Un permanent est engage sans terme : le dire explicitement vaut mieux
    // qu'un tiret, qui laisserait planer un doute sur une omission.
    $terme = $contrat->date_fin
        ? "jusqu'au " . $contrat->date_fin->format('d/m/Y')
        : 'pour une durée indéterminée';

    $remuneration = $contrat->remuneration_libelle;
    $estHoraire   = $contrat->mode_remuneration?->value === 'HORAIRE';
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
                            @if($etablissement->email) &bull; {{ $etablissement->email }}@endif
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="titre-doc">Contrat de travail</div>
    <div class="sous-titre">
        {{ $contrat->type_contrat_libelle ?? 'Contrat' }} &bull;
        N° <span class="numero">{{ $contrat->numero_contrat }}</span>
    </div>

    {{-- Un contrat clos continue de s'imprimer : on le consulte pour justifier
         une periode travaillee. Mais le papier doit dire qu'il n'engage plus. --}}
    @if($contrat->statut?->estClos())
        <div class="mention">
            <strong>Document d'archive.</strong>
            Ce contrat est {{ strtolower($contrat->statut_libelle) }}
            @if($contrat->date_resiliation)
                depuis le {{ $contrat->date_resiliation->format('d/m/Y') }}
            @endif
            et n'engage plus les parties.
        </div>
    @endif

    <h2>Entre les soussignés</h2>
    <p class="clause">
        <strong>{{ $nomEtab }}</strong>@if($etablissement?->adresse), sis à {{ $etablissement->adresse }}@endif,
        représenté par sa Direction, ci-après désigné « l'Établissement »,
        <strong>d'une part</strong> ;
    </p>
    <p class="clause">
        Et <strong>{{ $nomEmploye }}</strong>@if($matricule), matricule {{ $matricule }}@endif
        @if($employe?->email), joignable à l'adresse {{ $employe->email }}@endif,
        ci-après désigné « l'Employé », <strong>d'autre part</strong>.
    </p>
    <p class="clause">Il a été convenu ce qui suit.</p>

    <h2>Article 1 — Conditions de l'engagement</h2>
    <table>
        <tr>
            <td class="label">Fonction</td>
            <td>{{ $fonction }}</td>
        </tr>
        <tr>
            <td class="label">Nature du contrat</td>
            <td>{{ $contrat->type_contrat_libelle ?? $vide }}</td>
        </tr>
        <tr>
            <td class="label">Date de prise de fonction</td>
            <td>{{ $dateFr($contrat->date_debut) }}</td>
        </tr>
        <tr>
            <td class="label">Terme du contrat</td>
            <td>
                @if($contrat->date_fin)
                    {{ $contrat->date_fin->format('d/m/Y') }}
                @else
                    Durée indéterminée
                @endif
            </td>
        </tr>
        @if($contrat->duree_periode_essai)
            <tr>
                <td class="label">Période d'essai</td>
                <td>{{ $contrat->duree_periode_essai }} mois</td>
            </tr>
        @endif
        <tr>
            <td class="label">Lieu de travail</td>
            <td>{{ $lieu }}</td>
        </tr>
        @if($contrat->volume_horaire_hebdo)
            <tr>
                <td class="label">Volume horaire hebdomadaire</td>
                <td>{{ $contrat->volume_horaire_hebdo }} heures</td>
            </tr>
        @endif
    </table>

    <h2>Article 2 — Rémunération</h2>
    <table>
        <tr>
            <td class="label">{{ $estHoraire ? 'Taux horaire' : 'Salaire de base' }}</td>
            <td>{{ $remuneration ?? $vide }}</td>
        </tr>
        <tr>
            <td class="label">Mode de rémunération</td>
            <td>{{ $contrat->mode_remuneration?->libelle() ?? $vide }}</td>
        </tr>
    </table>
    <p class="clause">
        <span class="num">2.1.</span>
        @if($estHoraire)
            La rémunération est calculée sur la base des heures effectivement
            accomplies et constatées par l'Établissement.
        @else
            La rémunération est versée mensuellement, à terme échu.
        @endif
        Elle s'entend brute, sous déduction des cotisations et retenues légales.
    </p>

    <h2>Article 3 — Obligations des parties</h2>
    <p class="clause">
        <span class="num">3.1.</span> L'Employé exerce ses fonctions avec diligence
        et se conforme au règlement intérieur ainsi qu'aux instructions de la
        Direction. Il est tenu à une obligation de discrétion sur les
        informations relatives aux élèves, à leurs familles et à l'Établissement.
    </p>
    <p class="clause">
        <span class="num">3.2.</span> L'Établissement s'engage à fournir à l'Employé
        les moyens nécessaires à l'exercice de ses fonctions et à verser la
        rémunération convenue aux échéances prévues.
    </p>

    @if($contrat->duree_periode_essai)
        <p class="clause">
            <span class="num">3.3.</span> Durant la période d'essai de
            {{ $contrat->duree_periode_essai }} mois, chacune des parties peut
            mettre fin au présent contrat sans préavis ni indemnité.
        </p>
    @endif

    <h2>Article 4 — Rupture du contrat</h2>
    <p class="clause">
        <span class="num">4.1.</span> Hors période d'essai, la rupture du contrat
        à l'initiative de l'une des parties est notifiée par écrit, dans le
        respect du préavis légal applicable.
    </p>
    <p class="clause">
        <span class="num">4.2.</span> Le présent contrat prend fin de plein droit
        {{ $terme === 'pour une durée indéterminée' ? 'dans les conditions prévues par la loi' : $terme }},
        sans que sa poursuite au-delà de ce terme ne puisse être présumée.
    </p>

    @if($contrat->observations)
        <h2>Article 5 — Dispositions particulières</h2>
        <p class="clause">{{ $contrat->observations }}</p>
    @endif

    {{-- Le bloc qui rend le papier verifiable. Il vient avant les signatures :
         c'est un element du document, pas une note de bas de page. --}}
    <div class="authenticite">
        <table>
            <tr>
                @if($qrCode)
                    <td class="qr">
                        <img src="{{ $qrCode }}" alt="QR code de vérification">
                    </td>
                @endif
                <td>
                    <div class="titre">Authenticité du document</div>
                    <div class="texte">
                        @if($qrCode)
                            Scannez ce QR code, ou saisissez l'adresse ci-dessous,
                        @else
                            Ouvrez l'adresse ci-dessous
                        @endif
                        pour vérifier en ligne que ce contrat correspond bien à un
                        engagement enregistré par {{ $nomEtab }}.
                    </div>
                    @if($lien)
                        <div class="url">{{ $lien }}</div>
                    @endif
                    <div class="code">
                        Code de vérification : <strong>{{ $contrat->code_verification }}</strong>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="fait-a">
        Fait à {{ $etablissement?->adresse ?: '……………………' }}, le {{ now()->format('d/m/Y') }}, en deux exemplaires originaux.
    </div>

    <table class="signatures">
        <tr>
            <td>
                <div class="role">Pour l'Établissement</div>
                <div class="mention-manuscrite">La Direction</div>
                <div class="trait"></div>
            </td>
            <td>
                <div class="role">L'Employé</div>
                <div class="mention-manuscrite">Précédé de la mention « Lu et approuvé »</div>
                <div class="trait"></div>
            </td>
        </tr>
    </table>

    <div class="footer">
        {{ $contrat->numero_contrat }} &bull; Document généré le {{ now()->format('d/m/Y à H:i') }}
        @if($lien) &bull; Vérifiable en ligne @endif
    </div>

</body>
</html>
