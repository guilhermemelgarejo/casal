@php
    $appName = config('app.name', 'DuoZen');
    $legal = config('duozen.legal', []);
    $trialDays = (int) config('duozen.trial_days', 14);
    $updatedAt = \Carbon\Carbon::parse($legal['terms_updated_at'] ?? now())->format('d/m/Y');
@endphp
@extends('legal.layout')

@section('title', 'Termos e Condições de Uso')
@section('description', 'Termos e Condições de Uso do '.$appName.': assinatura, teste grátis, cancelamento, espaço do casal e proteção de dados.')

@section('content')
<header class="lp-legal__head">
    <span class="lp-section__eyebrow">Documento legal</span>
    <h1>Termos e Condições de Uso</h1>
    <p>Última atualização: {{ $updatedAt }}</p>
</header>

<nav class="lp-legal__toc" aria-label="Índice">
    <strong>Nesta página</strong>
    <ol>
        <li><a href="#fornecedor">Quem somos</a></li>
        <li><a href="#aceitacao">Aceitação</a></li>
        <li><a href="#servico">O que é o {{ $appName }}</a></li>
        <li><a href="#conta">Cadastro e conta</a></li>
        <li><a href="#casal">Espaço do casal</a></li>
        <li><a href="#assinatura">Assinatura, teste e pagamento</a></li>
        <li><a href="#cancelamento">Cancelamento e arrependimento</a></li>
        <li><a href="#uso">Regras de uso</a></li>
        <li><a href="#dados">Seus dados</a></li>
        <li><a href="#privacidade">Privacidade e LGPD</a></li>
        <li><a href="#exclusao">Exclusão da conta</a></li>
        <li><a href="#disponibilidade">Disponibilidade e suporte</a></li>
        <li><a href="#propriedade">Propriedade intelectual</a></li>
        <li><a href="#responsabilidade">Responsabilidades</a></li>
        <li><a href="#suspensao">Suspensão e encerramento</a></li>
        <li><a href="#alteracoes">Alterações destes Termos</a></li>
        <li><a href="#lei">Lei aplicável e foro</a></li>
        <li><a href="#contato">Contato</a></li>
    </ol>
</nav>

<section id="fornecedor">
    <h2>1. Quem somos</h2>
    <p>O {{ $appName }} é oferecido por {!! $providerName !!}, inscrito(a) sob o nº {!! $providerDocument !!}, com endereço em {!! $providerAddress !!} (“{{ $appName }}”, “nós”).</p>
    <p>Atendimento: {!! $contactEmail !!} ou pela <a href="{{ route('contact.show') }}">página de contato</a>.</p>
</section>

<section id="aceitacao">
    <h2>2. Aceitação</h2>
    <p>Estes Termos regulam o uso do site e da aplicação {{ $appName }}. Ao criar uma conta ou usar o serviço, você declara que leu, entendeu e concorda com eles. Se não concordar, não use o serviço.</p>
    <p>Para usar o {{ $appName }} é preciso ter 18 anos ou mais e plena capacidade civil.</p>
</section>

<section id="servico">
    <h2>3. O que é o {{ $appName }}</h2>
    <p>O {{ $appName }} é uma ferramenta de organização financeira para casais. Nele, vocês registram receitas, despesas, contas, cartões, faturas, orçamentos, metas, investimentos e dívidas, e acompanham resumos e relatórios.</p>
    <p>É importante saber o que o {{ $appName }} <strong>não</strong> é:</p>
    <ul>
        <li><strong>Não é banco nem instituição financeira.</strong> Não guarda, movimenta, transfere nem investe dinheiro.</li>
        <li><strong>Não se conecta às suas contas bancárias.</strong> Não pedimos senhas de banco. As informações são lançadas por vocês.</li>
        <li><strong>Não é consultoria financeira, contábil, jurídica ou de investimentos.</strong> Orçamento diário, alertas, relatórios e cálculos são estimativas feitas a partir dos dados informados por vocês e não constituem recomendação.</li>
        <li><strong>Cotações de ativos</strong> (como criptomoedas) vêm de fontes de terceiros, têm caráter apenas informativo e podem conter atrasos ou erros.</li>
    </ul>
</section>

<section id="conta">
    <h2>4. Cadastro e conta</h2>
    <ul>
        <li>Cada pessoa tem o próprio login, com e-mail e senha pessoais. Não compartilhe sua senha, nem mesmo com seu par.</li>
        <li>Você se compromete a informar dados verdadeiros e a mantê-los atualizados.</li>
        <li>Você é responsável pelas atividades realizadas com o seu login. Se suspeitar de acesso indevido, troque a senha e fale com a gente.</li>
    </ul>
</section>

<section id="casal">
    <h2>5. Espaço do casal</h2>
    <ul>
        <li>O espaço do casal reúne no máximo duas pessoas. <strong>Os dois membros veem, incluem, alteram e excluem todos os dados do espaço</strong>, inclusive os lançados pelo outro. Cada lançamento indica quem o registrou.</li>
        <li>Só convide para o seu espaço alguém que concorde em compartilhar essas informações com você.</li>
        <li>O código de convite dá acesso ao espaço enquanto houver vaga. Compartilhe-o apenas com seu par. Vocês podem gerar um novo código a qualquer momento na página do Casal, e um novo código é gerado automaticamente sempre que um membro sai.</li>
        <li>Quem sai do espaço perde o acesso a ele. <strong>Os dados lançados permanecem no espaço</strong> e continuam disponíveis para o membro que fica, para preservar o histórico financeiro comum.</li>
        <li>Em caso de separação ou desacordo sobre os dados, o {{ $appName }} não atua como mediador. Antes de sair, qualquer membro pode baixar os lançamentos do casal em planilha, em <strong>Exportar dados</strong> na página do Casal.</li>
    </ul>
</section>

<section id="assinatura">
    <h2>6. Assinatura, teste grátis e pagamento</h2>
    <ul>
        <li><strong>Plano por casal.</strong> Basta uma assinatura ativa de um dos membros para os dois terem acesso ao espaço.</li>
        <li><strong>Teste grátis.</strong> O plano começa com {{ $trialDays }} dias de teste. Para ativá-lo, é preciso cadastrar um cartão de crédito no ambiente de pagamento da Stripe. Nada é cobrado durante o teste.</li>
        <li><strong>Renovação automática.</strong> Se o teste não for cancelado antes de terminar, a assinatura passa a ser cobrada mensalmente, de forma recorrente, no cartão cadastrado, até o cancelamento.</li>
        <li><strong>Preço.</strong> O valor e a moeda são informados na tela de pagamento antes da contratação. Reajustes serão comunicados com pelo menos 30 dias de antecedência e só valem a partir do ciclo seguinte ao aviso.</li>
        <li><strong>Quem paga.</strong> A cobrança é feita no cartão de quem ativou a assinatura, mesmo que a responsabilidade pela assinatura seja transferida ao outro membro dentro do aplicativo. Para evitar cobranças fora do casal, <strong>quem tem assinatura com renovação ativa precisa cancelá-la no portal de pagamento antes de sair do espaço</strong>.</li>
        <li><strong>Pagamentos.</strong> São processados pela Stripe. Não armazenamos o número completo do seu cartão.</li>
        <li><strong>Falta de pagamento.</strong> Se a cobrança não for aprovada, o acesso às funções do aplicativo pode ser suspenso até a regularização. Os dados não são apagados por esse motivo.</li>
    </ul>
</section>

<section id="cancelamento">
    <h2>7. Cancelamento e direito de arrependimento</h2>
    <ul>
        <li>Você pode cancelar a qualquer momento, sem multa, pelo portal de pagamento acessível na página do Casal.</li>
        <li>Cancelando durante o teste, nada é cobrado. Cancelando depois, o acesso continua até o fim do período já pago, e não há novas cobranças.</li>
        <li><strong>Direito de arrependimento.</strong> Conforme o art. 49 do Código de Defesa do Consumidor, você pode desistir da contratação em até 7 dias. Se houver cobrança e você pedir o cancelamento em até 7 dias contados da primeira cobrança, devolvemos o valor integralmente, pelo mesmo meio de pagamento.</li>
        <li>Fora dessas hipóteses, valores de períodos já iniciados não são reembolsados proporcionalmente, salvo quando a lei exigir.</li>
    </ul>
</section>

<section id="uso">
    <h2>8. Regras de uso</h2>
    <p>Ao usar o {{ $appName }}, você se compromete a não:</p>
    <ul>
        <li>usar o serviço para fins ilegais, fraudulentos ou para registrar dados de terceiros sem autorização;</li>
        <li>tentar acessar espaços de outros casais, contas de outras pessoas ou áreas restritas do sistema;</li>
        <li>enviar convites ou mensagens em massa, spam ou conteúdo ofensivo pelo serviço;</li>
        <li>copiar, revender, fazer engenharia reversa ou sobrecarregar o serviço, inclusive com robôs ou automações não autorizadas;</li>
        <li>contornar limites de uso, de cobrança ou de período de teste.</li>
    </ul>
</section>

<section id="dados">
    <h2>9. Seus dados e conteúdos</h2>
    <ul>
        <li>Os dados que vocês lançam pertencem a vocês. Usamos essas informações apenas para operar, manter, proteger e melhorar o serviço.</li>
        <li>Vocês são responsáveis pela exatidão do que lançam. Saldos, faturas, orçamentos e projeções dependem desses dados.</li>
        <li>Não vendemos seus dados e não os usamos para publicidade de terceiros.</li>
    </ul>
</section>

<section id="privacidade">
    <h2>10. Privacidade e proteção de dados (LGPD)</h2>
    <p>Tratamos dados pessoais conforme a Lei Geral de Proteção de Dados (Lei nº 13.709/2018).</p>
    <ul>
        <li><strong>Dados tratados:</strong> nome, e-mail, senha (armazenada de forma criptografada), registros de acesso e os dados financeiros que vocês lançam. Os dados do cartão ficam com a Stripe.</li>
        <li><strong>Finalidades:</strong> prestar o serviço contratado, cobrar a assinatura, dar suporte, garantir a segurança e cumprir obrigações legais.</li>
        <li><strong>Fornecedores:</strong> para funcionar, o serviço usa empresas de hospedagem, envio de e-mails, processamento de pagamentos (Stripe) e armazenamento de cópias de segurança (Google). Alguns desses fornecedores podem armazenar dados fora do Brasil, com as garantias previstas na LGPD.</li>
        <li><strong>Registros de acesso</strong> são guardados pelo prazo de 6 meses exigido pelo Marco Civil da Internet (Lei nº 12.965/2014).</li>
        <li><strong>Seus direitos:</strong> você pode pedir confirmação, acesso, correção, portabilidade, informações sobre compartilhamento e eliminação dos seus dados, nos limites da lei, pelo canal de contato indicado abaixo.</li>
    </ul>
    <p>Os detalhes estão na <a href="{{ route('legal.privacy') }}">Política de Privacidade</a>, que complementa estes Termos.</p>
</section>

<section id="exclusao">
    <h2>11. Exclusão da conta</h2>
    <ul>
        <li>Você pode excluir sua conta na página de Perfil. Antes, é preciso encerrar a assinatura e, se você for o responsável pela assinatura do casal, transferir essa responsabilidade.</li>
        <li>Se a conta não tiver lançamentos, ela é apagada. Se tiver, seus dados de identificação (nome e e-mail) são anonimizados e o acesso é desativado, mas os lançamentos permanecem no espaço do casal, sem identificar você, para não apagar o histórico do outro membro.</li>
        <li>Cópias de segurança podem conter dados por um período adicional até serem substituídas, e alguns dados podem ser mantidos quando a lei exigir.</li>
    </ul>
</section>

<section id="disponibilidade">
    <h2>12. Disponibilidade e suporte</h2>
    <p>Trabalhamos para manter o serviço disponível e seguro, mas ele pode passar por manutenções, atualizações ou instabilidades, inclusive de fornecedores. Sempre que possível, avisaremos com antecedência sobre interrupções programadas. O suporte é prestado pelos canais de contato, em dias úteis.</p>
</section>

<section id="propriedade">
    <h2>13. Propriedade intelectual</h2>
    <p>A marca {{ $appName }}, o logotipo, o software, o layout e os textos do serviço são protegidos por lei e pertencem ao {{ $appName }} ou a seus licenciadores. A assinatura dá direito de uso pessoal e não exclusivo do serviço durante sua vigência, sem transferir nenhum desses direitos.</p>
</section>

<section id="responsabilidade">
    <h2>14. Responsabilidades</h2>
    <ul>
        <li>As decisões financeiras tomadas com base nas informações do aplicativo são de responsabilidade de vocês.</li>
        <li>O {{ $appName }} não responde por dados lançados de forma incorreta, por informações de terceiros (como cotações) nem por falhas causadas por uso indevido da conta ou por senha compartilhada.</li>
        <li>Nada nestes Termos limita direitos que o Código de Defesa do Consumidor garante a você.</li>
    </ul>
</section>

<section id="suspensao">
    <h2>15. Suspensão e encerramento</h2>
    <p>Podemos suspender ou encerrar contas que violem estes Termos ou a lei, ou que coloquem em risco a segurança do serviço ou de outras pessoas. Salvo em casos graves ou urgentes, avisaremos antes e daremos a oportunidade de regularizar a situação.</p>
</section>

<section id="alteracoes">
    <h2>16. Alterações destes Termos</h2>
    <p>Podemos atualizar estes Termos para refletir mudanças no serviço ou na lei. Mudanças relevantes serão comunicadas por e-mail ou no aplicativo com pelo menos 15 dias de antecedência. Se você não concordar, pode cancelar a assinatura antes de a mudança valer. A data da última atualização aparece no topo desta página.</p>
</section>

<section id="lei">
    <h2>17. Lei aplicável e foro</h2>
    <p>Estes Termos são regidos pelas leis brasileiras, em especial o Código de Defesa do Consumidor, o Marco Civil da Internet e a LGPD. Fica eleito o foro do domicílio do consumidor para resolver eventuais conflitos.</p>
</section>

<section id="contato">
    <h2>18. Contato</h2>
    <p>Dúvidas, pedidos sobre dados pessoais, cancelamentos ou reclamações: {!! $contactEmail !!} ou pela <a href="{{ route('contact.show') }}">página de contato</a>.</p>
</section>
@endsection
