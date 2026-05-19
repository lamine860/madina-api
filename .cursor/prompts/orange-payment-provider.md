# Prompt — Intégration Orange Money (provider `orange`)

## Objectif

Implémenter un **provider de paiement Orange Money** dans le module `Modules/Payments`, sur le même modèle que **LengoPay** (`LengoPayService`, routes, contrôleur, tests Pest). Orange est un fournisseur **distinct** : `provider = 'orange'` en base, routes dédiées `payments/orange/*`, et `payment_method` fixé à `PaymentMethod::Orange`.

L’intégration cible l’**Orange Money Web Payment API** (Guinée, devise GNF), documentée sur [Orange Developer — OM Web Payment](https://developer.orange.com/apis/om-webpay). Les détails HTTP exacts (chemins, noms de champs) doivent être **configurables via `.env`** comme pour LengoPay, afin de s’adapter aux environnements sandbox / production sans changer le code.

---

## Contexte projet

- **Stack** : Laravel 13, PHP 8.5, architecture modulaire (`Modules/`), injection de dépendances, classes `final` pour services/contrôleurs/requests.
- **Référence obligatoire** : dupliquer les patterns de :
  - `Modules/Payments/app/Services/LengoPayService.php`
  - `Modules/Payments/app/Http/Controllers/PaymentController.php` (méthodes dédiées ou extension propre)
  - `Modules/Payments/tests/Feature/LengoPayPaymentsTest.php`
- **Événement métier** : en cas de succès webhook, dispatcher `Modules\Payments\Events\PaymentConfirmed` (idempotent) — le listener `HandlePaymentConfirmed` marque la commande `paid`.
- **Commande éligible** : uniquement `OrderStatus::Pending`.
- **Auth API Kilora** : initiation protégée par `auth:sanctum` + `$this->authorize('view', $order)`.
- **Webhook** : route publique, **sans** Sanctum ; sécurisée par signature HMAC ou mécanisme documenté Orange.
- **Logs** : canal `payments`, payloads sensibles masqués (`sanitizeForLog`).
- **Tests** : Pest feature tests avec `Http::fake()`, exécuter `php artisan test --compact` sur le fichier créé.
- **Style** : `vendor/bin/pint --dirty --format agent` après modification PHP.
- **Docs API** : annotations Scribe sur les nouvelles routes (groupe `Paiements`, sous-groupe `Orange Money`).

Ne pas modifier LengoPay existant sauf si nécessaire pour un contrat partagé (préférer la duplication minimale côté Orange).

---

## Fichiers à créer / modifier

### Créer

| Fichier | Rôle |
|---------|------|
| `Modules/Payments/app/Services/OrangeMoneyService.php` | Initiation + webhook |
| `Modules/Payments/app/Exceptions/OrangeMoneyException.php` | Erreurs métier / transport |
| `Modules/Payments/app/Exceptions/InvalidOrangeMoneyWebhookSignatureException.php` | Signature invalide → HTTP 401 |
| `Modules/Payments/app/Http/Requests/InitiateOrangeMoneyPaymentRequest.php` | Validation (MSISDN client si requis par l’API) |
| `Modules/Payments/tests/Feature/OrangeMoneyPaymentsTest.php` | Tests initiate + webhook |

### Modifier

| Fichier | Action |
|---------|--------|
| `Modules/Payments/config/config.php` | Bloc `orange` (voir ci-dessous) |
| `Modules/Payments/routes/api.php` | Routes `initiate` + `webhook` |
| `Modules/Payments/routes/web.php` | Routes `success` / `cancel` (retour navigateur) |
| `Modules/Payments/app/Http/Controllers/PaymentController.php` | Injecter `OrangeMoneyService`, méthodes `initiateOrange`, `handleOrangeWebhook`, `orangeSuccess`, `orangeCancel` (ou contrôleur dédié si plus lisible) |
| `Modules/Payments/app/Providers/PaymentsServiceProvider.php` | `singleton(OrangeMoneyService::class)` |
| `.env.example` | Variables `ORANGE_*` |

---

## Configuration (`config/payments.php` → clé `orange`)

```php
'orange' => [
    'base_url' => env('ORANGE_BASE_URL', 'https://api.orange.com'),
    'oauth_token_path' => env('ORANGE_OAUTH_TOKEN_PATH', '/oauth/v3/token'),
    'payment_initiate_path' => env('ORANGE_PAYMENT_INITIATE_PATH', '/orange-money-webpay/gn/v1/webpayment'),
    'payment_status_path' => env('ORANGE_PAYMENT_STATUS_PATH', '/orange-money-webpay/gn/v1/transactionstatus'),
    'client_id' => env('ORANGE_CLIENT_ID'),
    'client_secret' => env('ORANGE_CLIENT_SECRET'),
    'merchant_key' => env('ORANGE_MERCHANT_KEY'),
    'return_url' => env('ORANGE_RETURN_URL'), // optionnel si généré via route()
    'cancel_url' => env('ORANGE_CANCEL_URL'),
    'notif_url' => env('ORANGE_NOTIF_URL'), // webhook public Kilora
    'webhook_secret' => env('ORANGE_WEBHOOK_SECRET'),
    'webhook_signature_header' => env('ORANGE_WEBHOOK_SIGNATURE_HEADER', 'X-Orange-Signature'),
    'currency' => env('ORANGE_CURRENCY', 'GNF'),
    'country_code' => env('ORANGE_COUNTRY_CODE', 'GN'),
    /** Clés JSON configurables dans les réponses Orange */
    'pay_token_key' => env('ORANGE_PAY_TOKEN_KEY', 'pay_token'),
    'payment_url_key' => env('ORANGE_PAYMENT_URL_KEY', 'payment_url'),
    'transaction_id_key' => env('ORANGE_TRANSACTION_ID_KEY', 'txnid'),
    'status_key' => env('ORANGE_STATUS_KEY', 'status'),
],
```

Les routes Laravel nommées doivent être utilisées pour `return_url`, `cancel_url`, `notif_url` quand les variables d’environnement sont vides :

- `payments.orange.success`
- `payments.orange.cancel`
- `api.payments.orange.webhook`

---

## Routes API

Préfixe existant `v1` dans `Modules/Payments/routes/api.php` :

| Méthode | URI | Nom | Middleware |
|---------|-----|-----|------------|
| `POST` | `orders/{order}/payments/orange/initiate` | `orders.payments.orange.initiate` | `auth:sanctum` |
| `POST` | `payments/orange/webhook` | `payments.orange.webhook` | aucun |

Routes web (`routes/web.php`) :

| Méthode | URI | Nom |
|---------|-----|-----|
| `GET` | `payments/orange/success` | `payments.orange.success` |
| `GET` | `payments/orange/cancel` | `payments.orange.cancel` |

Redirections success/cancel : même comportement que LengoPay (`config('app.url').'?payment=success'` / `cancelled`).

---

## `OrangeMoneyService::initiatePayment(Order $order, ?string $customerMsisdn = null): string`

### Règles métier

1. Refuser si `$order->status !== OrderStatus::Pending` → `OrangeMoneyException` message FR.
2. Créer un `Payment` :
   - `provider` => `'orange'`
   - `payment_method` => `PaymentMethod::Orange`
   - `status` => `PaymentStatus::Pending`
   - `currency` => config `orange.currency`
   - `amount` => `$order->total_amount`
3. Obtenir un **token OAuth2** (client credentials) si absent ou expiré — mettre en cache (`Cache::remember`) avec marge de sécurité (ex. TTL = `expires_in - 60`).
4. Appeler l’endpoint d’**initialisation de paiement** Orange avec au minimum :
   - montant (entier ou string selon doc Orange — aligner sur ce que fait LengoPay : string du decimal)
   - `order_id` / référence marchand : `$order->order_number`
   - `merchant_key`
   - URLs de retour / notification
   - identifiant interne : `$payment->id` dans un champ metadata/reference si l’API le permet
   - `customer_msisdn` si fourni par la requête HTTP (format international `224…` pour GN)
5. En cas d’échec HTTP ou corps invalide : `PaymentStatus::Failed`, metadata enrichi, `OrangeMoneyException`.
6. Extraire l’URL de redirection (ou construire l’URL de paiement à partir de `pay_token` selon la doc) via les clés configurables.
7. Persister `transaction_id` / `pay_token` dans `transaction_id` et `metadata`.
8. Retourner l’URL string pour le JSON `{ "redirect_url": "..." }`.

### Headers HTTP

- OAuth : `Authorization: Bearer {access_token}`
- `Accept: application/json`
- Adapter `Content-Type` selon la doc (JSON recommandé).

---

## `OrangeMoneyService::verifyWebhook(Request $request): void`

### Sécurité

1. Lire le **corps brut** (`$request->getContent()`).
2. Vérifier la signature :
   - Par défaut : **HMAC-SHA256** du corps avec `ORANGE_WEBHOOK_SECRET`, header configurable (`ORANGE_WEBHOOK_SIGNATURE_HEADER`).
   - Si la doc Orange impose un autre schéma (ex. signature en base64, préfixe `sha256=`), l’implémenter dans une méthode privée documentée et couverte par test.
3. Secret vide → `InvalidOrangeMoneyWebhookSignatureException`.
4. Signature invalide → log `warning`, exception signature.

### Traitement (miroir LengoPay)

1. Décoder JSON (`JSON_THROW_ON_ERROR`).
2. Extraire : référence commande (`order_number` ou champ configurable), `transaction_id`, `status`.
3. Transaction DB + `lockForUpdate()` sur le `Payment` :
   - Chercher par `transaction_id` + `provider = orange`
   - Sinon dernier `Pending` pour la commande
4. **Idempotence** : si déjà `Success`, fusionner metadata webhook et return.
5. Mapper statuts Orange → `PaymentStatus` :
   - succès : `success`, `completed`, `paid`, `SUCCESS` (insensible à la casse)
   - échec : `failed`, `cancelled`, `canceled`, `FAILED`
   - inconnu : log `notice`, mettre à jour metadata seulement
6. Si passage à `Success` (et pas déjà success) : `event(new PaymentConfirmed($payment, $payment->order))`.

---

## Contrôleur & Form Request

### `InitiateOrangeMoneyPaymentRequest`

- Champs à valider selon besoin produit :
  - `customer_msisdn` : `nullable|string|regex:/^224\d{9}$/` (ajuster si autre pays)
- Pas de `payment_method` dans le body : toujours Orange côté serveur.

### Réponses HTTP

| Cas | Code |
|-----|------|
| Initiation OK | `200` `{ "redirect_url": "..." }` |
| Commande / Orange erreur | `422` `{ "message": "..." }` |
| Non autorisé commande | `403` |
| Webhook signature KO | `401` |
| Webhook payload invalide | `422` |
| Webhook OK | `200` `{ "received": true }` |

---

## Tests Pest (`OrangeMoneyPaymentsTest.php`)

Reproduire les 3 scénarios LengoPay :

1. **`it initiates orange payment and returns redirect url`**
   - Fake OAuth token endpoint + initiate endpoint
   - Commande `Pending`, user customer, `actingAs sanctum`
   - `POST /api/v1/orders/{id}/payments/orange/initiate`
   - Assert redirect_url, 1 payment `provider=orange`, `PaymentMethod::Orange`, `Pending`, `transaction_id` renseigné

2. **`it rejects orange webhook with invalid signature`**
   - `POST /api/v1/payments/orange/webhook` sans bonne signature → `401`

3. **`it confirms payment via orange webhook and marks order paid once when replayed`**
   - Payment pending pré-créé
   - Webhook signé HMAC correct, statut success
   - Double POST → order `Paid` une seule fois, metadata contient clé `webhook`

Utiliser `config([...])` dans `beforeEach` comme `LengoPayPaymentsTest`.

---

## Variables `.env.example` à documenter

```env
ORANGE_BASE_URL=
ORANGE_CLIENT_ID=
ORANGE_CLIENT_SECRET=
ORANGE_MERCHANT_KEY=
ORANGE_WEBHOOK_SECRET=
ORANGE_WEBHOOK_SIGNATURE_HEADER=X-Orange-Signature
ORANGE_CURRENCY=GNF
ORANGE_COUNTRY_CODE=GN
```

---

## Critères d’acceptation

- [ ] Aucune régression sur `LengoPayPaymentsTest`
- [ ] Nouveau fichier de tests Orange vert
- [ ] Pint exécuté sur fichiers PHP modifiés
- [ ] Scribe : sous-groupe Orange documenté (initiate + webhook)
- [ ] Pas de secret/log en clair
- [ ] Webhook idempotent
- [ ] `PaymentConfirmed` déclenché une seule fois par paiement réussi
- [ ] Messages d’erreur utilisateur en **français**, cohérents avec LengoPay

---

## Hors périmètre (ne pas faire dans cette tâche)

- Refactor multi-provider générique (strategy registry) — garder deux services explicites.
- Paiement Moov / Wave directs (déjà couverts indirectement via LengoPay si besoin).
- UI front mobile — uniquement API JSON + redirect URL.
- Facturation PDF (placeholder log existant dans `HandlePaymentConfirmed`).

---

## Ordre d’implémentation suggéré

1. Config + exceptions + service (initiate puis webhook)
2. Routes + contrôleur + request
3. Service provider binding
4. Tests + pint + test ciblé
5. `.env.example` + regénération Scribe si le projet le fait en CI

---

## Note pour l’agent exécutant ce prompt

Avant d’écrire le code, consulter la documentation installée via **Laravel Boost `search-docs`** pour : HTTP client, cache, transactions DB, Pest `Http::fake`. Lire intégralement `LengoPayService` et calquer structure, nommage et niveau de robustesse.

Si la documentation Orange officielle disponible dans le repo ou fournie par l’utilisateur contredit les noms de champs ci-dessus, **prioriser la doc Orange** et ajuster uniquement les clés `config('payments.orange.*_key')`, pas la forme du service.
