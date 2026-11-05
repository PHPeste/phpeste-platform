# Kit de UI — tema Sertão (issue #50)

Componentes base do front-admin. Vivem em `app/application/shared/components/ui/` e são
auto-importados com o prefixo `Ph` (`Button.vue` → `<PhButton>`), sem `import`.

## Tema

- Tokens `--ph-*` em `app/assets/css/main.css` (fundo, superfícies, tinta, marca, mostarda, terracota, estados).
- O tema DaisyUI `sertao` mapeia as variáveis do DaisyUI para esses tokens: classes como `btn-primary`
  e `input-error` já seguem a identidade. **Não use cores literais** nos componentes; use os tokens.
- Foco visível global: anel de 3 px na cor da marca (`:focus-visible`).

## Componentes

`PhButton` (primary/secondary/ghost/danger, `loading`, `disabled`, `to`), `PhInput`, `PhTextarea`,
`PhSelect`, `PhCheckbox`, `PhBadge` (ok/warn/danger/info/neutral), `PhStripe`, `PhCard`, `PhKpi`,
`PhTabs`, `PhChip`, `PhTable`, `PhPagination`, `PhSkeleton`, `PhEmptyState`, `PhModal`, `PhDrawer`,
`PhToastHost` (já montado em `layouts/default.vue`).

Avisos: `const toast = useToast(); toast.success('Salvo.')` (também `info`, `warning`, `error`, e uma ação
opcional `{ label, run }`). Estado em `stores/toastStore.ts`.

## Acessibilidade (regras do kit)

- Rótulo real (`<label for>`), erro com `aria-invalid` + `role="alert"`, dica ligada por `aria-describedby`.
- Estado nunca só por cor: `PhBadge` sempre tem texto.
- Abas com roving tabindex e setas/Home/End; modal e painel usam `<dialog>` nativo (foco preso, Esc fecha).
- Tabelas com legenda e região rolável focável; avisos numa região `aria-live`.
- Contraste: `tokens.test.ts` lê o `main.css` e exige ≥ 4,5:1 nos pares de texto (≥ 3:1 no anel de foco).

## Catálogo e testes

- Catálogo visual: `/_dev/componentes` (só em dev; em produção devolve 404).
- Testes de componente: `*.test.ts` ao lado de cada componente (`npm test`).
- Teclado e foco no navegador: `e2e/ui-catalog.spec.ts` (`npm run test:e2e`).
