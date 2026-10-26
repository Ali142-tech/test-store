<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContactController extends Controller
{
    public function show()
    {
        return view('pages.contact');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120', 'email' => 'required|email|max:190', 'phone' => 'nullable|string|max:30',
            'subject' => 'nullable|string|max:150', 'message' => 'required|string|max:3000', 'is_wholesale_lead' => 'nullable|boolean',
            'website' => 'max:0', // honeypot
        ]);
        unset($data['website']);
        ContactMessage::create($data + ['is_wholesale_lead' => $request->boolean('is_wholesale_lead')]);

        return back()->with('status', 'Thanks, your message has been received.');
    }

    public function subscribe(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:190', 'website' => 'max:0']);
        $sub = NewsletterSubscriber::firstOrNew(['email' => strtolower($data['email'])]);
        $sub->token = $sub->token ?: Str::random(48);
        $sub->subscribed_at = now();
        $sub->unsubscribed_at = null;
        $sub->save();

        return back()->with('status', 'You are subscribed. You can unsubscribe at any time.');
    }

    public function unsubscribe(string $token)
    {
        NewsletterSubscriber::where('token', $token)->update(['unsubscribed_at' => now()]);

        return view('pages.show', ['page' => new \App\Models\Page(['title' => 'Unsubscribed', 'body' => '<p>You have been unsubscribed from marketing emails.</p>'])]);
    }
}
