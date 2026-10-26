<?php

namespace App\Filament\Resources\Pages\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Textarea::make('body')
                    ->default(null)
                    ->columnSpanFull(),
                Toggle::make('is_published')
                    ->required(),
                TextInput::make('seo_title')
                    ->default(null),
                TextInput::make('seo_description')
                    ->default(null),
            ]);
    }
}
