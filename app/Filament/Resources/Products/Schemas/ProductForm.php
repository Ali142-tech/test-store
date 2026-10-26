<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\DeviceModel;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Product')->columnSpanFull()->tabs([
                Tab::make('General')->schema([
                    TextInput::make('name')->required()->maxLength(190)->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, Set $set, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                    TextInput::make('slug')->required()->unique(ignoreRecord: true)->helperText('Changing the slug of a live product automatically creates a 301 redirect from the old URL.'),
                    Select::make('brand_id')->relationship('brand', 'name')->searchable()->preload()
                        ->createOptionForm([TextInput::make('name')->required(), TextInput::make('slug')->required()->unique('brands', 'slug')]),
                    Select::make('category_id')->relationship('category', 'name')->searchable()->preload()
                        ->createOptionForm([TextInput::make('name')->required(), TextInput::make('slug')->required()->unique('categories', 'slug')]),
                    Select::make('type')->options(['simple' => 'Simple / variants', 'bundle' => 'Bundle (combo pack)'])->default('simple')->required()->live(),
                    Select::make('status')->options(['draft' => 'Draft', 'active' => 'Active', 'archived' => 'Archived (discontinued)'])->default('draft')->required(),
                    Toggle::make('is_featured'), Toggle::make('is_best_seller'),
                    Toggle::make('wholesale_enabled')->helperText('Lets approved wholesale buyers see tiers and quote this product.'),
                    DateTimePicker::make('published_at')->helperText('Used for "New arrivals".'),
                ])->columns(2),

                Tab::make('Description')->schema([
                    Textarea::make('short_description')->maxLength(500)->rows(2),
                    Textarea::make('description')->rows(8)->helperText('Plain text. Do not claim features or authenticity you cannot verify.'),
                ]),

                Tab::make('Details')->schema([
                    TextInput::make('manufacturer'), TextInput::make('material'), TextInput::make('warranty')->helperText('Shown to customers. Only enter real warranty terms.'),
                    Toggle::make('is_authentic_verified')->helperText('Only switch on if authenticity is genuinely verified; the note below is then shown publicly.'),
                    Textarea::make('authenticity_note')->columnSpanFull(),
                    Textarea::make('compatibility_note')->columnSpanFull(),
                    KeyValue::make('specifications')->columnSpanFull()->keyLabel('Specification')->valueLabel('Value'),
                    TextInput::make('weight_grams')->numeric()->suffix('g')->helperText('Used for weight-based shipping.'),
                    TextInput::make('length_cm')->numeric(), TextInput::make('width_cm')->numeric(), TextInput::make('height_cm')->numeric(),
                ])->columns(2),

                Tab::make('Compatibility')->schema([
                    Select::make('deviceModels')->relationship('deviceModels', 'name')->multiple()->searchable()->preload()
                        ->getOptionLabelFromRecordUsing(fn (DeviceModel $m) => $m->deviceBrand->name.' '.$m->name)
                        ->helperText('Phones this accessory fits.'),
                    Select::make('related')->relationship('related', 'name')->multiple()->searchable()->preload()->label('Related products'),
                ]),

                Tab::make('Bundle')->visible(fn ($get) => $get('type') === 'bundle')->schema([
                    Repeater::make('bundleItems')->relationship()->schema([
                        Select::make('product_variant_id')->label('Component variant')->options(
                            fn () => \App\Models\ProductVariant::with('product')->get()->mapWithKeys(fn ($v) => [$v->id => $v->sku.' - '.$v->displayName()])
                        )->searchable()->required(),
                        TextInput::make('quantity')->numeric()->minValue(1)->default(1)->required(),
                    ])->columns(2)->helperText('Bundle stock is derived from its components; selling a bundle deducts each component.'),
                ]),

                Tab::make('SEO')->schema([
                    TextInput::make('seo_title')->maxLength(70), Textarea::make('seo_description')->maxLength(170)->rows(2), TextInput::make('seo_image')->helperText('Image path or URL for social sharing.'),
                ]),
            ]),
        ]);
    }
}
