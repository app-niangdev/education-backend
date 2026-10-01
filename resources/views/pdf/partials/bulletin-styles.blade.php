{{-- Styles du bulletin, partagés par le tirage individuel et le tirage de classe. --}}
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { margin: 0; color: #1f2937; font-size: 11px; }

    /* Un brouillon ne doit jamais pouvoir passer pour un bulletin officiel. */
    .brouillon {
        border: 1px solid #d97706;
        background: #fffbeb;
        color: #b45309;
        text-align: center;
        font-size: 10px;
        font-weight: bold;
        text-transform: uppercase;
        padding: 4px;
        margin-bottom: 10px;
    }

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

    .titre-bulletin {
        border-top: 2px solid {{ $couleur }};
        border-bottom: 2px solid {{ $couleur }};
        text-align: center;
        font-size: 15px;
        font-weight: bold;
        letter-spacing: 1px;
        padding: 4px 0;
        margin-bottom: 8px;
    }

    table.identite { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    table.identite td { border: none; padding: 2px 4px; font-size: 11px; white-space: nowrap; }
    table.identite .lib { color: #4b5563; width: 18%; }
    table.identite .val { font-weight: bold; width: 32%; }

    table.notes { width: 100%; border-collapse: collapse; }
    table.notes th, table.notes td {
        border: 1px solid #d1d5db;
        padding: 5px 6px;
        vertical-align: middle;
    }
    table.notes thead th {
        background: {{ $couleur }};
        color: #fff;
        font-size: 10px;
        text-transform: uppercase;
        text-align: center;
    }
    .col-discipline   { width: 24%; text-align: left; }
    .col-num          { width: 9%; }
    .col-coef         { width: 6%; }
    .col-rang         { width: 7%; }
    .col-appreciation { width: 21%; text-align: left; }

    table.notes td.discipline   { text-align: left; }
    table.notes td.num          { text-align: center; white-space: nowrap; }
    table.notes td.appreciation { text-align: left; }
    table.notes td.vide         { text-align: center; color: #9ca3af; font-style: italic; padding: 16px; }

    table.notes tr.total td {
        background: #f3f4f6;
        font-weight: bold;
        text-align: center;
    }
    table.notes tr.total td:first-child { text-align: left; }

    table.synthese { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.synthese td { border: 1px solid #d1d5db; padding: 5px 6px; text-align: center; }
    table.synthese .lib { background: {{ $couleur_claire }}; color: {{ $couleur_foncee }}; font-weight: bold; width: 10%; }
    table.synthese .val { width: 8%; }
    table.synthese .val-forte { width: 12%; font-weight: bold; font-size: 12px; }

    table.conseil { width: 100%; border-collapse: collapse; margin-top: 12px; }
    table.conseil > tr > td, table.conseil td.colonne-conseil, table.conseil td.espaceur {
        border: none;
        padding: 0;
        vertical-align: top;
    }
    .colonne-conseil { width: 47%; }
    .espaceur        { width: 6%; }

    table.cases { width: 100%; border-collapse: collapse; }
    table.cases td { border: 1px solid #d1d5db; padding: 4px 6px; font-size: 10px; }
    table.cases .libelle-case { width: 78%; }
    table.cases .case { width: 22%; text-align: center; font-weight: bold; }

    .observations-titre { margin-top: 12px; font-size: 11px; font-weight: bold; }
    .observations {
        border: 1px solid #d1d5db;
        min-height: 46px;
        padding: 6px;
        margin-top: 4px;
        font-size: 10px;
    }

    table.signatures { width: 100%; border-collapse: collapse; margin-top: 14px; }
    table.signatures td {
        border: none;
        width: 50%;
        font-size: 11px;
        font-weight: bold;
        /* Réserve la place du cachet et de la signature manuscrite. */
        padding-bottom: 56px;
    }
    table.signatures td:last-child { text-align: right; }

    .footer {
        position: fixed;
        bottom: -20px; left: 0; right: 0;
        text-align: center;
        font-size: 9px;
        color: #9ca3af;
    }
</style>
