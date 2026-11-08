<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Actions\Admin\AdjustWallet;
use App\Actions\Admin\SetAdminAccess;
use App\Exceptions\AdminActionRefused;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\User;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

/**
 * @extends ViewRecord<User>
 */
class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->adjustWalletAction(),
            $this->setAdminAction('makeAdmin', 'Make admin', true),
            $this->setAdminAction('removeAdmin', 'Remove admin', false),
        ];
    }

    private function adjustWalletAction(): Action
    {
        return Action::make('adjustWallet')
            ->label('Adjust wallet')
            ->icon('heroicon-o-banknotes')
            ->modalHeading('Adjust wallet')
            ->schema([
                Radio::make('direction')
                    ->options(['credit' => 'Credit (add to the wallet)', 'debit' => 'Debit (take from the wallet)'])
                    ->default('credit')
                    ->required(),
                TextInput::make('amount')
                    ->label('Amount (dollars)')
                    ->placeholder('12.50')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->maxValue(AdjustWallet::MAX_CENTS / 100)
                    ->rule('regex:/^\d+(\.\d{1,2})?$/'),
                Textarea::make('reason')
                    ->required()
                    ->maxLength(AdjustWallet::MAX_REASON_LENGTH)
                    ->rows(2)
                    ->helperText('Shown to the customer in their wallet history.'),
            ])
            ->action(function (array $data, Action $action, AdjustWallet $adjust): void {
                $customer = $this->getRecord();

                try {
                    $adjust->handle(
                        $customer,
                        $this->admin(),
                        Money::fromDollars((string) $data['amount']),
                        $data['direction'] === 'credit',
                        $data['reason'],
                    );
                } catch (AdminActionRefused $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();
                    $action->halt();

                    return;
                }

                $this->getRecord()->refresh();
                Notification::make()->success()->title('Wallet adjusted')->send();
            });
    }

    private function setAdminAction(string $name, string $label, bool $makeAdmin): Action
    {
        return Action::make($name)
            ->label($label)
            ->color($makeAdmin ? 'primary' : 'danger')
            ->requiresConfirmation()
            ->visible(fn (): bool => $this->getRecord()->is_admin !== $makeAdmin
                && ($makeAdmin || ! $this->getRecord()->is($this->admin())))
            ->action(function (SetAdminAccess $access) use ($makeAdmin): void {
                $customer = $this->getRecord();

                try {
                    $access->handle($customer, $this->admin(), $makeAdmin);
                } catch (AdminActionRefused $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return;
                }

                $this->getRecord()->refresh();
                Notification::make()->success()->title($makeAdmin ? 'Admin access granted' : 'Admin access removed')->send();
            });
    }

    private function admin(): User
    {
        /** @var User */
        return Auth::user();
    }
}
