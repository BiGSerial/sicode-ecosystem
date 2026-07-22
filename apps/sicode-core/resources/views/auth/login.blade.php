<x-layouts.core title="Entrar · SICODE CORE">
    <main id="conteudo-principal" class="min-h-screen grid grid-cols-1 lg:grid-cols-12 bg-background">
        <!-- LADO ESQUERDO: Formulário de Autenticação -->
        <div class="lg:col-span-5 flex flex-col justify-between p-6 sm:p-10 lg:p-12 border-r border-border bg-surface shadow-sicode-lg z-10">
            <div>
                <!-- Header / Logo -->
                <div class="flex items-center justify-between">
                    <div class="inline-flex items-baseline gap-1 text-heading-1 font-extrabold text-secondary tracking-tight">
                        <span>SICODE</span><span class="text-success" aria-hidden="true">.</span>
                    </div>
                    <span class="inline-flex items-center rounded-full border border-border bg-surface-muted px-2.5 py-1 text-caption font-semibold text-text-muted">
                        CORE v1.0
                    </span>
                </div>

                <div class="mt-8 sm:mt-12">
                    <p class="text-caption font-bold uppercase tracking-widest text-primary">Plataforma de Identidade</p>
                    <h1 id="login-title" class="mt-1 text-display font-extrabold text-text tracking-tight">Entrar no CORE</h1>
                    <p class="mt-2 text-body text-text-muted">Autentique-se com sua credencial autorizada para acessar o ecossistema.</p>
                </div>

                @if ($errors->any())
                    <div class="mt-6 rounded-md border border-danger bg-danger-subtle p-3.5 text-body-small text-danger-subtle-foreground flex items-start gap-2" role="alert">
                        <span class="font-bold">▲</span>
                        <div>
                            <p class="font-semibold">As credenciais informadas não puderam ser validadas.</p>
                            <p class="text-xs opacity-90">Verifique seu e-mail e senha e tente novamente.</p>
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('login.store') }}" class="mt-6 space-y-5" novalidate>
                    @csrf

                    <div>
                        <label for="identifier" class="block text-label font-semibold text-text">E-mail corporativo</label>
                        <input
                            id="identifier"
                            name="identifier"
                            type="email"
                            value="{{ old('identifier') }}"
                            placeholder="seu.nome@organizacao.com.br"
                            autocomplete="username"
                            required
                            class="mt-1.5 block w-full rounded-md border border-border bg-surface px-3.5 py-2.5 text-body text-text placeholder:text-text-subtle focus:border-border-focus focus:outline-none focus:ring transition-all"
                        >
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <label for="password" class="block text-label font-semibold text-text">Senha de acesso</label>
                        </div>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required
                            class="mt-1.5 block w-full rounded-md border border-border bg-surface px-3.5 py-2.5 text-body text-text focus:border-border-focus focus:outline-none focus:ring transition-all"
                        >
                    </div>

                    <button type="submit" class="w-full inline-flex items-center justify-center rounded-md bg-primary px-4 py-3 text-label font-bold text-primary-foreground hover:bg-primary-hover focus-visible:ring shadow-sicode-sm transition-all cursor-pointer">
                        Entrar no Hub
                    </button>
                </form>
            </div>

            <!-- Footer do lado esquerdo com Seletor de Foto de Fundo (EDP ES / EDP SP) -->
            <div class="mt-8 pt-6 border-t border-border flex flex-wrap items-center justify-between gap-4 text-caption text-text-subtle">
                <div class="flex items-center gap-2">
                    <span class="font-semibold text-text-muted">Vista Regional:</span>
                    <div class="inline-flex rounded-md border border-border bg-surface-muted p-0.5" role="group" aria-label="Seletor de Vista Regional EDP">
                        <button type="button" id="btn-view-es" onclick="switchEdpView('es')" class="rounded px-2.5 py-1 text-caption font-semibold transition-all bg-primary text-primary-foreground shadow-sicode-sm cursor-pointer">
                            EDP ES
                        </button>
                        <button type="button" id="btn-view-sp" onclick="switchEdpView('sp')" class="rounded px-2.5 py-1 text-caption font-semibold transition-all text-text-muted hover:text-text cursor-pointer">
                            EDP SP
                        </button>
                    </div>
                </div>
                <div>
                    <span>&copy; {{ date('Y') }} EDP · SICODE Ecosystem</span>
                </div>
            </div>
        </div>

        <!-- LADO DIREITO: Painel Visual com Foto Configurável EDP ES / EDP SP -->
        <div class="hidden lg:col-span-7 lg:relative lg:flex flex-col justify-between p-12 overflow-hidden bg-secondary">
            <!-- Imagens de Fundo (ES e SP) com transição de opacidade -->
            <div id="bg-edp-es" class="absolute inset-0 bg-cover bg-center transition-opacity duration-700 ease-in-out opacity-100" style="background-image: url('{{ asset('images/edp_es_bg.jpg') }}');">
            </div>
            <div id="bg-edp-sp" class="absolute inset-0 bg-cover bg-center transition-opacity duration-700 ease-in-out opacity-0" style="background-image: url('{{ asset('images/edp_sp_bg.jpg') }}');">
            </div>

            <!-- Overlay Gradiente Escuro elegante -->
            <div class="absolute inset-0 bg-gradient-to-t from-secondary/95 via-secondary/70 to-secondary/40 backdrop-blur-[2px]"></div>

            <!-- Conteúdo sobreposto na imagem -->
            <div class="relative z-10 flex items-center justify-between">
                <div class="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 backdrop-blur-md px-3.5 py-1.5 text-caption font-semibold text-white">
                    <span id="view-badge-indicator" class="h-2 w-2 rounded-full bg-accent animate-pulse"></span>
                    <span id="view-badge-text">Espírito Santo · Terceira Ponte & Vitória</span>
                </div>
            </div>

            <div class="relative z-10 max-w-xl">
                <blockquote class="space-y-3">
                    <p class="text-heading-1 font-extrabold text-white leading-tight">
                        Conectando energia, inovação e eficiência operacional.
                    </p>
                    <footer class="text-body text-white/80 font-medium">
                        SICODE CORE · Autoridade Canônica de Identidade & Governança Institucional.
                    </footer>
                </blockquote>

                <!-- Cards com dados em destaque -->
                <div class="mt-8 grid grid-cols-3 gap-3">
                    <div class="rounded-lg border border-white/15 bg-white/10 backdrop-blur-md p-3.5 text-white">
                        <p class="text-caption text-white/70 font-semibold uppercase">Instâncias</p>
                        <p class="mt-1 text-heading-3 font-bold">ES & SP</p>
                    </div>
                    <div class="rounded-lg border border-white/15 bg-white/10 backdrop-blur-md p-3.5 text-white">
                        <p class="text-caption text-white/70 font-semibold uppercase">Segurança</p>
                        <p class="mt-1 text-heading-3 font-bold">Core Auth</p>
                    </div>
                    <div class="rounded-lg border border-white/15 bg-white/10 backdrop-blur-md p-3.5 text-white">
                        <p class="text-caption text-white/70 font-semibold uppercase">Arquitetura</p>
                        <p class="mt-1 text-heading-3 font-bold">Canônica</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        function switchEdpView(unit) {
            const bgEs = document.getElementById('bg-edp-es');
            const bgSp = document.getElementById('bg-edp-sp');
            const btnEs = document.getElementById('btn-view-es');
            const btnSp = document.getElementById('btn-view-sp');
            const badgeText = document.getElementById('view-badge-text');

            if (unit === 'es') {
                bgEs.classList.remove('opacity-0');
                bgEs.classList.add('opacity-100');
                bgSp.classList.remove('opacity-100');
                bgSp.classList.add('opacity-0');

                btnEs.className = "rounded px-2.5 py-1 text-caption font-semibold transition-all bg-primary text-primary-foreground shadow-sicode-sm cursor-pointer";
                btnSp.className = "rounded px-2.5 py-1 text-caption font-semibold transition-all text-text-muted hover:text-text cursor-pointer";

                badgeText.textContent = "Espírito Santo · Terceira Ponte & Vitória";
                localStorage.setItem('sicode-login-view', 'es');
            } else {
                bgSp.classList.remove('opacity-0');
                bgSp.classList.add('opacity-100');
                bgEs.classList.remove('opacity-100');
                bgEs.classList.add('opacity-0');

                btnSp.className = "rounded px-2.5 py-1 text-caption font-semibold transition-all bg-primary text-primary-foreground shadow-sicode-sm cursor-pointer";
                btnEs.className = "rounded px-2.5 py-1 text-caption font-semibold transition-all text-text-muted hover:text-text cursor-pointer";

                badgeText.textContent = "São Paulo · Ponte Estaiada & Capital";
                localStorage.setItem('sicode-login-view', 'sp');
            }
        }

        // Restaura preferencia salva no localStorage
        document.addEventListener('DOMContentLoaded', () => {
            const savedView = localStorage.getItem('sicode-login-view') || 'es';
            switchEdpView(savedView);
        });
    </script>
</x-layouts.core>
