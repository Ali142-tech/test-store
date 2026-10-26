@props(['title', 'subtitle' => null])
<div class="container-x flex justify-center py-16">
    <div class="w-full max-w-md">
        <h1 class="text-3xl font-semibold tracking-tight">{{ $title }}</h1>
        @if ($subtitle) <p class="mt-2 text-ink-soft">{{ $subtitle }}</p> @endif
        @if (session('status')) <p class="mt-4 rounded-xl bg-accent-soft p-3 text-sm" role="status">{{ session('status') }}</p> @endif
        <div class="mt-8">{{ $slot }}</div>
    </div>
</div>
