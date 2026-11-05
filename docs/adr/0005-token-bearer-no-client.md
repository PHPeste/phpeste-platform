# 0005 · Token Bearer no client, não cookie httpOnly

**Status:** aceito

## Contexto

A issue A04 pedia guardar o token de sessão em cookie `httpOnly` via rota de servidor do Nuxt, nunca acessível a JavaScript do navegador — o jeito mais seguro contra roubo de token por XSS.

## Decisão

O `front-admin` continua com o padrão do template `base_nuxt_modular`: token Bearer guardado em `authStore` (Pinia) e persistido em `localStorage`, enviado pelo `HttpClient` como `Authorization: Bearer <token>`.

## Por quê

O app do participante (Flutter, `base_clean_arch_bloc`) vai consumir a mesma API com o mesmo token Sanctum. Cookie `httpOnly` só existe no navegador — um app mobile não tem como usá-lo. Ter dois mecanismos de autenticação (cookie pro admin web, Bearer pro app) duplicaria a lógica de emissão/revogação de sessão no backend e a superfície de teste, sem benefício pro mobile. Unificar em Bearer mantém web e app no mesmo fluxo de login/logout/expiração.

## Risco aceito e mitigação

Token em `localStorage` fica exposto a um XSS bem-sucedido no admin. Mitigações até agora: CSP e sanitização de entrada estão no escopo de B05 (segurança base, ainda não feita); o token tem TTL configurável no Sanctum; `EnsureIdempotencyKey`/rate limiting (B04) reduzem o dano de um token roubado em endpoints sensíveis. Revisitar esta decisão se o admin passar a lidar com dado de cartão ou algo de risco equivalente.

## Consequências

- `HttpClient`/`authStore` do `front-admin` não mudam de mecanismo nesta issue.
- Todo endpoint de autenticação do backend (`Identity`, quando existir) deve devolver `{user, token}` — formato que `useAuthSubmit` já espera.
