<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ImagesRelationManager extends RelationManager
{
    protected static string $relationship = 'images';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            FileUpload::make('path')->image()->disk(config('store.media_disk'))->directory('products')->visibility('public')->required()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->maxSize(4096),
            TextInput::make('alt')->helperText('Describe the image for accessibility and SEO.'),
            Select::make('product_variant_id')->label('Variant (optional)')->relationship('variant', 'sku', fn ($query) => $query->where('product_id', $this->getOwnerRecord()->id)),
            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table->recordTitleAttribute('alt')->reorderable('sort_order')->defaultSort('sort_order')
            ->columns([ImageColumn::make('path')->disk(config('store.media_disk')), TextColumn::make('alt'), TextColumn::make('sort_order')])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
