<x-layouts.core title="Entrar · SICODE CORE">
    <main id="conteudo-principal" class="flex min-h-screen items-center justify-center px-4 py-10">
        <section class="w-full max-w-md rounded-lg border border-border bg-surface p-6 shadow-sicode-md" aria-labelledby="login-title">
            <div class="mb-6 border-b border-border pb-5">
                <div class="inline-flex items-baseline gap-1 text-heading-2 font-bold text-secondary">
                    <span>SICODE</span><span class="text-success" aria-hidden="true">.</span>
                </div>
                <p class="mt-1 text-caption font-medium uppercase text-text-subtle">CORE · Hub de aplicações</p>
                <h1 id="login-title" class="mt-5 text-heading-2 font-semibold text-text">Entrar no CORE</h1>
                <p class="mt-2 text-body-small text-text-muted">Use sua credencial local autorizada para acessar o Hub.</p>
            </div>

            @if ($errors->any())
                <div class="mb-4 rounded-md border border-danger bg-danger-subtle px-3 py-2 text-body-small text-danger-subtle-foreground" role="alert">
                    As credenciais informadas não puderam ser validadas.
                </div>
            @endif

            <form method="POST" action="{{ route('login.store') }}" class="space-y-4" novalidate>
                @csrf

                <div>
                    <label for="identifier" class="block text-label font-semibold text-text">Identificação</label>
                    <input
                        id="identifier"
                        name="identifier"
                        type="email"
                        value="{{ old('identifier') }}"
                        autocomplete="username"
                        required
                        class="mt-1 block w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text placeholder:text-text-subtle focus:border-border-focus focus:outline-none focus:ring"
                    >
                </div>

                <div>
                    <label for="password" class="block text-label font-semibold text-text">Senha</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="mt-1 block w-full rounded-md border border-border bg-surface px-3 py-2 text-body text-text focus:border-border-focus focus:outline-none focus:ring"
                    >
                </div>

                <button type="submit" class="inline-flex w-full items-center justify-center rounded-md bg-primary px-4 py-2.5 text-label font-semibold text-primary-foreground hover:bg-primary-hover focus-visible:ring">
                    Entrar
                </button>
            </form>
        </section>
    </main>
</x-layouts.core>
