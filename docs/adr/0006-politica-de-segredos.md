# 0006 · Política de segredos

**Status:** aceito

## Contexto

O backend guarda dados pessoais de participantes e, eventualmente, credenciais de gateway de pagamento por edição. Nada disso pode vazar por commit, log ou configuração mal feita.

## Decisão

- Nenhum segredo (chave de API, senha, DSN com credencial, token) vive no repositório. `.env` é por ambiente, nunca commitado (`backend/.gitignore` já cobre `.env*`), `.env.example` documenta cada variável sem valor real.
- Credencial de gateway por edição (quando o módulo Payments existir) é criptografada em banco — nunca texto plano, nunca devolvida pela API depois de salva (já previsto em `docs/produto.md`).
- Log nunca contém segredo: `App\Logging\RedactSensitiveDataProcessor` redige `password`, `token`, `api_key`, `secret`, `authorization`, `card_number`, `cvv`/`cvc` (e variantes aninhadas) em todo `Log::info/error/...`, automaticamente — quem loga não precisa lembrar de redigir na mão.
- Sentry (observabilidade de erro) é opt-in: `SENTRY_LARAVEL_DSN` vazio por padrão desliga a integração inteira, sem código condicional espalhado.

## Consequências

- Rotacionar uma chave vazada é trocar variável de ambiente, nunca reescrever histórico de git.
- Um novo tipo de dado sensível (ex.: CPF) deve entrar na lista de `RedactSensitiveDataProcessor` se algum dia for logado — o teste `tests/Unit/Logging/RedactSensitiveDataProcessorTest.php` é onde isso se documenta.
