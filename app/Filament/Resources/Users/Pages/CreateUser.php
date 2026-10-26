<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    // is_active / customer_type are not mass-assignable on purpose; this is the explicit, authorised path.
    protected function handleRecordCreation(array $data): Model
    {
        abort_unless(auth()->user()->can('manage roles'), 403);

        return User::forceCreate($data + ['email_verified_at' => now()]);
    }
}
