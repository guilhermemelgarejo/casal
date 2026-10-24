# Guia de marca — DuoZen

Referência para a landing, posts, anúncios e perfis nas redes sociais. Mantenha a mesma promessa, as mesmas cores e o mesmo tom em todos os canais.

## 1. Essência

- **Nome:** DuoZen = *duo* (a dois) + *zen* (calma). O símbolo mostra duas pessoas que se encaixam: verde e roxo, lado a lado.
- **Promessa:** finanças a dois, em paz.
- **Slogan principal:** **Menos briga por dinheiro. Mais planos juntos.**
- **Para quem:** casais que dividem a vida e as contas, com cartão parcelado, Pix, boleto e fatura.
- **Inimigo:** a conversa tensa no fim do mês, a planilha que só um atualiza e a fatura que assusta.

## 2. Mensagens-chave

Use uma por peça. Todas são verdadeiras no produto atual.

| Tema | Mensagem |
|---|---|
| Transparência | Cada lançamento mostra quem registrou, em qual conta e categoria. |
| Cartão | Parcelas, fatura por mês, pagamento parcial e limite disponível. |
| Orçamento diário | Saibam quanto podem gastar hoje sem comprometer as contas do mês. |
| Metas | Cofrinhos com meta e progresso para cada sonho do casal. |
| Contas fixas | Aluguel, internet e streaming viram lembretes com lançamento pré-preenchido. |
| Privacidade | Sem senha de banco. Cada casal tem um espaço só seu. |
| Plano | Uma assinatura libera o acesso dos dois. |

**Não prometer:** conexão automática com bancos, aplicativo nas lojas, números de clientes, depoimentos ou economia garantida. Só publique prova social real e autorizada.

## 3. Cores

| Uso | Cor | Hex |
|---|---|---|
| Roxo principal (botões, destaques) | Roxo DuoZen | `#7C3AED` |
| Roxo escuro (texto em destaque, hover) | Roxo profundo | `#5B21B6` |
| Verde principal (sucesso, metas) | Verde DuoZen | `#16A34A` |
| Verde escuro (início do gradiente) | Verde profundo | `#099268` |
| Verde claro (detalhe do símbolo) | Lima | `#82C91E` |
| Texto principal | Tinta | `#1E1B2E` |
| Texto secundário | Ardósia | `#475569` |
| Fundo | Névoa | `#FAFAFC` |

**Gradiente da marca:** `#099268 → #16A34A → #7C3AED → #5B21B6`, da esquerda para a direita. Use em faixas, destaques e capas, nunca atrás de texto pequeno.

## 4. Tipografia

- **Web:** Figtree (700–800 em títulos, 400–500 em texto).
- **Peças estáticas sem Figtree:** Segoe UI ou Inter.
- Títulos curtos, no máximo duas linhas. A segunda linha pode levar o gradiente.

## 5. Logo e arquivos

| Arquivo | Uso |
|---|---|
| `public/images/duozen-logo-full.png` | Foto de perfil (Instagram, TikTok, YouTube) e capas. Símbolo + nome, fundo branco. |
| `public/images/duozen-logo.png` | Cabeçalhos e rodapés, só o nome. |
| `public/favicon.svg` | Símbolo isolado (favicon, marca-d'água). |
| `public/images/social/duozen-og.png` | Prévia de link (1200×630) em WhatsApp, Facebook, LinkedIn e X. |

Regras: não distorcer, não trocar as cores, manter área livre ao redor equivalente à altura da letra "D". Para perfis redondos, use a versão com símbolo centralizado. Os PNGs atuais têm 500 px; para material impresso ou capas grandes, peça a versão vetorial ao designer.

## 6. Tom de voz

- Falamos com **"vocês"** (o casal), nunca com "você" isolado.
- Leve, acolhedor e direto. Sem culpa, sem medo, sem jargão de banco.
- Trocamos "controle" por "clareza" e "corte de gastos" por "planos juntos".

| Em vez de | Prefira |
|---|---|
| "Pare de gastar demais!" | "Saibam quanto podem gastar hoje." |
| "Controle seu parceiro" | "Os dois veem a mesma coisa." |
| "Elimine suas dívidas já" | "Dívidas com parcelas e saldo sempre à vista." |

## 7. Perfis nas redes

**Bio sugerida (Instagram/TikTok, até 150 caracteres):**

> Finanças a dois, em paz 💜💚 Menos briga por dinheiro, mais planos juntos. Teste 14 dias grátis 👇

**Configuração na landing:** preencha no `.env` da hospedagem:

```
DUOZEN_PUBLIC_PRICE_AMOUNT=   # mesmo valor do preço no Stripe
DUOZEN_SOCIAL_HANDLE=@seu.perfil
DUOZEN_SOCIAL_INSTAGRAM_URL=https://www.instagram.com/seu.perfil
DUOZEN_SOCIAL_TIKTOK_URL=https://www.tiktok.com/@seu.perfil
```

Os links e o preço só aparecem na página quando preenchidos. Use links com parâmetros UTM nos perfis (ex.: `?utm_source=instagram&utm_medium=bio`) para medir a origem dos cadastros.

## 8. Linhas de conteúdo

1. **"Soa familiar?"** — as perguntas do casal no fim do mês: "Quem pagou a luz?", "Quanto falta da fatura?", "Dá para pedir delivery hoje?", "Quando vamos viajar?". Formato: vídeo curto com os dois, resposta na tela do app.
2. **Dica de 15 segundos** — um recurso por post (orçamento diário, parcelas, cofrinho).
3. **Conversa sem briga** — roteiros para falar de dinheiro a dois (reunião mensal, metas comuns, mesada pessoal).
4. **Meta do casal** — acompanhar um cofrinho fictício (viagem, casamento, reserva), deixando claro que é exemplo.
5. **Bastidores** — novidades do produto e respostas a dúvidas dos seguidores.

Toda peça termina com uma chamada única: **"Testem 14 dias grátis — link na bio."**

## 9. Formatos

| Peça | Tamanho |
|---|---|
| Post quadrado | 1080×1080 |
| Post vertical (feed) | 1080×1350 |
| Stories, Reels, TikTok | 1080×1920 (margem segura de 250 px em cima e embaixo) |
| Prévia de link | 1200×630 (`duozen-og.png`) |
| Capa do YouTube | 2560×1440 (área segura central 1546×423) |
