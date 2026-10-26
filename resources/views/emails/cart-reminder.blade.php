<x-mail::message>
# Still thinking it over?

Hi {{ $user->name }}, the items in your cart are waiting for you.

<x-mail::button :url="route('cart.show')">Return to your cart</x-mail::button>

You are receiving this because you opted in to marketing emails. You can turn them off any time in your [profile]({{ route('account.profile') }}).

{{ config('app.name') }}
</x-mail::message>
