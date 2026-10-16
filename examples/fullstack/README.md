# PayKit Playground : Établi et Plume

Un monorepo local pour essayer le bundle dans deux applications complètes :

- **Établi** (`#/etabli`) : une boutique d'objets de bureau avec catalogue,
  fiches produit, panier et commandes. L'adhésion Club, facturée par PayKit,
  débloque la livraison offerte, les prix membres et des crédits de livraison
  express.
- **Plume** (`#/plume`) : un assistant d'écriture en SaaS, avec page d'accueil,
  tarifs, espace d'écriture et facturation. Chaque réécriture consomme un
  message du quota, le modèle Expert demande un forfait Pro.

Le bouton « PayKit » en bas de l'écran ouvre les devtools : il permet de passer
d'une application à l'autre et de lire les réponses JSON de l'API.

L'exemple utilise React 19, Vite 7, TypeScript, Tailwind CSS 4, shadcn/ui et
Symfony 7.4. Les composants shadcn sont présents dans le code source et modifiables.
Le bundle est installé par un dépôt Composer `path` avec `symlink: true`.
Une modification de `src/` à la racine est donc directement utilisée par l'API.

```text
.---------------------------------------------.
|         React + Vite + shadcn (5184)        |
|     #/etabli et #/plume, devtools PayKit    |
'----------------------+----------------------'
                       | /api via le proxy Vite
                       v
.----------------------+----------------------.
|    API Symfony (8087) : GET /api/modules    |
'----------+-----------------------+----------'
           |                       |
           v                       v
.----------+--------.     .--------+----------.
|    Module Shop    |     |    Module Saas    |
|    /api/shop/*    |     |    /api/saas/*    |
|   groupe "club"   |     |   groupe "saas"   |
'----------+--------'     '--------+----------'
           |                       |
           '-----------+-----------'
                       v
.----------------------+----------------------.
|  Module Shared : Billing, FakeProviderStore |
'----------------------+----------------------'
                       v
              .--------+--------.
             (                   )
             |'-----------------'|
             | SQLite + fake     |
             | apps/api/var/     |
             '-------------------'
```

## Prérequis

- Bun 1.3 ou supérieur.
- PHP 8.2 ou supérieur, Composer 2 et les extensions `pdo_sqlite`, `intl`,
  `mbstring`, `dom` et `xml` pour les vérifications et PHPUnit.
- Ce dépôt complet. Copier uniquement ce dossier ailleurs casse le lien Composer
  vers le bundle local.
- `make`, `lsof` et les outils shell habituels pour gérer les serveurs.

Le frontend s'exécute avec Bun. Vite 7 demande Node 20.19+ ou 22.12+ si tu choisis
de l'exécuter avec Node au lieu de Bun.

## Installation

Depuis la racine du dépôt, lors de la première installation :

```bash
make install
```

`make install` installe les dépendances JavaScript puis appelle `setup:api`.
`setup:api` installe les dépendances PHP, applique les migrations du bundle puis
synchronise les forfaits avec le fournisseur fictif. Cette commande est réexécutable.
Les fichiers `bun.lock` et `apps/api/composer.lock` fixent les dépendances de l'exemple.
La résolution PHP cible PHP 8.2 pour conserver cette compatibilité.

SQLite est embarqué dans `apps/api/var/paykit.db`. Aucun service Docker n'est créé
et les services `infra-*` existants ne sont pas modifiés.

## Lancement

Depuis la racine du dépôt :

```bash
make dev
```

Les deux serveurs démarrent en arrière-plan. React écoute sur
<http://127.0.0.1:5184> et Symfony sur <http://127.0.0.1:8087>.
Le proxy Vite transmet `/api` à Symfony. Ces ports ont été choisis après avoir
constaté que `5173` était déjà occupé par un autre projet sur la machine.

`make dev` refuse un port occupé par un autre service et n'arrête pas son
processus. Si les deux serveurs du projet tournent déjà, il affiche leur état.
Un échec de démarrage entraîne l'arrêt des serveurs lancés par cette commande.

| Commande | Effet |
| --- | --- |
| `make` | Affiche l'aide colorée, regroupée par section |
| `make ports` | Vérifie les ports configurés |
| `make status` | Affiche les PID et les adresses des serveurs suivis |
| `make logs` | Suit les logs ; `Ctrl+C` quitte uniquement le suivi |
| `make stop` | Arrête les serveurs HTTP de cet exemple et libère leurs ports |
| `make check` | Vérifie TypeScript, le conteneur, le schéma et les tests API |

Les PID, ports et logs sont conservés dans `examples/fullstack/.dev/`, ignoré
par Git. L'arrêt utilise les PID enregistrés et recherche aussi les serveurs
sur les ports mémorisés et configurés. Avant chaque signal, il vérifie le dossier
de travail et la commande du processus. Un processus d'un autre projet est conservé.
Après un délai d'arrêt normal, un serveur récalcitrant appartenant au projet est tué.
L'état de facturation et les services Docker partagés restent intacts.

Les ports sont configurables, avec propagation au proxy Vite et aux origines
acceptées par Symfony :

```bash
make dev WEB_PORT=5185 API_PORT=8088
make stop
```

`make stop` retrouve les anciens ports même sans répéter les variables.
Pour un lancement manuel au premier plan, `bun run dev:api` et `bun run dev:web`
restent disponibles depuis `examples/fullstack`, dans deux terminaux.

## Ce que tu peux essayer

La dernière application ouverte est mémorisée dans le navigateur. Chaque module
a son propre client fictif : les deux applications ne se mélangent pas.

### Établi, la boutique

1. Le client `client@example.test` commence « Sans adhésion » : la livraison
   standard coûte 4,90 € et l'express 9,90 €.
2. Ajoute des produits depuis l'accueil ou une fiche produit. Dans le panier,
   le total est recalculé par `POST /api/shop/quote`,
   qui interroge PayKit sans rien consommer. Le panier survit au rechargement.
3. Adhère au Club depuis la page Club : la livraison standard devient offerte et tu disposes de deux
   livraisons express par mois. Chaque commande express consomme un crédit avec
   `report()`. Au-delà, l'express redevient payante.
4. Club+ ajoute les prix membres, environ 10 % de remise, et l'express illimitée.
5. Les commandes sont enregistrées par l'application dans la table `shop_order`.
   Leur paiement est simulé : PayKit ne facture que des abonnements, pas
   d'achat ponctuel.

### Plume, le SaaS

1. Le compte fictif `saas@example.test` commence sur le forfait Découverte avec
   10 réécritures par mois. « Ouvrir Plume » mène à l'espace d'écriture.
2. L'espace d'écriture reproduit la [démo Claude d'assistant-ui](https://www.assistant-ui.com/demos/claude)
   (code MIT) avec `useLocalRuntime`. Un fichier `.txt` ou `.md` peut être
   joint, et la dictée vocale utilise le navigateur. Le bouton soleil / lune du rail
   choisit le thème clair, sombre ou système (`ThemeProvider` shadcn), limité aux
   pages de l'app Plume. Chaque envoi appelle
   `POST /api/saas/chat`, qui consomme un message avec `report()`. La réécriture est simulée par `Writer` : seule la facturation
   autour est réelle.
3. Le modèle Expert est vérifié avec `can('pro_models')` avant tout décompte.
   Sur Découverte, il retourne HTTP 402 et l'interface propose les forfaits.
4. Une fois le quota épuisé, l'envoi suivant retourne HTTP 402 sans rien
   décompter. Pro donne 100 réécritures et le modèle Expert, Ultra les rend
   illimitées. Les conversations restent dans le navigateur.

### Commun aux deux

Une carte fictive 4242 est enregistrée pour chaque client, ce qui permet une
activation immédiate via le service d'abonnement du bundle. Une baisse d'offre
est programmée en fin de période. Les accès restent actifs et « Garder mon
adhésion » (Établi) ou « Garder Pro » (Plume) annule le changement programmé.

Le fournisseur `fake` n'appelle pas Stripe. Il ne fait pas avancer les périodes
automatiquement et n'émet pas de webhooks en arrière-plan : une baisse programmée
ne s'appliquera pas seule dans cette démo. Les scénarios de fin de période et de
webhooks sont couverts dans les tests d'intégration du bundle à la racine.
Les URL Checkout et portail du fournisseur `fake` ne sont pas des pages utilisables.

Cet exemple utilise volontairement des comptes fictifs partagés entre les onglets
et les navigateurs. Il n'a pas d'authentification et doit rester sur la boucle
locale. Une vraie application doit résoudre le client depuis l'utilisateur
authentifié. Passer le fournisseur à `stripe` demande aussi une intégration
Checkout et des webhooks ; changer uniquement la configuration ne suffit pas.

## Organisation

L'API est découpée en modules sous `apps/api/src/Module/`. Un module regroupe
ses routes (`/api/{id}`), son client fictif et un groupe de forfaits dans
`config/packages/paykit.yaml`. Il s'enregistre en implémentant `DemoModule`,
puis apparaît dans `GET /api/modules`. Côté React, `apps/web/src/App.tsx`
associe chaque application à sa route.

| Chemin | Rôle |
| --- | --- |
| `apps/api/src/Module/DemoModule.php` | Contrat d'un module, tagué `app.demo_module` |
| `apps/api/src/Module/ModuleController.php` | `GET /api/modules`, liste des modules |
| `apps/api/src/Module/Shared/Billing.php` | Appels PayKit communs, filtrés par groupe |
| `apps/api/src/Module/Shared/FakeProviderStore.php` | Sauvegarde verrouillée du fournisseur fictif |
| `apps/api/src/Module/Shop/` | Catalogue, calcul du panier, commandes `ShopOrder` |
| `apps/api/src/Module/Saas/` | Forfaits, quota de messages, accès Pro |
| `apps/api/migrations/` | Migration de la table `shop_order` |
| `apps/api/tests/Module/` | Un test d'API par module |
| `apps/web/src/lib/router.ts` | Routeur par hash (`#/etabli/...`, `#/plume/...`) |
| `apps/web/src/components/shell/` | Hook d'appel à l'API et devtools PayKit |
| `apps/web/src/modules/etabli/` | Boutique : accueil, fiche, panier, Club, commandes |
| `apps/web/src/modules/plume/` | SaaS : accueil et tarifs, écriture, facturation |
| `apps/web/src/modules/plume/workspace-page.tsx`, `claude-rail.tsx` | Clone de la démo Claude d'assistant-ui |
| `apps/web/src/components/assistant-ui/` | Composant `Thread` d'[assistant-ui](https://www.assistant-ui.com), ajouté par le registre shadcn |
| `apps/web/src/components/ui/` | Composants shadcn/ui |

PayKit attache le forfait par défaut de chaque groupe à un nouveau client.
Chaque module ne lit donc que son groupe et ses fonctionnalités.

Le fichier du fournisseur contient des objets PHP sérialisés dans
`apps/api/var/fake-provider.bin`, hors du dossier public. La base et ce fichier
forment ensemble l'état de la démo : conserve-les ou réinitialise-les ensemble.

## Routes

| Méthode | Route | Effet |
| --- | --- | --- |
| GET | `/api/modules` | Liste les scénarios disponibles |
| POST | `/api/{module}/bootstrap` | Initialise le client du module et sa carte fictive |
| GET | `/api/{module}` | Renvoie le client, les offres du module et son état |
| POST | `/api/{module}/subscribe` | Change d'offre avec `{"planId":"club"}` |
| POST | `/api/shop/quote` | Calcule un panier sans rien consommer |
| POST | `/api/shop/checkout` | Passe la commande et consomme un crédit express |
| POST | `/api/saas/chat` | Réécrit un texte avec `{"prompt":"...","model":"expert"}` |

Un panier s'envoie sous la forme
`{"items":[{"productId":"clavier-75","quantity":1}],"shipping":"express"}`.
Les POST attendent `Content-Type: application/json`. Les requêtes navigateur
provenant d'une autre origine sont refusées. Le client ne fournit jamais
d'identifiant de compte à ces routes, ni de prix : l'API les calcule.

## Vérifications

Depuis la racine : `make check`. Les commandes individuelles restent disponibles
depuis `examples/fullstack` :

```bash
bun run typecheck
bun run check:api
bun run test:api
```

Les tests API créent leur propre base SQLite et leur propre état fictif dans un
dossier temporaire. Ils ne touchent pas aux comptes de démonstration.
`check:api` attend que `setup:api` ait créé la base.

Après modification du catalogue :

```bash
php apps/api/bin/console paykit:push
```

Une installation antérieure aux modules se met à jour avec `make install` depuis la
racine : la migration crée `shop_order` et `paykit:push` publie les nouveaux
groupes. Un client créé avant ce push garde l'ancienne version de son forfait.

Les clients existants conservent leur version de forfait. Pour repartir de zéro,
exécute `make stop` depuis la racine, puis depuis `examples/fullstack`,
supprime uniquement ces deux fichiers de l'exemple,
puis relance la préparation :

```bash
rm -f apps/api/var/paykit.db apps/api/var/fake-provider.bin
bun run setup:api
```

Pour ajouter un composant shadcn depuis `examples/fullstack` :

```bash
cd apps/web
bunx --bun shadcn@latest add input
```

Configuration basée sur les documentations officielles de
[shadcn/ui avec Vite](https://ui.shadcn.com/docs/installation/vite) et du
[MicroKernel Symfony](https://symfony.com/doc/7.4/configuration/micro_kernel_trait.html).
