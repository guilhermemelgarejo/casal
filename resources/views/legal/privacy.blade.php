@php
    $appName = config('app.name', 'DuoZen');
    $updatedAt = \Carbon\Carbon::parse(config('duozen.legal.terms_updated_at') ?? now())->format('d/m/Y');
@endphp
@extends('legal.layout')

@section('title', 'Política de Privacidade')
@section('description', 'Como o '.$appName.' coleta, usa, compartilha e protege dados pessoais, conforme a LGPD.')

@section('content')
<header class="lp-legal__head">
    <span class="lp-section__eyebrow">Documento legal</span>
    <h1>Política de Privacidade</h1>
    <p>Última atualização: {{ $updatedAt }}</p>
</header>

<nav class="lp-legal__toc" aria-label="Índice">
    <strong>Nesta página</strong>
    <ol>
        <li><a href="#controlador">Quem cuida dos seus dados</a></li>
        <li><a href="#coleta">Dados que tratamos</a></li>
        <li><a href="#finalidades">Para que usamos</a></li>
        <li><a href="#compartilhamento">Com quem compartilhamos</a></li>
        <li><a href="#internacional">Transferência internacional</a></li>
        <li><a href="#retencao">Por quanto tempo guardamos</a></li>
        <li><a href="#cookies">Cookies e armazenamento local</a></li>
        <li><a href="#seguranca">Segurança</a></li>
        <li><a href="#direitos">Seus direitos</a></li>
        <li><a href="#menores">Menores de idade</a></li>
        <li><a href="#alteracoes">Alterações</a></li>
        <li><a href="#contato">Contato</a></li>
    </ol>
</nav>

<p>Esta Política explica como o {{ $appName }} trata dados pessoais, conforme a Lei Geral de Proteção de Dados (Lei nº 13.709/2018, “LGPD”). Ela complementa os <a href="{{ route('legal.terms') }}">Termos e Condições de Uso</a>.</p>

<section id="controlador">
    <h2>1. Quem cuida dos seus dados</h2>
    <p>O controlador dos dados é {!! $providerName !!}, inscrito(a) sob o nº {!! $providerDocument !!}, com endereço em {!! $providerAddress !!}. Pedidos e dúvidas sobre dados pessoais podem ser enviados para {!! $contactEmail !!}, que é o canal de atendimento ao titular.</p>
</section>

<section id="coleta">
    <h2>2. Dados que tratamos</h2>
    <ul>
        <li><strong>Cadastro:</strong> nome, e-mail e senha. A senha é guardada de forma criptografada (hash) e ninguém da equipe consegue vê-la.</li>
        <li><strong>Dados financeiros que vocês lançam:</strong> contas, cartões, lançamentos, descrições, valores, categorias, orçamentos, metas, investimentos e dívidas. Evite escrever nas descrições dados pessoais de outras pessoas.</li>
        <li><strong>Espaço do casal:</strong> nome do casal, renda informada, membros e quem registrou cada lançamento.</li>
        <li><strong>Convites:</strong> o e-mail de quem vocês convidam, usado apenas para enviar o convite.</li>
        <li><strong>Pagamento:</strong> os dados do cartão são informados diretamente à Stripe. Recebemos apenas o identificador do cliente, a bandeira, os últimos quatro dígitos e a situação da assinatura.</li>
        <li><strong>Registros técnicos:</strong> endereço IP, data e hora de acesso, dados da sessão e do navegador.</li>
        <li><strong>Atendimento:</strong> nome, e-mail e mensagem enviados pela página de contato.</li>
    </ul>
    <p>Não coletamos dados bancários de acesso, não nos conectamos às suas contas e não tratamos dados sensíveis de forma intencional.</p>
</section>

<section id="finalidades">
    <h2>3. Para que usamos e com qual base legal</h2>
    <ul>
        <li><strong>Prestar o serviço contratado</strong> (criar a conta, manter o espaço do casal, calcular saldos, faturas e relatórios): execução de contrato (art. 7º, V).</li>
        <li><strong>Cobrar a assinatura</strong> e emitir comunicações sobre ela: execução de contrato (art. 7º, V).</li>
        <li><strong>Guardar registros de acesso</strong> e cumprir obrigações fiscais e regulatórias: obrigação legal (art. 7º, II).</li>
        <li><strong>Proteger a conta e o serviço</strong> contra fraudes e acessos indevidos, e melhorar o produto com dados de uso: legítimo interesse (art. 7º, IX).</li>
        <li><strong>Enviar o convite</strong> ao e-mail indicado por um usuário: legítimo interesse de quem convida (art. 7º, IX).</li>
        <li><strong>Responder a contatos</strong> e se defender em processos: exercício regular de direitos (art. 7º, VI).</li>
    </ul>
    <p>Não vendemos dados pessoais e não os usamos para publicidade de terceiros.</p>
</section>

<section id="compartilhamento">
    <h2>4. Com quem compartilhamos</h2>
    <ul>
        <li><strong>O outro membro do casal</strong> vê todos os dados do espaço compartilhado, como explicado nos Termos.</li>
        <li><strong>Stripe</strong>, para processar pagamentos e assinaturas.</li>
        <li><strong>Empresa de hospedagem</strong>, onde o aplicativo e o banco de dados funcionam.</li>
        <li><strong>Provedor de e-mail</strong>, para enviar convites, recuperação de senha e avisos.</li>
        <li><strong>Google</strong> (Google Drive), onde ficam cópias de segurança criptografadas do banco de dados.</li>
        <li><strong>Bunny Fonts</strong>, que entrega a fonte do site e recebe o endereço IP do navegador ao carregá-la.</li>
        <li><strong>Autoridades</strong>, quando houver ordem judicial ou obrigação legal.</li>
    </ul>
    <p>Os serviços de cotação de ativos consultados pelo aplicativo não recebem dados pessoais.</p>
</section>

<section id="internacional">
    <h2>5. Transferência internacional</h2>
    <p>Stripe, Google e outros fornecedores podem armazenar dados fora do Brasil, como nos Estados Unidos. Essas transferências seguem o art. 33 da LGPD, com base em cláusulas contratuais e garantias de proteção adotadas por esses fornecedores.</p>
</section>

<section id="retencao">
    <h2>6. Por quanto tempo guardamos</h2>
    <ul>
        <li><strong>Dados da conta e do espaço do casal:</strong> enquanto a conta existir.</li>
        <li><strong>Após a exclusão da conta:</strong> nome e e-mail são anonimizados. Lançamentos que fazem parte do histórico do casal permanecem no espaço, sem identificar você.</li>
        <li><strong>Registros de acesso:</strong> 6 meses, como exige o Marco Civil da Internet (art. 15).</li>
        <li><strong>Registro de aceite dos Termos:</strong> enquanto puder servir de prova da contratação.</li>
        <li><strong>Cópias de segurança:</strong> até {!! $backupRetention !!}, quando são substituídas por cópias mais novas.</li>
        <li><strong>Dados de pagamento e fiscais:</strong> pelos prazos exigidos pela legislação.</li>
    </ul>
</section>

<section id="cookies">
    <h2>7. Cookies e armazenamento local</h2>
    <p>Usamos apenas cookies necessários ao funcionamento: o cookie de sessão (mantém você conectado), o de proteção contra falsificação de requisições e, se você escolher “Lembrar de mim”, o de login persistente. O navegador também guarda localmente preferências como o tema claro ou escuro. Não usamos cookies de publicidade nem ferramentas de rastreamento de terceiros.</p>
</section>

<section id="seguranca">
    <h2>8. Segurança</h2>
    <p>Adotamos conexão criptografada (HTTPS), senhas com hash, separação dos dados por casal, controle de acesso e cópias de segurança criptografadas. Nenhum sistema é totalmente imune a falhas; se ocorrer um incidente de segurança que possa causar risco ou dano relevante, comunicaremos os titulares afetados e a Autoridade Nacional de Proteção de Dados (ANPD), como determina a lei.</p>
</section>

<section id="direitos">
    <h2>9. Seus direitos</h2>
    <p>Pelo art. 18 da LGPD, você pode pedir:</p>
    <ul>
        <li>confirmação de que tratamos seus dados e acesso a eles;</li>
        <li>correção de dados incompletos ou desatualizados (nome e e-mail podem ser alterados no Perfil);</li>
        <li>portabilidade: os lançamentos do casal podem ser baixados em planilha (CSV) na página do Casal, em <strong>Exportar dados</strong>;</li>
        <li>anonimização, bloqueio ou eliminação de dados desnecessários ou tratados em desconformidade;</li>
        <li>eliminação dos dados, pela exclusão da conta no Perfil, observadas as regras de retenção acima;</li>
        <li>informação sobre com quem compartilhamos seus dados;</li>
        <li>revisão de decisões tomadas apenas com base em tratamento automatizado.</li>
    </ul>
    <p>Para os demais pedidos, escreva para {!! $contactEmail !!}. Responderemos em até 15 dias. Você também pode apresentar reclamação à ANPD.</p>
</section>

<section id="menores">
    <h2>10. Menores de idade</h2>
    <p>O {{ $appName }} é destinado a maiores de 18 anos e não coleta intencionalmente dados de crianças ou adolescentes.</p>
</section>

<section id="alteracoes">
    <h2>11. Alterações</h2>
    <p>Esta Política pode ser atualizada. Mudanças relevantes serão avisadas por e-mail ou no aplicativo, e a data da última atualização aparece no topo da página.</p>
</section>

<section id="contato">
    <h2>12. Contato</h2>
    <p>{!! $contactEmail !!} ou pela <a href="{{ route('contact.show') }}">página de contato</a>.</p>
</section>
@endsection
