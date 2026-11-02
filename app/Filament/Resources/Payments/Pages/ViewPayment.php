<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Actions\Admin\CheckPaymentWithProvider;
use App\Enums\ProviderCheckOutcome;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Payments\Exceptions\PaymentProviderRejected;
use App\Payments\Exceptions\PaymentProviderUnavailable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * @extends ViewRecord<Payment>
 */
class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkWithProvider')
                ->label('Check with provider')
                ->icon('heroicon-o-magnifying-glass')
                ->requiresConfirmation()
                ->modalHeading('Check with provider')
                ->modalDescription('Ask the provider what happened to this payment. If it was paid, the order is placed or the wallet is credited as usual.')
                ->visible(fn (): bool => $this->getRecord()->isPending())
                ->action(function (CheckPaymentWithProvider $check): void {
                    $payment = $this->getRecord();
                    $provider = $payment->method->label();

                    try {
                        $answer = $check->handle($payment);
                    } catch (PaymentProviderUnavailable) {
                        Notification::make()->danger()->title("{$provider} couldn't be reached. Try again in a moment.")->send();

                        return;
                    } catch (PaymentProviderRejected) {
                        Notification::make()->danger()->title("{$provider} couldn't check this payment. Try again in a moment.")->send();

                        return;
                    }

                    $this->getRecord()->refresh();
                    Notification::make()
                        ->status(match ($answer->outcome) {
                            ProviderCheckOutcome::Paid, ProviderCheckOutcome::AlreadySettled => 'success',
                            ProviderCheckOutcome::StillPending => 'warning',
                            ProviderCheckOutcome::AmountMismatch, ProviderCheckOutcome::Closed => 'danger',
                        })
                        ->title($answer->message)
                        ->send();
                }),
        ];
    }
}
