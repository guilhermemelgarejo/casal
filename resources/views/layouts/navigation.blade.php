<nav class="navbar navbar-expand-lg navbar-light app-navbar border-bottom sticky-top">
    <div class="container-xxl px-3 px-lg-4">
        <a class="navbar-brand app-navbar-brand py-2 me-lg-4" href="{{ route('dashboard') }}">
            <img
                src="{{ asset('images/duozen-logo.png') }}"
                alt="{{ config('app.name', 'DuoZen') }}"
                class="d-block app-navbar-logo"
            />
        </a>

        <button
            class="navbar-toggler app-navbar-toggler border-0 shadow-sm"
            type="button"
            title="Abrir ou fechar o menu em telas pequenas"
            data-bs-toggle="collapse"
            data-bs-target="#mainNavbar"
            aria-controls="mainNavbar"
            aria-expanded="false"
            aria-label="Alternar navegação"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-3 mb-lg-0 py-lg-1 align-items-lg-center gap-lg-1">
                <li class="nav-item">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        Painel
                    </x-nav-link>
                </li>
                <li class="nav-item">
                    <x-nav-link :href="route('reports.index')" :active="request()->routeIs('reports.*')">
                        Relatórios
                    </x-nav-link>
                </li>
                <li class="nav-item">
                    <x-nav-link :href="route('recurring-transactions.index')" :active="request()->routeIs('recurring-transactions.*')">
                        Recorrentes
                    </x-nav-link>
                </li>
                <li class="nav-item">
                    <x-nav-link :href="route('categories.index')" :active="request()->routeIs('categories.*')">
                        Categorias
                    </x-nav-link>
                </li>
                <li class="nav-item">
                    <x-nav-link :href="route('accounts.index')" :active="request()->routeIs('accounts.*')">
                        Contas
                    </x-nav-link>
                </li>
                <li class="nav-item">
                    <x-nav-link :href="route('cofrinhos.index')" :active="request()->routeIs('cofrinhos.*')">
                        Cofrinhos
                    </x-nav-link>
                </li>
                <li class="nav-item">
                    <x-nav-link :href="route('credit-card-statements.index')" :active="request()->routeIs('credit-card-statements.*')">
                        Faturas
                    </x-nav-link>
                </li>
                <li class="nav-item">
                    <x-nav-link :href="route('couple.index')" :active="request()->routeIs('couple.*')">
                        Casal
                    </x-nav-link>
                </li>
                <li class="nav-item">
                    <x-nav-link :href="route('contact.show')" :active="request()->routeIs('contact.*')">
                        Contato
                    </x-nav-link>
                </li>
            </ul>

            <div class="d-none d-lg-flex align-items-center ms-lg-2 gap-2">
                <button
                    type="button"
                    class="btn app-navbar-privacy-toggle rounded-circle p-2 d-inline-flex align-items-center justify-content-center text-secondary border-0"
                    id="duozen-privacy-toggle"
                    aria-label="Ocultar ou exibir valores"
                    data-bs-toggle="tooltip"
                    data-bs-placement="bottom"
                    title="Ocultar valores"
                >
                    <svg class="privacy-icon-visible" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                    </svg>
                    <svg class="privacy-icon-hidden d-none" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                    </svg>
                </button>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            type="button"
                            class="btn app-navbar-user-btn dropdown-toggle d-flex align-items-center gap-2"
                            title="Menu da conta: perfil, assinatura e sair"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >
                            <span class="app-navbar-user-avatar" aria-hidden="true">{{ \Illuminate\Support\Str::substr(Auth::user()->name, 0, 1) }}</span>
                            <span class="text-truncate" style="max-width: 10rem;">{{ Auth::user()->name }}</span>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <li class="px-3 py-2 small text-secondary border-bottom d-none d-xl-block">
                            {{ Auth::user()->email }}
                        </li>
                        <x-dropdown-link :href="route('profile.edit')">
                            Perfil
                        </x-dropdown-link>
                        @if(Auth::user()->couple_id)
                            <x-dropdown-link :href="route('billing.index')">
                                Assinatura
                            </x-dropdown-link>
                        @endif
                        @if(Auth::user()->isCasalAdmin())
                            <x-dropdown-link :href="route('admin.subscriptions.index')">
                                Assinaturas (admin)
                            </x-dropdown-link>
                        @endif
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}" class="d-inline w-100">
                                @csrf
                                <button type="submit" class="dropdown-item text-danger rounded-2" title="Terminar sessão">
                                    Sair
                                </button>
                            </form>
                        </li>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="d-lg-none app-navbar-mobile border-top mt-2 pt-3 pb-1 w-100">
                <div class="d-flex align-items-center justify-content-between px-2 mb-2">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                        <span class="app-navbar-user-avatar app-navbar-user-avatar--sm" aria-hidden="true">{{ \Illuminate\Support\Str::substr(Auth::user()->name, 0, 1) }}</span>
                        <div class="min-w-0">
                            <div class="small fw-semibold text-truncate">{{ Auth::user()->name }}</div>
                            <div class="small text-secondary text-truncate">{{ Auth::user()->email }}</div>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="btn app-navbar-privacy-toggle app-navbar-privacy-toggle--mobile btn-outline-secondary btn-sm rounded-pill d-inline-flex align-items-center gap-1 px-3 py-1 flex-shrink-0"
                        id="duozen-privacy-toggle-mobile"
                        aria-label="Ocultar ou exibir valores"
                    >
                        <svg class="privacy-icon-visible" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <svg class="privacy-icon-hidden d-none" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                        </svg>
                        <span class="privacy-toggle-label small">Ocultar</span>
                    </button>
                </div>
                <x-responsive-nav-link :href="route('profile.edit')">
                    Perfil
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('contact.show')">
                    Contato
                </x-responsive-nav-link>
                @if(Auth::user()->couple_id)
                    <x-responsive-nav-link :href="route('billing.index')">
                        Assinatura
                    </x-responsive-nav-link>
                @endif
                @if(Auth::user()->isCasalAdmin())
                    <x-responsive-nav-link :href="route('admin.subscriptions.index')">
                        Admin — assinaturas
                    </x-responsive-nav-link>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-link btn btn-link text-start w-100 py-2 px-3 rounded-3 text-danger text-decoration-none" title="Terminar sessão">
                        Sair
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
