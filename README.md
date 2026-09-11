# Plex webhooks

Application NestJS qui reçoit les webhooks Plex, conserve leur contenu dans PostgreSQL et envoie un récapitulatif hebdomadaire des nouveaux films. Prisma remplace Doctrine sans changer le schéma de données existant.

## Prérequis

- Node.js 24 ou plus récent
- PostgreSQL
- un serveur SMTP et, pour résoudre les GUID locaux, un serveur Plex

## Configuration

Les noms de variables restent ceux de l’application Symfony :

| Variable | Rôle |
| --- | --- |
| `DATABASE_URL` | URL PostgreSQL utilisée par Prisma |
| `WEBHOOK_TOKEN` | secret attendu dans `POST /?token=...` |
| `MAILER_DSN` | URL SMTP Nodemailer (`smtp://` ou `smtps://`), `sendmail://default` ou `null://null` |
| `DESTINATION_EMAIL` | destinataires séparés par des virgules |
| `MAIL_SENDER` | adresse d’expédition |
| `PLEX_SERVER_URL` | URL du serveur Plex, sans port si le port standard `32400` est utilisé |
| `PLEX_TOKEN` | jeton Plex |
| `PORT` | port HTTP, `3000` par défaut |

Le fichier `.env` existant reste utilisable. Les variables fournies par l’environnement du processus sont prioritaires.

## Installation et exécution

```bash
npm install
npm run prisma:generate
npm run build
npm start
```

Le webhook Plex reste un formulaire `multipart/form-data` avec un champ texte `payload` contenant du JSON et, facultativement, un fichier `thumb`. L’URL reste `POST /?token=WEBHOOK_TOKEN`. Une miniature n’est stockée que si elle est réellement une image et ne dépasse pas 2 Mio.

## Base de données et bascule depuis Doctrine

Le modèle Prisma correspond à la table existante :

| Prisma | PostgreSQL existant | Type |
| --- | --- | --- |
| `id` | `id` | `SERIAL` / entier |
| `content` | `content` | `JSON` |
| `type` | `type` | `VARCHAR(255)` |
| `createdAt` | `created_at` | `TIMESTAMP(0) WITHOUT TIME ZONE` |
| `thumb` | `thumb` | `BYTEA`, nullable |

Pour une base neuve :

```bash
npm run prisma:migrate:deploy
```

Pour une base déjà créée par Doctrine, sauvegarder la base, arrêter l’ancienne application, puis enregistrer la migration initiale comme déjà appliquée sans recréer la table :

```bash
npx prisma migrate resolve --applied 20260911000000_initial
```

Il est recommandé de comparer auparavant `npx prisma db pull --print` avec [prisma/schema.prisma](prisma/schema.prisma), car ce dépôt Symfony ne contenait aucun fichier de migration SQL permettant de certifier l’état de chaque base déployée.

## Commandes planifiées

Les noms historiques sont conservés par le point d’entrée CLI :

```bash
node dist/cli.js app:mail:new-movies
node dist/cli.js app:webhook:purge
node dist/cli.js app:webhook:purge --days=30
```

Raccourcis npm équivalents :

```bash
npm run mail:new-movies
npm run webhook:purge -- --days=30
```

La planification cron/systemd doit simplement remplacer `bin/console` par `node dist/cli.js`.

## Vérification

```bash
npm test
npm run build
```

Les tests couvrent les statuts HTTP, l’authentification à temps constant, le formulaire Plex, les miniatures, le mapping des métadonnées, la fenêtre hebdomadaire, la résolution des GUID locaux, la déduplication, le rendu HTML, les pièces jointes inline et la purge.
