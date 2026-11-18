Closes #

## Resumo
-

## Testes
- [ ] Backend: `composer check`
- [ ] Front: `npm run lint`, `npm run typecheck`, `npm test` e `npm run build`
- [ ] `npm run test:e2e`, com cenário novo para o fluxo alterado
- [ ] Contra o backend real, quando a mudança envolve API

## Checklist de tela (front-admin)
- [ ] Carregando: esqueleto (`PhPageSkeleton`/`PhSkeleton`) no lugar do conteúdo, sem rodinha
- [ ] Erro ao carregar: `PhLoadError` com "Tentar de novo"
- [ ] Toda lista tem estado vazio (`PhEmptyState`) com a próxima ação sugerida, e vazio por filtro oferece limpar
- [ ] Formulário: erro no campo e, com mais de um campo, resumo no topo (`PhFormErrors`)
- [ ] Avisos: sucesso que dá para desfazer oferece "Desfazer"
- [ ] Edição encerrada: ações de alteração escondidas ou desabilitadas (`isReadOnly`)
- [ ] Textos em português, sem termo técnico para quem usa o painel
