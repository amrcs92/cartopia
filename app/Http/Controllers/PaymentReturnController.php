<?php

namespace App\Http\Controllers;

use App\Actions\SettlePayment;
use App\Http\Controllers\Concerns\RedirectsAfterPayment;
use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use Illuminate\Http\RedirectResponse;

class PaymentReturnController extends Controller
{
    use RedirectsAfterPayment;

    public function __invoke(Payment $payment, SettlePayment $settlePayment): RedirectResponse
    {
        try {
            $settlePayment->handle($payment, notifyOnFailure: false);
        } catch (PaymentProviderUnavailable|PaymentProviderRejected $e) {
            return $this->providerUnreachable($payment, $e);
        }

        return $this->afterPayment($payment->fresh());
    }
}
