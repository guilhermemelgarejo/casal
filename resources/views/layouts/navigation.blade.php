<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom" style="--bs-navbar-padding-y: 0; --bs-navbar-brand-padding-y: 0;">
    <div class="container-xxl">
        <a class="navbar-brand py-0" href="{{ route('dashboard') }}">
            <img
                src="{{ asset('images/duozen-logo.png') }}"
                alt="{{ config('app.name', 'DuoZen') }}"
                class="d-block"
                style="height: 4rem; width: auto; max-height: 100%;"
            />
        </a>

        <button
            class="navbar-toggler"
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
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-lg-1">
                <li class="nav-item">
                    <x-nav-link class="px-2" :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        Painel
                    </x-nav-link>
                </li>
                <li class="nav-item">
                    <x-nav-link class="px-2" :href="route('transactions.index')" :active="request()->routeIs('transactions.*')">
                        Lançamentos
                    </x-nav-link>
                </li>
                <li class="nav-item">
                    <x-nav-link class="px-2" :href="route('categories.index')" :active="request()->routeIs('categories.*')">
                        Categorias
                    </x-nav-link>
                </li>
                <li class="nav-item">
                    <x-nav-link class="px-2" :href="route('accounts.index')" :active="request()->routeIs('accounts.*')">
                        Contas
                    </x-nav-link>
                </li>
                <li class="nav-item">
                    <x-nav-link class="px-2" :href="route('budgets.index')" :active="request()->routeIs('budgets.*')">
                        Orçamentos
                    </x-nav-link>
                </li>
                <li class="nav-item">
                    <x-nav-link class="px-2" :href="route('couple.index')" :active="request()->routeIs('couple.*')">
                        Casal
                    </x-nav-link>
                </li>
                @if(Auth::user()->couple_id)
                    <li class="nav-item">
                        <x-nav-link class="px-2" :href="route('billing.index')" :active="request()->routeIs('billing.*')">
                            Assinatura
                        </x-nav-link>
                    </li>
                @endif
                @if(Auth::user()->isCasalAdmin())
                    <li class="nav-item">
                        <x-nav-link class="px-2" :href="route('admin.subscriptions.index')" :active="request()->routeIs('admin.*')">
                            Admin
                        </x-nav-link>
                    </li>
                @endif
            </ul>

            <div class="d-none d-lg-block">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            type="button"
                            class="btn btn-light dropdown-toggle"
                            data-bs-toggle="dropdown"
                            aria-expanded="false"
                        >
                            {{ Auth::user()->name }}
                        </button>
                    </x-slot>

                    <x-slot name="content">
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
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}" class="d-inline w-100">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    Sair
                                </button>
                            </form>
                        </li>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="d-lg-none border-top mt-2 pt-2 w-100">
                <div class="px-2 py-1 small text-secondary">{{ Auth::user()->name }}</div>
                <div class="px-2 pb-2 small text-muted">{{ Auth::user()->email }}</div>
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
                    <button type="submit" class="nav-link btn btn-link text-start w-100 py-2 text-danger">
                        Sair
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
