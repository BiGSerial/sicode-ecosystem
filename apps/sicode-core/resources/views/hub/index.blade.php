<x-layouts.core title="Hub de Aplicações · SICODE CORE">
    <div class="min-h-screen lg:grid lg:grid-cols-[16rem_1fr]">
        <aside class="bg-secondary text-secondary-foreground" aria-label="Navegação principal">
            <div class="border-b border-white/10 px-5 py-5">
                <x-core.brand />
                <p class="mt-1 text-caption font-medium uppercase text-white/70">Core · Hub de aplicações</p>
            </div>
            <nav class="px-3 py-4" aria-label="Módulos do CORE">
                <a href="{{ route('hub') }}" aria-current="page" class="flex items-center gap-2 rounded-md border-l-4 border-success bg-secondary-hover px-3 py-2 text-label font-semibold text-white">
                    <span aria-hidden="true">▦</span>
                    Hub
                </a>
            </nav>
        </aside>

        <div class="min-w-0">
            <header class="border-b border-border bg-surface">
                <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 lg:px-6">
                    <div>
                        <p class="text-caption font-medium uppercase text-text-subtle">SICODE CORE</p>
                        <h1 class="text-heading-2 font-semibold text-text">Hub de aplicações</h1>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="hidden text-right sm:block">
                            <p class="text-label font-semibold text-text">{{ $user->display_name }}</p>
                            @if ($user->primary_email !== null)
                                <p class="text-caption text-text-muted">{{ $user->primary_email }}</p>
                            @endif
                        </div>
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-secondary text-caption font-bold text-secondary-foreground" aria-hidden="true">
                            {{ mb_strtoupper(mb_substr($user->display_name, 0, 1)) }}
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-md border border-border bg-surface px-3 py-2 text-label font-semibold text-text-muted hover:bg-primary-subtle hover:text-primary-subtle-foreground focus-visible:ring">
                                Sair
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <main id="conteudo-principal" class="px-4 py-5 lg:px-6">
                <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 class="text-heading-1 font-bold text-text">Aplicações disponíveis</h2>
                        <p class="mt-1 text-body text-text-muted">
                            Entradas permitidas por decisão canônica do ApplicationEntry.
                        </p>
                    </div>
                    <p class="rounded-md border border-border bg-surface px-3 py-2 text-caption text-text-muted">
                        Avaliado em {{ $evaluatedAt->format('d/m/Y H:i:s') }}
                    </p>
                </div>

                @if (count($applications) === 0)
                    <x-core.empty-state
                        title="Nenhuma aplicação disponível"
                        message="Sua sessão está ativa, mas não há aplicações permitidas para exibição neste momento."
                    />
                @else
                    <section aria-label="Aplicações autorizadas" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($applications as $entry)
                            <x-hub.application-card :entry="$entry" />
                        @endforeach
                    </section>
                @endif
            </main>
        </div>
    </div>
</x-layouts.core>
