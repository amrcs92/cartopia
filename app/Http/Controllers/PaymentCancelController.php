<?php

namespace App\Http\Controllers;

use App\Actions\CancelPayment;
use App\Http\Controllers\Concerns\RedirectsAfterPayment;
use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use Illuminate\Http\RedirectResponse;

class PaymentCancelController extends Controller
{
    use RedirectsAfterPayment;

    public function __invoke(Payment $payment, CancelPayment $cancelPayment): RedirectResponse
    {
        try {
            $cancelPayment->handle($payment);
        } catch (PaymentProviderUnavailable|PaymentProviderRejected $e) {
            return $this->providerUnreachable($payment, $e);
        }

        return $this->afterPayment($payment->fresh());
    }
}
