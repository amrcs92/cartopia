<?php

namespace App\Filament\Pages;

use App\Models\StoreSettings;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;

/**
 * @property-read Schema $form
 */
class EditStoreSettings extends Page
{
    protected static ?string $title = 'Store settings';

    protected static ?string $navigationLabel = 'Store settings';

    protected static ?string $slug = 'store-settings';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 90;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'signup_credit' => number_format(StoreSettings::current()->signup_credit->cents() / 100, 2, '.', ''),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('signup_credit')
                    ->label('Sign-up credit (dollars)')
                    ->placeholder('20.00')
                    ->prefix('$')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(StoreSettings::MAX_SIGNUP_CREDIT_CENTS / 100)
                    ->rule('regex:/^\d+(\.\d{1,2})?$/')
                    ->helperText('Added to the wallet of each new account, and shown in the store\'s sign-up copy. Set 0 to stop giving credit and hide that copy. Accounts that already exist keep what they got.'),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')->label('Save')->submit('save')->keyBindings(['mod+s']),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        StoreSettings::current()->update([
            'signup_credit' => Money::fromDollars((string) $data['signup_credit']),
        ]);

        Notification::make()->success()->title('Store settings saved')->send();
    }
}
