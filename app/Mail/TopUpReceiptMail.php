<?php

namespace App\Mail;

use App\Models\Payment;
use App\Models\WalletTransaction;
use App\Payments\PaymentWording;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Emailed once a paid top-up has been credited to the wallet (spec 8.1).
 */
class TopUpReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Payment $payment)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You added {$this->payment->amount->format()} to your Cartopia wallet");
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.top-up-receipt',
            text: 'mail.top-up-receipt-text',
            with: $this->data(),
        );
    }

    public function renderText(): string
    {
        return view('mail.top-up-receipt-text', $this->data())->render();
    }

    /**
     * @return array<string, mixed>
     */
    private function data(): array
    {
        $payment = $this->payment;
        $amount = $payment->amount->format();
        $balanceAfter = WalletTransaction::query()->where('payment_id', $payment->id)->first()?->balance_after;

        $rows = [
            ['label' => 'Amount', 'value' => $amount, 'bold' => true],
            ['label' => 'Paid with', 'value' => PaymentWording::topUpPaidWith($payment), 'bold' => false],
        ];

        $reference = PaymentWording::reference($payment);
        if ($reference !== null) {
            $rows[] = ['label' => 'Payment ref.', 'value' => $reference['short'], 'bold' => false];
        }
        if ($balanceAfter !== null) {
            $rows[] = ['label' => 'New balance', 'value' => $balanceAfter->format(), 'bold' => true];
        }

        return [
            'title' => "{$amount} is in your wallet",
            'rows' => $rows,
            'url' => route('wallet.show'),
        ];
    }
}
