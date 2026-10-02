<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AffectationController;
use App\Http\Controllers\AnneeScolaireController;
use App\Http\Controllers\AnnuaireTuteurController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BilanController;
use App\Http\Controllers\ClasseController;
use App\Http\Controllers\CompteTuteurController;
use App\Http\Controllers\ContratController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ClasseMatiereController;
use App\Http\Controllers\EleveController;
use App\Http\Controllers\EmploiDuTempsController;
use App\Http\Controllers\EnseignantController;
use App\Http\Controllers\AssiduiteController;
use App\Http\Controllers\BulletinController;
use App\Http\Controllers\EvaluationController;
use App\Http\Controllers\MatiereController;
use App\Http\Controllers\EtablissementController;
use App\Http\Controllers\FinanceTresorierController;
use App\Http\Controllers\RelancePaiementController;
use App\Http\Controllers\WhatsappController;
use App\Http\Controllers\FraisScolaireController;
use App\Http\Controllers\InscriptionController;
use App\Http\Controllers\DepenseController;
use App\Http\Controllers\NiveauController;
use App\Http\Controllers\NotesTuteurController;
use App\Http\Controllers\ParametrageController;
use App\Http\Controllers\PeriodeController;
use App\Http\Controllers\StatistiqueController;
use App\Http\Controllers\SurveillantController;
use App\Http\Controllers\TresorierController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function () {

    // Le blocage progressif par IP couvre deja ces trois routes (middleware
    // global). Le limiteur pose ici est une seconde barriere, sur un autre
    // critere : il borne la cadence, la ou le blocage borne le nombre d'echecs.
    // Un attaquant qui n'echoue jamais — parce qu'il enumere des jetons de
    // challenge plutot que des mots de passe — ne declenche que celui-ci.
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login');

    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:10,1')
        ->name('verify-otp');

    // Chaque renvoi part en e-mail : la cadence y est plus serree, pour que le
    // bouton « renvoyer » ne devienne pas un moyen d'inonder une boite.
    Route::post('resend-otp', [AuthController::class, 'resendOtp'])
        ->middleware('throttle:5,10')
        ->name('resend-otp');

    // Mot de passe oublie. Chaque demande envoie un e-mail : la cadence y est
    // serree, sans quoi le formulaire — ouvert a tous — servirait a inonder la
    // boite de n'importe qui. Le service pose en plus un delai par adresse ;
    // celui-ci borne l'IP, qui pourrait sinon balayer les adresses une a une.
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])
        ->middleware('throttle:5,10')
        ->name('forgot-password');

    Route::post('reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:10,10')
        ->name('reset-password');

    Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');

    Route::middleware('jwt.auth')->group(function () {
        Route::post('logout',          [AuthController::class, 'logout'])->name('logout');
        Route::get('me',               [AuthController::class, 'me'])->name('me');

        // Coordonnees et photo du compte connecte. En POST et non en PUT :
        // la photo voyage en multipart, que PHP ne sait lire que sur un POST
        // (php://input n'est pas parse pour les autres verbes).
        Route::post('profil',          [AuthController::class, 'updateProfil'])->name('profil.update');
        Route::post('change-password', [AuthController::class, 'changePassword'])->name('change-password');
        Route::post('two-factor',      [AuthController::class, 'toggleTwoFactor'])->name('two-factor');
    });
});

Route::get('etablissement/infos',[EtablissementController::class, 'index']);

// Formulaire de contact de la page d'accueil. Publique par nature — le
// visiteur n'a pas de compte — donc limitee : chaque appel declenche un envoi
// de courrier vers la boite de l'etablissement.
Route::post('etablissement/contact',[EtablissementController::class, 'contact'])
    ->middleware('throttle:5,10');
// Grille tarifaire de l'annee en cours (bareme + niveau), affichee sur la page
// d'accueil publique. Remplace l'ancienne route 'liste-niveaux' : les montants
// ne vivent plus sur le niveau, seul le bareme les porte.
Route::get('liste-frais-scolaires',[FraisScolaireController::class, 'listePublique']);

// Verification d'un contrat depuis le QR code de sa version imprimee. Publique
// par necessite : celui qui scanne le papier — banque, bailleur, administration
// — n'a pas de compte ici. La reponse se borne a attester le document.
Route::get('verification-contrat/{code}', [ContratController::class, 'verifier'])
    ->where('code', '[A-Za-z0-9]{20,64}');

Route::middleware(['jwt.auth'])->group(function () {

    Route::prefix('users')->group(function () {
        Route::get('/list',                    [UserController::class, 'index']);
        Route::post('/add',                    [UserController::class, 'store']);
        Route::get('/show/{id}',               [UserController::class, 'show']);
        Route::put('/update/{id}',             [UserController::class, 'update']);
        Route::delete('/disable/{id}',         [UserController::class, 'disable']);
        Route::delete('/destroy/{id}/force',   [UserController::class, 'destroy']);
        Route::post('/restore/{id}',           [UserController::class, 'restore']);

        // Renvoi du lien d'ouverture de compte, pour un message perdu ou un
        // lien expire avant d'avoir servi.
        Route::post('/renvoyer-activation/{id}', [UserController::class, 'renvoyerActivation']);

        // Reinitialisation des acces d'un membre du personnel, a la main du
        // manager. Limitee : chaque appel envoie un courriel, et le lien emis
        // annule le precedent.
        Route::post('/reinitialiser-acces/{id}', [UserController::class, 'reinitialiserAcces'])
            ->middleware('throttle:10,1');
    });

    Route::prefix('etablissement')->group(function () {
        Route::post('/store',        [EtablissementController::class, 'store']);
        Route::patch('/update/{id}', [EtablissementController::class, 'update']);
        Route::post('/logo/{id}',    [EtablissementController::class, 'updateLogo']);
    });

    // Parametrage : meme ecran que l'etablissement cote client, mais la
    // modification reste reservee a l'admin (cf. UpdateParametrageRequest).
    // Le mode maintenance se pilote par ce PUT, il n'a pas de route dediee :
    // les anciennes ne savaient qu'activer, jamais desactiver.
    Route::prefix('parametrages')->group(function () {
        Route::get('/infos',  [ParametrageController::class, 'infos']);
        Route::put('/update', [ParametrageController::class, 'update']);
    });

    Route::prefix('annees-scolaires')->group(function () {
        Route::get('/list',          [AnneeScolaireController::class, 'index']);
        Route::get('/all',           [AnneeScolaireController::class, 'all']);
        Route::get('/show/{id}',     [AnneeScolaireController::class, 'show']);
        Route::post('/add',          [AnneeScolaireController::class, 'store']);
        Route::put('/update/{id}',   [AnneeScolaireController::class, 'update']);
        Route::delete('/delete/{id}',[AnneeScolaireController::class, 'destroy']);
    });

    // Tableaux de bord : manager (effectifs, répartitions, finances) et
    // trésorier (recouvrement, encaissements, modes de paiement).
    Route::prefix('statistiques')->group(function () {
        Route::get('/dashboard',            [StatistiqueController::class, 'dashboard']);
        Route::get('/dashboard-tresorier',  [StatistiqueController::class, 'dashboardTresorier']);
        Route::get('/dashboard-enseignant', [StatistiqueController::class, 'dashboardEnseignant']);
    });

    // Bilan financier de l'annee scolaire : resultat de tresorerie, depenses
    // par poste, masse salariale et creances. Filtrable par mois. Ouvert au
    // tresorier, au manager et a l'admin (controle dans le controleur).
    Route::get('bilan', [BilanController::class, 'index']);

    Route::prefix('niveaux')->group(function () {
        Route::get('/list',          [NiveauController::class, 'index']);
        // Referentiel complet, non pagine : listes deroulantes (classes…).
        Route::get('/all',           [NiveauController::class, 'all']);
        // Niveaux + bareme de l'annee en cours : l'interface unifiee.
        Route::get('/grille',        [NiveauController::class, 'grille']);
        Route::get('/show/{id}',     [NiveauController::class, 'show']);
        Route::post('/add',          [NiveauController::class, 'store']);
        Route::put('/update/{id}',   [NiveauController::class, 'update']);
        Route::delete('/delete/{id}',[NiveauController::class, 'destroy']);
    });

    // Depenses de l'etablissement : admin, manager et tresorier. Le detail des
    // autorisations (suppression reservee) est porte par le controleur.
    Route::prefix('depenses')->group(function () {
        Route::get('/list',           [DepenseController::class, 'index']);
        Route::get('/all',            [DepenseController::class, 'all']);
        Route::get('/totaux',         [DepenseController::class, 'totaux']);
        Route::get('/mois-en-cours',  [DepenseController::class, 'moisAnneeEnCours']);
        Route::get('/referentiels',   [DepenseController::class, 'referentiels']);
        Route::get('/show/{id}',      [DepenseController::class, 'show']);
        Route::post('/add',           [DepenseController::class, 'store']);
        Route::put('/update/{id}',    [DepenseController::class, 'update']);
        // Le circuit de validation : le tresorier saisit, le manager engage.
        Route::post('/valider/{id}',  [DepenseController::class, 'valider']);
        Route::post('/refuser/{id}',  [DepenseController::class, 'refuser']);
        Route::delete('/delete/{id}', [DepenseController::class, 'destroy']);
    });

    Route::prefix('frais-scolaires')->group(function () {
        Route::get('/list',                          [FraisScolaireController::class, 'index']);
        Route::get('/all',                           [FraisScolaireController::class, 'all']);
        // Annee en cours + niveaux encore sans bareme : ce que le formulaire propose.
        Route::get('/referentiels',                  [FraisScolaireController::class, 'referentiels']);
        Route::get('/show/{id}',                     [FraisScolaireController::class, 'show']);
        Route::post('/add',                          [FraisScolaireController::class, 'store']);
        Route::put('/update/{id}',                   [FraisScolaireController::class, 'update']);
        Route::delete('/delete/{id}',                [FraisScolaireController::class, 'destroy']);
        Route::post('/generer-grille/{anneeId}',     [FraisScolaireController::class, 'genererGrille']);
    });

    Route::prefix('periodes')->group(function () {
        Route::get('/list',                        [PeriodeController::class, 'index']);
        Route::get('/by-annee/{anneeId}',          [PeriodeController::class, 'byAnnee']);
        Route::get('/show/{id}',                   [PeriodeController::class, 'show']);
        Route::post('/add',                        [PeriodeController::class, 'store']);
        Route::put('/update/{id}',                 [PeriodeController::class, 'update']);
        Route::delete('/delete/{id}',              [PeriodeController::class, 'destroy']);
    });

    Route::prefix('surveillants')->group(function () {
        Route::get('/list',              [SurveillantController::class, 'index']);
        Route::get('/all',               [SurveillantController::class, 'all']);
        Route::get('/show/{id}',         [SurveillantController::class, 'show']);
        Route::post('/add',              [SurveillantController::class, 'store']);
        Route::put('/update/{id}',       [SurveillantController::class, 'update']);
        Route::delete('/delete/{id}',    [SurveillantController::class, 'destroy']);
        Route::post('/restore/{id}',     [SurveillantController::class, 'restore']);
    });

    Route::prefix('tresoriers')->group(function () {
        Route::get('/list',              [TresorierController::class, 'index']);
        Route::get('/all',               [TresorierController::class, 'all']);
        Route::get('/show/{id}',         [TresorierController::class, 'show']);
        Route::post('/add',              [TresorierController::class, 'store']);
        Route::put('/update/{id}',       [TresorierController::class, 'update']);
        Route::delete('/delete/{id}',    [TresorierController::class, 'destroy']);
        Route::post('/restore/{id}',     [TresorierController::class, 'restore']);
    });

    Route::prefix('enseignants')->group(function () {
        Route::get('/list',              [EnseignantController::class, 'index']);
        Route::get('/all',               [EnseignantController::class, 'all']);
        Route::get('/show/{id}',         [EnseignantController::class, 'show']);
        Route::post('/add',              [EnseignantController::class, 'store']);
        Route::put('/update/{id}',       [EnseignantController::class, 'update']);
        Route::delete('/delete/{id}',    [EnseignantController::class, 'destroy']);
        Route::post('/restore/{id}',     [EnseignantController::class, 'restore']);
    });

    // Contrats de travail du personnel (enseignant, tresorier, surveillant).
    // Le contrat initial nait avec le profil ; ces routes servent le suivi :
    // consultation, correction, resiliation et renouvellement.
    Route::prefix('contrats')->group(function () {
        Route::get('/list',                        [ContratController::class, 'index']);
        Route::get('/meta',                        [ContratController::class, 'meta']);
        Route::get('/show/{id}',                   [ContratController::class, 'show']);
        Route::get('/historique/{type}/{id}',      [ContratController::class, 'historique']);
        // Le contrat imprimable, QR de verification inclus.
        Route::get('/pdf/{id}',                    [ContratController::class, 'pdf']);
        Route::post('/add',                        [ContratController::class, 'store']);
        Route::put('/update/{id}',                 [ContratController::class, 'update']);
        Route::post('/resilier/{id}',              [ContratController::class, 'resilier']);
        Route::post('/renouveler/{id}',            [ContratController::class, 'renouveler']);
        Route::delete('/delete/{id}',              [ContratController::class, 'destroy']);
    });

    Route::prefix('classes')->group(function () {
        Route::get('/list',              [ClasseController::class, 'index']);
        Route::get('/all',               [ClasseController::class, 'all']);
        Route::get('/show/{id}',         [ClasseController::class, 'show']);
        Route::post('/add',              [ClasseController::class, 'store']);
        Route::put('/update/{id}',       [ClasseController::class, 'update']);
        Route::delete('/delete/{id}',    [ClasseController::class, 'destroy']);
    });

    Route::prefix('matieres')->group(function () {
        Route::get('/list',              [MatiereController::class, 'index']);
        Route::get('/all',               [MatiereController::class, 'all']);
        Route::get('/show/{id}',         [MatiereController::class, 'show']);
        Route::post('/add',              [MatiereController::class, 'store']);
        Route::put('/update/{id}',       [MatiereController::class, 'update']);
        Route::delete('/delete/{id}',    [MatiereController::class, 'destroy']);
    });

    // Assiduité : appel par créneau, justification et suivi.
    // L'enseignant appelle sur ses seuls créneaux (et dans la semaine), le
    // surveillant justifie et corrige sans limite : tout est contrôlé dans le
    // service, qui exclut par ailleurs le trésorier.
    Route::prefix('assiduite')->group(function () {
        Route::get('/meta',                      [AssiduiteController::class, 'meta']);
        Route::get('/mes-creneaux',              [AssiduiteController::class, 'mesCreneaux']);
        Route::get('/feuille-appel',             [AssiduiteController::class, 'feuilleAppel']);
        Route::get('/list',                      [AssiduiteController::class, 'index']);
        Route::get('/show/{id}',                 [AssiduiteController::class, 'show']);
        Route::get('/dashboard-surveillant',     [AssiduiteController::class, 'dashboard']);
        Route::get('/fiche-eleve/{eleveId}',     [AssiduiteController::class, 'ficheEleve']);
        Route::get('/fiche-eleve-pdf/{eleveId}', [AssiduiteController::class, 'pdfFicheEleve']);
        Route::get('/feuille-vierge-pdf',        [AssiduiteController::class, 'pdfFeuilleVierge']);
        Route::post('/enregistrer-appel',        [AssiduiteController::class, 'enregistrerAppel']);
        Route::post('/justifier/{id}',           [AssiduiteController::class, 'justifier']);
        Route::put('/update/{id}',               [AssiduiteController::class, 'corriger']);
        Route::delete('/delete/{id}',            [AssiduiteController::class, 'destroy']);
    });

    // Programme d'une classe : matières + coefficients propres à la classe.
    Route::prefix('classe-matieres')->group(function () {
        Route::get('/by-classe/{classeId}', [ClasseMatiereController::class, 'byClasse']);
        Route::get('/show/{id}',            [ClasseMatiereController::class, 'show']);
        Route::post('/add',                 [ClasseMatiereController::class, 'store']);
        Route::put('/update/{id}',          [ClasseMatiereController::class, 'update']);
        Route::put('/reordonner/{classeId}', [ClasseMatiereController::class, 'reordonner']);
        Route::delete('/delete/{id}',       [ClasseMatiereController::class, 'destroy']);
    });

    // Emploi du temps : creneaux d'une classe + export PDF.
    Route::prefix('emploi-du-temps')->group(function () {
        Route::get('/by-classe/{classeId}', [EmploiDuTempsController::class, 'byClasse']);
        Route::get('/pdf/{classeId}',       [EmploiDuTempsController::class, 'pdf']);
        Route::get('/show/{id}',            [EmploiDuTempsController::class, 'show']);
        Route::post('/add',                 [EmploiDuTempsController::class, 'store']);
        Route::put('/update/{id}',          [EmploiDuTempsController::class, 'update']);
        Route::delete('/delete/{id}',       [EmploiDuTempsController::class, 'destroy']);
    });

    // Affectations : enseignant ↔ (classe × matière).
    Route::prefix('affectations')->group(function () {
        Route::get('/list',                     [AffectationController::class, 'index']);
        Route::get('/by-enseignant/{enseignantId}', [AffectationController::class, 'byEnseignant']);
        Route::get('/by-classe/{classeId}',     [AffectationController::class, 'byClasse']);
        Route::get('/show/{id}',                [AffectationController::class, 'show']);
        Route::post('/add',                     [AffectationController::class, 'store']);
        Route::put('/update/{id}',              [AffectationController::class, 'update']);
        Route::delete('/delete/{id}',           [AffectationController::class, 'destroy']);
    });

    // Évaluations par période + saisie des notes.
    // La vue est bornée à l'enseignant côté service ; manager/admin voient tout.
    Route::prefix('evaluations')->group(function () {
        Route::get('/mes-affectations',     [EvaluationController::class, 'mesAffectations']);
        Route::get('/meta',                 [EvaluationController::class, 'meta']);
        Route::get('/list',                 [EvaluationController::class, 'index']);
        Route::get('/show/{id}',            [EvaluationController::class, 'show']);
        Route::get('/grade-sheet/{id}',     [EvaluationController::class, 'gradeSheet']);
        Route::post('/add',                 [EvaluationController::class, 'store']);
        Route::put('/update/{id}',          [EvaluationController::class, 'update']);
        Route::delete('/delete/{id}',       [EvaluationController::class, 'destroy']);
        Route::post('/save-notes/{id}',     [EvaluationController::class, 'saveNotes']);
    });

    // Bulletins de notes : génération par classe, conseil de classe, publication.
    // La consultation exclut le trésorier et borne l'enseignant à ses classes
    // (contrôlé dans le service) ; les écritures sont réservées admin/manager.
    Route::prefix('bulletins')->group(function () {
        Route::get('/meta',                 [BulletinController::class, 'meta']);
        Route::get('/list',                 [BulletinController::class, 'index']);
        Route::get('/show/{id}',            [BulletinController::class, 'show']);
        Route::get('/pdf/{id}',             [BulletinController::class, 'pdf']);
        Route::get('/pdf-classe',           [BulletinController::class, 'pdfClasse']);
        Route::post('/generer',             [BulletinController::class, 'generer']);
        Route::put('/conseil-classe/{id}',  [BulletinController::class, 'updateConseil']);
        Route::post('/publier',             [BulletinController::class, 'publierClasse']);
        Route::post('/publier/{id}',        [BulletinController::class, 'publierUn']);
        Route::post('/depublier',           [BulletinController::class, 'depublierClasse']);
        Route::post('/depublier/{id}',      [BulletinController::class, 'depublierUn']);
    });

    Route::prefix('eleves')->group(function () {
        Route::get('/list',              [EleveController::class, 'index']);
        Route::get('/all',               [EleveController::class, 'all']);
        Route::get('/show/{id}',         [EleveController::class, 'show']);
        Route::post('/add',              [EleveController::class, 'store']);
        // Reprise de donnees : import en masse (admin/manager, cf. ImportElevesRequest).
        Route::post('/import',           [EleveController::class, 'import']);
        Route::put('/update/{id}',       [EleveController::class, 'update']);
        Route::delete('/delete/{id}',    [EleveController::class, 'destroy']);
        Route::post('/restore/{id}',     [EleveController::class, 'restore']);
    });

    Route::prefix('inscriptions')->group(function () {
        Route::get('/list',              [InscriptionController::class, 'index']);
        Route::get('/all',               [InscriptionController::class, 'all']);
        Route::get('/show/{id}',         [InscriptionController::class, 'show']);
        Route::post('/add',              [InscriptionController::class, 'store']);
        Route::put('/update/{id}',       [InscriptionController::class, 'update']);
        Route::post('/annuler/{id}',     [InscriptionController::class, 'annuler']);
        Route::delete('/delete/{id}',    [InscriptionController::class, 'destroy']);
        Route::get('/fiche-pdf/{id}',    [InscriptionController::class, 'fichePdf']);
    });

    // Voyant de la liaison WhatsApp (admin et manager).
    Route::get('/whatsapp/statut', [WhatsappController::class, 'statut']);

    Route::prefix('finance-tresorier')->group(function () {
        Route::get('/inscriptions/a-encaisser',              [FinanceTresorierController::class, 'aEncaisser']);
        Route::post('/inscriptions/valider/{inscriptionId}', [FinanceTresorierController::class, 'validerInscription']);
        Route::get('/paiements/list',                        [FinanceTresorierController::class, 'paiements']);
        Route::get('/paiements/show/{id}',                   [FinanceTresorierController::class, 'showPaiement']);
        // Justificatif : recu si le versement solde l'inscription, decharge sinon.
        Route::get('/paiements/recu-pdf/{id}',               [FinanceTresorierController::class, 'recuInscriptionPdf']);
        // Renvoi du justificatif au tuteur sur WhatsApp (l'envoi automatique
        // suit deja chaque encaissement).
        Route::post('/paiements/whatsapp/{id}',              [FinanceTresorierController::class, 'recuInscriptionWhatsapp'])->middleware('throttle:20,1');

        // Mensualites : vue regroupee par eleve + reglement par tranches.
        Route::get('/mensualites/par-eleve',                 [FinanceTresorierController::class, 'inscriptionsMensualites']);
        Route::post('/mensualites/payer-reparti/{inscriptionId}', [FinanceTresorierController::class, 'payerMensualitesReparti']);
        Route::get('/mensualites/a-encaisser',               [FinanceTresorierController::class, 'mensualitesAEncaisser']);
        Route::post('/mensualites/payer/{mensualiteId}',     [FinanceTresorierController::class, 'payerMensualite']);
        Route::get('/mensualites/paiements/list',            [FinanceTresorierController::class, 'paiementsMensualite']);
        Route::get('/mensualites/paiements/show/{id}',       [FinanceTresorierController::class, 'showPaiementMensualite']);
        // Justificatif : recu si le versement solde le mois, decharge sinon.
        Route::get('/mensualites/paiements/recu-pdf/{id}',   [FinanceTresorierController::class, 'recuMensualitePdf']);

        // Facture multi-mois : plusieurs mois regles sous un numero unique,
        // avec un seul justificatif PDF detaillant chaque mois.
        Route::post('/mensualites/factures/payer/{inscriptionId}', [FinanceTresorierController::class, 'payerFactureMensualites']);
        Route::get('/mensualites/factures/list',              [FinanceTresorierController::class, 'facturesMensualite']);
        Route::get('/mensualites/factures/show/{id}',         [FinanceTresorierController::class, 'showFactureMensualite']);
        Route::get('/mensualites/factures/pdf/{id}',          [FinanceTresorierController::class, 'factureMensualitePdf']);
        Route::post('/mensualites/factures/whatsapp/{id}',    [FinanceTresorierController::class, 'factureMensualiteWhatsapp'])->middleware('throttle:20,1');

        // Relances WhatsApp des familles en retard de paiement.
        Route::get('/relances/debiteurs',                     [RelancePaiementController::class, 'debiteurs']);
        Route::get('/relances/historique',                    [RelancePaiementController::class, 'historique']);
        Route::post('/relances/envoyer/{tuteurId}',           [RelancePaiementController::class, 'relancer'])->middleware('throttle:60,1');
    });

    Route::prefix('activity-logs')->group(function () {
        Route::get('/list',              [ActivityLogController::class, 'index']);
        Route::get('/show/{id}',         [ActivityLogController::class, 'show']);
        Route::delete('/purge',          [ActivityLogController::class, 'purge']);
    });

    /*
     * Les acces de connexion des tuteurs. Distinct du module « users », qui
     * ne gere que le personnel : un tuteur n'a ni contrat ni profil metier,
     * son compte ne sert qu'a dialoguer avec l'ecole.
     */
    /*
     * L'espace des familles : ce qu'un tuteur consulte sur ses propres
     * enfants. Distinct du prefixe « tuteurs » ci-dessous, qui est l'outil de
     * l'administration pour ouvrir et revoquer les acces.
     */
    Route::prefix('mon-espace')->group(function () {
        Route::get('/eleves',                  [NotesTuteurController::class, 'mesEleves']);
        Route::get('/eleves/{eleveId}/notes',  [NotesTuteurController::class, 'releve'])
            ->where('eleveId', '[0-9]+');
    });

    Route::prefix('tuteurs')->group(function () {
        // L'annuaire consulte pendant la saisie d'un eleve, pour rattacher la
        // fratrie a un tuteur deja enregistre au lieu d'en creer un doublon.
        Route::get('/rechercher',              [AnnuaireTuteurController::class, 'rechercher']);
        Route::get('/list',                    [CompteTuteurController::class, 'index']);
        Route::post('/{id}/compte',            [CompteTuteurController::class, 'store']);
        Route::post('/{id}/compte/reinitialiser', [CompteTuteurController::class, 'reinitialiser']);
        Route::delete('/{id}/compte',          [CompteTuteurController::class, 'revoquer']);
    });

    /*
     * Autorisation des canaux WebSocket.
     *
     * Declaree ici, dans le groupe jwt.auth, et non via Broadcast::routes() :
     * la route par defaut s'appuie sur le garde « web », donc sur une session
     * de navigateur. L'application n'en ouvre aucune — elle authentifie par
     * jeton — et toute demande d'abonnement repartirait avec un 403.
     *
     * Les regles appliquees ici vivent dans routes/channels.php.
     */
    Route::post('/broadcasting/auth', function (Request $request) {
        return Broadcast::auth($request);
    });

    /*
     * Messagerie tuteurs <-> services.
     *
     * Le meme jeu de routes sert les agents et les tuteurs : ce que chacun
     * voit depend de son role, pas de l'URL qu'il appelle. Deux prefixes
     * distincts obligeraient a dupliquer chaque route et laisseraient croire
     * qu'un tuteur qui devine l'URL « agent » y gagne quelque chose — la
     * portee est etablie dans ConversationService, jamais dans le routeur.
     */
    Route::prefix('conversations')->group(function () {
        Route::get('/list',                  [ConversationController::class, 'index']);
        Route::get('/referentiels',          [ConversationController::class, 'referentiels']);
        // Le badge de la barre d'outils : appele souvent, garde leger.
        Route::get('/non-lus',               [ConversationController::class, 'nonLus']);
        Route::get('/show/{id}',             [ConversationController::class, 'show']);
        Route::get('/{id}/messages',         [ConversationController::class, 'messages']);
        // Justificatif porte par un message (recu de paiement). Le PDF est
        // regenere a la demande ; l'acces au fil commande l'acces a la piece.
        Route::get('/{id}/messages/{messageId}/piece-jointe',
            [ConversationController::class, 'pieceJointe']);
        Route::post('/add',                  [ConversationController::class, 'store']);

        // Une conversation tres active reste possible, mais on borne la
        // cadence : le WebSocket rediffuse chaque message a tous les agents du
        // service, un envoi en boucle les inonderait.
        Route::post('/{id}/messages', [ConversationController::class, 'repondre'])
            ->middleware('throttle:30,1');

        Route::post('/{id}/prendre-en-charge', [ConversationController::class, 'prendreEnCharge']);
        Route::post('/{id}/escalader',         [ConversationController::class, 'escalader']);
        Route::put('/{id}/statut',             [ConversationController::class, 'changerStatut']);
        Route::post('/{id}/marquer-lu',        [ConversationController::class, 'marquerLu']);
    });
});
