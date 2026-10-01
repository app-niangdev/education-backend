# Sécurité des connexions — blocage progressif et OTP

Protection du formulaire de connexion : limitation des tentatives, blocage
progressif par adresse IP, et code de vérification à usage unique.

---

## Le principe

Une connexion se joue en trois temps, et le jeton n'est délivré qu'au bout.

```
LOGIN
  │
  ▼
IP bloquée ?  ──── oui ──▶  HTTP 429, rien d'autre n'est exécuté
  │ non
  ▼
Identifiants  ──── faux ─▶  +1 échec, blocage si le seuil est atteint
  │ justes                   (aucun code envoyé)
  ▼
2FA activée OU IP sortant d'un blocage ?
  │                    │
 non                  oui
  │                    ▼
  ▼              Code envoyé, challenge ouvert
SESSION               │
                      ▼
                 Code validé ──▶ SESSION + remise à zéro
```

La règle qui commande tout le reste : **une IP bloquée ne subit aucun
traitement**. Le middleware la renvoie avant le routage. Pendant le blocage,
les compteurs sont figés, aucun code n'est produit ni envoyé, et le blocage ne
se prolonge pas — même si la tentative présentait le bon mot de passe.

L'OTP n'est **jamais** la conséquence d'un échec. Il protège les connexions
réussies dans deux cas seulement : second facteur activé, ou sortie de blocage.

---

## Blocage progressif

Cinq échecs consécutifs déclenchent le premier blocage. Chaque échec ultérieur
double la durée, jusqu'à un plafond de 24 h.

| Échec | Niveau | Durée   | Échec | Niveau | Durée |
|-------|--------|---------|-------|--------|-------|
| 5e    | 1      | 1 min   | 11e   | 7      | 1 h 04 |
| 6e    | 2      | 2 min   | 12e   | 8      | 2 h 08 |
| 7e    | 3      | 4 min   | 13e   | 9      | 4 h 16 |
| 8e    | 4      | 8 min   | 14e   | 10     | 8 h 32 |
| 9e    | 5      | 16 min  | 15e   | 11     | 17 h 04 |
| 10e   | 6      | 32 min  | 16e+  | 12+    | 24 h (plafond) |

Formule : `60 s × 2^(niveau − 1)`, plafonnée à 86 400 s. Les valeurs au-delà du
10e échec ne sont pas arrondies à l'heure ronde : elles suivent la formule.

Le niveau ne redescend jamais seul. Seule une authentification **complète**
(code compris, quand il est exigé) remet compteur, niveau et drapeau à zéro.
Un attaquant qui attend la fin d'un blocage repart donc au palier suivant.

---

## Ce que voit le client

### `POST /api/auth/login`

| Code | HTTP | Signification |
|------|------|---------------|
| `LOGIN_SUCCESS` | 200 | Session ouverte, jetons dans `data` |
| `OTP_REQUIRED` | 200 | Code envoyé ; `data.challenge_token`, **aucun jeton** |
| `INVALID_CREDENTIALS` | 401 | `attempts_left` indique ce qui reste avant blocage |
| `ACCOUNT_DISABLED` | 403 | Compte désactivé ; l'échec est compté |
| `IP_BLOCKED` | 429 | Émis par le middleware, sur **n'importe quelle** route |

Le refus d'identifiants ne distingue pas « compte inconnu » de « mot de passe
faux » : la distinction permettrait d'énumérer les comptes.

### `POST /api/auth/verify-otp`

Attend `challenge_token` + `otp` (6 chiffres). Réponses : `OTP_VERIFIED` (200,
jetons délivrés), `INVALID_OTP` (401, avec `attempts_left`), `OTP_EXPIRED`
(401), `OTP_TOO_MANY_ATTEMPTS` (429), `INVALID_CHALLENGE` (401).

### `POST /api/auth/resend-otp`

Attend `challenge_token`. Réponses : `OTP_SENT`, `OTP_RESEND_COOLDOWN` (429,
avec `retry_after`), `OTP_RESEND_LIMIT` (429), `OTP_EXPIRED`.

### `POST /api/auth/forgot-password`

Attend `email`. Répond **toujours** `RESET_LINK_SENT` (200) avec le même
message, que l'adresse existe ou non — un message différent ferait de ce
formulaire ouvert un annuaire du personnel. Seul `RESET_THROTTLED` (429) s'en
écarte, quand un lien encore valable vient d'être émis pour cette adresse.

### `POST /api/auth/reset-password`

Attend `token`, `email`, `password`, `password_confirmation`. Réponses :
`PASSWORD_RESET` (200), `RESET_TOKEN_EXPIRED` (422), `RESET_TOKEN_INVALID`
(422).

Le nouveau mot de passe doit faire 8 caractères minimum et contenir au moins
une lettre et un chiffre. C'est le moment où l'exigence de robustesse a le plus
de valeur : on remplace le mot de passe d'un compte auquel on accède sans le
connaître.

### `POST /api/auth/two-factor` *(authentifié)*

Attend `enabled` + `password`. Le mot de passe est redemandé : sans lui, un
poste laissé ouvert suffirait à désarmer la protection. L'activation est
refusée si le compte n'a pas d'adresse e-mail — le code n'aurait aucun moyen
d'arriver.

### Corps d'un refus 429 pour IP bloquée

```json
{
  "success": false,
  "code": "IP_BLOCKED",
  "message": "Trop de tentatives de connexion. Veuillez patienter.",
  "blocked_until": "2026-08-01T12:05:00+00:00",
  "retry_after": 100
}
```

---

## Portée du blocage

Le middleware `BlockSuspiciousIp` est en tête du groupe `api` : une IP bloquée
reçoit 429 sur **toutes** les routes, y compris celles d'une session déjà
ouverte.

Trois routes publiques y échappent (`config/login_security.php`,
`unblocked_paths`) : vérification d'un contrat par QR code, infos de
l'établissement, grille tarifaire. Elles servent des tiers sans compte et
n'offrent aucune prise à une attaque par force brute ; les fermer punirait un
visiteur innocent partageant l'IP de l'école.

> **À surveiller en production.** Derrière une IP publique partagée — le cas
> d'un réseau scolaire — le blocage frappe tout l'établissement. Si cela se
> produit, restreindre la portée aux seules routes d'authentification en
> remplaçant `prependToGroup` par le middleware nommé `ip.blocked` sur le
> groupe `auth` dans [routes/api.php](routes/api.php).

---

## Le code de vérification

Six chiffres, tirés de `random_int` (générateur cryptographique). Stocké
**haché** : la table `login_challenges` ne permet donc pas de terminer une
connexion, même lue intégralement.

- Validité : 5 min, usage unique, invalidé dès validation.
- Ouvrir un challenge périme les précédents du même utilisateur.
- Lié à l'IP d'origine : un jeton intercepté ne s'utilise pas ailleurs.
- 5 saisies erronées maximum ; un renvoi ne réarme pas ce compteur.
- 3 renvois maximum, 60 s d'intervalle.

L'envoi est immédiat, hors file d'attente : un code qui ne vaut que cinq
minutes n'a rien à gagner à passer par un worker qui pourrait être arrêté.

---

## Mot de passe oublié

Le jeton envoyé par e-mail remplace momentanément la connaissance du mot de
passe : qui le détient peut prendre la main sur le compte. Il est donc traité
comme un secret à part entière.

- Aléatoire sur 64 caractères, stocké **haché** dans `password_reset_tokens`.
- Valable 1 h (`config/auth.php`, `passwords.users.expire`), usage unique.
- Une nouvelle demande écrase la précédente : un seul lien vaut à la fois.
- 60 s minimum entre deux demandes pour une même adresse (`throttle`), plus un
  limiteur de cadence par IP sur la route.
- Le lien porte le jeton **et** l'adresse : l'utilisateur ne ressaisit pas son
  e-mail, une faute de frappe ferait échouer la réinitialisation sans qu'il
  puisse comprendre pourquoi.

Une réinitialisation réussie remet `must_change_password` à `false` — le mot de
passe vient d'être choisi par son propriétaire — et **périme les challenges OTP
en attente** de cet utilisateur : ils appartiennent à l'ancien mot de passe, les
laisser ouverts offrirait une session postérieure au changement.

Un compte désactivé ne reçoit aucun lien, et la réponse reste identique : le
formulaire ne dit ni qui possède un compte, ni lesquels sont encore actifs.

---

## Journalisation

Canal `security` → `storage/logs/security-YYYY-MM-DD.log`, conservé 180 jours
(`LOG_SECURITY_DAYS`).

Événements : `LOGIN_FAILED`, `IP_BLOCKED`, `IP_BLOCKED_REQUEST`,
`IP_BLOCK_EXPIRED`, `LOGIN_SUCCESS`, `OTP_SENT`, `OTP_VERIFICATION_FAILED`,
`OTP_VERIFIED`, `LOGIN_COMPLETED`, `TWO_FACTOR_CHANGED`,
`PASSWORD_RESET_REQUESTED`, `PASSWORD_RESET_SENT`, `PASSWORD_RESET_FAILED`,
`PASSWORD_RESET_COMPLETED`.

Chaque ligne porte `ip_address`, `user_id`, `user_agent`, `timestamp`. Aucun
mot de passe, code en clair ou jeton n'y figure — un journal n'est pas un
endroit sûr.

Les demandes de réinitialisation journalisent une empreinte courte de l'adresse
(`email_hash`) et non l'adresse elle-même : cela permet de relier les lignes
d'une même campagne sans écrire en clair l'adresse de quelqu'un qui n'a
peut-être pas de compte ici.

---

## Réglages

Tout est dans [config/login_security.php](config/login_security.php), surchargeable par `.env` :

```dotenv
LOGIN_MAX_ATTEMPTS=5           # échecs avant blocage
LOGIN_BASE_BLOCK_SECONDS=60    # durée du premier blocage
LOGIN_MAX_BLOCK_SECONDS=86400  # plafond (24 h)
LOGIN_OTP_TTL=300              # validité d'un code
LOGIN_OTP_MAX_ATTEMPTS=5       # saisies erronées tolérées
LOGIN_OTP_MAX_RESEND=3         # renvois autorisés
LOGIN_OTP_RESEND_COOLDOWN=60   # délai entre deux envois
LOG_SECURITY_DAYS=180          # rétention du journal
```

En production, `MAIL_MAILER` doit pointer vers un vrai transporteur : avec la
valeur par défaut `log`, les codes n'arrivent nulle part.

---

## Nettoyage

`php artisan login-security:prune` — planifié chaque nuit à 03 h 30
([routes/console.php](routes/console.php)). Supprime les états d'IP dormants (30 j) et les
challenges consommés ou périmés (7 j). Les blocages en cours sont préservés :
les effacer reviendrait à déverrouiller une IP avant terme.

La planification suppose que `schedule:run` tourne (cron ou `php artisan
schedule:work`).

---

## Côté Angular

| Fichier | Rôle |
|---------|------|
| [ip-block.service.ts](../frontend-G2000/src/app/auth/services/ip-block.service.ts) | Décompte partagé, alimenté par les 429 |
| [ip-block.interceptor.ts](../frontend-G2000/src/app/core/interceptors/ip-block.interceptor.ts) | Capte `IP_BLOCKED` sur toute route |
| [OtpState.service.ts](../frontend-G2000/src/app/auth/services/OtpState.service.ts) | Challenge en cours (`sessionStorage`) |
| [otp.component.ts](../frontend-G2000/src/app/auth/otp/otp.component.ts) | Saisie du code, expiration, renvoi |
| [login.component.ts](../frontend-G2000/src/app/auth/login/login.component.ts) | Compte à rebours, redirection OTP |
| [profile-security.component.ts](../frontend-G2000/src/app/layouts/components/profile/profile-security/profile-security.component.ts) | Activation du second facteur |

Le compte à rebours affiché n'est qu'un affichage : il dit combien de temps
patienter, il ne débloque rien. Arrivé à zéro, le formulaire se rouvre et c'est
le serveur, seul, qui accepte ou refuse la tentative suivante. Une horloge
locale avancée ne fait gagner que le droit de recevoir un nouveau 429.

Le challenge vit en `sessionStorage` et non `localStorage` : une vérification
abandonnée ne doit pas ressurgir dans un autre onglet.

---

## Tables

`login_security` — une ligne par IP : `failed_attempts`, `block_level`,
`blocked_until`, `was_blocked`, `last_failed_at`, `last_success_at`.

`was_blocked` se distingue de `blocked_until` : le second dit « bloquée en ce
moment », le premier « a été bloquée depuis la dernière connexion réussie ».
C'est ce drapeau, et non l'expiration, qui rend l'OTP obligatoire ensuite.

`login_challenges` — `challenge_token`, `user_id`, `ip_address`, `otp_hash`,
`reason`, `expires_at`, `attempts`, `resend_count`, `last_sent_at`,
`verified_at`.

Toutes les écritures sur `login_security` passent par une transaction avec
verrou de ligne : cinq essais lancés en parallèle ne comptent que pour cinq.
