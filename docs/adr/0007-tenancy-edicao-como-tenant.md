# 0007 · Tenancy: a edição é o tenant

**Status:** aceito

## Contexto

Uma instalação, um banco (decisão já registrada em `docs/produto.md`). Faltava decidir, antes de codar Identity (#8) e Community (#9/#14/#16/#17), **o que exatamente é o tenant** e como ele chega em cada requisição, job e webhook — sem isso, qualquer migration ou query escrita agora vira retrabalho.

## Decisão

### O tenant é a edição, não a comunidade

Comunidade (PHP-PB) é só a dona — o que muda de verdade ano a ano é a edição (PHPeste 2027, 2028...). Ingresso, CFP, grade, tema, gateway e equipe são todos por edição. Se o tenant fosse a comunidade, duas edições da mesma comunidade se misturariam e "criar edição a partir da anterior" (#16) viraria cópia dentro do mesmo tenant. Hierarquia: Comunidade → Edição → dados da edição.

### Global vs. por edição

| Global (fora do tenant) | Por edição (`edition_id`) |
| --- | --- |
| Usuário — conta única (#8) | Ingressos, pedidos, pagamentos |
| Perfil de palestrante (#13) | CFP, propostas, avaliações |
| Fornecedor (#34) | Grade, salas, sessões |
| | Tema, domínio, equipe e papéis |

Motivo: a mesma pessoa palestra em 2027 e é voluntária em 2028 sem criar outra conta; o histórico de palestrante e de fornecedor sobrevive de um ano para o outro.

### Login não carrega tenant

O token Sanctum (Bearer, ADR 0005) só diz quem é a pessoa — nunca edição, nunca papel. Os papéis vêm de `GET /me`, que lista as edições em que a pessoa atua; é isso que alimenta o seletor de edição do painel (#54/A06).

### Papel é um vínculo pessoa × edição, não atributo do usuário

Cinco papéis fixos, hardcoded: organizador, financeiro, avaliador, check-in, voluntário. Matriz de permissão em código (Gates/Policies do Laravel, sem pacote externo — nota técnica já registrada em #14). Sem tela de gerenciar papéis — só entra se a necessidade aparecer de verdade.

### Como a edição chega em cada tipo de operação

- **Rota admin**: edição explícita na URL (`/api/v1/editions/{edition}/...`), nunca em header. Mais explícito, mais fácil de cachear e de testar — e já estende a macro `Route::apiV1()` da B04 com um middleware que resolve a edição, confere o papel da pessoa nela e liga o global scope.
- **Site público e checkout**: edição vem do host (#17, domínio por edição) — `2027.phpeste.dev` ou o domínio da comunidade resolvem sem login.
- **Jobs**: não há request. **Todo Job que mexe em dado de edição recebe `edition_id` explícito no construtor — nunca resolve do contexto do processo que disparou.** Essa é a regra mais fácil de violar por acidente (um `dispatch()` escrito rápido, um listener que assume a edição "atual"), e o tipo de bug que só aparece em produção com duas edições ativas ao mesmo tempo.
- **Webhooks**: sem request de usuário. O `edition_id` é resolvido pela credencial do gateway daquela edição (payload assinado, ver Payments em `docs/produto.md`), nunca por header ou parâmetro confiável do lado de fora.

### Chave primária: UUID em toda tabela

Todas as tabelas (globais e por edição) usam UUID como chave primária — não auto-increment, não ULID. Consistência simples: uma única convenção de chave em todo o schema, sem decidir caso a caso qual tabela "precisa" esconder sequência. (Isso substitui a sugestão de ULID que estava em #8.)

### Unicidade é por edição, não global

E-mail de participante, código de cupom, slug de sessão — únicos dentro da edição (`unique(['edition_id', 'email'])`), nunca globalmente. Repetir isso aqui porque é o tipo de constraint que, se escrita errada na primeira migration, exige migração de dados pra corrigir depois.

## Riscos aceitos

- **Vazamento entre edições** se alguém esquecer o global scope: query crua, job sem `edition_id` explícito, relação mal feita. Mitigação: teste de arquitetura (já existe via #4) mais um teste de feature específico — autenticado na edição A, tentar ler recurso da edição B, esperar 403/404.
- **Isolamento fraco** — é um banco só. Suficiente pro tamanho do PHPeste; se uma comunidade um dia exigir isolamento físico de dado, é uma mudança grande, decidida conscientemente aqui como fora de escopo por ora.

## Consequências

- Migrations de #8/#9/#14 seguem: UUID em toda PK, `edition_id` com FK + índice em toda tabela de edição, unique compound em vez de unique simples onde fizer sentido.
- Job de qualquer módulo que toque dado de edição declara `edition_id` no construtor — revisão de PR deve barrar job que resolve edição via `auth()->user()->currentEdition` ou equivalente.
- Middleware de resolução de edição por URL é construído junto com #9, reaproveitando `Route::apiV1()`.
