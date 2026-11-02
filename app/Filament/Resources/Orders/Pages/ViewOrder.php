<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Actions\Admin\RefundOrder;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\RefundStatus;
use App\Exceptions\AdminActionRefused;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use App\Payments\PaymentWording;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\UnorderedList;
use Illuminate\Support\Facades\Auth;

/**
 * @extends ViewRecord<Order>
 */
class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->refundAction('refundOrder', 'Refund order')
                ->visible(fn (): bool => $this->isPlaced() && $this->getRecord()->refund === null),
            $this->refundAction('retryRefund', 'Retry refund')
                ->visible(fn (): bool => $this->isPlaced() && in_array($this->getRecord()->refund?->status, [RefundStatus::Pending, RefundStatus::Failed], true)),
        ];
    }

    private function refundAction(string $name, string $label): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('danger')
            ->modalHeading($label)
            ->modalSubmitActionLabel(fn (): string => $this->resumesPendingRefund()
                ? 'Retry refund'
                : 'Refund '.$this->getRecord()->total->format())
            ->schema([
                UnorderedList::make(fn (): array => $this->breakdown($this->getRecord()))->columns(1),
                // A pending refund resumes with its first admin and reason, so it asks for none.
                Textarea::make('reason')
                    ->required()
                    ->maxLength(255)
                    ->rows(2)
                    ->hidden(fn (): bool => $this->resumesPendingRefund()),
            ])
            ->action(function (array $data, RefundOrder $refund): void {
                $order = $this->getRecord();

                try {
                    $refund->handle($order, $this->admin(), $data['reason'] ?? (string) $order->refund?->reason);
                } catch (AdminActionRefused $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $order->refresh();

                    return;
                } catch (PaymentProviderUnavailable) {
                    $provider = ($order->succeededPayment === null ? $order->payment_method : $order->succeededPayment->method)->label();
                    Notification::make()->danger()->title("{$provider} couldn't be reached. Try again in a moment.")->send();
                    $order->refresh();

                    return;
                }

                $order->refresh();
                Notification::make()->success()->title("Order #{$order->id} is refunded.")->send();
            });
    }

    private function isPlaced(): bool
    {
        return $this->getRecord()->status === OrderStatus::Placed;
    }

    private function resumesPendingRefund(): bool
    {
        return $this->getRecord()->refund?->status === RefundStatus::Pending;
    }

    /**
     * What the refund will send back and where (spec 7.3): the provider's
     * part, the wallet part when there is one, then the total. A retry shows
     * the amounts on the refund it resumes.
     *
     * @return list<string>
     */
    private function breakdown(Order $order): array
    {
        $lines = [];
        $refund = $order->refund;
        $providerPart = $refund === null ? $order->total->subtract($order->wallet_amount) : $refund->provider_amount;
        $walletPart = $refund === null ? $order->wallet_amount : $refund->wallet_amount;

        if ($providerPart->cents() > 0) {
            $payment = $order->succeededPayment;
            $to = match ($order->payment_method) {
                PaymentMethod::Stripe => $payment?->card_last4 === null
                    ? 'Stripe'
                    : PaymentWording::cardBrand($payment->card_brand)." ending in {$payment->card_last4}, through Stripe",
                default => 'PayPal',
            };
            $lines[] = "{$to}: {$providerPart->format()}";
        }

        if ($walletPart->cents() > 0) {
            $lines[] = "Wallet: {$walletPart->format()}";
        }

        $lines[] = "Total: {$providerPart->add($walletPart)->format()}";

        return $lines;
    }

    private function admin(): User
    {
        /** @var User */
        return Auth::user();
    }
}
