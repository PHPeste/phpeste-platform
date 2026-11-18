# Domínios das edições e certificados TLS

Cada edição publica o site em um ou mais domínios (`edition_domains`, módulo Branding). O front-public descobre a edição pelo host da requisição chamando a API, e o proxy reverso emite o certificado de cada domínio sob demanda.

## Tipos de domínio

| Tipo | Exemplo | Verificação |
| --- | --- | --- |
| Subdomínio da plataforma | `2027-php-pb.phpeste.dev` | Automática: o DNS wildcard `*.phpeste.dev` é da plataforma |
| Domínio próprio | `phpeste.com.br`, `evento.phpce.org` | TXT em `_phpeste-verify.<host>` com o valor mostrado no painel, **ou** CNAME do host para `PUBLIC_CNAME_TARGET` |

Subdomínio da plataforma é sempre de um nível e não pode usar rótulo reservado (`www`, `api`, `admin`, `app`, `painel`, `mail`, `email`, `status`, `docs`, `cdn`, `static`, `edicoes`). Um host só pode ser cadastrado uma vez em toda a instalação.

Variáveis (`backend/.env`):

```dotenv
PUBLIC_BASE_DOMAIN=phpeste.dev
PUBLIC_CNAME_TARGET=edicoes.phpeste.dev
```

## Resolução pelo host

```
GET /api/v1/public/resolve?host=phpeste.com.br
```

Devolve `edition_id`, `edition_name`, o `host` pedido e o `primary_host` da edição (para o site redirecionar ao domínio principal). Responde 404 para host não verificado, edição em rascunho ou host malformado. O host é sempre normalizado (minúsculas, sem porta, sem esquema, IDN em punycode) e comparado com a tabela, **nunca** usado para montar URL ou redirecionamento sem passar por aqui. Cache de 60 s na API e `Cache-Control: public, max-age=60` na resposta. Verificar, trocar o principal ou remover um domínio invalida o cache na hora.

## Certificados com Caddy (on-demand TLS)

O Caddy emite o certificado Let's Encrypt no primeiro acesso a cada domínio, e só depois de perguntar à API se aquele domínio é de uma edição publicada e verificada. Sem isso, qualquer pessoa apontando um domínio para o servidor faria o Caddy emitir certificados e estourar o limite do Let's Encrypt.

```caddyfile
{
	on_demand_tls {
		ask http://backend:8000/api/v1/public/tls-check
	}
}

# Sites das edições (domínio próprio e subdomínios da plataforma)
https:// {
	tls {
		on_demand
	}
	reverse_proxy front-public:3000
}

# Painel e API têm certificado normal
admin.phpeste.dev {
	reverse_proxy front-admin:3000
}

api.phpeste.dev {
	reverse_proxy backend:8000
}
```

`GET /api/v1/public/tls-check?domain=<host>` responde 200 para host verificado de edição publicada e 404 para qualquer outro, que é o contrato do `ask` do Caddy.

Para o wildcard `*.phpeste.dev`, um certificado wildcard (desafio DNS no provedor do domínio) evita uma emissão por edição; com on-demand ele também funciona, só emite um certificado por subdomínio.
