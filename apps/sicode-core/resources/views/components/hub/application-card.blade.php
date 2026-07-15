@props([
    'entry',
])

<article class="flex min-h-44 flex-col rounded-lg border border-border bg-surface p-5 shadow-sicode-sm transition hover:border-border-strong hover:shadow-sicode-md">
    <div class="flex items-start justify-between gap-4">
        <div class="flex min-w-0 items-start gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md bg-secondary text-label font-bold text-secondary-foreground" aria-hidden="true">
                {{ mb_strtoupper(mb_substr($entry->applicationName, 0, 1)) }}
            </div>
            <div class="min-w-0">
                <h2 class="truncate text-heading-3 font-semibold text-text">{{ $entry->applicationName }}</h2>
                <p class="mt-1 text-body-small text-text-muted">{{ $entry->applicationCode }}</p>
            </div>
        </div>
        <span class="inline-flex shrink-0 items-center rounded-sm bg-success-subtle px-2 py-1 text-caption font-medium text-success-subtle-foreground">
            Permitida
        </span>
    </div>

    @if ($entry->applicationDescription !== null)
        <p class="mt-4 text-body text-text-muted">{{ $entry->applicationDescription }}</p>
    @else
        <p class="mt-4 text-body text-text-muted">Entrada autorizada pelo CORE para esta aplicação{{ $entry->displayContext() !== null ? ' e contexto' : '' }}.</p>
    @endif

    <dl class="mt-4 space-y-2 text-body-small">
        <div class="flex items-center justify-between gap-3 border-t border-border pt-3">
            <dt class="font-medium text-text-muted">Contexto</dt>
            <dd class="truncate text-right font-semibold text-text">{{ $entry->displayContext() ?? 'Aplicação sem contexto' }}</dd>
        </div>
        <div class="flex items-center justify-between gap-3">
            <dt class="font-medium text-text-muted">Lançamento</dt>
            <dd class="text-right {{ $entry->launchUrl !== null ? 'font-semibold text-success-subtle-foreground' : 'text-text-muted' }}">
                {{ $entry->launchUrl !== null ? 'Configurado' : 'Protocolo pendente' }}
            </dd>
        </div>
    </dl>

    <div class="mt-auto pt-5">
        @if ($entry->launchUrl !== null)
            <form method="POST" action="{{ $entry->launchUrl }}">
                @csrf
                @if ($entry->contextId !== null)
                    <input type="hidden" name="context_id" value="{{ $entry->contextId }}">
                @endif
                <button type="submit" class="inline-flex w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-label font-semibold text-primary-foreground hover:bg-primary-hover focus-visible:ring" aria-label="Entrar em {{ $entry->applicationName }}">
                    Entrar
                </button>
            </form>
        @else
            <button type="button" class="inline-flex w-full cursor-not-allowed items-center justify-center rounded-md border border-border bg-surface-muted px-4 py-2 text-label font-semibold text-text-muted" disabled>
                Entrada em breve
            </button>
        @endif
    </div>
</article>
