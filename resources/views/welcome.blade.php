@php
    $appName = config('app.name', 'DuoZen');
    $marketing = config('duozen.marketing', []);
    $tagline = $marketing['tagline'] ?? 'Menos briga por dinheiro. Mais planos juntos.';
    $trialDays = (int) config('duozen.trial_days', 14);
    $priceAmount = $marketing['price_amount'] ?? null;
    $priceLabel = $priceAmount !== null ? number_format((float) $priceAmount, 2, ',', '.') : null;
    $socialLinks = $marketing['social'] ?? [];
    $socialHandle = $marketing['social_handle'] ?? null;
    $pageTitle = $appName.' — Finanças a dois, em paz';
    $pageDescription = 'Organizem o dinheiro do casal num só painel: contas, cartões, faturas, orçamento e metas. Saibam quanto podem gastar por dia. '.$trialDays.' dias grátis.';
    $ogImage = asset('images/social/duozen-og.png');
    $canRegister = Route::has('register');
    $registerUrl = $canRegister ? route('register') : route('login');
    $shareText = 'Achei o '.$appName.', um app para organizarmos nosso dinheiro juntos: '.url('/');
    $structuredData = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => $appName,
        'applicationCategory' => 'FinanceApplication',
        'operatingSystem' => 'Web',
        'inLanguage' => 'pt-BR',
        'description' => $pageDescription,
        'url' => url('/'),
        'image' => $ogImage,
        'offers' => $priceAmount !== null ? [
            '@type' => 'Offer',
            'price' => number_format((float) $priceAmount, 2, '.', ''),
            'priceCurrency' => 'BRL',
        ] : null,
        'sameAs' => $socialLinks !== [] ? array_values($socialLinks) : null,
    ]);
    $check = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $pageTitle }}</title>
        <meta name="description" content="{{ $pageDescription }}">
        <meta name="theme-color" content="#7C3AED">
        <link rel="canonical" href="{{ url('/') }}">

        <meta property="og:type" content="website">
        <meta property="og:locale" content="pt_BR">
        <meta property="og:site_name" content="{{ $appName }}">
        <meta property="og:title" content="{{ $appName }} — {{ $tagline }}">
        <meta property="og:description" content="{{ $pageDescription }}">
        <meta property="og:url" content="{{ url('/') }}">
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ $appName }}: {{ $tagline }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $appName }} — {{ $tagline }}">
        <meta name="twitter:description" content="{{ $pageDescription }}">
        <meta name="twitter:image" content="{{ $ogImage }}">
        <script type="application/ld+json">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
        @include('layouts.partials.assets')
        @php $landingCss = public_path('css/landing.css'); @endphp
        <link href="{{ asset('css/landing.css') }}?v={{ file_exists($landingCss) ? filemtime($landingCss) : 1 }}" rel="stylesheet">
    </head>
    <body class="lp">
        <header class="lp-nav">
            <div class="container lp-nav__inner">
                <a href="{{ url('/') }}" class="d-inline-flex align-items-center" aria-label="{{ $appName }}, página inicial">
                    <img src="{{ asset('images/duozen-logo.png') }}" alt="{{ $appName }}" class="lp-nav__logo">
                </a>
                <nav class="lp-nav__links" aria-label="Seções da página">
                    <a href="#recursos">Recursos</a>
                    <a href="#como-funciona">Como funciona</a>
                    <a href="#plano">Plano</a>
                    <a href="#duvidas">Dúvidas</a>
                </nav>
                <div class="lp-nav__actions">
                    @auth
                        <a class="lp-btn lp-btn--primary lp-btn--sm" href="{{ url('/dashboard') }}">Ir ao painel</a>
                    @else
                        <a class="lp-nav__login" href="{{ route('login') }}">Entrar</a>
                        @if ($canRegister)
                            <a class="lp-btn lp-btn--primary lp-btn--sm" href="{{ $registerUrl }}">Começar grátis</a>
                        @endif
                    @endauth
                </div>
            </div>
        </header>

        <main>
            <section class="lp-hero">
                <div class="container">
                    <div class="row align-items-center g-5">
                        <div class="col-lg-6">
                            <span class="lp-kicker"><span class="lp-kicker__dot" aria-hidden="true"></span>Finanças a dois</span>
                            <h1 class="lp-hero__title">
                                Menos briga por dinheiro.
                                <span class="lp-gradient-text">Mais planos juntos.</span>
                            </h1>
                            <p class="lp-hero__lead">
                                O {{ $appName }} reúne contas, cartões, faturas, orçamento e metas do casal num só painel.
                                Vocês sabem quanto podem gastar hoje e quanto falta para o próximo sonho.
                            </p>
                            <div class="lp-hero__ctas">
                                @auth
                                    <a href="{{ url('/dashboard') }}" class="lp-btn lp-btn--primary">Abrir o painel</a>
                                @else
                                    <a href="{{ $registerUrl }}" class="lp-btn lp-btn--primary">Testar {{ $trialDays }} dias grátis</a>
                                    <a href="#como-funciona" class="lp-btn lp-btn--ghost">Ver como funciona</a>
                                @endauth
                            </div>
                            <ul class="lp-trust">
                                <li>{!! $check !!} Um plano para os dois</li>
                                <li>{!! $check !!} Sem senha de banco</li>
                                <li>{!! $check !!} Cancele quando quiser</li>
                            </ul>
                        </div>
                        <div class="col-lg-6">
                            <div class="lp-mock" aria-hidden="true">
                                <div class="lp-mock__window">
                                    <div class="lp-mock__bar">
                                        <span>Painel do casal · Novembro</span>
                                        <span class="lp-mock__avatars">
                                            <span class="lp-mock__avatar lp-mock__avatar--a">A</span>
                                            <span class="lp-mock__avatar lp-mock__avatar--b">B</span>
                                        </span>
                                    </div>
                                    <div class="lp-mock__body">
                                        <div class="lp-mock__highlight">
                                            <small>Vocês podem gastar hoje</small>
                                            <strong>R$ 142,00</strong>
                                            <small>sem comprometer as contas do mês</small>
                                        </div>
                                        <div class="lp-mock__stats">
                                            <div class="lp-mock__stat">Receitas <b>R$ 12.400</b></div>
                                            <div class="lp-mock__stat">Despesas <b>R$ 8.150</b></div>
                                        </div>
                                        <div class="lp-mock__row">
                                            <span class="lp-mock__row-icon lp-mock__row-icon--card">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                                            </span>
                                            <span class="lp-mock__row-main"><b>Fatura do cartão</b><span>Vence em 3 dias</span></span>
                                            <span class="lp-mock__value">R$ 2.318,40</span>
                                        </div>
                                        <div class="lp-mock__row">
                                            <span class="lp-mock__row-icon lp-mock__row-icon--goal">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/></svg>
                                            </span>
                                            <span class="lp-mock__row-main"><b>Viagem de aniversário</b><span class="lp-mock__progress"><i></i></span></span>
                                            <span class="lp-mock__value">62%</span>
                                        </div>
                                    </div>
                                </div>
                                <p class="lp-mock__caption">Exemplo ilustrativo do painel</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="lp-section lp-section--white" aria-labelledby="lp-questions-title">
                <div class="container">
                    <div class="lp-section__head">
                        <span class="lp-section__eyebrow">Soa familiar?</span>
                        <h2 id="lp-questions-title" class="lp-section__title">As perguntas que todo casal faz no fim do mês</h2>
                        <p class="lp-section__lead">Quando os números ficam visíveis para os dois, a conversa deixa de ser sobre culpa e passa a ser sobre planos.</p>
                    </div>
                    <div class="lp-questions">
                        <div class="lp-question">
                            <q>Quem pagou a luz?</q>
                            <p>Cada lançamento mostra quem registrou, em qual conta e em qual categoria.</p>
                        </div>
                        <div class="lp-question">
                            <q>Quanto ainda falta da fatura?</q>
                            <p>Faturas por cartão, parcelas, pagamentos parciais e limite disponível.</p>
                        </div>
                        <div class="lp-question">
                            <q>Dá para pedir delivery hoje?</q>
                            <p>O orçamento diário mostra quanto sobra por dia depois das contas fixas.</p>
                        </div>
                        <div class="lp-question">
                            <q>Quando vamos conseguir viajar?</q>
                            <p>Cofrinhos com meta, aportes e progresso para cada sonho do casal.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="recursos" class="lp-section" aria-labelledby="lp-features-title">
                <div class="container">
                    <div class="lp-section__head">
                        <span class="lp-section__eyebrow">Recursos</span>
                        <h2 id="lp-features-title" class="lp-section__title">Tudo o que a vida a dois movimenta, num só lugar</h2>
                        <p class="lp-section__lead">Feito para o jeito brasileiro de lidar com dinheiro: Pix, cartão parcelado, boleto e fatura.</p>
                    </div>
                    <div class="lp-features">
                        <article class="lp-feature">
                            <div class="lp-feature__icon lp-feature__icon--purple">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/></svg>
                            </div>
                            <h3>Cartões e faturas sem susto</h3>
                            <p>Compras parceladas em até 12 vezes, fatura por mês, pagamento parcial e limite disponível sempre atualizado.</p>
                        </article>
                        <article class="lp-feature">
                            <div class="lp-feature__icon lp-feature__icon--green">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 2v20M17 6.5c0-1.9-2.2-3-5-3s-5 1.1-5 3 2 2.6 5 3.3 5 1.6 5 3.6-2.2 3.1-5 3.1-5-1.2-5-3.1"/></svg>
                            </div>
                            <h3>Orçamento diário</h3>
                            <p>Renda prevista menos faturas, contas fixas e parcelas de dívidas: o resultado é quanto vocês podem gastar por dia.</p>
                        </article>
                        <article class="lp-feature">
                            <div class="lp-feature__icon lp-feature__icon--purple">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/></svg>
                            </div>
                            <h3>Metas por categoria</h3>
                            <p>Definam limites para mercado, lazer ou transporte e acompanhem o gasto real de cada categoria ao longo do mês.</p>
                        </article>
                        <article class="lp-feature">
                            <div class="lp-feature__icon lp-feature__icon--green">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                            </div>
                            <h3>Lembretes de contas fixas</h3>
                            <p>Aluguel, internet e streaming viram lembretes. Um toque e o lançamento já vem preenchido.</p>
                        </article>
                        <article class="lp-feature">
                            <div class="lp-feature__icon lp-feature__icon--purple">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/></svg>
                            </div>
                            <h3>Cofrinhos e investimentos</h3>
                            <p>Reserva de emergência, viagem ou casa própria. Acompanhem aportes, rendimentos e até ativos com cotação.</p>
                        </article>
                        <article class="lp-feature">
                            <div class="lp-feature__icon lp-feature__icon--green">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10"/></svg>
                            </div>
                            <h3>Dívidas sob controle</h3>
                            <p>Financiamentos e empréstimos com parcelas, amortizações e saldo devedor sempre à vista.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section id="como-funciona" class="lp-section lp-section--white" aria-labelledby="lp-steps-title">
                <div class="container">
                    <div class="lp-section__head">
                        <span class="lp-section__eyebrow">Como funciona</span>
                        <h2 id="lp-steps-title" class="lp-section__title">Em poucos minutos, os dois na mesma página</h2>
                    </div>
                    <ol class="lp-steps">
                        <li class="lp-step">
                            <h3>Criem o espaço do casal</h3>
                            <p>Um de vocês cria a conta e o espaço compartilhado. As categorias principais já vêm prontas.</p>
                        </li>
                        <li class="lp-step">
                            <h3>Convidem o par</h3>
                            <p>Mandem o convite por e-mail ou compartilhem o código. Cada um entra com o próprio login.</p>
                        </li>
                        <li class="lp-step">
                            <h3>Lancem e acompanhem</h3>
                            <p>Cadastrem contas e cartões, registrem os gastos e vejam o mês ganhar forma no painel.</p>
                        </li>
                    </ol>
                </div>
            </section>

            <section id="plano" class="lp-section" aria-labelledby="lp-plan-title">
                <div class="container">
                    <div class="lp-section__head">
                        <span class="lp-section__eyebrow">Plano</span>
                        <h2 id="lp-plan-title" class="lp-section__title">Um plano. Duas pessoas. Zero surpresa.</h2>
                        <p class="lp-section__lead">Experimentem com calma e decidam juntos se continuam.</p>
                    </div>
                    <div class="lp-plan">
                        <div class="lp-plan__head">
                            <h3>{{ $appName }} para o casal</h3>
                            <span>Acesso completo para os dois</span>
                            <div class="lp-plan__price">
                                @if ($priceLabel !== null)
                                    <strong>R$ {{ $priceLabel }}</strong><span>/mês por casal</span>
                                @else
                                    <strong>{{ $trialDays }} dias</strong><span>grátis para testar</span>
                                @endif
                            </div>
                        </div>
                        <div class="lp-plan__body">
                            <ul class="lp-plan__list">
                                <li>{!! $check !!} {{ $trialDays }} dias de teste sem cobrança</li>
                                <li>{!! $check !!} Uma assinatura libera o acesso dos dois</li>
                                <li>{!! $check !!} Todos os recursos: cartões, orçamento, cofrinhos, dívidas e relatórios</li>
                                <li>{!! $check !!} Cancelamento a qualquer momento, sem multa</li>
                            </ul>
                            @auth
                                <a href="{{ url('/dashboard') }}" class="lp-btn lp-btn--primary w-100">Abrir o painel</a>
                            @else
                                <a href="{{ $registerUrl }}" class="lp-btn lp-btn--primary w-100">Começar o teste grátis</a>
                            @endauth
                            <p class="lp-plan__note">
                                O cartão é pedido no pagamento seguro do Stripe para ativar o teste. A cobrança mensal só começa depois de {{ $trialDays }} dias, e vocês podem cancelar antes.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="duvidas" class="lp-section lp-section--white" aria-labelledby="lp-faq-title">
                <div class="container">
                    <div class="lp-section__head">
                        <span class="lp-section__eyebrow">Dúvidas</span>
                        <h2 id="lp-faq-title" class="lp-section__title">Perguntas frequentes</h2>
                    </div>
                    <div class="lp-faq">
                        <details>
                            <summary>Preciso conectar minha conta do banco?</summary>
                            <p>Não. O {{ $appName }} não pede senha nem acesso ao seu banco. Vocês registram os lançamentos e decidem o que entra no painel.</p>
                        </details>
                        <details>
                            <summary>Cada um precisa pagar uma assinatura?</summary>
                            <p>Não. Basta um de vocês ativar o plano para os dois terem acesso completo ao mesmo espaço.</p>
                        </details>
                        <details>
                            <summary>Por que pedem cartão no teste grátis?</summary>
                            <p>O cartão ativa o período de teste no Stripe, que processa o pagamento com segurança. Nada é cobrado durante os {{ $trialDays }} dias, e o cancelamento pode ser feito antes do fim do teste.</p>
                        </details>
                        <details>
                            <summary>Tem aplicativo para celular?</summary>
                            <p>O {{ $appName }} funciona no navegador do celular e do computador, com a mesma conta e os mesmos dados.</p>
                        </details>
                        <details>
                            <summary>Os dados de outros casais ficam misturados com os nossos?</summary>
                            <p>Não. Cada casal tem um espaço próprio, e só os dois membros acessam as informações dele.</p>
                        </details>
                        <details>
                            <summary>Ainda tenho dúvidas. Com quem falo?</summary>
                            <p>Escreva para a gente pela <a href="{{ route('contact.show') }}">página de contato</a>. Respondemos por e-mail.</p>
                        </details>
                    </div>
                </div>
            </section>

            <section class="lp-cta">
                <div class="container">
                    <h2>Dinheiro organizado é mais tempo para vocês dois.</h2>
                    <p>Criem o espaço do casal hoje e terminem o mês sabendo exatamente para onde o dinheiro foi.</p>
                    <div class="lp-cta__actions">
                        @auth
                            <a href="{{ url('/dashboard') }}" class="lp-btn lp-btn--light">Abrir o painel</a>
                        @else
                            <a href="{{ $registerUrl }}" class="lp-btn lp-btn--light">Testar {{ $trialDays }} dias grátis</a>
                        @endauth
                        <a href="https://wa.me/?text={{ rawurlencode($shareText) }}" class="lp-btn lp-btn--whatsapp" target="_blank" rel="noopener">Mandar para meu par no WhatsApp</a>
                    </div>
                </div>
            </section>
        </main>

        <footer class="lp-footer">
            <div class="container lp-footer__inner">
                <div class="lp-footer__brand">
                    <img src="{{ asset('images/duozen-logo.png') }}" alt="{{ $appName }}">
                    <p>{{ $tagline }}</p>
                </div>
                <div class="lp-footer__links">
                    <a href="#recursos">Recursos</a>
                    <a href="#plano">Plano</a>
                    <a href="{{ route('contact.show') }}">Contato</a>
                    <a href="{{ route('legal.terms') }}">Termos de uso</a>
                    <a href="{{ route('legal.privacy') }}">Privacidade</a>
                    @guest
                        <a href="{{ route('login') }}">Entrar</a>
                    @endguest
                </div>
                @if ($socialLinks !== [])
                    <nav class="lp-social" aria-label="{{ $appName }} nas redes sociais{{ $socialHandle ? ' ('.$socialHandle.')' : '' }}">
                        @foreach ($socialLinks as $network => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener me">{{ $network }}</a>
                        @endforeach
                    </nav>
                @endif
                <small class="w-100 text-center text-secondary">&copy; {{ date('Y') }} {{ $appName }}@if ($socialHandle) · {{ $socialHandle }}@endif</small>
            </div>
        </footer>
        @include('layouts.partials.scripts')
    </body>
</html>
