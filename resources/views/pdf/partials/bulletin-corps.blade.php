{{--
    Le corps d'un bulletin, sans enveloppe HTML : inclus tel quel par
    pdf/bulletin.blade.php (un élève) et pdf/bulletins-classe.blade.php
    (toute une classe, une page par élève). Les styles vivent dans les deux
    enveloppes, pas ici.

    La mise en page suit le bulletin papier de l'établissement. Deux écarts
    assumés par rapport au scan :

    1. La colonne « T.H » a été retirée à la demande du client : sa
       signification n'a pas pu être établie. Ce n'est pas un oubli — ne pas
       la rétablir sans une règle métier claire.
    2. « Retards » et « Absences » restent vides tant que le module
       d'assiduité n'existe pas. Les cases sont conservées pour que le
       document reste conforme au modèle et annotable à la main.

    DomPDF ne gère pas flexbox : toutes les mises en colonnes passent par des
    <table> sans bordure.
--}}
@php
    /** Affiche un nombre à la française, ou un tiret s'il n'y a rien à montrer. */
    $fmt = fn ($v, int $decimales = 2) => $v === null
        ? '-'
        : number_format((float) $v, $decimales, ',', '');

    $coche = fn (bool $actif) => $actif ? '[X]' : '[&nbsp;&nbsp;]';

    $decisions    = \App\Enums\DecisionConseilEnum::cases();
    $distinctions = \App\Enums\DistinctionEnum::cases();
@endphp

@if(!$bulletin->estPublie())
    <div class="brouillon">Brouillon — document non définitif</div>
@endif

{{-- En-tête : hiérarchie académique à gauche, année et période à droite --}}
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
            <div>Année Scolaire : {{ $bulletin->anneeScolaire?->nom ?? '—' }}</div>
            <div>{{ $bulletin->periode?->libelle ?? '—' }}</div>
        </td>
    </tr>
</table>

<div class="titre-bulletin">BULLETIN DE NOTES</div>

{{-- Identité de l'élève, telle que figée à la génération --}}
<table class="identite">
    <tr>
        <td class="lib">Prénoms</td>
        <td class="val">{{ $bulletin->eleve_prenom ?? '—' }}</td>
        <td class="lib">Nom</td>
        <td class="val">{{ $bulletin->eleve_nom ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lib">Né(e) le</td>
        <td class="val">{{ $bulletin->eleve_date_naissance?->format('d/m/y') ?? '—' }}</td>
        <td class="lib">à</td>
        <td class="val">{{ $bulletin->eleve_lieu_naissance ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lib">Matricule</td>
        <td class="val">{{ $bulletin->eleve_matricule ?? '—' }}</td>
        <td class="lib">Classe</td>
        <td class="val">{{ $bulletin->classe_nom ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lib">Nbre d'élèves</td>
        <td class="val">{{ $bulletin->effectif_classe }}</td>
        <td class="lib">Classe Redoublée</td>
        <td class="val">{{ $bulletin->classe_redoublee ? '1' : '0' }}</td>
    </tr>
</table>

{{-- Les disciplines --}}
<table class="notes">
    <thead>
        <tr>
            <th class="col-discipline">Disciplines</th>
            <th class="col-num">Devoir</th>
            <th class="col-num">Comp</th>
            <th class="col-num">Moy/20</th>
            <th class="col-coef">Coef</th>
            <th class="col-num">Moy x</th>
            <th class="col-rang">Rang</th>
            <th class="col-appreciation">Appréciations</th>
        </tr>
    </thead>
    <tbody>
        @forelse($bulletin->lignes as $ligne)
            <tr>
                <td class="discipline">{{ $ligne->matiere_nom }}</td>
                <td class="num">{{ $fmt($ligne->moy_devoirs) }}</td>
                <td class="num">{{ $fmt($ligne->composition) }}</td>
                <td class="num">{{ $fmt($ligne->moyenne) }}</td>
                <td class="num">{{ $ligne->coefficient ?? '-' }}</td>
                <td class="num">{{ $fmt($ligne->moy_x_coef) }}</td>
                <td class="num">{{ $ligne->rang ?? '' }}</td>
                <td class="appreciation">{{ $ligne->appreciation?->libelle() ?? '' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="vide">Aucune matière au programme de cette classe.</td>
            </tr>
        @endforelse

        <tr class="total">
            <td colspan="4">TOTAL</td>
            <td class="num">{{ $bulletin->total_coefficients }}</td>
            <td class="num">{{ $fmt($bulletin->total_points) }}</td>
            <td colspan="2"></td>
        </tr>
    </tbody>
</table>

{{-- Synthèse : moyenne, rang, et les deux cases d'assiduité encore vides --}}
<table class="synthese">
    <tr>
        <td class="lib">Moyenne</td>
        <td class="val-forte">{{ $fmt($bulletin->moyenne_generale) }} /20</td>
        <td class="lib">Rang</td>
        <td class="val-forte">
            {{ $bulletin->rang ?? '—' }}@if($bulletin->rang_ex_aequo) ex æquo @endif
        </td>
        <td class="lib">Retards</td>
        <td class="val">{{ $bulletin->retards ?? '—' }}</td>
        <td class="lib">Absences</td>
        <td class="val">{{ $bulletin->absences ?? '—' }}</td>
    </tr>
</table>

{{-- Les deux blocs de décision du conseil des professeurs --}}
<table class="conseil">
    <tr>
        <td class="colonne-conseil">
            <table class="cases">
                @foreach($decisions as $decision)
                    <tr>
                        <td class="libelle-case">{{ $decision->libelle() }}</td>
                        <td class="case">{!! $coche($bulletin->decision_conseil === $decision) !!}</td>
                    </tr>
                @endforeach
            </table>
        </td>
        <td class="espaceur"></td>
        <td class="colonne-conseil">
            <table class="cases">
                @foreach($distinctions as $distinction)
                    <tr>
                        <td class="libelle-case">{{ $distinction->libelle() }}</td>
                        <td class="case">{!! $coche($bulletin->distinction === $distinction) !!}</td>
                    </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>

<div class="observations-titre">Observations du conseil des professeurs</div>
<div class="observations">
    {{-- e() avant nl2br : échapper d'abord, sinon on rouvre une injection HTML --}}
    {!! $bulletin->observations ? nl2br(e($bulletin->observations)) : '&nbsp;' !!}
</div>

<table class="signatures">
    <tr>
        <td>Le Chef d'Etablissement</td>
        <td>Le Directeur des Etudes</td>
    </tr>
</table>
