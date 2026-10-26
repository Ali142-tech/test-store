<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Filament\Resources\Quotes\QuoteResource;
use App\Services\Wholesale\QuoteService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use InvalidArgumentException;

class EditQuote extends EditRecord
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('send')->label('Save & send to customer')->icon('heroicon-o-paper-airplane')->color('success')
                ->requiresConfirmation()
                ->visible(fn () => in_array($this->record->status, ['requested', 'quoted', 'accepted'], true))
                ->action(function () {
                    $this->save(shouldRedirect: false);
                    try {
                        app(QuoteService::class)->send($this->record->fresh(), auth()->user());
                        Notification::make()->title('Quote sent to the customer')->success()->send();
                        $this->record->refresh();
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
            Action::make('reject')->label('Reject request')->color('danger')->icon('heroicon-o-x-mark')
                ->visible(fn () => in_array($this->record->status, ['requested', 'quoted'], true))
                ->schema([Textarea::make('note')->label('Reason')])
                ->action(function (array $data) {
                    app(QuoteService::class)->reject($this->record, auth()->user(), $data['note'] ?? null);
                    Notification::make()->title('Quote rejected')->success()->send();
                    $this->record->refresh();
                }),
        ];
    }
}
