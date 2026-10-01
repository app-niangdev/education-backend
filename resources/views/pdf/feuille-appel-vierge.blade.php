{{--
    Feuille d'appel vierge, à imprimer quand il n'y a pas d'écran en classe.

    Une colonne par créneau de la journée, une ligne par élève : l'enseignant
    coche à la main puis reporte dans l'application. Le format paysage tient
    jusqu'à sept ou huit créneaux ; au-delà les colonnes deviendraient trop
    étroites pour être annotées.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Feuille d'appel - {{ $classe->nom }}</title>
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
            margin-bottom: 6px;
        }

        .contexte {
            text-align: center;
            font-size: 11px;
            margin-bottom: 10px;
        }
        .contexte .classe { font-weight: bold; font-size: 13px; }

        table.appel { width: 100%; border-collapse: collapse; }
        table.appel th, table.appel td {
            border: 1px solid #d1d5db;
            padding: 5px 6px;
        }
        table.appel thead th {
            background: {{ $couleur }};
            color: #fff;
            font-size: 9px;
            text-transform: uppercase;
            text-align: center;
        }
        table.appel thead th.eleve { text-align: left; }
        .matiere { font-size: 9px; }
        .horaire { font-size: 8px; font-weight: normal; }

        table.appel td.num { text-align: center; color: #6b7280; width: 4%; }
        table.appel td.nom { font-size: 10px; }
        table.appel td.matricule { font-size: 9px; color: #6b7280; }
        /* Cases de pointage : laissées vides, l'enseignant y écrit A ou R. */
        table.appel td.case-appel { height: 20px; }

        .legende {
            margin-top: 8px;
            font-size: 9px;
            color: #6b7280;
        }

        .vide {
            text-align: center;
            padding: 24px;
            color: #6b7280;
            font-style: italic;
            border: 1px solid #d1d5db;
        }

        table.signature { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.signature td {
            border: none;
            width: 50%;
            font-size: 10px;
            font-weight: bold;
            padding-bottom: 40px;
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
        $heure = fn (?string $h) => \Illuminate\Support\Str::substr($h ?? '', 0, 5);

        // Les colonnes de pointage se partagent la place restante après le
        // numéro, le nom et le matricule.
        $largeurCase = count($creneaux) > 0
            ? round(56 / count($creneaux), 2)
            : 0;
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
                {{-- `$jour` est null le dimanche (aucun jour ouvrable) : on
                     retombe sur le nom du jour pour ne pas laisser la date orpheline. --}}
                <div>{{ $jour ?? ucfirst($date->translatedFormat('l')) }} {{ $date->format('d/m/Y') }}</div>
                <div>{{ $eleves->count() }} élève(s)</div>
            </td>
        </tr>
    </table>

    <div class="titre">FEUILLE D'APPEL</div>

    <div class="contexte">
        <span class="classe">{{ $classe->nom }}</span>
        @if($classe->niveau)
            &bull; {{ $classe->niveau->nom }}
        @endif
    </div>

    @if(count($creneaux) === 0)
        <div class="vide">
            Aucun cours programmé ce jour pour cette classe.
        </div>
    @else
        <table class="appel">
            <thead>
                <tr>
                    <th style="width: 4%;">N°</th>
                    <th class="eleve" style="width: 26%;">Élève</th>
                    <th style="width: 14%;">Matricule</th>

                    @foreach($creneaux as $creneau)
                        <th style="width: {{ $largeurCase }}%;">
                            <div class="matiere">
                                {{ $creneau->affectation?->classeMatiere?->matiere?->nom ?? '—' }}
                            </div>
                            <div class="horaire">
                                {{ $heure($creneau->heure_debut) }}–{{ $heure($creneau->heure_fin) }}
                            </div>
                        </th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @foreach($eleves as $eleve)
                    <tr>
                        <td class="num">{{ $loop->iteration }}</td>
                        <td class="nom">{{ $eleve->nom_complet }}</td>
                        <td class="matricule">{{ $eleve->matricule ?? '—' }}</td>

                        @foreach($creneaux as $creneau)
                            <td class="case-appel"></td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="legende">
            Inscrire dans la case : <strong>A</strong> pour une absence,
            <strong>R</strong> pour un retard (préciser les minutes),
            <strong>E</strong> pour un renvoi de cours. Laisser vide si l'élève est présent.
        </div>
    @endif

    <table class="signature">
        <tr>
            <td>Nom et signature de l'enseignant</td>
            <td>Visa du Surveillant Général</td>
        </tr>
    </table>

    <div class="footer">
        {{ $classe->nom }} — {{ $date->format('d/m/Y') }} —
        Document généré le {{ now()->format('d/m/Y à H:i') }}
    </div>
</body>
</html>
