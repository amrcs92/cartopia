<?php

namespace App\Mail;

use App\Enums\WalletTransactionType;
use App\Models\Order;
use App\Payments\PaymentWording;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Where the money went, emailed once an admin has refunded an order (spec 9).
 */
class OrderRefundedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Order $order)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your Cartopia Order #{$this->order->id} Has Been Refunded");
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.order-refunded',
            text: 'mail.order-refunded-text',
            with: $this->data(),
        );
    }

    public function renderText(): string
    {
        return view('mail.order-refunded-text', $this->data())->render();
    }

    /**
     * @return array<string, mixed>
     */
    private function data(): array
    {
        $order = $this->order->loadMissing(['shippingMethod', 'items', 'refund.payment']);
        $refund = $order->refund;

        $balanceAfter = $refund === null ? null : $order->walletTransactions()
            ->where('type', WalletTransactionType::Refund)
            ->where('refund_id', $refund->id)
            ->first()?->balance_after;

        return [
            'order' => $order,
            'rows' => PaymentWording::refundRows($order, $balanceAfter),
            'hasProviderPart' => $refund !== null && $refund->provider_amount->cents() > 0,
            'refundedAt' => $refund?->completed_at?->format('j M Y, H:i'),
            'url' => route('orders.show', $order),
        ];
    }
}
