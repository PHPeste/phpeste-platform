# red_cloudstack

Ambiente de desenvolvimento para construir em cima do **Apache CloudStack**: um SDK PHP/Laravel para a API, um app mobile para operar a nuvem e, possivelmente, um MCP próprio.

Este repositório sobe o **CloudStack Simulator** (imagem oficial `apache/cloudstack-simulator`) em Docker. Ele roda o management server de verdade, com a API real, mas simula o hypervisor. Serve para desenvolver e testar SDK, backend e app sem precisar de um host KVM.

## Início rápido

Requisitos: Docker (com Compose v2), `make`, `curl` e `python3`.

```bash
cp .env.example .env      # opcional, só se quiser mudar porta/tag/perfil
make bootstrap            # sobe o container, cria o datacenter e gera as chaves
make smoke                # chamadas assinadas de teste
```

Depois do bootstrap:

- UI: <http://localhost:8080/client> (`admin` / `password`)
- API: `http://localhost:8080/client/api`
- Chaves de API em `.env.local` (ignorado pelo git)

A primeira subida é lenta (imagem grande, MySQL e Java iniciando, deploy do datacenter). Leve alguns minutos a sério.

## Comandos

| Comando | O que faz |
| --- | --- |
| `make up` | Sobe o simulator em background |
| `make bootstrap` | Aguarda a API, cria o datacenter simulado e gera `.env.local` |
| `make smoke` | Roda `listZones`, `listServiceOfferings` e `listTemplates` assinados |
| `make logs` | Acompanha os logs |
| `make shell` | Abre um shell no container |
| `make down` | Para o container |
| `make reset` | Destrói o container e o `.env.local` para recomeçar |

Também dá para chamar qualquer comando da API direto:

```bash
python3 scripts/cs_call.py listVirtualMachines listall=true
```

## Documentação

- [docs/01-simulator.md](docs/01-simulator.md): como o simulator funciona, limites e troubleshooting
- [docs/02-api-e-autenticacao.md](docs/02-api-e-autenticacao.md): assinatura HMAC, jobs assíncronos e `listApis`
- [docs/03-roadmap.md](docs/03-roadmap.md): plano para SDK Laravel, backend, app mobile e MCP
- [docs/04-lab-real-kvm.md](docs/04-lab-real-kvm.md): da dev machine para o home lab de verdade
- [docs/05-conceitos-e-provisionamento.md](docs/05-conceitos-e-provisionamento.md): zone, pod, cluster, host, storage e o fluxo de provisionamento de uma VM
- [docs/06-hospedagem-vps.md](docs/06-hospedagem-vps.md): como funciona uma hospedagem de VPS, com e sem CloudStack
- [docs/07-controle-da-vm.md](docs/07-controle-da-vm.md): o que o CloudStack vê da VM e como o app chega por dentro dela

## Status

- [x] Simulator via Docker Compose
- [x] Bootstrap (datacenter + chaves de API)
- [x] Cliente assinado de referência (`scripts/cs_call.py`)
- [ ] Gerador de SDK a partir do `listApis`
- [ ] Pacote Laravel (repo próprio, público)
- [ ] Backend + app mobile: plano de controle (CloudStack)
- [ ] App mobile: plano interno da VM (console, SSH, agente)
- [ ] MCP próprio (ou avaliação dos existentes)

> Nota: os scripts foram escritos a partir da documentação oficial da imagem e ainda precisam de uma primeira rodada real numa máquina com Docker. Se algum passo do bootstrap falhar, veja o troubleshooting em `docs/01-simulator.md` e ajuste.
