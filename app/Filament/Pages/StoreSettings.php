<?php

namespace App\Filament\Pages;

use App\Models\StoreSetting;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class StoreSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Store settings';

    protected string $view = 'filament.pages.store-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    private const BOOLEANS = ['guest_checkout', 'payment_cod_enabled', 'payment_cod_wholesale', 'payment_bank_enabled'];

    private const KEYS = [
        'store_name', 'announcement', 'contact_email', 'contact_phone', 'whatsapp_number', 'currency', 'currency_symbol', 'tax_rate', 'guest_checkout',
        'wholesale_min_order_value', 'payment_cod_enabled', 'payment_cod_wholesale', 'payment_bank_enabled',
        'bank_name', 'bank_account_title', 'bank_account_number', 'bank_instructions',
    ];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('manage settings');
    }

    public function mount(): void
    {
        $values = collect(self::KEYS)->mapWithKeys(fn ($k) => [$k => StoreSetting::get($k)])->all();
        foreach (self::BOOLEANS as $b) {
            $values[$b] = (bool) ($values[$b] ?? false);
        }
        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Section::make('Store')->columns(2)->schema([
                TextInput::make('store_name')->required(), TextInput::make('announcement')->helperText('Shown in the bar at the top of every page.'),
                TextInput::make('contact_email')->email(), TextInput::make('contact_phone'), TextInput::make('whatsapp_number')->helperText('International format, digits only, e.g. 923027171164'),
                TextInput::make('currency')->required()->length(3)->helperText('ISO code, e.g. PKR'), TextInput::make('currency_symbol')->required(),
                TextInput::make('tax_rate')->numeric()->minValue(0)->maxValue(100)->suffix('%')->helperText('Added on top of goods after discounts. 0 = no tax.'),
                Toggle::make('guest_checkout')->label('Allow guest checkout'),
            ]),
            Section::make('Wholesale')->schema([
                TextInput::make('wholesale_min_order_value')->numeric()->minValue(0)->helperText('Minimum goods total for wholesale orders (not applied to accepted quotes).'),
            ]),
            Section::make('Payment methods')->columns(2)->description('Online gateways (JazzCash, Easypaisa, card) are not available until integrated with real merchant credentials.')->schema([
                Toggle::make('payment_cod_enabled')->label('Cash on delivery'), Toggle::make('payment_cod_wholesale')->label('Allow COD for wholesale buyers'),
                Toggle::make('payment_bank_enabled')->label('Bank transfer'),
                TextInput::make('bank_name'), TextInput::make('bank_account_title'), TextInput::make('bank_account_number'),
                Textarea::make('bank_instructions')->columnSpanFull(),
            ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();

        foreach (self::KEYS as $key) {
            $value = $data[$key] ?? null;
            $value = in_array($key, self::BOOLEANS, true) ? ($value ? '1' : '0') : (string) ($value ?? '');
            StoreSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Notification::make()->title('Settings saved')->success()->send();
    }
}
