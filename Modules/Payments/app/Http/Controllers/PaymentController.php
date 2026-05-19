<?php

declare(strict_types=1);

namespace Modules\Payments\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Orders\Models\Order;
use Modules\Payments\Enums\PaymentMethod;
use Modules\Payments\Exceptions\InvalidLengoPayWebhookSignatureException;
use Modules\Payments\Exceptions\InvalidOrangeMoneyWebhookSignatureException;
use Modules\Payments\Exceptions\LengoPayException;
use Modules\Payments\Exceptions\OrangeMoneyException;
use Modules\Payments\Http\Requests\InitiateLengoPayPaymentRequest;
use Modules\Payments\Http\Requests\InitiateOrangeMoneyPaymentRequest;
use Modules\Payments\Services\LengoPayService;
use Modules\Payments\Services\OrangeMoneyService;

final class PaymentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly LengoPayService $lengoPayService,
        private readonly OrangeMoneyService $orangeMoneyService,
    ) {}

    /**
     * Crée un paiement LengoPay en attente pour une commande et retourne l’URL de redirection vers la page de paiement.
     *
     * La commande doit être au statut `pending`. L’utilisateur authentifié doit avoir le droit `view` sur la commande
     * (acheteur propriétaire, vendeur concerné ou administrateur).
     *
     * @group Paiements
     *
     * @subgroup LengoPay
     *
     * @authenticated
     *
     * @urlParam order integer required Identifiant de la commande. Example: 42
     *
     * @response 200 {
     *   "redirect_url": "https://checkout.lengopay.example/pay/abc123"
     * }
     * @response 422 scenario="Commande non éligible ou erreur LengoPay" {
     *   "message": "Seules les commandes en attente de paiement peuvent être initiées."
     * }
     * @response 403 scenario="Accès refusé à la commande"
     */
    public function initiate(InitiateLengoPayPaymentRequest $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $method = PaymentMethod::from($request->validated('payment_method'));

        try {
            $redirectUrl = $this->lengoPayService->initiatePayment($order, $method);
        } catch (LengoPayException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'redirect_url' => $redirectUrl,
        ]);
    }

    /**
     * Webhook serveur-à-serveur appelé par LengoPay après un changement de statut de paiement.
     *
     * Ne pas appeler depuis le client mobile : aucune authentification Bearer. La requête doit contenir le corps JSON brut
     * signé par HMAC-SHA256 avec le secret webhook (`LENGOPAY_WEBHOOK_SECRET`). Le nom d’en-tête est configurable via
     * `LENGOPAY_WEBHOOK_SIGNATURE_HEADER` (défaut : `X-Lengopay-Signature`).
     *
     * Traitement idempotent : les replays avec le même `transaction_id` ne déclenchent pas deux fois la confirmation.
     *
     * @group Paiements
     *
     * @subgroup Webhook LengoPay
     *
     * @unauthenticated
     *
     * @header X-Lengopay-Signature required Signature HMAC-SHA256 du corps brut JSON (hex). Example: a1b2c3d4e5f6...
     *
     * @bodyParam order_number string required Numéro de commande Kilora (`orders.order_number`). Example: ORD-663f1a2b3c4d5
     * @bodyParam transaction_id string required Identifiant de transaction LengoPay. Example: lp-tx-9f8e7d6c
     * @bodyParam status string required Statut côté fournisseur : success, completed, paid, failed, cancelled, etc. Example: success
     *
     * @response 200 {
     *   "received": true
     * }
     * @response 401 scenario="Signature manquante ou invalide" {
     *   "message": "Signature webhook invalide."
     * }
     * @response 422 scenario="JSON ou payload invalide" {
     *   "message": "Payload webhook incomplet."
     * }
     */
    public function handleWebhook(Request $request): JsonResponse
    {
        try {
            $this->lengoPayService->verifyWebhook($request);
        } catch (InvalidLengoPayWebhookSignatureException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        } catch (LengoPayException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['received' => true]);
    }

    /**
     * Crée un paiement Orange Money en attente pour une commande et retourne l’URL de redirection.
     *
     * La commande doit être au statut `pending`. L’utilisateur authentifié doit avoir le droit `view` sur la commande.
     *
     * @group Paiements
     *
     * @subgroup Orange Money
     *
     * @authenticated
     *
     * @urlParam order integer required Identifiant de la commande. Example: 42
     *
     * @response 200 {
     *   "redirect_url": "https://payment.orange.com/checkout/abc123"
     * }
     * @response 422 scenario="Commande non éligible ou erreur Orange Money" {
     *   "message": "Seules les commandes en attente de paiement peuvent être initiées."
     * }
     * @response 403 scenario="Accès refusé à la commande"
     */
    public function initiateOrange(InitiateOrangeMoneyPaymentRequest $request, Order $order): JsonResponse
    {
        $this->authorize('view', $order);

        $msisdn = $request->validated('customer_msisdn');

        try {
            $redirectUrl = $this->orangeMoneyService->initiatePayment(
                $order,
                is_string($msisdn) ? $msisdn : null,
            );
        } catch (OrangeMoneyException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'redirect_url' => $redirectUrl,
        ]);
    }

    /**
     * Webhook serveur-à-serveur appelé par Orange Money après un changement de statut de paiement.
     *
     * Ne pas appeler depuis le client mobile : aucune authentification Bearer. Corps JSON signé par HMAC-SHA256
     * avec `ORANGE_WEBHOOK_SECRET`. En-tête configurable via `ORANGE_WEBHOOK_SIGNATURE_HEADER` (défaut : `X-Orange-Signature`).
     *
     * @group Paiements
     *
     * @subgroup Webhook Orange Money
     *
     * @unauthenticated
     *
     * @header X-Orange-Signature required Signature HMAC-SHA256 du corps brut JSON (hex). Example: a1b2c3d4e5f6...
     *
     * @bodyParam order_number string required Numéro de commande Kilora. Example: ORD-663f1a2b3c4d5
     * @bodyParam transaction_id string required Identifiant de transaction Orange. Example: om-tx-9f8e7d6c
     * @bodyParam status string required Statut côté fournisseur. Example: success
     *
     * @response 200 {
     *   "received": true
     * }
     * @response 401 scenario="Signature manquante ou invalide" {
     *   "message": "Signature webhook invalide."
     * }
     * @response 422 scenario="JSON ou payload invalide" {
     *   "message": "Payload webhook incomplet."
     * }
     */
    public function handleOrangeWebhook(Request $request): JsonResponse
    {
        try {
            $this->orangeMoneyService->verifyWebhook($request);
        } catch (InvalidOrangeMoneyWebhookSignatureException $e) {
            return response()->json(['message' => $e->getMessage()], 401);
        } catch (OrangeMoneyException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['received' => true]);
    }

    public function success(): RedirectResponse
    {
        return redirect()->to(config('app.url').'?payment=success');
    }

    public function cancel(): RedirectResponse
    {
        return redirect()->to(config('app.url').'?payment=cancelled');
    }

    public function orangeSuccess(): RedirectResponse
    {
        return redirect()->to(config('app.url').'?payment=success');
    }

    public function orangeCancel(): RedirectResponse
    {
        return redirect()->to(config('app.url').'?payment=cancelled');
    }
}
