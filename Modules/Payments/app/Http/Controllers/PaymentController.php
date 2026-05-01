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
use Modules\Payments\Exceptions\LengoPayException;
use Modules\Payments\Http\Requests\InitiateLengoPayPaymentRequest;
use Modules\Payments\Services\LengoPayService;

final class PaymentController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly LengoPayService $lengoPayService,
    ) {}

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

    public function success(): RedirectResponse
    {
        return redirect()->to(config('app.url').'?payment=success');
    }

    public function cancel(): RedirectResponse
    {
        return redirect()->to(config('app.url').'?payment=cancelled');
    }
}
