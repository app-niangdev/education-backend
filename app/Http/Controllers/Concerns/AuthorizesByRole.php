<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Autorisations par role, mutualisees entre controleurs.
 *
 * On distingue les actions d'ecriture (reservees a l'admin et au manager) des
 * consultations que le surveillant doit pouvoir faire (classes, eleves,
 * inscriptions, matieres, niveaux, frais, enseignants, emploi du temps...).
 *
 * Le surveillant peut creer et modifier un eleve, mais jamais encaisser ni
 * supprimer : ces actions restent protegees par authorizeAdminOrManager().
 *
 * Ces deux droits-la ne se lisent pas ici : ils sont portes par les
 * FormRequest correspondantes (StoreEleveRequest, UpdateEleveRequest), le
 * controleur ne posant aucune garde sur store() ni update().
 */
trait AuthorizesByRole
{
    /**
     * Retourne le nom du role courant, normalise. Accepte une requete
     * explicite (certains controleurs la passent) ou retombe sur request().
     */
    private function currentRole(?Request $request = null): ?string
    {
        return ($request ?? request())->user()?->role?->name;
    }

    /**
     * Reserve l'action au seul admin. Pour les operations dont la portee
     * depasse la gestion courante : supprimer une annee scolaire efface le
     * cadre de tout le reste, le manager ne doit pas pouvoir le faire.
     */
    private function authorizeAdmin(?Request $request = null): void
    {
        if ($this->currentRole($request) !== 'admin') {
            abort(403, 'Seul un administrateur peut effectuer cette action.');
        }
    }

    /** Reserve l'action a l'admin et au manager (ecritures, suppressions). */
    private function authorizeAdminOrManager(?Request $request = null): void
    {
        if (!in_array($this->currentRole($request), ['admin', 'manager'], true)) {
            abort(403, 'Accès non autorisé.');
        }
    }

    /**
     * Consultation ouverte au surveillant en plus de l'admin et du manager.
     * A utiliser sur les listes / fiches que le surveillant doit voir.
     *
     * Sert aussi de garde d'entree pour la suppression d'inscription : le
     * role tranche large ici, la portee fine (statut EN_ATTENTE, et pour le
     * surveillant, ses seules inscriptions) est departagee dans
     * InscriptionService::delete().
     */
    private function authorizeAdminManagerOrSupervisor(?Request $request = null): void
    {
        if (!in_array($this->currentRole($request), ['admin', 'manager', 'supervisor'], true)) {
            abort(403, 'Accès non autorisé.');
        }
    }

    /**
     * Les ecrans du parcours d'inscription : fiche eleve, liste des eleves,
     * liste des inscriptions, annuaire des tuteurs.
     *
     * Le tresorier s'y ajoute en lecture seule : il encaisse des inscriptions
     * qu'il ne saisit pas, et doit pouvoir consulter la fiche de l'eleve et de
     * son tuteur avant de prendre un paiement ou de relancer une famille.
     *
     * Les ecritures restent departagees par les FormRequest, et la suppression
     * par authorizeAdminOrManager().
     */
    private function authorizeParcoursInscription(?Request $request = null): void
    {
        if (!in_array($this->currentRole($request), ['admin', 'manager', 'supervisor', 'treasurer'], true)) {
            abort(403, 'Accès non autorisé.');
        }
    }

    /**
     * Ouvre l'action au tresorier en plus de l'admin et du manager : la
     * gestion des depenses releve de son metier, il la consulte et la saisit.
     * La suppression, elle, reste sous authorizeAdminOrManager().
     */
    private function authorizeAdminManagerOrTreasurer(?Request $request = null): void
    {
        if (!in_array($this->currentRole($request), ['admin', 'manager', 'treasurer'], true)) {
            abort(403, 'Accès non autorisé.');
        }
    }
}
