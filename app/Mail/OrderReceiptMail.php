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
 * The receipt page's content, emailed once an order is placed (spec 8.1).
 */
class OrderReceiptMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Order $order)
    {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Your Cartopia Order #{$this->order->id} Is Confirmed!");
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.order-receipt',
            text: 'mail.order-receipt-text',
            with: $this->data(),
        );
    }

    public function renderText(): string
    {
        return view('mail.order-receipt-text', $this->data())->render();
    }

    /**
     * @return array<string, mixed>
     */
    private function data(): array
    {
        $order = $this->order->loadMissing(['shippingMethod', 'succeededPayment', 'items']);

        $balanceAfter = $order->walletTransactions()
            ->where('type', WalletTransactionType::Purchase)
            ->latest('id')
            ->first()?->balance_after;

        $rows = [['label' => 'Paid with', 'value' => PaymentWording::paidWith($order), 'bold' => true]];

        $reference = $order->succeededPayment ? PaymentWording::reference($order->succeededPayment) : null;
        if ($reference !== null) {
            $rows[] = ['label' => 'Payment ref.', 'value' => $reference['short'], 'bold' => false];
        }
        foreach (PaymentWording::slipLines($order, $balanceAfter) as $line) {
            $rows[] = $line + ['bold' => false];
        }

        return [
            'order' => $order,
            'rows' => $rows,
            'placedAt' => $order->created_at->format('j M Y, H:i'),
            'url' => route('orders.show', $order),
        ];
    }
}
