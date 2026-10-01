<?php

namespace App\Services;

use App\Interfaces\NotesTuteurServiceInterface;
use App\Models\ClasseMatiere;
use App\Models\Eleve;
use App\Models\Evaluation;
use App\Models\Periode;
use App\Models\Tuteur;
use App\Models\User;
use App\Services\Bulletin\CalculateurBulletin;
use App\Services\Bulletin\LigneCalculee;
use Illuminate\Support\Collection;

/**
 * Les resultats scolaires tels que la famille les consulte.
 *
 * Deux principes tiennent tout cet ecran :
 *
 *  1. La famille ne designe jamais l'eleve qu'elle regarde. L'identifiant
 *     arrive bien du client, mais il est confronte a la liste des enfants
 *     rattaches au compte connecte : un tuteur qui change le numero dans l'URL
 *     se heurte a un 403, il ne decouvre pas les notes de l'enfant du voisin.
 *
 *  2. Les moyennes ne sont pas recalculees ici. Elles viennent de
 *     CalculateurBulletin, qui porte les regles validees sur le bulletin
 *     papier de reference — absence comptee zero, matiere non notee hors du
 *     total des coefficients, moyennes exactes et non arrondies. Une seconde
 *     implementation divergerait tot ou tard, et la famille verrait une
 *     moyenne differente de celle du bulletin.
 *
 * Ce qui est montre ici, ce sont les notes au fil de l'eau : elles n'attendent
 * pas la publication du bulletin. Une note corrigee apres coup aura donc pu
 * etre vue sous son ancienne valeur — c'est le prix du suivi en temps reel, et
 * l'ecran l'annonce.
 */
class NotesTuteurService implements NotesTuteurServiceInterface
{
    public function __construct(
        private readonly CalculateurBulletin $calculateur,
    ) {}

    public function mesEleves(User $user): array
    {
        $eleves = $this->elevesDuTuteur($user);

        return $eleves
            ->map(fn (Eleve $eleve) => [
                'id'         => $eleve->id,
                'nom_complet'=> $eleve->nom_complet,
                'matricule'  => $eleve->matricule,
                'photo'      => $eleve->photo,
                'classe'     => $eleve->classeActuelle?->nom,
                'classe_id'  => $eleve->classe_actuelle_id,
            ])
            ->values()
            ->all();
    }

    public function relevePourEleve(User $user, int|string $eleveId, int|string|null $periodeId = null): array
    {
        $eleve = $this->eleveAutorise($user, $eleveId);

        $classe = $eleve->classeActuelle;

        if (! $classe) {
            // Un eleve sans classe n'a pas de programme : il n'y a rien a
            // presenter, mais ce n'est pas une erreur — l'inscription peut
            // etre en cours.
            return [
                'eleve'    => $this->resumeEleve($eleve),
                'periodes' => [],
                'periode'  => null,
                'matieres' => [],
                'synthese' => null,
            ];
        }

        $periodes = $this->periodesDeLaClasse($classe->annee_scolaire_id);
        $periode  = $this->periodeChoisie($periodes, $periodeId);

        if (! $periode) {
            return [
                'eleve'    => $this->resumeEleve($eleve),
                'periodes' => $periodes->map(fn (Periode $p) => $this->resumePeriode($p))->all(),
                'periode'  => null,
                'matieres' => [],
                'synthese' => null,
            ];
        }

        // Le programme de la classe : chaque matiere y figure, meme celles ou
        // rien n'a encore ete note. Une matiere absente de l'ecran laisserait
        // croire qu'elle n'est pas enseignee.
        $programme = ClasseMatiere::query()
            ->where('classe_id', $classe->id)
            ->with('matiere:id,nom,code')
            ->orderBy('id')
            ->get();

        $evaluations = $this->evaluationsDeLaPeriode($classe->id, $periode->id);

        $lignes = $this->calculateur->lignesPourEleve((int) $eleve->id, $programme, $evaluations);

        return [
            'eleve'    => $this->resumeEleve($eleve),
            'periodes' => $periodes->map(fn (Periode $p) => $this->resumePeriode($p))->all(),
            'periode'  => $this->resumePeriode($periode),
            'matieres' => $this->matieresAvecDetail($lignes, $programme, $evaluations, (int) $eleve->id),
            'synthese' => $this->synthese($lignes),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────
    // Acces
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Les enfants rattaches au compte connecte.
     *
     * La fiche tuteur est le pivot : le compte `users` sert a se connecter, la
     * fiche `tuteurs` porte les enfants. Un compte sans fiche ne voit rien.
     */
    private function elevesDuTuteur(User $user): Collection
    {
        $tuteur = Tuteur::query()
            ->where('user_id', $user->id)
            ->first();

        if (! $tuteur) {
            return collect();
        }

        return Eleve::query()
            ->where('tuteur_id', $tuteur->id)
            // `annee_scolaire_id` fait partie de la selection : c'est par lui
            // que les periodes sont retrouvees. Le limiter au nom laisserait
            // la colonne a null et l'ecran sans aucune periode.
            ->with('classeActuelle:id,nom,annee_scolaire_id')
            ->orderBy('prenom')
            ->orderBy('nom')
            ->get();
    }

    /**
     * L'eleve demande, s'il est bien l'un des siens.
     *
     * Le controle se fait sur la liste des enfants du tuteur, et non sur un
     * simple `tuteur_id` lu depuis l'eleve : c'est la meme verite, mais dite
     * dans le sens ou la question se pose — « cet enfant est-il des siens ? ».
     */
    private function eleveAutorise(User $user, int|string $eleveId): Eleve
    {
        $eleve = $this->elevesDuTuteur($user)
            ->firstWhere('id', (int) $eleveId);

        if (! $eleve) {
            abort(403, "Vous ne pouvez consulter que les résultats de vos enfants.");
        }

        return $eleve;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Donnees
    // ─────────────────────────────────────────────────────────────────────

    private function periodesDeLaClasse(?int $anneeScolaireId): Collection
    {
        if (! $anneeScolaireId) {
            return collect();
        }

        return Periode::query()
            ->where('annee_scolaire_id', $anneeScolaireId)
            ->orderBy('ordre')
            ->get();
    }

    /**
     * La periode demandee, ou celle du moment.
     *
     * A defaut de periode courante — entre deux trimestres, ou apres la
     * derniere — on retient la derniere commencee : la famille retrouve ainsi
     * le dernier releve qui la concerne, plutot qu'un ecran vide.
     */
    private function periodeChoisie(Collection $periodes, int|string|null $periodeId): ?Periode
    {
        if ($periodes->isEmpty()) {
            return null;
        }

        if ($periodeId !== null) {
            return $periodes->firstWhere('id', (int) $periodeId);
        }

        $aujourdhui = now()->startOfDay();

        $enCours = $periodes->first(
            fn (Periode $p) => $p->date_debut
                && $p->date_fin
                && $aujourdhui->betweenIncluded($p->date_debut, $p->date_fin)
        );

        if ($enCours) {
            return $enCours;
        }

        $commencees = $periodes->filter(
            fn (Periode $p) => $p->date_debut && $aujourdhui->greaterThanOrEqualTo($p->date_debut)
        );

        return $commencees->isNotEmpty() ? $commencees->last() : $periodes->first();
    }

    /**
     * Les evaluations de la periode pour cette classe, notes chargees.
     *
     * `notes` est charge en entier plutot que filtre sur l'eleve : le
     * calculateur attend cette forme, et la meme collection sert ensuite au
     * detail evaluation par evaluation.
     */
    private function evaluationsDeLaPeriode(int $classeId, int $periodeId): Collection
    {
        return Evaluation::query()
            ->where('periode_id', $periodeId)
            ->whereHas('affectation.classeMatiere', fn ($q) => $q->where('classe_id', $classeId))
            ->with([
                'affectation:id,classe_matiere_id',
                'affectation.classeMatiere:id,classe_id,matiere_id,coefficient',
                'notes',
            ])
            ->orderBy('date_evaluation')
            ->get();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Mise en forme
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Les matieres, chacune avec sa moyenne et le detail de ses evaluations.
     *
     * @param  array<int, LigneCalculee>  $lignes
     * @return array<int, array<string, mixed>>
     */
    private function matieresAvecDetail(
        array $lignes,
        Collection $programme,
        Collection $evaluations,
        int $eleveId
    ): array {
        $parClasseMatiere = $evaluations->groupBy(
            fn (Evaluation $e) => (int) ($e->affectation?->classe_matiere_id ?? 0)
        );

        $resultat = [];

        foreach ($programme as $classeMatiere) {
            $matiereId = $classeMatiere->matiere_id !== null ? (int) $classeMatiere->matiere_id : null;

            $ligne = $this->ligneDeLaMatiere($lignes, $matiereId, $classeMatiere->matiere?->nom);

            $evals = $parClasseMatiere
                ->get((int) $classeMatiere->id, collect())
                ->map(fn (Evaluation $e) => $this->detailEvaluation($e, $eleveId))
                ->values()
                ->all();

            $resultat[] = [
                'matiere_id'  => $matiereId,
                'matiere'     => $classeMatiere->matiere?->nom ?? 'Matière supprimée',
                'code'        => $classeMatiere->matiere?->code,
                'coefficient' => (int) ($classeMatiere->coefficient ?? 1),
                'moy_devoirs' => $ligne?->moyDevoirs !== null ? round($ligne->moyDevoirs, 2) : null,
                'composition' => $ligne?->composition !== null ? round($ligne->composition, 2) : null,
                'moyenne'     => $ligne?->moyenneAffichee(),
                'appreciation'=> $ligne?->appreciation()?->value,
                'notee'       => (bool) $ligne?->notee,
                'evaluations' => $evals,
            ];
        }

        return $resultat;
    }

    /**
     * Retrouve la ligne calculee d'une matiere.
     *
     * Les lignes sont indexees par `matiere_id`, sauf celles dont la matiere a
     * ete supprimee : le calculateur les range alors sous une cle negative. On
     * retombe donc sur le nom pour ce cas.
     */
    private function ligneDeLaMatiere(array $lignes, ?int $matiereId, ?string $nom): ?LigneCalculee
    {
        if ($matiereId !== null && isset($lignes[$matiereId])) {
            return $lignes[$matiereId];
        }

        foreach ($lignes as $ligne) {
            if ($ligne->matiereNom === $nom) {
                return $ligne;
            }
        }

        return null;
    }

    /**
     * Une evaluation vue par la famille : le titre, la date, et la note de son
     * enfant — jamais celles des autres eleves de la classe.
     */
    private function detailEvaluation(Evaluation $evaluation, int $eleveId): array
    {
        $note = $evaluation->notes->firstWhere('eleve_id', $eleveId);

        return [
            'id'      => $evaluation->id,
            'titre'   => $evaluation->titre,
            'type'    => $evaluation->type_libelle,
            'date'    => $evaluation->date_evaluation?->toDateString(),
            'bareme'  => (int) $evaluation->bareme,
            'valeur'  => $note && ! $note->absent ? (float) $note->valeur : null,
            'absent'  => (bool) ($note?->absent),
            // Une evaluation sans note pour cet eleve n'est pas une absence :
            // elle n'a simplement pas encore ete saisie.
            'saisie'  => $note !== null,
        ];
    }

    /**
     * Moyenne generale et totaux, calcules par le meme moteur que le bulletin.
     *
     * @param  array<int, LigneCalculee>  $lignes
     */
    private function synthese(array $lignes): ?array
    {
        $agregats = $this->calculateur->agregats($lignes);

        if ($agregats['moyenne_generale'] === null) {
            return null;
        }

        return [
            'moyenne_generale'   => round($agregats['moyenne_generale'], 2),
            'total_points'       => $agregats['total_points'] !== null
                ? round($agregats['total_points'], 2)
                : null,
            'total_coefficients' => $agregats['total_coefficients'],
            'mention'            => $this->calculateur->mentionPour($agregats['moyenne_generale'])?->value,
        ];
    }

    private function resumeEleve(Eleve $eleve): array
    {
        return [
            'id'          => $eleve->id,
            'nom_complet' => $eleve->nom_complet,
            'matricule'   => $eleve->matricule,
            'photo'       => $eleve->photo,
            'classe'      => $eleve->classeActuelle?->nom,
        ];
    }

    private function resumePeriode(Periode $periode): array
    {
        return [
            'id'      => $periode->id,
            'libelle' => $periode->libelle,
            'ordre'   => $periode->ordre,
        ];
    }
}
