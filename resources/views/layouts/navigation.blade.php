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
                    <x-nav-link :href="route('transactions.index')" :active="request()->routeIs('transactions.*')">
                        Lançamentos
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
                    <x-nav-link :href="route('credit-card-statements.index')" :active="request()->routeIs('credit-card-statements.*')">
                        Faturas
                    </x-nav-link>
                </li>
                <li class="nav-item">
                    <x-nav-link :href="route('budgets.index')" :active="request()->routeIs('budgets.*')">
                        Orçamentos
                    </x-nav-link>
                </li>
                <li class="nav-item">
                    <x-nav-link :href="route('couple.index')" :active="request()->routeIs('couple.*')">
                        Casal
                    </x-nav-link>
                </li>
                @if(Auth::user()->couple_id)
                    <li class="nav-item">
                        <x-nav-link :href="route('billing.index')" :active="request()->routeIs('billing.*')">
                            Assinatura
                        </x-nav-link>
                    </li>
                @endif
                @if(Auth::user()->isCasalAdmin())
                    <li class="nav-item">
                        <x-nav-link :href="route('admin.subscriptions.index')" :active="request()->routeIs('admin.*')">
                            Admin
                        </x-nav-link>
                    </li>
                @endif
            </ul>

            <div class="d-none d-lg-flex align-items-center ms-lg-2">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            type="button"
                            class="btn app-navbar-user-btn dropdown-toggle d-flex align-items-center gap-2"
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
                                <button type="submit" class="dropdown-item text-danger rounded-2">
                                    Sair
                                </button>
                            </form>
                        </li>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="d-lg-none app-navbar-mobile border-top mt-2 pt-3 pb-1 w-100">
                <div class="d-flex align-items-center gap-2 px-2 mb-2">
                    <span class="app-navbar-user-avatar app-navbar-user-avatar--sm" aria-hidden="true">{{ \Illuminate\Support\Str::substr(Auth::user()->name, 0, 1) }}</span>
                    <div class="min-w-0">
                        <div class="small fw-semibold text-truncate">{{ Auth::user()->name }}</div>
                        <div class="small text-secondary text-truncate">{{ Auth::user()->email }}</div>
                    </div>
                </div>
                <x-responsive-nav-link :href="route('profile.edit')">
                    Perfil
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
                    <button type="submit" class="nav-link btn btn-link text-start w-100 py-2 px-3 rounded-3 text-danger text-decoration-none">
                        Sair
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
