<?php

namespace App\Providers;

use App\Interfaces\ActivityLogRepositoryInterface;
use App\Interfaces\ActivityLogServiceInterface;
use App\Interfaces\AffectationRepositoryInterface;
use App\Interfaces\AffectationServiceInterface;
use App\Interfaces\AnneeScolaireRepositoryInterface;
use App\Interfaces\AnneeScolaireServiceInterface;
use App\Interfaces\AssiduiteRepositoryInterface;
use App\Interfaces\AssiduiteServiceInterface;
use App\Interfaces\BilanServiceInterface;
use App\Interfaces\BulletinRepositoryInterface;
use App\Interfaces\BulletinServiceInterface;
use App\Interfaces\ClasseMatiereRepositoryInterface;
use App\Interfaces\ClasseMatiereServiceInterface;
use App\Interfaces\ClasseRepositoryInterface;
use App\Interfaces\ClasseServiceInterface;
use App\Interfaces\ContratRepositoryInterface;
use App\Interfaces\ContratServiceInterface;
use App\Interfaces\CompteTuteurServiceInterface;
use App\Interfaces\ConversationRepositoryInterface;
use App\Interfaces\ConversationServiceInterface;
use App\Interfaces\QrCodeServiceInterface;
use App\Interfaces\DepenseRepositoryInterface;
use App\Interfaces\DepenseServiceInterface;
use App\Interfaces\EmploiDuTempsRepositoryInterface;
use App\Interfaces\EmploiDuTempsServiceInterface;
use App\Interfaces\EvaluationRepositoryInterface;
use App\Interfaces\EvaluationServiceInterface;
use App\Interfaces\FraisScolaireRepositoryInterface;
use App\Interfaces\FraisScolaireServiceInterface;
use App\Interfaces\StatistiqueServiceInterface;
use App\Interfaces\MatiereRepositoryInterface;
use App\Interfaces\MatiereServiceInterface;
use App\Interfaces\EleveRepositoryInterface;
use App\Interfaces\EleveServiceInterface;
use App\Interfaces\EnseignantRepositoryInterface;
use App\Interfaces\EnseignantServiceInterface;
use App\Interfaces\FinanceTresorierRepositoryInterface;
use App\Interfaces\FinanceTresorierServiceInterface;
use App\Interfaces\InscriptionRepositoryInterface;
use App\Interfaces\InscriptionServiceInterface;
use App\Interfaces\JustificatifServiceInterface;
use App\Interfaces\NotificationPaiementServiceInterface;
use App\Interfaces\LoginChallengeServiceInterface;
use App\Interfaces\LoginSecurityServiceInterface;
use App\Interfaces\PasswordResetServiceInterface;
use App\Interfaces\ProfilServiceInterface;
use App\Interfaces\NotesTuteurServiceInterface;
use App\Interfaces\NiveauRepositoryInterface;
use App\Interfaces\NiveauServiceInterface;
use App\Interfaces\PeriodeRepositoryInterface;
use App\Interfaces\PeriodeServiceInterface;
use App\Interfaces\SurveillantRepositoryInterface;
use App\Interfaces\SurveillantServiceInterface;
use App\Interfaces\TresorierRepositoryInterface;
use App\Interfaces\TresorierServiceInterface;
use App\Interfaces\TuteurRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Interfaces\UserServiceInterface;
use App\Repositories\ActivityLogRepository;
use App\Repositories\AffectationRepository;
use App\Repositories\AnneeScolaireRepository;
use App\Repositories\AssiduiteRepository;
use App\Repositories\BulletinRepository;
use App\Repositories\ClasseMatiereRepository;
use App\Repositories\ClasseRepository;
use App\Repositories\ContratRepository;
use App\Repositories\ConversationRepository;
use App\Repositories\DepenseRepository;
use App\Repositories\EmploiDuTempsRepository;
use App\Repositories\EvaluationRepository;
use App\Repositories\FraisScolaireRepository;
use App\Repositories\MatiereRepository;
use App\Repositories\EleveRepository;
use App\Repositories\EnseignantRepository;
use App\Repositories\FinanceTresorierRepository;
use App\Repositories\InscriptionRepository;
use App\Repositories\NiveauRepository;
use App\Repositories\PeriodeRepository;
use App\Repositories\SurveillantRepository;
use App\Repositories\TresorierRepository;
use App\Repositories\TuteurRepository;
use App\Repositories\UserRepository;
use App\Services\ActivityLogService;
use App\Services\AffectationService;
use App\Services\AnneeScolaireService;
use App\Services\AssiduiteService;
use App\Services\BilanService;
use App\Services\BulletinService;
use App\Services\ClasseMatiereService;
use App\Services\ClasseService;
use App\Services\CompteTuteurService;
use App\Services\ContratService;
use App\Services\ConversationService;
use App\Services\DepenseService;
use App\Services\EmploiDuTempsService;
use App\Services\EvaluationService;
use App\Services\FraisScolaireService;
use App\Services\MatiereService;
use App\Services\StatistiqueService;
use App\Services\EleveService;
use App\Services\EnseignantService;
use App\Services\FinanceTresorierService;
use App\Services\InscriptionService;
use App\Services\JustificatifService;
use App\Services\LoginChallengeService;
use App\Services\NotificationPaiementService;
use App\Services\LoginSecurityService;
use App\Services\PasswordResetService;
use App\Services\ProfilService;
use App\Services\NotesTuteurService;
use App\Services\NiveauService;
use App\Services\PeriodeService;
use App\Services\QrCodeService;
use App\Services\SurveillantService;
use App\Services\TresorierService;
use App\Services\UserService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ActivityLogRepositoryInterface::class, ActivityLogRepository::class);
        $this->app->bind(ActivityLogServiceInterface::class, ActivityLogService::class);

        // Protection du formulaire de connexion : blocage progressif des IP et
        // codes de verification.
        $this->app->bind(LoginSecurityServiceInterface::class, LoginSecurityService::class);
        $this->app->bind(LoginChallengeServiceInterface::class, LoginChallengeService::class);
        $this->app->bind(PasswordResetServiceInterface::class, PasswordResetService::class);
        $this->app->bind(ProfilServiceInterface::class, ProfilService::class);
        $this->app->bind(NotesTuteurServiceInterface::class, NotesTuteurService::class);

        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(UserServiceInterface::class, UserService::class);

        $this->app->bind(AnneeScolaireRepositoryInterface::class, AnneeScolaireRepository::class);
        $this->app->bind(AnneeScolaireServiceInterface::class, AnneeScolaireService::class);

        $this->app->bind(NiveauRepositoryInterface::class, NiveauRepository::class);
        $this->app->bind(NiveauServiceInterface::class, NiveauService::class);

        $this->app->bind(PeriodeRepositoryInterface::class, PeriodeRepository::class);
        $this->app->bind(PeriodeServiceInterface::class, PeriodeService::class);

        $this->app->bind(SurveillantRepositoryInterface::class, SurveillantRepository::class);
        $this->app->bind(SurveillantServiceInterface::class, SurveillantService::class);

        $this->app->bind(TresorierRepositoryInterface::class, TresorierRepository::class);
        $this->app->bind(TresorierServiceInterface::class, TresorierService::class);

        $this->app->bind(EnseignantRepositoryInterface::class, EnseignantRepository::class);
        $this->app->bind(EnseignantServiceInterface::class, EnseignantService::class);

        $this->app->bind(ContratRepositoryInterface::class, ContratRepository::class);
        $this->app->bind(ContratServiceInterface::class, ContratService::class);
        $this->app->bind(QrCodeServiceInterface::class, QrCodeService::class);

        $this->app->bind(ClasseRepositoryInterface::class, ClasseRepository::class);
        $this->app->bind(ClasseServiceInterface::class, ClasseService::class);

        $this->app->bind(MatiereRepositoryInterface::class, MatiereRepository::class);
        $this->app->bind(MatiereServiceInterface::class, MatiereService::class);

        $this->app->bind(ClasseMatiereRepositoryInterface::class, ClasseMatiereRepository::class);
        $this->app->bind(ClasseMatiereServiceInterface::class, ClasseMatiereService::class);

        $this->app->bind(AffectationRepositoryInterface::class, AffectationRepository::class);
        $this->app->bind(AffectationServiceInterface::class, AffectationService::class);

        $this->app->bind(EmploiDuTempsRepositoryInterface::class, EmploiDuTempsRepository::class);
        $this->app->bind(EmploiDuTempsServiceInterface::class, EmploiDuTempsService::class);

        $this->app->bind(EvaluationRepositoryInterface::class, EvaluationRepository::class);
        $this->app->bind(EvaluationServiceInterface::class, EvaluationService::class);

        $this->app->bind(BulletinRepositoryInterface::class, BulletinRepository::class);
        $this->app->bind(BulletinServiceInterface::class, BulletinService::class);

        $this->app->bind(AssiduiteRepositoryInterface::class, AssiduiteRepository::class);
        $this->app->bind(AssiduiteServiceInterface::class, AssiduiteService::class);

        $this->app->bind(FraisScolaireRepositoryInterface::class, FraisScolaireRepository::class);
        $this->app->bind(FraisScolaireServiceInterface::class, FraisScolaireService::class);
        $this->app->bind(DepenseRepositoryInterface::class, DepenseRepository::class);
        $this->app->bind(DepenseServiceInterface::class, DepenseService::class);

        $this->app->bind(StatistiqueServiceInterface::class, StatistiqueService::class);
        $this->app->bind(BilanServiceInterface::class, BilanService::class);

        $this->app->bind(TuteurRepositoryInterface::class, TuteurRepository::class);

        // Messagerie tuteurs <-> services de l'etablissement.
        $this->app->bind(ConversationRepositoryInterface::class, ConversationRepository::class);
        $this->app->bind(ConversationServiceInterface::class, ConversationService::class);

        // Ouverture des acces de connexion pour les familles.
        $this->app->bind(CompteTuteurServiceInterface::class, CompteTuteurService::class);

        // Justificatifs de paiement : rendu PDF mutualise entre le module
        // finance et la messagerie, et depot automatique du recu chez la famille.
        $this->app->bind(JustificatifServiceInterface::class, JustificatifService::class);
        $this->app->bind(NotificationPaiementServiceInterface::class, NotificationPaiementService::class);

        $this->app->bind(EleveRepositoryInterface::class, EleveRepository::class);
        $this->app->bind(EleveServiceInterface::class, EleveService::class);

        $this->app->bind(InscriptionRepositoryInterface::class, InscriptionRepository::class);
        $this->app->bind(InscriptionServiceInterface::class, InscriptionService::class);

        $this->app->bind(FinanceTresorierRepositoryInterface::class, FinanceTresorierRepository::class);
        $this->app->bind(FinanceTresorierServiceInterface::class, FinanceTresorierService::class);
    }

    public function boot(): void
    {
        Gate::define('admin', fn ($user) => $user->role?->name === 'admin');
    }
}
