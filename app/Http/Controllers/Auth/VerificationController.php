<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use App\Support\SafeMail;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function notice(Request $request)
    {
        return $request->user()->hasVerifiedEmail() ? redirect()->route('account.dashboard') : view('auth.verify');
    }

    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return redirect()->route('account.dashboard')->with('status', 'Email verified.');
    }

    public function resend(Request $request)
    {
        $sent = SafeMail::run(fn () => $request->user()->sendEmailVerificationNotification());

        return back()->with('status', $sent ? 'Verification link sent.' : 'We could not send the email right now. Please try again in a few minutes.');
    }
}
