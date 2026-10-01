# Messagerie tuteurs ↔ services

Échanges entre les familles et les guichets de l'établissement, en temps réel
via Laravel Reverb (WebSocket auto-hébergé).

---

## Démarrer en développement

Trois processus, dans trois terminaux :

```bash
# 1. API
cd backend-G2000 && php artisan serve

# 2. Serveur WebSocket — SANS LUI, PAS DE TEMPS RÉEL
cd backend-G2000 && php artisan reverb:start

# 3. Front
cd frontend-G2000 && npm start
```

Le point le plus facile à oublier est le deuxième. Sans Reverb, la console du
navigateur affiche `ERR_CONNECTION_REFUSED` sur `ws://localhost:8080` : les
messages continuent de partir et d'arriver en HTTP, mais il faut recharger la
page pour les voir. Rien n'est perdu, seule l'instantanéité disparaît.

**Aucun worker de file d'attente n'est nécessaire.** Les deux événements
implémentent `ShouldBroadcastNow` et non `ShouldBroadcast` : la diffusion part
dans la même requête que l'enregistrement du message.

C'est délibéré. `ShouldBroadcast` met la diffusion en file, et l'application
tourne sur `QUEUE_CONNECTION=database` : sans `php artisan queue:work` lancé en
permanence, les événements s'empilaient dans la table `jobs` sans jamais
atteindre Reverb — d'où l'obligation de rafraîchir la page. Une messagerie ne
peut pas dépendre d'un processus annexe.

Si un jour vous ajoutez un worker, ne repassez pas à `ShouldBroadcast` : le
gain serait nul (l'envoi vers Reverb est un appel HTTP local, borné à 5 s) et
le temps réel redeviendrait tributaire du worker.

Symptôme à reconnaître : `SELECT COUNT(*) FROM jobs` qui grimpe pendant que
rien n'arrive à l'écran.

---

## Comment ça marche

### Un fil appartient à un service, pas à une personne

Le tuteur écrit à la **scolarité** ou à la **trésorerie**. N'importe quel agent
habilité répond : l'absence d'un collègue ne laisse pas une famille sans
réponse.

### La direction ne se saisit pas, elle se remonte

Un tuteur ne peut pas adresser la direction directement — le serveur renvoie un
422. Seul un agent peut escalader, en indiquant un motif (10 caractères
minimum). Sans cette règle, la direction deviendrait le premier guichet et
court-circuiterait les services compétents.

Le service d'origine et le motif sont conservés : la direction voit d'où vient
le dossier et sur quoi le premier niveau a buté.

---

## Rôles

| Rôle | Voit |
|---|---|
| `admin`, `manager` | Tous les guichets |
| `supervisor` | Scolarité |
| `treasurer` | Trésorerie |
| `tuteur` | Ses propres fils uniquement |
| `teacher` | Rien — le suivi pédagogique passe par les bulletins |

---

## Reçus de paiement envoyés automatiquement

Après chaque encaissement — inscription, mensualité, versement réparti ou
facture multi-mois — un message part dans la messagerie de la famille avec le
justificatif en pièce jointe.

```
Paiement reçu — Frais d'inscription de Aminata Sarr.
Montant versé : 75 000 FCFA.
Cette somme solde le montant dû. Merci.
Votre reçu n° R-0042 est joint à ce message.
```

Les versements partiels sont notifiés aussi, avec un libellé distinct
(« Versement enregistré », « Reste à payer : X ») et une décharge plutôt qu'un
reçu. La famille suit son échéancier sans avoir à ouvrir le PDF.

### Le fil « Reçus de paiement »

Un seul fil par tuteur, service Trésorerie, réutilisé à chaque versement :
ouvrir une conversation par paiement noierait les échanges réels sous les
accusés automatiques. Il couvre toute la fratrie — chaque message nomme
l'élève concerné.

Le fil est créé **même si le tuteur n'a pas de compte** : le jour où l'école
lui ouvre un accès, il retrouve tout son historique. S'il avait été archivé,
un nouveau versement le rouvre.

Son statut est `OUVERTE` et non `RESOLUE` : la famille peut répondre
(contester un montant, demander un échéancier) et la trésorerie voit arriver
cette réponse dans sa corbeille.

### Le PDF n'est pas stocké

Le message porte `piece_jointe_type` + `piece_jointe_id` : de quoi
**régénérer** le document, pas le document lui-même. Rien ne s'accumule sur le
disque, rien à sauvegarder, et une correction comptable se reflète
immédiatement dans le reçu téléchargé.

```
GET /api/conversations/{id}/messages/{messageId}/piece-jointe
```

C'est cette route, et non l'emplacement d'un fichier, qui protège le document.
Elle refuse quiconque n'a pas accès au fil, puis vérifie que le message
appartient bien à ce fil — sans quoi connaître un id de message suffirait à le
lire depuis n'importe quelle conversation accessible.

Comportement vérifié en HTTP :

| Demandeur | Réponse |
|---|---|
| Le tuteur propriétaire | 200, `application/pdf` |
| Un agent d'un autre guichet | 403 |
| Sans jeton | 401 |
| Message absent du fil | 404 |

### Une notification ne casse jamais un encaissement

`NotificationPaiementService` avale ses erreurs et les journalise. Une
messagerie indisponible ne doit pas empêcher un trésorier de prendre l'argent
d'une famille : le paiement est déjà enregistré quand la notification part.

Un élève sans tuteur rattaché n'est pas une anomalie — il n'y a simplement
personne à prévenir.

---

## Saisie du tuteur : pas de double saisie

Quand le tuteur est **le père ou la mère**, ses coordonnées sont saisies une
seule fois, dans le bloc parent (`nom_pere`, `prenom_pere`,
`telephone_pere`…). Le bloc `tuteur` ne porte alors que `lien_parente`, et le
backend en déduit la fiche tuteur.

Les redemander sous `tuteur.*` ferait saisir deux fois la même chose, avec le
risque que les deux versions divergent au fil des corrections.

| Lien déclaré | Bloc obligatoire | Bloc facultatif |
|---|---|---|
| `PERE` | `nom_pere`, `prenom_pere`, `telephone_pere` | `tuteur.*` |
| `MERE` | `nom_mere`, `prenom_mere`, `telephone_mere` | `tuteur.*` |
| Autre (oncle, tante…) | `tuteur.nom/prenom/telephone_principal/adresse` + `nin` | — |

La fiche tuteur reste créée dans tous les cas : c'est elle que référencent les
paiements, les bulletins et la messagerie. Elle est déduite, jamais contournée.
Une valeur explicitement fournie sous `tuteur.*` n'est jamais écrasée, et
l'adresse retombe sur celle de l'élève si le bloc parent la laisse vide.

Voir `EleveService::completerDepuisParent()`.

---

## Comptes tuteurs

Une fiche tuteur existe dès l'inscription de l'enfant ; le **compte de
connexion** se crée séparément, à la demande. Beaucoup de familles n'en auront
jamais, et une inscription ne doit jamais en dépendre.

```
GET    /api/tuteurs/list                      # avec ?sans_compte=1
POST   /api/tuteurs/{id}/compte               # → mot de passe, UNE SEULE FOIS
POST   /api/tuteurs/{id}/compte/reinitialiser
DELETE /api/tuteurs/{id}/compte
```

### Le téléphone est l'identifiant

Un tuteur se connecte avec **son numéro de téléphone** et son mot de passe.
C'est le numéro qui est exigé à la création du compte, pas l'adresse e-mail —
beaucoup de familles n'en ont pas, et en faire une condition fermerait la
messagerie à celles-là mêmes qu'elle doit servir. L'e-mail reste accepté, et
utile : c'est par lui que passe « mot de passe oublié ».

Le numéro est **normalisé** avant enregistrement comme avant comparaison
(`Tuteur::normaliserTelephone`). Ces quatre saisies ouvrent la même session :

```
771234567    77 123 45 67    +221771234567    00221771234567
```

Un numéro étranger est laissé intact plutôt que mutilé.

Deux comptes actifs ne peuvent pas partager un numéro : un index unique partiel
(`users_phone_one_unique_active`) le garantit en base. Deux **fiches** tuteurs
le peuvent, en revanche — une fratrie inscrite par le même oncle est un cas
courant.

### Le mot de passe : d'où il vient, où il va

Il est **généré aléatoirement** (12 caractères, sans symboles pour rester
dictable au téléphone) et renvoyé **une seule fois**, dans la réponse à
`POST /api/tuteurs/{id}/compte` :

```json
{ "payload": { "mot_de_passe": "Iw4eS4M9UKsw", "tuteur": { … } } }
```

C'est à l'agent de le transmettre à la famille — SMS, appel, remise en main
propre. Il n'est **jamais stocké en clair** et aucune route ne permet de le
relire : en cas de perte, la seule issue est la réinitialisation, qui en
produit un nouveau.

Parcours complet de la famille :

| Étape | Ce qui se passe |
|---|---|
| 1 | L'école ouvre l'accès → mot de passe affiché une fois |
| 2 | La famille se connecte : **téléphone + ce mot de passe** |
| 3 | `must_change_password: true` → l'app impose `/change-password` |
| 4 | La famille choisit son mot de passe (8 caractères min) |
| 5 | Le drapeau retombe, la session continue sans reconnexion |

Le mot de passe provisoire a transité par un tiers : tant qu'il n'est pas
changé, l'application n'ouvre **rien d'autre** que l'écran de changement. La
règle vaut pour tous les comptes, personnel compris (`UserService` pose le même
drapeau).

Révoquer un accès supprime le compte mais **conserve la fiche tuteur et les
conversations** : les élèves et les paiements y restent rattachés.

---

## Autorisation des canaux WebSocket

`POST /api/broadcasting/auth`, déclarée à la main dans le groupe `jwt.auth`.

La route par défaut de Laravel (`Broadcast::routes()`) s'appuie sur le garde
`web`, donc sur une session de navigateur. Cette application n'en ouvre
aucune — elle authentifie par jeton — et toute demande d'abonnement repartirait
avec un 403.

Les règles vivent dans [`routes/channels.php`](routes/channels.php). Elles
doublent volontairement les contrôles du service : celui-ci protège les
réponses HTTP, alors qu'un canal WebSocket pousse les messages sans qu'aucune
route soit appelée.

Comportement vérifié :

| Demande | Réponse |
|---|---|
| Surveillant → `private-service.SCOLARITE` | 200 + signature |
| Surveillant → `private-service.TRESORERIE` | 403 |
| Sans jeton | 401 |

---

## Icônes

Le projet ne copie dans `src/assets/` qu'une **sélection** d'icônes Material,
pas la bibliothèque entière. Une icône absente produit un 404 et un carré vide.

Pour en ajouter une :

```bash
cd frontend-G2000
cp node_modules/@material-design-icons/svg/two-tone/NOM.svg \
   src/assets/img/icons/material-design-icons/two-tone/
```

Aucun enregistrement n'est nécessaire : `IconsService` résout les icônes par
chemin de fichier.

---

## Mise en production

1. **Générer de nouvelles clés** — celles du `.env` sont pour le dev local :
   ```bash
   php -r "echo random_int(100000,999999);"   # REVERB_APP_ID
   php -r "echo bin2hex(random_bytes(16));"   # REVERB_APP_KEY
   php -r "echo bin2hex(random_bytes(16));"   # REVERB_APP_SECRET
   ```

2. **Renseigner `environment.prod.ts`** : `apiUrl`, et `reverb.key` (la même
   valeur que `REVERB_APP_KEY`).

3. **Nginx** — le WebSocket passe par le domaine public en `wss`/443, Reverb
   restant sur son port local :

   ```nginx
   location /app {
       proxy_pass http://127.0.0.1:8080;
       proxy_http_version 1.1;
       proxy_set_header Upgrade $http_upgrade;
       proxy_set_header Connection "upgrade";
       proxy_set_header Host $host;
       proxy_read_timeout 60s;
   }
   ```

4. **systemd** — Reverb est un processus persistant, il doit redémarrer seul :

   ```ini
   [Unit]
   Description=Laravel Reverb
   After=network.target

   [Service]
   User=www-data
   Restart=always
   RestartSec=3
   ExecStart=/usr/bin/php /chemin/vers/backend-G2000/artisan reverb:start

   [Install]
   WantedBy=multi-user.target
   ```

5. **CORS** — ajouter le domaine de production dans `config/cors.php`, qui
   n'autorise aujourd'hui que `http://localhost:4200`.

---

## Désactiver le temps réel

Sans supprimer la messagerie :

- Backend : `BROADCAST_CONNECTION=log` dans `.env`
- Frontend : `reverb.enabled: false` dans `environment.ts`

Les messages continuent de s'enregistrer et de s'afficher au chargement.
