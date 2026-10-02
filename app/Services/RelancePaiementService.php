<?php

namespace App\Services;

use App\Enums\StatutInscriptionEnum;
use App\Models\Etablissement;
use App\Models\Inscription;
use App\Models\Mensualite;
use App\Models\RelancePaiement;
use App\Models\Tuteur;
use App\Models\User;
use App\Support\WhatsappNumber;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Throwable;

/**
 * Relance WhatsApp des familles en retard de paiement.
 *
 * Ce qui compte comme arriere, sur l'annee en cours et pour une inscription
 * validee :
 *
 *  - le reste des frais d'inscription ;
 *  - les mensualites dont l'echeance est passee et qui ne sont pas soldees.
 *
 * Un mois a venir n'est pas un retard : on ne reclame pas ce qui n'est pas
 * encore du. Une inscription en attente n'en est pas un non plus — tant que
 * le premier versement n'a pas ete pris, l'eleve n'est pas inscrit.
 *
 * La relance s'adresse au tuteur, pas a l'eleve : une fratrie donne UN
 * message, qui detaille chaque enfant. Un delai minimal separe deux relances
 * d'une meme famille — le numero d'envoi de l'etablissement ne doit pas etre
 * signale comme indesirable.
 */
class RelancePaiementService
{
    private const MOIS = [
        1 => 'Janvier',   2 => 'Février',  3 => 'Mars',      4 => 'Avril',
        5 => 'Mai',       6 => 'Juin',     7 => 'Juillet',   8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];

    public function __construct(
        private readonly WahaService $waha,
    ) {}

    /** Les relances sont-elles possibles (WAHA configure) ? */
    public function active(): bool
    {
        return $this->waha->isConfigured();
    }

    public function delaiHeures(): int
    {
        return max(0, (int) config('services.waha.relance_delai_heures'));
    }

    /**
     * Les familles en retard, de la plus endettee a la moins endettee, avec ce
     * qui empeche eventuellement de les relancer.
     */
    public function debiteurs(): Collection
    {
        $familles = $this->arrieres();

        $dernieres = RelancePaiement::query()
            ->whereIn('tuteur_id', $familles->keys())
            ->where('statut', RelancePaiement::ENVOYEE)
            ->groupBy('tuteur_id')
            ->selectRaw('tuteur_id, max(created_at) as derniere')
            ->pluck('derniere', 'tuteur_id');

        return $familles
            ->map(function (array $famille) use ($dernieres) {
                /** @var Tuteur $tuteur */
                $tuteur   = $famille['tuteur'];
                $derniere = isset($dernieres[$tuteur->id]) ? Carbon::parse($dernieres[$tuteur->id]) : null;
                $blocage  = $this->blocage($tuteur, $famille['total'], $derniere);

                return [
                    'tuteur' => [
                        'id'          => $tuteur->id,
                        'nom_complet' => $tuteur->nom_complet,
                        'telephone'   => $tuteur->telephone_principal,
                    ],
                    'eleves'              => $famille['eleves'],
                    'total'               => $famille['total'],
                    'derniere_relance_at' => $derniere?->toIso8601String(),
                    'blocage'             => $blocage,
                    'blocage_message'     => $blocage ? $this->messageBlocage($blocage) : null,
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Envoie la relance. Les arrieres sont recalcules ici : une famille qui a
     * paye depuis l'affichage de la liste n'est pas relancee.
     *
     * @return array{statut: 'envoyee'|'ignoree'|'echec', message: string, fatal: bool, derniere_relance_at: ?string}
     */
    public function relancer(int|string $tuteurId, User $user): array
    {
        // Double clic, ou deux tresoriers en meme temps : un seul message part.
        $verrou = Cache::lock('relance-paiement:' . $tuteurId, 60);

        if (!$verrou->get()) {
            return $this->resultat('ignoree', 'Une relance est déjà en cours pour cette famille.');
        }

        try {
            return $this->envoyer(Tuteur::findOrFail($tuteurId), $user);
        } finally {
            $verrou->release();
        }
    }

    private function envoyer(Tuteur $tuteur, User $user): array
    {
        $famille = $this->arrieres($tuteur->id)->get($tuteur->id);
        $total   = $famille['total'] ?? 0;

        $derniere = RelancePaiement::query()
            ->where('tuteur_id', $tuteur->id)
            ->where('statut', RelancePaiement::ENVOYEE)
            ->max('created_at');

        $blocage = $this->blocage($tuteur, $total, $derniere ? Carbon::parse($derniere) : null);

        if ($blocage !== null) {
            return $this->resultat('ignoree', $this->messageBlocage($blocage));
        }

        $tentative = [
            'tuteur_id'      => $tuteur->id,
            'utilisateur_id' => $user->id,
            'type'           => RelancePaiement::RELANCE,
            'montant'        => $total,
            'telephone'      => WhatsappNumber::normalize($tuteur->telephone_principal),
        ];

        try {
            $this->waha->sendText(
                $this->waha->chatId($tuteur->telephone_principal),
                $this->message($tuteur, $famille),
            );
        } catch (Throwable $e) {
            Log::warning('Relance WhatsApp impossible', [
                'tuteur_id' => $tuteur->id,
                'erreur'    => $e->getMessage(),
            ]);

            RelancePaiement::create($tentative + [
                'statut' => RelancePaiement::ECHEC,
                'erreur' => Str::limit($e->getMessage(), 490),
            ]);

            [$message, $fatal] = $this->echec($e);

            return $this->resultat('echec', $message, $fatal);
        }

        $relance = RelancePaiement::create($tentative + ['statut' => RelancePaiement::ENVOYEE]);

        return $this->resultat(
            'envoyee',
            "Relance envoyée à {$tuteur->nom_complet}.",
            false,
            $relance->created_at->toIso8601String(),
        );
    }

    /**
     * Ce qui a ete envoye, du plus recent au plus ancien : relances du
     * tresorier et rappels automatiques, reussis ou non.
     */
    public function historique(int $perPage, string $search): LengthAwarePaginator
    {
        return RelancePaiement::query()
            ->with([
                // withTrashed : une relance reste lisible meme si la fiche du
                // tuteur a ete supprimee depuis.
                'tuteur' => fn ($q) => $q->withTrashed()->select('id', 'nom', 'prenom'),
                'utilisateur:id,first_name,last_name',
            ])
            ->when($search, fn ($q) => $q->where(function ($q) use ($search) {
                $q->where('telephone', 'ilike', "%{$search}%")
                  ->orWhereHas('tuteur', fn ($q) => $q->withTrashed()
                      ->where('nom', 'ilike', "%{$search}%")
                      ->orWhere('prenom', 'ilike', "%{$search}%")
                      ->orWhereRaw("(prenom || ' ' || nom) ilike ?", ["%{$search}%"])
                      ->orWhereRaw("(nom || ' ' || prenom) ilike ?", ["%{$search}%"]));
            }))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Rappelle aux familles les mensualites qui arrivent a echeance dans les
     * prochains jours. Appele chaque jour par le planificateur.
     *
     * On vise une fenetre (aujourd'hui -> J+N) et non un jour exact : si le
     * planificateur manque une journee, le rappel part le lendemain au lieu
     * d'etre perdu. En contrepartie, une famille deja rappelee dans cette
     * fenetre est ecartee, sans quoi elle recevrait le meme message N fois.
     *
     * @return array{envoyes: int, ignores: int, echecs: int, motif: ?string}
     */
    public function rappelerEcheances(): array
    {
        $bilan = ['envoyes' => 0, 'ignores' => 0, 'echecs' => 0, 'motif' => null];
        $jours = (int) config('services.waha.rappel_jours_avant');

        if ($jours <= 0) {
            return ['motif' => 'Rappel désactivé (WAHA_RAPPEL_JOURS_AVANT=0).'] + $bilan;
        }

        if (!$this->active()) {
            return ['motif' => "WAHA n'est pas configuré."] + $bilan;
        }

        $familles = $this->echeancesProches($jours);

        // Deja rappelee pour cette echeance, ou deja contactee trop
        // recemment (relance d'arriere comprise) : on n'insiste pas.
        $dejaContactes = RelancePaiement::query()
            ->whereIn('tuteur_id', $familles->keys())
            ->where('statut', RelancePaiement::ENVOYEE)
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q
                    ->where('type', RelancePaiement::RAPPEL)
                    ->where('created_at', '>', now()->subDays($jours + 1)))
                ->orWhere('created_at', '>', now()->subHours($this->delaiHeures())))
            ->pluck('tuteur_id')
            ->flip();

        foreach ($familles as $tuteurId => $famille) {
            /** @var Tuteur $tuteur */
            $tuteur = $famille['tuteur'];
            $chatId = $this->waha->chatId($tuteur->telephone_principal);

            if ($chatId === null || $dejaContactes->has($tuteurId)) {
                $bilan['ignores']++;

                continue;
            }

            $tentative = [
                'tuteur_id'      => $tuteur->id,
                'utilisateur_id' => null,
                'type'           => RelancePaiement::RAPPEL,
                'montant'        => $famille['total'],
                'telephone'      => WhatsappNumber::normalize($tuteur->telephone_principal),
            ];

            try {
                $this->waha->sendText($chatId, $this->messageRappel($tuteur, $famille));
            } catch (Throwable $e) {
                Log::warning("Rappel d'échéance WhatsApp impossible", [
                    'tuteur_id' => $tuteur->id,
                    'erreur'    => $e->getMessage(),
                ]);

                RelancePaiement::create($tentative + [
                    'statut' => RelancePaiement::ECHEC,
                    'erreur' => Str::limit($e->getMessage(), 490),
                ]);

                $bilan['echecs']++;

                [$message, $fatal] = $this->echec($e);

                // Service en panne : les familles restantes seront rappelees
                // au prochain passage, la fenetre le permet.
                if ($fatal) {
                    $bilan['motif'] = $message;

                    break;
                }

                continue;
            }

            RelancePaiement::create($tentative + ['statut' => RelancePaiement::ENVOYEE]);
            $bilan['envoyes']++;

            // WhatsApp tolere mal les rafales depuis un meme numero.
            Sleep::for(1500)->milliseconds();
        }

        return $bilan;
    }

    /**
     * Les mensualites non soldees dont l'echeance tombe d'ici N jours,
     * regroupees par tuteur.
     *
     * @return Collection<int, array{tuteur: Tuteur, lignes: list<array>, total: int}>
     */
    private function echeancesProches(int $jours): Collection
    {
        $mensualites = Mensualite::query()
            ->with(['paiements', 'inscription.eleve.tuteur', 'inscription.classe'])
            ->nonSolde()
            ->whereBetween('date_echeance', [now()->toDateString(), now()->addDays($jours)->toDateString()])
            ->whereHas('inscription', fn ($q) => $q
                ->where('statut_inscription', StatutInscriptionEnum::VALIDEE)
                ->whereHas('anneeScolaire', fn ($q) => $q->where('en_cours', true)))
            ->orderBy('date_echeance')
            ->get();

        $familles = [];

        foreach ($mensualites as $mensualite) {
            $eleve  = $mensualite->inscription?->eleve;
            $tuteur = $eleve?->tuteur;

            if ($tuteur === null || $mensualite->reste <= 0) {
                continue;
            }

            $familles[$tuteur->id] ??= ['tuteur' => $tuteur, 'lignes' => [], 'total' => 0];

            $familles[$tuteur->id]['lignes'][] = [
                'eleve'    => $eleve->nom_complet,
                'classe'   => $mensualite->inscription->classe?->nom,
                'libelle'  => sprintf('%s %d', self::MOIS[$mensualite->mois] ?? $mensualite->mois, $mensualite->annee),
                'reste'    => $mensualite->reste,
                'echeance' => $mensualite->date_echeance->format('d/m/Y'),
            ];
            $familles[$tuteur->id]['total'] += $mensualite->reste;
        }

        return collect($familles);
    }

    private function messageRappel(Tuteur $tuteur, array $famille): string
    {
        $etablissement = Etablissement::first();
        $montant       = fn (int $v) => number_format($v, 0, ',', ' ') . ' FCFA';

        $lignes = array_map(
            fn (array $l) => "• *{$l['eleve']}*"
                . ($l['classe'] ? " ({$l['classe']})" : '')
                . " : {$l['libelle']} — {$montant($l['reste'])}, à régler au plus tard le {$l['echeance']}",
            $famille['lignes'],
        );

        $contact = $etablissement?->telephone_principal;

        return "Bonjour {$tuteur->nom_complet},\n\n"
            . 'Petit rappel' . ($etablissement?->nom ? " de *{$etablissement->nom}*" : '')
            . (count($lignes) > 1 ? ' : les mensualités suivantes arrivent' : ' : la mensualité suivante arrive')
            . " à échéance.\n"
            . implode("\n", $lignes)
            . "\n\nMerci de passer à la caisse"
            . ($contact ? " ou de nous contacter au {$contact}" : '') . ".\n"
            . 'Si vous avez déjà réglé, merci de ne pas tenir compte de ce message.';
    }

    /**
     * Les arrieres regroupes par tuteur.
     *
     * @return Collection<int, array{tuteur: Tuteur, eleves: list<array>, total: int}>
     */
    private function arrieres(?int $tuteurId = null): Collection
    {
        $inscriptions = Inscription::query()
            ->with([
                'eleve.tuteur',
                'classe',
                'paiements',
                'mensualites' => fn ($q) => $q->orderBy('annee')->orderBy('mois'),
                'mensualites.paiements',
            ])
            ->whereHas('anneeScolaire', fn ($q) => $q->where('en_cours', true))
            ->where('statut_inscription', StatutInscriptionEnum::VALIDEE)
            ->whereHas('eleve', fn ($q) => $tuteurId === null
                ? $q->whereNotNull('tuteur_id')
                : $q->where('tuteur_id', $tuteurId))
            ->get();

        $familles = [];

        foreach ($inscriptions as $inscription) {
            $tuteur = $inscription->eleve?->tuteur;

            if ($tuteur === null) {
                continue;
            }

            $mois = $inscription->mensualites
                ->filter(fn (Mensualite $m) => $m->estEnRetard())
                ->map(fn (Mensualite $m) => [
                    'libelle' => sprintf('%s %d', self::MOIS[$m->mois] ?? $m->mois, $m->annee),
                    'reste'   => $m->reste,
                ])
                ->values()
                ->all();

            $resteInscription = (int) $inscription->montant_inscription_restant;
            $total            = $resteInscription + array_sum(array_column($mois, 'reste'));

            if ($total <= 0) {
                continue;
            }

            $familles[$tuteur->id] ??= ['tuteur' => $tuteur, 'eleves' => [], 'total' => 0];

            $familles[$tuteur->id]['eleves'][] = [
                'nom_complet'       => $inscription->eleve->nom_complet,
                'matricule'         => $inscription->eleve->matricule,
                'classe'            => $inscription->classe?->nom,
                'reste_inscription' => $resteInscription,
                'mois'              => $mois,
                'total'             => $total,
            ];
            $familles[$tuteur->id]['total'] += $total;
        }

        return collect($familles);
    }

    /** Ce qui empeche de relancer (`inactif`, `sans_arriere`, `sans_telephone`, `recent`), ou null. */
    private function blocage(Tuteur $tuteur, int $total, ?CarbonInterface $derniere): ?string
    {
        return match (true) {
            !$this->active() => 'inactif',
            $total <= 0 => 'sans_arriere',
            WhatsappNumber::normalize($tuteur->telephone_principal) === null => 'sans_telephone',
            $derniere !== null && $derniere->gt(now()->subHours($this->delaiHeures())) => 'recent',
            default => null,
        };
    }

    private function messageBlocage(string $blocage): string
    {
        return match ($blocage) {
            'inactif'        => "L'envoi WhatsApp n'est pas configuré pour cet établissement.",
            'sans_arriere'   => "Cette famille n'a plus d'arriéré.",
            'sans_telephone' => "Le téléphone du tuteur n'est pas un numéro WhatsApp valide.",
            'recent'         => 'Famille déjà relancée il y a moins de ' . $this->delaiHeures() . ' h.',
        };
    }

    /**
     * Message affiche au tresorier, et si la panne touche tout le service
     * (inutile de poursuivre un lot) ou seulement ce destinataire.
     *
     * @return array{0: string, 1: bool}
     */
    private function echec(Throwable $e): array
    {
        if ($e instanceof ConnectionException) {
            return ['Le service WhatsApp ne répond pas. Réessayez dans quelques minutes.', true];
        }

        $code = $e instanceof RequestException ? $e->response->status() : null;

        return match (true) {
            $code === 401, $code === 403 => ["Le service WhatsApp refuse l'accès. Contactez l'administrateur.", true],
            $code === 404, $code === 422 => ["La session WhatsApp n'est pas connectée. Contactez l'administrateur.", true],
            default => ["Le message n'a pas pu être envoyé à ce numéro.", false],
        };
    }

    /**
     * Le texte recu par la famille : le total, puis le detail par enfant. Le
     * ton reste celui d'un rappel — « sauf erreur de notre part » — parce
     * qu'un paiement fait la veille peut ne pas encore etre saisi.
     */
    private function message(Tuteur $tuteur, array $famille): string
    {
        $etablissement = Etablissement::first();
        $montant       = fn (int $v) => number_format($v, 0, ',', ' ') . ' FCFA';

        $lignes = [];

        foreach ($famille['eleves'] as $eleve) {
            $details = [];

            if ($eleve['reste_inscription'] > 0) {
                $details[] = "frais d'inscription ({$montant($eleve['reste_inscription'])})";
            }

            foreach ($eleve['mois'] as $mois) {
                $details[] = "{$mois['libelle']} ({$montant($mois['reste'])})";
            }

            $lignes[] = "• *{$eleve['nom_complet']}*"
                . ($eleve['classe'] ? " ({$eleve['classe']})" : '')
                . ' : ' . implode(', ', $details);
        }

        $contact = $etablissement?->telephone_principal;

        return "Bonjour {$tuteur->nom_complet},\n\n"
            . "Sauf erreur de notre part, il vous reste *{$montant($famille['total'])}* à régler"
            . ($etablissement?->nom ? " à *{$etablissement->nom}*" : '') . " :\n"
            . implode("\n", $lignes)
            . "\n\nMerci de passer à la caisse"
            . ($contact ? " ou de nous contacter au {$contact}" : '') . ".\n"
            . 'Si vous avez déjà réglé, merci de ne pas tenir compte de ce message.';
    }

    private function resultat(string $statut, string $message, bool $fatal = false, ?string $derniere = null): array
    {
        return [
            'statut'              => $statut,
            'message'             => $message,
            'fatal'               => $fatal,
            'derniere_relance_at' => $derniere,
        ];
    }
}
