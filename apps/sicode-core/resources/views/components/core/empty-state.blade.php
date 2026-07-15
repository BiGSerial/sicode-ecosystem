@props([
    'title',
    'message',
])

<section {{ $attributes->merge(['class' => 'rounded-lg border border-border bg-surface p-6 text-center shadow-sicode-sm']) }}>
    <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-md bg-primary-subtle text-primary-subtle-foreground" aria-hidden="true">
        <span class="text-heading-3">□</span>
    </div>
    <h2 class="mt-4 text-heading-3 font-semibold text-text">{{ $title }}</h2>
    <p class="mx-auto mt-2 max-w-xl text-body text-text-muted">{{ $message }}</p>
</section>
