# Guia da comunidade: escolher e configurar o gateway de pagamento

A plataforma não recebe o dinheiro dos ingressos (ADR 0009). Cada edição cadastra a conta da própria comunidade num gateway, e o dinheiro cai direto nela. Hoje dá para usar **Asaas** ou **Mercado Pago**. Os dois funcionam com Pix e cartão, aparecem do mesmo jeito no painel e podem ser trocados numa edição sem vendas sem nenhuma mudança de código: basta cadastrar a conta nova em **Configurações → Pagamentos**.

## Taxas e prazos

Valores das tabelas públicas em novembro de 2026, sem negociação. Os gateways mudam preço com frequência e dão condições diferentes por volume: **confira na sua conta antes de decidir**.

| | Asaas | Mercado Pago |
| --- | --- | --- |
| Pix | R$ 1,99 por Pix recebido (R$ 0,99 em promoção para contas novas) | 0,99% do valor |
| Pix: quando o dinheiro cai | Na hora | Na hora |
| Cartão de crédito | 2,99% + R$ 0,49, a mesma taxa à vista ou parcelado | 4,98% para receber na hora, 4,48% em 14 dias, 3,99% em 30 dias |
| Cartão: quando o dinheiro cai | 32 dias depois da confirmação de cada parcela (dá para antecipar, com custo) | Conforme o prazo escolhido na conta |
| Mensalidade | Não tem | Não tem |

Num ingresso de R$ 150,00:

- **Pix:** R$ 1,99 no Asaas (taxa fixa) e R$ 1,49 no Mercado Pago (percentual). Em ingressos acima de R$ 200, o Pix do Asaas fica mais barato.
- **Cartão:** cerca de R$ 4,98 no Asaas, que só paga em 32 dias. No Mercado Pago vai de R$ 5,99 (em 30 dias) a R$ 7,47 (na hora).

A taxa sai da conta da comunidade. Por tipo de ingresso, a edição pode repassar a taxa estimada para quem compra.

Fontes:
- Asaas: [taxas](https://blog.asaas.com/taxas-asaas/) e [prazos de recebimento](https://www.asaas.com/meios-de-pagamento).
- Mercado Pago: [checkout transparente](https://www.mercadopago.com.br/ferramentas-para-vender/check-out) e [link de pagamento](https://www.mercadopago.com.br/ferramentas-para-vender/link-de-pagamento).

## O que muda para quem compra

| | Asaas | Mercado Pago |
| --- | --- | --- |
| Pix | QR e copia e cola no checkout da edição | QR e copia e cola no checkout da edição |
| Cartão | Quem compra vai para a fatura do Asaas, digita o cartão lá e volta para o site da edição | Quem compra digita o cartão no formulário do Mercado Pago dentro do checkout da edição, sem sair do site |
| Cartão recusado | O motivo aparece na fatura do Asaas | O checkout mostra o motivo ("Cartão sem limite para esta compra.", "Algum dado do cartão está errado...") e deixa tentar de novo |
| Nome no Pix | Titular da conta Asaas | Titular da conta Mercado Pago |

Nos dois casos o número do cartão nunca passa pela plataforma.

## Configurar o Asaas

1. **Chave de API:** em **Integrações → Chaves de API**, gere uma chave. As chaves do sandbox começam com `$aact_hmlg_` e as de produção com `$aact_prod_`.
2. **Webhook:** em **Integrações → Webhooks**:
   - crie um webhook com a URL que o painel da edição mostra depois do cadastro;
   - marque os eventos de cobrança;
   - escolha um token de autenticação, que é o "segredo do webhook" no cadastro da plataforma.
3. **Pix no sandbox:** cadastre uma chave Pix na conta, ou o Asaas não gera o QR.

## Configurar o Mercado Pago

1. **Aplicação:** em [Suas integrações](https://www.mercadopago.com.br/developers/panel/app), crie uma aplicação do tipo "Pagamentos online" com checkout transparente.
2. **Credenciais:** em **Credenciais de produção**, ou **Credenciais de teste** para o ambiente de testes, copie:
   - o **Access Token**, que é a chave de API;
   - a **Public Key**, que é a chave pública. O formulário de cartão usa essa chave no navegador, e ela pode ficar visível.
3. **Webhook:** em **Webhooks → Configurar notificações**:
   - cole a URL que o painel da edição mostra;
   - marque o evento **Pagamentos** e salve;
   - copie a **assinatura secreta** que aparece, que é o "segredo do webhook" no cadastro da plataforma.
4. **Testar o webhook:** use **Simular notificação**. A conta só fica pronta para vender depois de a plataforma receber um aviso assinado.

A conta precisa ser brasileira, porque a plataforma recusa contas de outros países, que não recebem em reais.

## Qual escolher

- **Mercado Pago:** é melhor quando a maior parte das vendas é por Pix e os ingressos são baratos, ou quando a comunidade quer o cartão dentro do próprio site e aceita uma taxa maior no cartão. Também é o caminho mais simples se alguém da organização já tem conta.
- **Asaas:** é melhor quando há muita venda no cartão e parcelada, porque a taxa é menor e igual em qualquer parcela. Também é o único dos dois que gera boleto pela plataforma. A contrapartida é esperar 32 dias pelo dinheiro do cartão.

Trocar de gateway depois que a edição já vendeu exige confirmação no painel. Os pedidos antigos continuam na conta antiga para reembolso e conciliação, e as vendas novas caem na conta nova.
