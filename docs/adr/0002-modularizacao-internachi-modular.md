# 0002 · Modularização com internachi/modular

**Status:** aceito

## Contexto

A plataforma tem muitos domínios (identidade, CFP, ingressos, pagamentos, etc.) que precisam evoluir de forma independente, sem um módulo acoplar no Model de outro por acidente.

## Decisão

- Pacote [`internachi/modular`](https://github.com/InterNACHI/modular): cada módulo vive em `backend/app-modules/<nome>`, é um pacote Composer real (`type: library`), instalado via path-repository com symlink, namespace `PHPeste\<Nome>`.
- Dentro de cada módulo, `src/` tem subpastas fixas: `Models`, `Actions`, `Data`, `Events`, `Listeners`, `Contracts`, `Http`, `Providers`. `Actions/` é o único lugar que pode mutar um Model com regra de negócio por trás — nunca um Controller ou outro módulo direto.
- Módulos MVP: `identity`, `community`, `branding`, `cfp`, `program`, `ticketing`, `payments`, `check-in`, `notifications`, `audit`.
- Módulos de fase 2/3 (scaffold vazio por enquanto): `sponsors`, `suppliers`, `finance`, `tasks`, `engagement`.
- `tests/Arch/ModuleIsolationTest.php` tem dois níveis de teste de arquitetura: nenhum módulo usa `App\`, e o `Models/` de cada módulo só é referenciado dentro do próprio módulo (ou em `Tests`).

## Alternativas consideradas

- Monólito sem fronteira de módulo: descartado — difícil garantir isolamento conforme o time cresce.
- Microsserviços desde já: descartado — custo de operação desproporcional pro tamanho do evento (planning doc da plataforma).

## Consequências

- Criar um módulo novo é `php artisan make:module <Nome>` seguido de `composer update phpeste/<nome>`.
- Se algum módulo tentar usar `Model` de outro módulo diretamente, o CI quebra no `pest --group=arch`.

## Correção (B06): `expect('PHPeste')` sozinho não pegava nada

A regra original de "módulo não depende de `App\`" usava `arch('...')->expect('PHPeste')->not->toUse('App')` — e nunca funcionou de verdade: testado na prática (violação real inserida de propósito), o Pest arch não resolve o namespace raiz `PHPeste` através dos múltiplos path-repositories symlinkados. A regra de isolamento de `Models/` (que já usa `"PHPeste\\{Módulo}\\Models"`, uma subpasta real) sempre funcionou. Corrigido iterando módulo × subpasta real (`Models`, `Actions`, `Http`, etc.) — ver `tests/Arch/ModuleIsolationTest.php`. Permitido depender de `App\Http\Resources`, `App\Http\Middleware`, `App\Support` e `App\Logging` (kernel compartilhado); bloqueado `App\Models`, `App\Http\Controllers` e `App\Providers` (específicos da app raiz).
