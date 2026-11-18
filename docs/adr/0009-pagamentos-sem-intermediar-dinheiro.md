# 0009 · A plataforma não recebe nem repassa dinheiro: cada edição usa a própria conta do gateway

**Status:** aceito

## Contexto

As comunidades que organizam eventos de PHP no Nordeste quase nunca têm CNPJ. Hoje elas recebem ingressos e patrocínio na conta pessoal de alguém da organização, por plataformas como a Eventize. Se a PHPeste Platform recebesse o dinheiro e depois repassasse, ela viraria intermediadora de pagamento: precisaria de CNPJ próprio, conta de subcredenciadora, emissão de nota sobre o repasse, conciliação de saldo e responsabilidade por chargeback de todas as comunidades.

## Decisão

- **A plataforma não toca no dinheiro.** Cada edição cadastra as chaves da própria conta num gateway (Asaas primeiro, depois Mercado Pago; Stripe pode entrar como driver). A cobrança é criada nessa conta, e o dinheiro cai direto nela. A plataforma só guarda as chaves, cifradas, e o estado dos pedidos.
- **A conta pode ser de CPF ou de CNPJ:**
  - a pessoa que cuida das finanças da comunidade;
  - o CNPJ de alguém da organização;
  - ou uma associação.

  O código trata todas do mesmo jeito (`payment_accounts.holder_type`). O que muda é quem aparece para quem paga: no Pix aparece o nome do titular, por isso o checkout mostra antes do pagamento o nome exibido que a edição cadastrou (ex.: "Fulana, responsável financeira da PHPeste 2027").
- **Receber em conta pessoal é decisão e responsabilidade fiscal da comunidade.** A plataforma não emite nota fiscal e deixa claro no comprovante que ele não é nota. O financeiro (B33) separa a receita por origem e por conta de recebimento para ajudar quem declara no CPF (carnê-leão, se for o caso). A orientação no painel é falar com um contador e, no longo prazo, formalizar uma associação.
- **Trocar de conta não apaga a anterior.**
  - Trocar cria uma conta nova e marca a anterior como substituída (`retired_at`), só para consulta.
  - Cada pedido guarda a conta em que foi pago (`orders.payment_account_id`), e reembolso e conciliação usam sempre a conta do pedido.
  - Trocar uma conta que já recebeu pedidos pagos exige confirmação explícita.
  - Toda troca é auditada (`payments.account_changed`), sem a chave.
- **Vendas com valor só abrem com a conta validada e o webhook testado.** Isso vale para lote com preço maior que zero. Evento só com ingressos gratuitos abre vendas sem gateway.

## Consequências

- Sem CNPJ nem conta de intermediação para a plataforma, e sem saldo de terceiros para conciliar.
- Cada driver de gateway implementa o mesmo contrato (`PHPeste\Payments\Contracts\PaymentGateway`). O `FakeGateway` implementa o contrato inteiro e é o que os testes usam; ele não fica disponível em produção.
- A taxa do gateway é cobrada da conta da comunidade. A edição pode repassar a taxa estimada para quem compra, por tipo de ingresso.
- O split de pagamento (parte para a plataforma) fica fora. Se um dia a plataforma cobrar algo, a cobrança será separada, nunca descontada do dinheiro do ingresso.

## Rotação da chave de criptografia

As credenciais dos gateways (`payment_accounts.credentials`) e o CPF e o token dos pedidos (`orders.buyer_document`, `orders.access_token`) são cifrados com a `APP_KEY`. Para trocar a chave:

1. Gere a chave nova (`php artisan key:generate --show`).
2. No ambiente, troque `APP_KEY` pela nova e coloque a antiga em `APP_PREVIOUS_KEYS`, separadas por vírgula se houver mais de uma. Faça o deploy: o Laravel lê com a chave nova e, se falhar, com as anteriores.
3. Rode `php artisan payments:reencrypt-credentials` e `php artisan ticketing:reencrypt-orders` para regravar tudo com a chave nova.
4. Só então tire a chave antiga de `APP_PREVIOUS_KEYS` e faça um novo deploy.

Trocar a `APP_KEY` também encerra sessões e links assinados que ainda estejam valendo. Prefira um horário sem vendas.
