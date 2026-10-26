<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        abort_unless(UserResource::canEdit($record), 403);
        $record->forceFill($data)->save();

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
