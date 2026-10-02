<?php

namespace App\Http\Controllers;

use App\Enums\PieceJointeEnum;
use App\Helpers\ApiResponse;
use App\Http\Requests\PayerFactureMensualitesRequest;
use App\Helpers\ColorHelper;
use App\Http\Requests\PayerMensualiteRequest;
use App\Http\Requests\PayerMensualitesRepartiRequest;
use App\Http\Requests\ValiderInscriptionRequest;
use App\Interfaces\FinanceTresorierServiceInterface;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Parametrage;
use App\Services\WhatsappJustificatifService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FinanceTresorierController extends Controller
{
    public function __construct(
        private readonly FinanceTresorierServiceInterface $service,
        private readonly WhatsappJustificatifService      $whatsapp,
    ) {}

    /**
     * Encaisse un versement sur une inscription et la valide.
     *
     * L'inscription passe a VALIDEE uniquement si le montant verse est > 0 ;
     * statut_paiement devient PARTIEL, ou PAYE si la totalite est reglee.
     * Le PaiementInscription correspondant est cree dans la foulee.
     */
    public function validerInscription(ValiderInscriptionRequest $request, string $inscriptionId): JsonResponse
    {
        $this->authorizeCaisse();

        $inscription = $this->service->validerInscription(
            $inscriptionId,
            $request->validated(),
            $request->user(),
        );

        return ApiResponse::success(
            $inscription,
            $this->avecAvisWhatsapp('Inscription validée et paiement enregistré avec succès', $inscription->eleve),
        );
    }

    public function aEncaisser(Request $request): JsonResponse
    {
        $this->authorizeTresorier();

        $inscriptions = $this->service->listAEncaisser(
            perPage: (int) $request->input('per_page', 10),
            search:  trim($request->input('search', '')),
        );

        return ApiResponse::paginated($inscriptions, 'Inscriptions à encaisser récupérées avec succès');
    }

    public function paiements(Request $request): JsonResponse
    {
        $this->authorizeTresorier();

        $paiements = $this->service->listPaiements(
            perPage: (int) $request->input('per_page', 10),
            search:  trim($request->input('search', '')),
        );

        return ApiResponse::paginated($paiements, 'Liste des paiements d\'inscription récupérée avec succès');
    }

    public function showPaiement(string $id): JsonResponse
    {
        $this->authorizeTresorier();

        return ApiResponse::success($this->service->findPaiement($id));
    }

    /**
     * Justificatif PDF d'un versement sur inscription : recu de paiement si
     * l'inscription est soldee, decharge si un solde reste exigible.
     */
    public function recuInscriptionPdf(string $id): Response
    {
        $this->authorizeTresorier();

        return $this->renderRecu($this->service->dataForRecuInscription($id));
    }

    // ─── Mensualites ──────────────────────────────────────────────────────────

    /** Mensualites regroupees par eleve (inscription) pour l'annee en cours. */
    public function inscriptionsMensualites(Request $request): JsonResponse
    {
        $this->authorizeTresorier();

        $inscriptions = $this->service->listInscriptionsMensualites(
            perPage: (int) $request->input('per_page', 10),
            search:  trim($request->input('search', '')),
        );

        return ApiResponse::paginated($inscriptions, 'Mensualités par élève récupérées avec succès');
    }

    /**
     * Encaisse un versement global sur un eleve, reparti automatiquement sur ses
     * mensualites non soldees, des plus anciennes aux plus recentes.
     */
    public function payerMensualitesReparti(PayerMensualitesRepartiRequest $request, string $inscriptionId): JsonResponse
    {
        $this->authorizeCaisse();

        $inscription = $this->service->payerMensualitesReparti(
            $inscriptionId,
            $request->validated(),
            $request->user(),
        );

        return ApiResponse::success(
            $inscription,
            $this->avecAvisWhatsapp('Versement réparti enregistré avec succès', $inscription->eleve),
        );
    }

    /**
     * Encaisse plusieurs mois sur une seule facture.
     *
     * En mode AUTOMATIQUE, le montant recu est reparti en cascade sur les mois
     * non soldes : 50 000 pour une mensualite de 20 000 solde deux mois et
     * laisse 10 000 d'avance sur le troisieme. En mode SELECTION, le tresorier
     * a coche les mois et fixe les montants. Un seul numero, un seul PDF.
     */
    public function payerFactureMensualites(PayerFactureMensualitesRequest $request, string $inscriptionId): JsonResponse
    {
        $this->authorizeCaisse();

        $facture = $this->service->payerFactureMensualites(
            $inscriptionId,
            $request->validated(),
            $request->user(),
        );

        return ApiResponse::success(
            $facture,
            $this->avecAvisWhatsapp('Facture enregistrée avec succès', $facture->inscription?->eleve),
            201,
        );
    }

    public function facturesMensualite(Request $request): JsonResponse
    {
        $this->authorizeTresorier();

        $factures = $this->service->listFacturesMensualite(
            perPage: (int) $request->input('per_page', 10),
            search:  trim($request->input('search', '')),
        );

        return ApiResponse::paginated($factures, 'Liste des factures de mensualités récupérée avec succès');
    }

    public function showFactureMensualite(string $id): JsonResponse
    {
        $this->authorizeTresorier();

        return ApiResponse::success($this->service->findFactureMensualite($id));
    }

    /**
     * Justificatif PDF unique d'une facture multi-mois : le detail de chaque
     * mois regle sur un seul document, au lieu d'un PDF par mois.
     */
    public function factureMensualitePdf(string $id): Response
    {
        $this->authorizeTresorier();

        $data                  = $this->service->dataForFactureMensualite($id);
        $data['etablissement'] = Etablissement::first();
        $data['logo']          = $data['etablissement']?->logoDataUri();
        $data                  += ColorHelper::palette(Parametrage::couleurPrincipale());

        $prefixe = $data['estSolde'] ? 'facture' : 'decharge';
        $pdf     = Pdf::loadView('pdf.facture-mensualites', $data)->setPaper('a4', 'portrait');

        return $pdf->download(
            $prefixe . '-' . Str::slug($data['facture']->numero_facture) . '.pdf'
        );
    }

    public function mensualitesAEncaisser(Request $request): JsonResponse
    {
        $this->authorizeTresorier();

        $mensualites = $this->service->listMensualitesAEncaisser(
            perPage: (int) $request->input('per_page', 10),
            search:  trim($request->input('search', '')),
        );

        return ApiResponse::paginated($mensualites, 'Mensualités à encaisser récupérées avec succès');
    }

    /**
     * Encaisse un versement (eventuellement partiel) sur une mensualite.
     *
     * Le reglement peut se faire en plusieurs tranches : la mensualite passe a
     * PARTIEL tant qu'il reste un solde, puis a PAYE une fois soldee.
     */
    public function payerMensualite(PayerMensualiteRequest $request, string $mensualiteId): JsonResponse
    {
        $this->authorizeCaisse();

        $mensualite = $this->service->payerMensualite(
            $mensualiteId,
            $request->validated(),
            $request->user(),
        );

        return ApiResponse::success(
            $mensualite,
            $this->avecAvisWhatsapp('Versement enregistré avec succès', $mensualite->inscription?->eleve),
        );
    }

    public function paiementsMensualite(Request $request): JsonResponse
    {
        $this->authorizeTresorier();

        $paiements = $this->service->listPaiementsMensualite(
            perPage: (int) $request->input('per_page', 10),
            search:  trim($request->input('search', '')),
        );

        return ApiResponse::paginated($paiements, 'Liste des paiements de mensualité récupérée avec succès');
    }

    public function showPaiementMensualite(string $id): JsonResponse
    {
        $this->authorizeTresorier();

        return ApiResponse::success($this->service->findPaiementMensualite($id));
    }

    /**
     * Justificatif PDF d'un versement sur mensualite : recu de paiement si le
     * mois est solde, decharge si un solde reste exigible sur ce mois.
     */
    public function recuMensualitePdf(string $id): Response
    {
        $this->authorizeTresorier();

        return $this->renderRecu($this->service->dataForRecuMensualite($id));
    }

    // ─── Envoi WhatsApp au tuteur ─────────────────────────────────────────────

    /** Renvoie au tuteur le justificatif d'un versement sur inscription. */
    public function recuInscriptionWhatsapp(string $id): JsonResponse
    {
        $this->authorizeTresorier();

        return $this->renvoyerWhatsapp(PieceJointeEnum::RECU_INSCRIPTION, $id);
    }

    /** Renvoie au tuteur le justificatif d'une facture multi-mois. */
    public function factureMensualiteWhatsapp(string $id): JsonResponse
    {
        $this->authorizeTresorier();

        return $this->renvoyerWhatsapp(PieceJointeEnum::FACTURE_MENSUALITES, $id);
    }

    /**
     * L'envoi automatique suit chaque encaissement ; ce renvoi sert quand il a
     * echoue (session deconnectee, numero corrige depuis) ou que la famille
     * reclame de nouveau son document. Contrairement a l'envoi automatique,
     * l'echec est dit au tresorier : il doit savoir que rien n'est parti.
     */
    private function renvoyerWhatsapp(PieceJointeEnum $type, string $id): JsonResponse
    {
        $data = match ($type) {
            PieceJointeEnum::RECU_INSCRIPTION    => $this->service->dataForRecuInscription($id),
            PieceJointeEnum::RECU_MENSUALITE     => $this->service->dataForRecuMensualite($id),
            PieceJointeEnum::FACTURE_MENSUALITES => $this->service->dataForFactureMensualite($id),
        };

        if (! $this->whatsapp->disponible($data['eleve'])) {
            return ApiResponse::error($this->whatsapp->motifIndisponible($data['eleve']), 422);
        }

        try {
            $this->whatsapp->renvoyer($type, $id);
        } catch (\Throwable $e) {
            Log::warning('Renvoi WhatsApp du justificatif impossible', [
                'piece'    => $type->value,
                'piece_id' => $id,
                'erreur'   => $e->getMessage(),
            ]);

            return ApiResponse::error("L'envoi WhatsApp a échoué. Réessayez dans un instant.", 502);
        }

        return ApiResponse::success(null, 'Justificatif envoyé au tuteur sur WhatsApp.');
    }

    /** Dit au tresorier que le justificatif part aussi chez la famille. */
    private function avecAvisWhatsapp(string $message, ?Eleve $eleve): string
    {
        return $this->whatsapp->disponible($eleve)
            ? $message . '. Le justificatif est envoyé au tuteur sur WhatsApp.'
            : $message;
    }

    /**
     * Rend le justificatif commun aux deux types de versement. Le libelle du
     * fichier reprend la nature du document pour que le tresorier distingue un
     * recu d'une decharge sans l'ouvrir.
     */
    private function renderRecu(array $data): Response
    {
        $data['etablissement'] = Etablissement::first();
        $data['logo']          = $data['etablissement']?->logoDataUri();
        $data                  += ColorHelper::palette(Parametrage::couleurPrincipale());

        $prefixe = $data['estSolde'] ? 'recu' : 'decharge';
        $pdf     = Pdf::loadView('pdf.recu-paiement', $data)->setPaper('a4', 'portrait');

        return $pdf->download(
            $prefixe . '-' . Str::slug($data['paiement']->numero_recu) . '.pdf'
        );
    }

    /**
     * Les ecrans tresorier sont aussi accessibles a l'administrateur cote
     * frontend (TreasurerGuard) : on aligne l'autorisation serveur dessus.
     */
    private function authorizeTresorier(): void
    {
        if (! in_array(request()->user()?->role?->name, ['treasurer', 'admin'], true)) {
            abort(403, 'Accès non autorisé.');
        }
    }

    /**
     * Encaisser exige en plus le droit de caisse : `acces_caisse` decrit
     * precisement cela, mais n'etait jusqu'ici qu'affiche. La consultation
     * (listes, recus deja emis) reste ouverte, seul le maniement d'argent est
     * ferme. L'administrateur n'a pas de profil tresorier et n'est pas soumis
     * au drapeau : il supervise l'ensemble.
     */
    private function authorizeCaisse(): void
    {
        $this->authorizeTresorier();

        $user = request()->user();

        if ($user?->role?->name !== 'treasurer') {
            return;
        }

        $tresorier = $user->tresorier;

        // Le drapeau est NOT NULL DEFAULT true en base : seul un false explicite
        // ferme la caisse. On ne bloque donc que ce cas, et non l'absence de
        // profil, qui releverait d'une donnee incoherente plutot que d'un retrait
        // de droit — la traiter comme un refus masquerait le vrai probleme.
        if ($tresorier && $tresorier->acces_caisse === false) {
            abort(403, "Votre accès à la caisse a été désactivé : vous ne pouvez plus enregistrer d'encaissement.");
        }
    }
}
