<?php

namespace App\Http\Controllers\Wholesale;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WholesaleApplication;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Password;

class WholesaleController extends Controller
{
    public function showRegister(Request $request)
    {
        if ($request->user()?->wholesaleApplication) {
            return redirect()->route('wholesale.dashboard');
        }

        return view('wholesale.register');
    }

    public function register(Request $request)
    {
        $user = $request->user();
        abort_if($user && WholesaleApplication::where('user_id', $user->id)->exists(), 403, 'You already have a wholesale application.');

        $data = $request->validate([
            'business_name' => 'required|string|max:190', 'business_type' => 'nullable|string|max:100', 'tax_number' => 'nullable|string|max:60',
            'contact_name' => 'required|string|max:120', 'contact_phone' => 'required|string|max:30',
            'business_address' => 'required|string|max:500', 'city' => 'required|string|max:100', 'notes' => 'nullable|string|max:1000',
            'document' => ['nullable', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(4096)],
            ...($user ? [] : [
                'name' => 'required|string|max:120', 'email' => 'required|email:rfc|max:190|unique:users,email',
                'password' => ['required', 'confirmed', Password::min(8)],
            ]),
        ]);

        if (! $user) {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['contact_phone'], 'password' => $data['password']]);
            event(new Registered($user));
            Auth::login($user);
            $request->session()->regenerate();
        }

        $path = $original = null;
        if ($request->hasFile('document')) {
            // Private disk: never web-accessible; served only through an authorised admin route.
            $path = $request->file('document')->store('wholesale-documents', 'local');
            $original = $request->file('document')->getClientOriginalName();
        }

        WholesaleApplication::create([
            'user_id' => $user->id, 'business_name' => $data['business_name'], 'business_type' => $data['business_type'] ?? null,
            'tax_number' => $data['tax_number'] ?? null, 'contact_name' => $data['contact_name'], 'contact_phone' => $data['contact_phone'],
            'business_address' => $data['business_address'], 'city' => $data['city'], 'notes' => $data['notes'] ?? null,
            'document_path' => $path, 'document_original_name' => $original,
        ]);

        return redirect()->route('wholesale.dashboard')->with('status', 'Application received. We will review it and email you.');
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();
        $application = $user->wholesaleApplication;
        abort_unless($application, 404);

        return view('wholesale.dashboard', [
            'application' => $application,
            'approved' => $user->isApprovedWholesale(),
            'orders' => $user->isApprovedWholesale() ? $user->orders()->where('customer_type', 'wholesale')->latest('placed_at')->limit(10)->get() : collect(),
            'quotes' => $user->isApprovedWholesale() ? \App\Models\Quote::where('user_id', $user->id)->latest()->limit(5)->get() : collect(),
        ]);
    }
}
