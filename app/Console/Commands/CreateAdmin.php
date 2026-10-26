<?php

namespace App\Console\Commands;

use App\Models\StoreSetting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

/**
 * Creates (or upgrades) a Super Admin without ever putting a password in the code or the shell history:
 * the password is typed at a hidden prompt (or supplied through the STORE_ADMIN_PASSWORD environment variable).
 */
class CreateAdmin extends Command
{
    protected $signature = 'store:create-admin
        {email : Login email for the admin}
        {--name= : Display name}
        {--store-name= : Also set the store name (Admin > Store settings)}
        {--contact-email= : Also set the public contact email}
        {--contact-phone= : Also set the public contact phone}';

    protected $description = 'Create a verified Super Admin (password is asked securely)';

    public function handle(): int
    {
        $email = strtolower(trim($this->argument('email')));
        if (Validator::make(['e' => $email], ['e' => 'required|email:rfc'])->fails()) {
            $this->error('That is not a valid email address.');

            return self::FAILURE;
        }

        if (! Role::where('name', 'Super Admin')->exists()) {
            $this->callSilent('db:seed', ['--class' => RolesSeeder::class, '--force' => true]);
        }

        $existing = User::where('email', $email)->first();
        if ($existing && ! $this->confirm("A user with {$email} already exists. Make them a Super Admin and set a new password?", false)) {
            return self::FAILURE;
        }

        $password = getenv('STORE_ADMIN_PASSWORD') ?: $this->secret('Password (min 12 characters, hidden)');
        if (! getenv('STORE_ADMIN_PASSWORD') && $password !== $this->secret('Repeat password')) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }

        $check = Validator::make(['p' => $password], ['p' => ['required', Password::min(12)->letters()->mixedCase()->numbers()->uncompromised()]]);
        if ($check->fails()) {
            $this->error('Weak password: '.implode(' ', $check->errors()->all()));

            return self::FAILURE;
        }

        $user = $existing ?? new User;
        // is_active / customer_type are intentionally not mass-assignable; this is the explicit, authorised path.
        $user->forceFill([
            'name' => $this->option('name') ?: ($existing?->name ?? 'Store Admin'),
            'email' => $email, 'password' => $password, 'is_active' => true, 'email_verified_at' => now(),
        ])->save();
        $user->syncRoles(['Super Admin']);

        foreach (['store-name' => 'store_name', 'contact-email' => 'contact_email', 'contact-phone' => 'contact_phone'] as $opt => $key) {
            if ($this->option($opt)) {
                StoreSetting::updateOrCreate(['key' => $key], ['value' => $this->option($opt)]);
            }
        }

        $this->info("Super Admin ready: {$email}. Sign in at ".url('/admin'));

        return self::SUCCESS;
    }
}
