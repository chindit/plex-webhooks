# Analyse de migration Symfony vers NestJS

## Périmètre observé

L’application Symfony d’origine avait trois points d’entrée fonctionnels :

1. `POST /` reçoit un webhook Plex authentifié par `?token=...`, lit le champ JSON `payload` et la miniature multipart facultative `thumb`, puis répond avec un corps JSON `null`.
2. `app:mail:new-movies` sélectionne les événements `library.new` créés depuis strictement moins d’une semaine, produit un digest HTML et l’envoie par e-mail.
3. `app:webhook:purge --days=N` efface les webhooks strictement plus anciens que le seuil demandé, avec 30 jours par défaut.

Il n’existait ni interface web, ni API de lecture, ni authentification utilisateur, ni tâche planifiée dans le dépôt. La planification des deux commandes relève donc toujours de l’infrastructure externe.

## Matrice de parité

| Contrat historique | Implémentation NestJS | Vérification |
| --- | --- | --- |
| Route unique `POST /` | `WebhookController.receive` | tests HTTP multipart et urlencoded |
| Token absent, vide ou faux → `401` JSON `null` | comparaison SHA-256 à temps constant | tests HTTP |
| Payload absent, vide, JSON invalide ou sans `event` → `400` | validation avant écriture | tests HTTP |
| Méthode autre que POST → `405`, `Allow: POST` | route de repli `@All('/')` | test HTTP |
| Succès → `202` JSON `null` | réponse Express explicite | tests HTTP |
| Miniature réelle, ≤ 2 Mio | détection du contenu avec `file-type`, SVG inclus | tests image, faux fichier et dépassement |
| Miniature invalide ignorée, webhook néanmoins stocké | `thumb = null` | tests HTTP |
| JSON et miniature en PostgreSQL | Prisma `Json` et `Bytes` | schéma Prisma et migration SQL |
| Table/colonnes Doctrine conservées | mappings `@@map` / `@map` | `prisma migrate diff` |
| Filtre strict `createdAt > now - 1 semaine` | requête Prisma `gt` | test du repository |
| Événement exact `library.new` | enum `PlexEventType` | test du repository |
| GUID absent ignoré | service de digest | test métier |
| GUID commençant par `local` résolu via Plex si disponible | client Plex `/library/metadata/{ratingKey}` | tests métier et XML |
| Autre GUID converti depuis `Metadata` | `PlexWebhookEntity.asMovie` | tests de mapping et valeurs absentes |
| Déduplication par `ratingKey`, dernière occurrence gagnante | `Map.set` dans l’ordre de la requête | test métier |
| Aucun film → aucun e-mail | retour `empty` | test métier |
| Sujet, expéditeur « David », destinataires CSV | `MailerService` | test e-mail |
| Miniatures inline JPEG avec CID = `ratingKey` | pièces jointes Nodemailer | test e-mail |
| Template français, durée UTC, genres et cinq acteurs | Handlebars + Juice | test de rendu |
| Connexion Plex : port 32400, token en query, délai 10 s | `PlexService` | test client |
| `verify_host=false` de Symfony | agent TLS sans contrôle du nom, chaîne CA toujours vérifiée | configuration Undici |
| Purge stricte `createdAt < threshold` | `deleteMany` Prisma | tests repository/service |
| Option de purge invalide → code 2 | validation CLI avant connexion DB | code du point d’entrée |

## Choix de structure

- `webhook` contient le contrôleur, l’entité de domaine et le repository Prisma.
- `plex` remplace uniquement la partie réellement utilisée de `chindit/plex-api` : test de connexion et résolution d’un film par `ratingKey`.
- `mail` contient l’orchestration du digest, le rendu HTML et l’adaptateur Nodemailer.
- `purge` contient la politique de rétention.
- `prisma` centralise le cycle de vie du client PostgreSQL.
- `cli.ts` expose les deux noms de commandes historiques et partage exactement les mêmes services Nest que le serveur HTTP.

Cette séparation évite de coupler les règles métier à Nest, Prisma, Plex ou SMTP et permet des tests sans services externes.

## Conservation des données

Le dépôt Symfony ne contenait aucune migration Doctrine, seulement un dossier `migrations` vide. Le schéma a donc été dérivé des attributs ORM présents sur `PlexWebhook` :

- table `plex_webhook` ;
- clé primaire entière auto-incrémentée `id` ;
- `content` en `JSON` non nullable ;
- `type` en `VARCHAR(255)` non nullable ;
- `created_at` en `TIMESTAMP(0) WITHOUT TIME ZONE` non nullable, renseigné par l’application ;
- `thumb` en `BYTEA` nullable.

La migration initiale Prisma reproduit ce schéma. Sur une base existante, elle doit être marquée comme appliquée avec `prisma migrate resolve`, après comparaison avec l’introspection réelle et sauvegarde. Aucune transformation ou suppression de ligne n’est nécessaire.

## Points d’exploitation à vérifier lors du basculement

- sauvegarde et introspection de la base réellement déployée ;
- compatibilité du `MAILER_DSN` avec Nodemailer (SMTP, SMTPS, sendmail et transport null sont pris en charge ici) ;
- remplacement des lignes cron `bin/console ...` par `node dist/cli.js ...` ;
- exposition du nouveau `PORT` par le reverse proxy ;
- test d’un webhook Plex réel, d’un GUID Plex normal et d’un GUID `local` ;
- arrêt de Symfony avant activation de NestJS pour éviter un double traitement.

Comme auparavant, un même événement peut réapparaître dans plusieurs digests tant qu’il reste dans la fenêtre d’une semaine : aucun état « déjà envoyé » n’existait, et ce comportement a volontairement été conservé.
