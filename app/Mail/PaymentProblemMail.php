<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Emailed when a payment ends badly while the shopper is away (spec 8.1).
 */
class PaymentProblemMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Payment $payment)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        $order = $this->payment->order;
        $what = $order instanceof Order ? "Order #{$order->id}" : "Top-up of {$this->payment->amount->format()}";

        return new Envelope(subject: "Payment Not Completed – Cartopia {$what}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.payment-problem',
            text: 'mail.payment-problem-text',
            with: $this->data(),
        );
    }

    public function renderText(): string
    {
        return view('mail.payment-problem-text', $this->data())->render();
    }

    /**
     * @return array<string, mixed>
     */
    private function data(): array
    {
        $order = $this->payment->order;

        if ($order instanceof Order) {
            $ticks = ['Nothing was charged.'];
            if ($order->wallet_amount->cents() > 0) {
                $ticks[] = "{$order->wallet_amount->format()} has been restored to your wallet.";
            }
            $ticks[] = 'Your basket is still there.';

            return [
                'intro' => "We couldn't complete your payment for this order. Don't worry — your basket is saved, and you can try again whenever you're ready.",
                'rows' => [['label' => 'Order', 'value' => "#{$order->id} · {$order->total->format()}", 'bold' => true]],
                'ticks' => $ticks,
                'button' => 'Back to your basket',
                'url' => route('cart.show'),
            ];
        }

        return [
            'intro' => "We couldn't complete your payment for this top-up. Nothing was charged, and you can try again from your wallet whenever you're ready.",
            'rows' => [['label' => 'Amount', 'value' => $this->payment->amount->format(), 'bold' => true]],
            'ticks' => ['Nothing was charged.'],
            'button' => 'Go to your wallet',
            'url' => route('wallet.show'),
        ];
    }
}
