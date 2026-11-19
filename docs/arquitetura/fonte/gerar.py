#!/usr/bin/env python3
"""
Gera docs/arquitetura/fonte/arquitetura.html (diagramas em SVG inline).
O PDF sai de `node docs/arquitetura/fonte/imprimir.mjs`.

Uso (na raiz do repo):
    python3 docs/arquitetura/fonte/gerar.py && node docs/arquitetura/fonte/imprimir.mjs

Os números das páginas (tabelas, rotas, módulos) estão escritos aqui à mão:
ao mudar a arquitetura, atualize este arquivo no mesmo PR (ver CLAUDE.md).
"""
from html import escape
from pathlib import Path

OUT = Path(__file__).with_name("arquitetura.html")
RETRATO = "19/11/2026 · A16 (tela de pagamentos)"

# ---------------------------------------------------------------- estilo ---
# Cores do tema Sertão (front-admin) para o que é da plataforma, e neutras
# para o que é de fora.
K = {
    "client":   ("#FDF1DD", "#B8761E"),
    "backend":  ("#F7E9E1", "#6B2A14"),
    "edition":  ("#E6F2EC", "#2F7D5B"),
    "global":   ("#E8F0FB", "#3B6FD8"),
    "data":     ("#FFF6E5", "#C98A12"),
    "external": ("#F3F4F6", "#6B7280"),
    "infra":    ("#F1F5F9", "#475569"),
    "todo":     ("#FFFFFF", "#94A3B8"),
    "alert":    ("#FDECEC", "#C2410C"),
}
INK = "#2B1408"
MUTED = "#5B4636"


def t(x, y, s, size=10.5, weight=400, color=INK, anchor="start", mono=False, italic=False):
    fam = "'JetBrains Mono','SF Mono',Menlo,monospace" if mono else "inherit"
    st = ' font-style="italic"' if italic else ""
    return (f'<text x="{x}" y="{y}" font-size="{size}" font-weight="{weight}" fill="{color}" '
            f'text-anchor="{anchor}" font-family="{fam}" xml:space="preserve"{st}>{escape(s)}</text>')


def box(x, y, w, h, title, lines=(), kind="backend", dashed=False, mono=False, title_size=12, line_h=14, center=False, badge=None, size=10):
    fill, stroke = K[kind]
    dash = ' stroke-dasharray="6 4"' if dashed else ""
    out = [f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="9" fill="{fill}" stroke="{stroke}" stroke-width="1.6"{dash}/>']
    tx, anchor = (x + w / 2, "middle") if center else (x + 10, "start")
    out.append(t(tx, y + 19, title, size=title_size, weight=700, anchor=anchor))
    for i, line in enumerate(lines):
        color, weight, s = MUTED, 400, line
        if line.startswith("+ "):
            color, weight, s = K["edition"][1], 600, line[2:]
        elif line.startswith("! "):
            color, weight, s = K["alert"][1], 600, line[2:]
        out.append(t(tx, y + 36 + i * line_h, s, size=size, color=color, weight=weight, anchor=anchor, mono=mono))
    if badge:
        label, bkind = badge
        bw = 10 + len(label) * 6
        out.append(f'<rect x="{x + w - bw - 7}" y="{y + 6}" width="{bw}" height="16" rx="8" fill="{K[bkind][1]}"/>')
        out.append(t(x + w - bw / 2 - 7, y + 18, label, size=9, weight=700, color="#FFFFFF", anchor="middle"))
    return "\n".join(out)


def group(x, y, w, h, label, kind="infra", dashed=True, label_size=11):
    fill, stroke = K[kind]
    dash = ' stroke-dasharray="7 5"' if dashed else ""
    return (f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="14" fill="{fill}" fill-opacity="0.5" '
            f'stroke="{stroke}" stroke-width="1.4"{dash}/>' + t(x + 13, y + 18, label, size=label_size, weight=700, color=stroke))


def arrow(points, kind="infra", dashed=False, label=None, at=0, both=False, dy=-6, label_anchor="middle"):
    stroke = K[kind][1]
    d = "M " + " L ".join(f"{px} {py}" for px, py in points)
    dash = ' stroke-dasharray="6 4"' if dashed else ""
    start = f' marker-start="url(#a-{kind})"' if both else ""
    out = [f'<path d="{d}" fill="none" stroke="{stroke}" stroke-width="1.6"{dash} marker-end="url(#a-{kind})"{start}/>']
    if label:
        (x1, y1), (x2, y2) = points[at], points[at + 1]
        out.append(t((x1 + x2) / 2, (y1 + y2) / 2 + dy, label, size=9.5, weight=600, color=stroke, anchor=label_anchor))
    return "\n".join(out)


def svg(body, w=1100, h=560):
    markers = [f'<marker id="a-{k}" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="{s}"/></marker>' for k, (_, s) in K.items()]
    return f'<svg viewBox="0 0 {w} {h}" xmlns="http://www.w3.org/2000/svg" role="img"><defs>{"".join(markers)}</defs>{body}</svg>'


def page(num, kicker, title, subtitle, content, notes=None):
    notes_html = '<ul class="notes">' + "".join(f"<li>{n}</li>" for n in notes) + "</ul>" if notes else ""
    return f"""
<section class="page">
  <header>
    <div class="kicker">{num:02d} · {escape(kicker)}</div>
    <h1>{escape(title)}</h1>
    <p class="sub">{subtitle}</p>
  </header>
  <div class="content">{content}</div>
  {notes_html}
  <footer><span>PHPeste Platform · Arquitetura</span><span>{RETRATO} · página {num}</span></footer>
</section>"""


def table(head, rows, cls="cmp"):
    th = "".join(f"<th>{h}</th>" for h in head)
    body = "".join("<tr>" + "".join(f"<td>{c}</td>" for c in row) + "</tr>" for row in rows)
    return f'<table class="{cls}"><thead><tr>{th}</tr></thead><tbody>{body}</tbody></table>'


def legend():
    items = [
        ("client", "Front-end (painel, site, app)"), ("backend", "Backend Laravel"), ("edition", "Dado da edição (tenant)"),
        ("global", "Dado global ou da comunidade"), ("data", "Banco, cache e arquivos"), ("external", "Serviço externo"),
        ("infra", "Infra e operação"), ("todo", "Planejado, ainda vazio"),
    ]
    out = []
    for i, (k, label) in enumerate(items):
        x, y = 10 + (i % 4) * 255, 10 + (i // 4) * 32
        f, s = K[k]
        dash = ' stroke-dasharray="6 4"' if k == "todo" else ""
        out.append(f'<rect x="{x}" y="{y}" width="34" height="22" rx="6" fill="{f}" stroke="{s}" stroke-width="1.6"{dash}/>')
        out.append(t(x + 44, y + 15, label, size=12))
    out.append(arrow([(10, 88), (60, 88)], "infra"))
    out.append(t(70, 92, "chamada ou dependência", size=12))
    out.append(arrow([(265, 88), (315, 88)], "infra", dashed=True))
    out.append(t(325, 92, "assíncrono (evento, fila, webhook)", size=12))
    return svg("\n".join(out), w=1020, h=100)


pages = []

# =================================================================== 1 capa
toc = [
    "Visão geral do sistema", "Tenancy: comunidade, edição e o que é global", "Como uma requisição encontra a edição",
    "Ciclo de vida da edição", "Os 15 módulos do backend", "Como os módulos conversam", "Banco de dados por domínio",
    "Papéis e permissões", "Ingresso: da compra ao QR", "CFP: da proposta à grade", "Dinheiro: patrocínio, fornecedores e finanças",
    "Fila, rotinas agendadas, e-mail e auditoria", "Segurança", "Desenvolvimento, CI e deploy", "Telas: design e painel",
    "Telas: painel em uso", "Telas: vendas e organização", "Onde estamos e o que falta",
]
pages.append(f"""
<section class="page cover">
  <div class="cover-top">
    <div class="kicker">PHPeste Platform · documentação de arquitetura</div>
    <h1 class="cover-title">Uma plataforma, um banco,<br>uma edição por vez</h1>
    <p class="cover-sub">Como funciona a plataforma que organiza o PHPeste todo ano: o painel da organização, o site público de cada
    edição e a API Laravel em módulos por trás dos dois. Cada comunidade anfitriã cria a sua <b>edição</b>, e a edição é o tenant:
    todo dado de evento carrega <code>edition_id</code> e só aparece dentro dela.</p>
  </div>
  <div class="cover-grid">
    <div class="cover-card">
      <h3>Resumo em cinco pontos</h3>
      <ol>
        <li><b>Monorepo com três partes.</b> <code>backend/</code> (Laravel 13, API), <code>front-admin/</code> (Nuxt SPA da organização) e <code>front-public/</code> (Nuxt SSR do site da edição, resolvido pelo domínio).</li>
        <li><b>A edição é o tenant</b> (ADR 0007). Um banco só; escopo global por <code>edition_id</code> e a edição no contexto só durante a requisição ou o job.</li>
        <li><b>15 módulos</b> (<code>internachi/modular</code>) que só conversam por contratos e eventos. 13 já têm lógica; Program, CheckIn e Engagement ainda são casca.</li>
        <li><b>A plataforma não toca no dinheiro</b> (ADR 0009). Cada edição usa a conta do próprio gateway (Asaas ou Mercado Pago); a receita é calculada dos pedidos, nunca guardada.</li>
        <li><b>Toda ação sensível vira trilha de auditoria</b> por evento, com segredo removido e dado pessoal mascarado.</li>
      </ol>
    </div>
    <div class="cover-card">
      <h3>Conteúdo</h3>
      <ol class="toc">{"".join(f"<li><span>{escape(item)}</span><span>{i + 2}</span></li>" for i, item in enumerate(toc))}</ol>
    </div>
  </div>
  <div class="cover-legend"><h3>Legenda usada nos diagramas</h3>{legend()}</div>
  <footer><span>Fonte: código da main, backend/CLAUDE.md, docs/produto.md e ADRs 0001–0009.</span><span>{RETRATO} · página 1</span></footer>
</section>""")

# ========================================================= 2 visão geral
b = []
b.append(group(10, 30, 230, 500, "Quem usa", "client"))
b.append(box(24, 62, 202, 92, "Painel da organização", ["front-admin · Nuxt 4 SPA", "organizador, financeiro,", "avaliador, check-in"], "client"))
b.append(box(24, 168, 202, 104, "Site da edição", ["front-public · Nuxt 4 SSR", "site, CFP, checkout,", "área do palestrante e", "do participante"], "client"))
b.append(box(24, 286, 202, 78, "Site próprio da comunidade", ["opcional: chama só a", "API pública (CORS por edição)"], "client", dashed=True))
b.append(box(24, 378, 202, 64, "App Flutter", ["ainda não iniciado"], "todo", dashed=True))
b.append(box(24, 456, 202, 62, "Gateways (webhooks)", ["Asaas e Mercado Pago"], "external"))

b.append(box(275, 200, 120, 150, "Caddy", ["proxy reverso", "TLS sob demanda", "para domínio de", "cada edição", "(tls-check)"], "infra", center=True))
for y in (108, 220, 325, 487):
    b.append(arrow([(226, y), (275, 275)], "infra"))

b.append(group(430, 30, 380, 500, "API · Laravel 13 · PHP 8.4 · /api/v1", "backend", dashed=False))
b.append(box(445, 62, 350, 86, "Rotas por tipo", ["/editions/{id}/…  painel (Sanctum + papel na edição)", "/speaker, /attendee, /checkout/editions/{id}/…  publicada", "/public/…  site, tema, ingressos (cache + ETag)", "/webhooks/payments/{token}  assinado"], "backend", mono=False, size=9.5))
b.append(box(445, 160, 350, 98, "15 módulos (app-modules/)", ["Identity · Community · Branding · Cfp · Ticketing", "Payments · Notifications · Audit · Sponsors", "Suppliers · Finance · Tasks", "casca: Program · CheckIn · Engagement"], "backend", size=9.5))
b.append(box(445, 270, 170, 112, "Horizon (fila)", ["e-mail, webhooks,", "importação, clonagem", "de edição, emissão", "de ingressos"], "backend"))
b.append(box(625, 270, 170, 112, "Scheduler", ["cfp:sync-status", "ticketing:expire-orders", "ticketing:reconcile", "tasks:due-reminders", "…9 rotinas"], "backend", size=9.5))
b.append(box(445, 394, 350, 122, "Regras que valem para todo módulo", ["Tenancy por edição (BelongsToEdition)", "Mutação só em Action · validação em FormRequest", "Módulo não usa model de outro (arch test)", "Ação sensível → evento Auditable", "Logs JSON com segredo redigido · Sentry opcional"], "backend", size=9.5))
b.append(arrow([(395, 275), (430, 275)], "infra"))

b.append(box(845, 40, 245, 70, "MySQL 8", ["um banco · 69 tabelas", "UUID em toda tabela"], "data"))
b.append(box(845, 122, 245, 70, "Redis", ["cache, filas, locks,", "rate limit"], "data"))
b.append(box(845, 204, 245, 70, "Disco privado", ["CSV de importação, anexos,", "comprovantes (URL assinada)"], "data"))
b.append(box(845, 292, 245, 56, "Asaas · Mercado Pago", ["conta de cada edição (ADR 0009)"], "external"))
b.append(box(845, 360, 245, 50, "Resend (e-mail)", ["webhook de entrega · Mailpit no dev"], "external"))
b.append(box(845, 422, 245, 50, "GitHub OAuth", ["login de palestrante"], "external"))
b.append(box(845, 484, 245, 46, "Sentry", ["opcional, sem DSN fica desligado"], "external"))
for y in (75, 157, 239):
    b.append(arrow([(810, y), (845, y)], "data"))
for y in (320, 385, 447, 507):
    b.append(arrow([(810, y), (845, y)], "external"))
pages.append(page(2, "Visão geral", "Visão geral do sistema",
    "Dois front-ends Nuxt e um site próprio opcional falam com uma API só. O Caddy emite o certificado de cada domínio de edição na hora; a API resolve a edição pelo host.",
    svg("\n".join(b), h=545),
    ["Painel e site são projetos separados (decisão: <code>front-admin</code> SPA, <code>front-public</code> SSR). O token é Bearer do Sanctum nos dois (ADR 0005).",
     "Fila e agendador rodam a mesma imagem do backend (<code>horizon</code> e <code>scheduler</code> no <code>infra/docker-compose.yml</code>)."]))

# ======================================================= 3 tenancy
b = []
b.append(group(10, 20, 340, 520, "Global (da plataforma)", "global"))
b.append(box(25, 52, 310, 92, "users", ["conta única: organização, palestrante e", "participante · login por senha ou GitHub", "social_accounts · password_reset_tokens"], "global"))
b.append(box(25, 156, 310, 78, "speaker_profiles", ["perfil público do palestrante, vale em todas", "as edições · dados sensíveis cifrados à parte"], "global"))
b.append(box(25, 246, 310, 64, "communities", ["PHP-PB, PHPCE… dona das edições"], "global"))
b.append(box(25, 322, 310, 90, "suppliers + supplier_contacts", ["global DA COMUNIDADE: reaproveitado entre", "as edições dela, nunca visto por outra", "contato só para quem é de uma edição dele"], "global"))
b.append(box(25, 424, 310, 104, "infra do Laravel", ["personal_access_tokens (um por dispositivo)", "jobs, cache, sessions", "audit_logs: global, mas cada registro", "leva o edition_id"], "infra"))

b.append(group(380, 20, 710, 520, "Por edição (o tenant) · toda tabela tem edition_id + escopo global", "edition"))
b.append(box(395, 52, 230, 120, "editions", ["community_id · status (máquina", "de estados) · datas, cidade", "site_mode: plataforma ou", "site próprio"], "edition"))
b.append(box(640, 52, 435, 120, "Equipe e configuração", ["edition_members (papel na edição) · edition_invitations", "edition_settings (tema) · edition_domains", "cfp_settings · ticketing_settings · sponsor_settings", "email_templates · finance_categories"], "edition", size=9.8))
b.append(box(395, 186, 335, 110, "Conteúdo", ["cfp_tracks · cfp_criteria · proposals", "proposal_speakers · review_assignments", "reviews · speaker_confirmations · speaker_logistics"], "edition", size=9.8))
b.append(box(745, 186, 330, 110, "Vendas", ["ticket_types · ticket_batches · coupons", "orders · order_items · tickets", "payment_accounts · payment_charges", "webhook_events · payment_refunds"], "edition", size=9.8))
b.append(box(395, 310, 335, 96, "Organização", ["sponsors · sponsorships · sponsor_tiers", "edition_suppliers · supplier_quotes", "expenses · task_columns · tasks"], "edition", size=9.8))
b.append(box(745, 310, 330, 96, "Comunicação e operação", ["email_deliveries · email_unsubscribes", "ticket_imports · reconciliation_issues", "edition_clones"], "edition", size=9.8))
b.append(box(395, 420, 680, 108, "Como o isolamento é garantido", ["Model de edição usa o trait BelongsToEdition: liga o EditionScope e preenche edition_id ao criar.", "Sem edição no contexto, a consulta não devolve nada e criar sem edition_id lança LogicException.", "Ler outra edição de propósito exige ->forEdition($id) explícito (clonagem, rotinas agendadas).", "Comunidade A nunca vê edição, fornecedor ou dado da comunidade B."], "edition", size=9.8))
b.append(arrow([(335, 278), (365, 278), (365, 112), (395, 112)], "global"))
b.append(t(370, 200, "community_id", size=9.5, weight=600, color=K["global"][1]))
pages.append(page(3, "Tenancy", "Tenancy: comunidade, edição e o que é global",
    "ADR 0007: uma instalação e um banco. A edição é o tenant; usuários e perfis de palestrante são globais para guardar o histórico entre edições. Toda chave é UUID.",
    svg("\n".join(b), h=550),
    ["Por que não um banco por comunidade: a mesma pessoa palestra no PHPeste de um ano e compra ingresso no outro, e o perfil dela é um só.",
     "Dado de edição que vai para a próxima (\"criar edição a partir da anterior\") é copiado por um <code>EditionCloner</code> de cada módulo, nunca dado de pessoa."]))

# ============================================ 4 requisição encontra a edição
b = []
steps = [
    ("1 · Rota", ["Route::apiV1Edition('ticketing')", "/api/v1/editions/{edition}/…"], "backend"),
    ("2 · auth:sanctum", ["token Bearer válido", "(um por dispositivo)"], "backend"),
    ("3 · Papel na edição", ["edition_members tem a", "pessoa? senão 403"], "edition"),
    ("4 · CurrentEdition", ["liga a edição no contexto", "só durante a requisição"], "edition"),
    ("5 · Gate da permissão", ["Gate::authorize(", "'orders.refund')"], "backend"),
    ("6 · FormRequest → Action", ["valida, depois a Action", "muda o Model"], "backend"),
]
for i, (title, lines, kind) in enumerate(steps):
    x = 10 + i * 182
    b.append(box(x, 30, 168, 82, title, lines, kind))
    if i < len(steps) - 1:
        b.append(arrow([(x + 168, 71), (x + 182, 71)], "backend"))
b.append(box(10, 140, 530, 140, "Rotas de quem não é da equipe", ["Route::apiV1PublishedEdition('speaker', 'proposals', ['auth:sanctum'])", "→ /api/v1/speaker/editions/{edition}/proposals", "só aceita edição publicada (rascunho = 404) e liga a edição no contexto", "sem exigir papel. Usado por palestrante, participante e checkout.", "A posse do dado é conferida na Action (a proposta é dela?)."], "edition", size=9.8))
b.append(box(560, 140, 530, 140, "Rotas públicas e de máquina", ["/api/v1/public/resolve?host=…  site descobre a edição pelo domínio", "/api/v1/public/editions/{id}/theme  tema publicado com ETag", "/api/v1/webhooks/payments/{token} e /webhooks/resend  assinados", "/api/v1/sponsors/attachments/{id}/download  URL assinada", "CORS: só a origem do site próprio da edição, só em /public/*"], "infra", size=9.8))
b.append(box(10, 305, 530, 120, "Job e listener", ["recebem edition_id explícito no construtor e rodam dentro de", "resolve(CurrentEdition::class)->run($editionId, fn () => …)", "nunca herdam a edição de quem disparou: a fila roda depois,", "em outro processo, e a edição do contexto já não existe."], "backend", size=9.8))
b.append(box(560, 305, 530, 120, "Evento de domínio", ["carrega ids e enums, nunca Model de outro módulo", "ex.: EditionStatusChanged(editionId, from, to)", "ShouldDispatchAfterCommit: só sai se a transação gravou", "evento Auditable vira registro em audit_logs"], "backend", size=9.8))
b.append(box(10, 450, 1080, 80, "Front-public: a edição vem do host", ["O site chama /public/resolve com o host da requisição, recebe edition_id e o domínio principal e redireciona se for preciso (ADR 0008).", "O host é normalizado (minúsculas, sem porta, IDN em punycode) e nunca é usado para montar URL sem passar pela tabela edition_domains."], "client", size=9.8))
pages.append(page(4, "Tenancy na prática", "Como uma requisição encontra a edição",
    "Toda rota do painel passa pela mesma esteira; o módulo só escreve a regra de negócio. A edição entra no contexto e sai no fim, e o escopo global faz o resto.",
    svg("\n".join(b), h=540)))

# ===================================================== 5 ciclo de vida
b = []
states = [("Rascunho", "draft", 30), ("CFP aberto", "cfp_open", 245), ("Vendas abertas", "sales_open", 460), ("Em andamento", "ongoing", 675), ("Encerrada", "closed", 890)]
eff = {
    "draft": ["preparação, nada público"],
    "cfp_open": ["+ site e domínios públicos", "+ propostas na janela", "+ tema publicado vale", "! slug da URL travado"],
    "sales_open": ["+ venda de ingressos", "+ CFP segue a janela", "! exige gateway pronto", "  se houver lote pago"],
    "ongoing": ["+ check-in", "+ presença de palestrante", "! não volta a vender"],
    "closed": ["+ relatórios finais", "! só consulta", "! não reabre"],
}
for label, key, x in states:
    b.append(box(x, 50, 180, 150, label, eff[key], "edition", size=9.8))
for a, c in ((30, 245), (245, 460), (460, 675), (675, 890)):
    b.append(arrow([(a + 180, 125), (c, 125)], "edition"))
b.append(arrow([(120, 200), (120, 230), (550, 230), (550, 200)], "edition", label="rascunho pode ir direto para vendas", at=1, dy=14))
b.append(box(30, 270, 340, 130, "Quem decide a transição", ["A organização muda o status no painel.", "Cada módulo pode barrar a mudança com um", "EditionTransitionGuard (ex.: SalesReadinessGuard", "do Ticketing: lote pago sem conta de gateway", "pronta = não abre vendas)."], "backend", size=9.8))
b.append(box(385, 270, 340, 130, "Quem reage", ["EditionStatusChanged (auditado) dispara:", "Cfp: CfpOpened / CfpClosed, derivados da janela", "  e do status (cfp:sync-status a cada minuto)", "Branding: tema publicado passa a valer", "Ticketing: rotação de lotes e expiração"], "backend", size=9.8))
b.append(box(740, 270, 330, 130, "Criar a partir da anterior", ["Job CloneEdition: nova edição em rascunho,", "sem datas, e cada EditionCloner escolhido", "copia numa transação só: tema, domínios,", "equipe, CFP, e-mails, ingressos, cotas,", "fornecedores contratados, orçamento."], "backend", size=9.8))
b.append(box(30, 420, 1040, 110, "Visão geral da edição (um endpoint, vários módulos)", ["GET /editions/{id}/overview junta o que cada módulo devolve no seu EditionOverviewContributor: indicadores (ingressos, receita, propostas,", "palestrantes, patrocínio, fornecedores), pendências com caminho e filtro (propostas sem revisor, divergência na conciliação, entregas e despesas", "vencidas) e marcos. O Community filtra cada pendência pela permissão de quem vê: o voluntário não vê pendência de finanças."], "edition", size=9.8))
pages.append(page(5, "Edição", "Ciclo de vida da edição",
    "EditionStatus é uma máquina de estados com o que cada etapa libera e bloqueia (effects()). Só anda para a frente; o painel mostra a etapa atual e a próxima.",
    svg("\n".join(b), h=540)))

# ===================================================== 6 os 15 módulos
mods = [
    ("Identity", "contas, login (senha e GitHub), sessões por dispositivo, perfil e perfil de palestrante", 22, "backend"),
    ("Community", "comunidades, edições, estados, equipe, convites, tenancy, Visão geral, clonagem", 26, "backend"),
    ("Branding", "tema por edição (rascunho/publicado), presets, contraste AA, logos, domínios e TLS", 17, "backend"),
    ("Cfp", "chamada, trilhas, critérios, propostas, avaliação cega, decisão, cota do Nordeste, confirmação", 52, "backend"),
    ("Ticketing", "tipos, lotes, cupons, pedidos, estoque atômico, ingressos com QR, transferência, importação, conciliação", 50, "backend"),
    ("Payments", "contas de gateway por edição, drivers Asaas e Mercado Pago, cobranças, webhooks, reembolsos", 8, "backend"),
    ("Notifications", "e-mail transacional por edição com modelos editáveis, descadastro, Resend com bounce e spam", 12, "backend"),
    ("Audit", "trilha append-only das ações sensíveis, com redação de segredos", 1, "backend"),
    ("Sponsors", "cotas, funil, vagas, pagamentos, entregas, anexos por URL assinada", 28, "backend"),
    ("Suppliers", "cadastro da comunidade, orçamentos, contratação, anexos, reaproveitamento", 19, "backend"),
    ("Finance", "despesas, orçamento por categoria, receita calculada, saldo, planilha", 14, "backend"),
    ("Program", "salas, sessões e grade (B35–B37)", 0, "todo"),
    ("CheckIn", "leitura de QR, entrada, modo offline (B38–B39)", 0, "todo"),
    ("Tasks", "quadro kanban com colunas configuráveis, movimento seguro em paralelo, prazo, lembrete, vínculo a fornecedor e patrocinador", 10, "backend"),
    ("Engagement", "feedback, certificados, sorteio (B42)", 0, "todo"),
]
b = []
for i, (name, desc, routes, kind) in enumerate(mods):
    col, row = i % 3, i // 3
    x, y = 10 + col * 365, 20 + row * 104
    words, lines, cur = desc.split(" "), [], ""
    for w in words:
        if len(cur) + len(w) + 1 > 48:
            lines.append(cur)
            cur = w
        else:
            cur = (cur + " " + w).strip()
    lines.append(cur)
    b.append(box(x, y, 350, 94, name, lines[:3], kind, dashed=kind == "todo", badge=(f"{routes} rotas", "backend") if routes else ("casca", "todo"), size=9.8))
pages.append(page(6, "Backend", "Os 15 módulos do backend",
    "Cada módulo é um pacote Composer real em <code>backend/app-modules/&lt;nome&gt;</code> (ADR 0002), com namespace <code>PHPeste\\&lt;Nome&gt;</code>: Models, Actions, Contracts, Events, Http, Jobs. 260 rotas na API.",
    svg("\n".join(b), h=545)))

# ================================================= 7 como conversam
b = []
b.append(box(430, 20, 240, 96, "Community", ["EditionMemberships", "EditionDirectory", "pontos de extensão (tags) ↓"], "backend", center=True))
ext = [("EditionOverviewContributor", "números da Visão geral"), ("EditionCloner", "copiar para a próxima"), ("EditionTransitionGuard", "barrar mudança de status"), ("EditionParticipation", "quem participa (Identity)")]
for i, (name, what) in enumerate(ext):
    x = 20 + i * 270
    b.append(box(x, 150, 255, 52, name, [what], "edition", mono=False, size=9.5, title_size=11))
    b.append(arrow([(550, 116), (x + 127, 150)], "edition", dashed=True))
pairs = [
    (20, 240, "Cfp", ["→ UserAccounts, SpeakerProfiles (Identity)", "→ EditionMailer (Notifications)", "→ EditionSites (Branding)", "ReviewLedger ← quem conta avaliações"]),
    (390, 240, "Ticketing", ["→ PaymentProcessor, PaymentLedger", "→ PaymentAccounts (Payments), AuditTrail", "AccountUsage, TicketVerifier,", "TicketRevenue ← Payments, CheckIn, Finance"]),
    (760, 240, "Payments", ["→ PaymentGateway (driver Asaas/MP)", "ChargeStatusChanged → Ticketing", "RefundAccepted → Ticketing", "sem acesso a pedidos: só referência"]),
    (20, 395, "Finance", ["→ TicketRevenue (Ticketing)", "→ SponsorRevenue (Sponsors)", "→ PaymentAccounts::all (Payments)", "→ EditionSuppliers (Suppliers)"]),
    (390, 395, "Sponsors · Suppliers", ["→ EditionMemberships (responsável)", "→ UserAccounts (nomes)", "Suppliers → EditionDirectory::communityId", "para não vazar entre comunidades"]),
    (760, 395, "Audit · Notifications", ["Auditable: qualquer evento vira registro", "pelo listener genérico RecordAuditableEvent", "AuditTrail: histórico de um registro", "EditionMailer::send(...): todo e-mail,", "com o tema da edição"]),
]
for x, y, title, lines in pairs:
    b.append(box(x, y, 340, 130, title, lines, "backend", size=9.8))
pages.append(page(7, "Backend", "Como os módulos conversam",
    "Módulo nunca usa model de outro: ou chama um contrato (interface em <code>Contracts/</code>, ligada no service provider) ou escuta um evento. 26 contratos hoje. O arch test barra dependência de <code>App\\</code>.",
    svg("\n".join(b), h=540),
    ["Ponto de extensão é registrado com <code>$app-&gt;tag([...], Contrato::TAG)</code>: o Community não conhece os módulos, só a tag. Módulo novo aparece na Visão geral e na clonagem sem mexer no Community. O mesmo vale para <code>TaskLinkProvider</code> (Tasks): Suppliers e Sponsors registram o que uma tarefa pode apontar, e o Program vai registrar a sessão."]))

# ====================================================== 8 banco
dom = [
    ("Identity", ["users", "social_accounts", "password_reset_tokens", "speaker_profiles", "speaker_private_details"], "global"),
    ("Community", ["communities", "editions", "edition_members", "edition_invitations", "edition_clones"], "edition"),
    ("Branding", ["edition_settings", "edition_domains"], "edition"),
    ("Cfp", ["cfp_settings", "cfp_tracks", "cfp_criteria", "proposals", "proposal_speakers", "review_assignments", "reviews", "review_scores", "speaker_confirmations", "speaker_logistics"], "edition"),
    ("Ticketing", ["ticketing_settings", "ticket_types", "ticket_batches", "coupons", "coupon_redemptions", "orders", "order_items", "tickets", "ticket_transfers", "reconciliation_issues", "ticket_imports", "ticket_import_rejections"], "edition"),
    ("Payments", ["payment_accounts", "payment_charges", "webhook_events", "payment_refunds"], "edition"),
    ("Notifications", ["email_templates", "email_deliveries", "email_unsubscribes", "email_suppressions", "email_webhook_events ●"], "edition"),
    ("Audit", ["audit_logs"], "infra"),
    ("Sponsors", ["sponsor_settings", "sponsor_tiers", "sponsors", "sponsorships", "sponsorship_payments", "sponsor_deliverables", "sponsor_contacts", "sponsor_attachments"], "edition"),
    ("Suppliers", ["suppliers ●", "supplier_contacts ●", "edition_suppliers", "supplier_quotes", "supplier_attachments", "supplier_activities"], "edition"),
    ("Finance", ["finance_categories", "expenses"], "edition"),
    ("Tasks", ["task_columns", "tasks"], "edition"),
    ("Laravel", ["personal_access_tokens", "jobs · job_batches", "failed_jobs", "cache · cache_locks", "sessions"], "infra"),
]
b = []
cols = [10, 285, 560, 835]
heights = [0, 0, 0, 0]
for name, tables, kind in dom:
    col = heights.index(min(heights))
    h = 30 + len(tables) * 14 + 8
    count = {"Laravel": 7}.get(name, len(tables))
    b.append(box(cols[col], 12 + heights[col], 260, h, f"{name} · {count}", tables, kind, mono=True, size=9.5, line_h=14))
    heights[col] += h + 12
pages.append(page(8, "Dados", "Banco de dados por domínio",
    "MySQL 8, 69 tabelas, todas com UUID como chave. Verde é da edição (tem <code>edition_id</code>); azul é global. ● em Suppliers: global da comunidade (<code>community_id</code>); em Notifications: global, a edição vem do envio.",
    svg("\n".join(b), h=max(heights) + 20),
    ["Dinheiro sempre em centavos (inteiro). Dado sensível cifrado no banco: CPF de quem compra, credenciais do gateway, acessibilidade, corpo de webhook, linhas de importação. <code>APP_PREVIOUS_KEYS</code> + comandos <code>*:reencrypt-*</code> para trocar a chave.",
     "Estoque e cupom nunca são lidos e depois gravados: <code>UPDATE … WHERE allocated + q &lt;= quantity</code>. Testes com 12 processos simultâneos provam que não vende além do lote."]))

# ====================================================== 9 papéis
perms = [
    ("edition.manage", "Configurar edição e identidade", "●", "", "", "", ""),
    ("edition.team", "Gerenciar equipe e convites", "●", "", "", "", ""),
    ("cfp.review", "Avaliar propostas", "●", "", "●", "", ""),
    ("program.manage", "Gerenciar palestrantes e grade", "●", "", "", "", ""),
    ("speakers.private", "Ver acessibilidade e alimentação", "●", "", "", "", ""),
    ("orders.view", "Ver pedidos e pagamentos", "●", "●", "", "", ""),
    ("orders.refund", "Reembolsar pedidos", "●", "●", "", "", ""),
    ("payments.gateway", "Editar chaves do gateway", "●", "", "", "", ""),
    ("checkin.scan", "Fazer check-in", "●", "", "", "●", ""),
    ("sponsors.manage", "Gerenciar patrocinadores", "●", "", "", "", ""),
    ("suppliers.manage", "Gerenciar fornecedores", "●", "", "", "", ""),
    ("finance.manage", "Ver e editar finanças", "●", "●", "", "", ""),
    ("tasks.manage", "Gerenciar tarefas (a equipe vê o quadro e move as próprias)", "●", "", "", "", ""),
]
content = '<div class="two">' + table(["Permissão", "O que libera", "Organizador", "Financeiro", "Avaliador", "Check-in", "Voluntário"], [(f"<code>{p}</code>", d, *r) for p, d, *r in perms], "cmp perms") + """
<div class="side">
<h3>Como funciona</h3>
<ul>
<li>O papel é por edição (<code>edition_members</code>): a mesma pessoa pode organizar uma edição e só avaliar outra.</li>
<li>Papel e permissão vivem em código (<code>EditionRole::permissions()</code>). Cada permissão vira um Gate com o próprio nome.</li>
<li>Policy e Gate conferem <b>permissão</b>, nunca papel: <code>Gate::authorize('orders.refund')</code>.</li>
<li>Outro módulo pergunta pelo contrato: <code>EditionMemberships::can($edition, $user, $permissão)</code>.</li>
<li>A edição nunca fica sem organizador: trocar o papel ou remover o último é bloqueado.</li>
<li>O painel lê a tabela em <code>GET /roles</code> e monta o menu por papel.</li>
<li>Palestrante e participante não têm papel: entram pelas rotas de edição publicada.</li>
</ul></div></div>"""
pages.append(page(9, "Acesso", "Papéis e permissões", "Cinco papéis por edição e treze permissões. O organizador tem todas.", content))

# ============================================= 10 ingresso: compra ao QR
b = []
lanes = [("Quem compra (site)", "client"), ("Ticketing", "backend"), ("Payments", "backend"), ("Gateway", "external"), ("Fila e rotinas", "infra")]
for i, (label, kind) in enumerate(lanes):
    y = 18 + i * 104
    b.append(f'<rect x="10" y="{y}" width="1080" height="96" rx="10" fill="{K[kind][0]}" fill-opacity="0.45" stroke="{K[kind][1]}" stroke-width="1" stroke-dasharray="5 4"/>')
    b.append(t(20, y + 16, label, size=10.5, weight=700, color=K[kind][1]))
flow = [
    (0, 30, "Escolhe ingressos", ["tipo, lote, cupom,", "participantes"]),
    (1, 180, "PlaceOrder", ["reserva estoque e cupom", "numa transação", "Idempotency-Key"]),
    (0, 330, "Pix ou cartão", ["sem dado de cartão", "na nossa API"]),
    (2, 330, "ChargeService", ["cobrança na conta da", "edição · trava clique duplo"]),
    (3, 480, "Asaas / Mercado Pago", ["Pix com QR, cartão", "na fatura ou token"]),
    (3, 680, "Webhook assinado", ["→ /webhooks/payments/{t}"]),
    (2, 680, "webhook_events", ["caixa de entrada, 2xx", "antes de processar"]),
    (4, 880, "ProcessWebhookEvent", ["consulta o gateway:", "não confia no corpo"]),
    (1, 860, "Pedido pago", ["TransitionOrder", "IssueTicketsWhenPaid"]),
    (0, 860, "E-mail + QR", ["QR assinado (HMAC)", "/ingressos no site"]),
]
for lane, x, title, lines in flow:
    b.append(box(x, 18 + lane * 104 + 24, 170 if x < 860 or lane == 4 else 210, 66, title, lines, lanes[lane][1], size=9.5, title_size=11))
b.append(arrow([(115, 108), (200, 146)], "backend"))
b.append(arrow([(350, 146), (400, 108)], "client"))
b.append(arrow([(420, 108), (420, 250)], "backend"))
b.append(arrow([(500, 284), (565, 354)], "external"))
b.append(arrow([(650, 384), (680, 384)], "external", dashed=True))
b.append(arrow([(765, 354), (765, 316)], "external", dashed=True))
b.append(arrow([(850, 283), (930, 458)], "infra", dashed=True, label="job na fila", at=0, dy=-4, label_anchor="start"))
b.append(arrow([(1030, 458), (1030, 212)], "infra", dashed=True, label="sync", at=0, dy=0, label_anchor="start"))
b.append(arrow([(960, 146), (960, 108)], "backend"))
b.append(box(30, 458, 600, 70, "Rotinas que fecham as pontas", ["ticketing:expire-orders (1/min) devolve estoque de reserva vencida · ticketing:rotate-batches abre o próximo lote", "payments:retry-webhooks (1, 5, 15, 60 min) · ticketing:reconcile (diário) grava divergência com o gateway"], "infra", size=9.5, title_size=11))
pages.append(page(10, "Fluxo", "Ingresso: da compra ao QR",
    "O pedido nasce reservando estoque; o pagamento só confirma quando a API consulta o gateway. Evento falso, repetido ou fora de ordem não muda pedido.",
    svg("\n".join(b), h=540),
    ["Reembolso: <code>PaymentLedger::refund</code> reserva o valor antes de chamar o gateway (dois reembolsos ao mesmo tempo nunca passam do pago). Pedido importado de outra plataforma entra pago, sem cobrança, e não passa por reembolso nem conciliação. Pagamento recebido fora do gateway é marcado à mão pela organização (<code>MarkOrderPaid</code>), com justificativa na auditoria, emissão dos ingressos e cancelamento da cobrança aberta."]))

# ================================================== 11 CFP
b = []
cfp = [
    ("Configuração", ["janela, trilhas com", "vagas, critérios com", "peso, cota Nordeste,", "avaliação anônima"]),
    ("Submissão", ["palestrante com perfil", "global · rascunho e", "envio na janela · até", "3 coautores · limite"]),
    ("Atribuição", ["manual ou automática", "(menor carga primeiro)", "sem conflito: autoria", "ou mesma empresa"]),
    ("Avaliação", ["notas 1–5 por critério", "nota ponderada na hora", "revisor não vê autor", "quando é anônima"]),
    ("Decisão", ["ranking por trilha", "respeitando vagas · simula", "cobertura por UF e % do", "Nordeste antes de gravar"]),
    ("Confirmação", ["e-mail com prazo", "lembrete 48 h antes", "recusa libera vaga e", "sugere lista de espera"]),
]
for i, (title, lines) in enumerate(cfp):
    x = 10 + i * 182
    b.append(box(x, 30, 168, 120, title, lines, "edition", size=9.6))
    if i < len(cfp) - 1:
        b.append(arrow([(x + 168, 90), (x + 182, 90)], "edition"))
b.append(box(10, 180, 350, 130, "Status do CFP é derivado, nunca editado", ["CfpStatus vem da janela + status da edição.", "cfp:sync-status (1/min) e o listener de", "EditionStatusChanged disparam CfpOpened e", "CfpClosed uma vez cada."], "backend", size=9.8))
b.append(box(375, 180, 350, 130, "Convite direto", ["organização convida pelo e-mail: cria a conta", "sem senha se precisar, proposta já aprovada", "(origin = invited), fora da avaliação mas", "ocupando vaga da trilha."], "backend", size=9.8))
b.append(box(740, 180, 350, 130, "Depois do aceite", ["logística por palestrante (viagem, hospedagem)", "quadro de palestrantes com os 9 estados do", "Nordeste · acessibilidade e alimentação só", "com speakers.private · presença no evento"], "backend", size=9.8))
b.append(box(10, 335, 1080, 90, "Próximo passo: grade (Program, B35–B37)", ["Propostas confirmadas viram sessões em salas e horários, com validação de conflito; a programação sai pela API pública com cache, e quem vai ao evento", "monta a agenda pessoal. É o que falta para o Marco 3."], "todo", dashed=True, size=9.8))
b.append(box(10, 445, 1080, 85, "Telas prontas no painel", ["Configurar CFP (A10) · Avaliar propostas com teclado (A11) · Palestrantes: lista, cobertura e detalhe (A12) · no site público: formulário de", "proposta e área do palestrante com entrar pelo GitHub (P03, P11)."], "client", size=9.8))
pages.append(page(11, "Fluxo", "CFP: da proposta à grade",
    "A chamada de trabalhos é o coração do Marco 1. O estatuto do PHPeste pede pelo menos metade de palestrantes do Nordeste e um de cada estado; a decisão mostra isso antes de gravar.",
    svg("\n".join(b), h=540)))

# ================================================== 12 dinheiro
b = []
b.append(box(10, 30, 330, 170, "Sponsors", ["cotas: valor, vagas, contrapartidas", "funil: prospecção → negociando →", "fechado → pago · perdido com motivo", "fechar acima das vagas exige confirmação", "pagamento registrado à mão; cobrir o valor", "passa para pago sozinho", "+ contrapartida vira entrega com prazo"], "backend", size=9.6))
b.append(box(385, 30, 330, 170, "Suppliers", ["cadastro global DA COMUNIDADE", "na edição: a contatar → em orçamento →", "contratado · recusado com motivo", "orçado = menor orçamento válido", "contato só para quem é de uma edição", "em que ele aparece", "+ reaproveitar de edição anterior"], "backend", size=9.6))
b.append(box(760, 30, 330, 170, "Ticketing", ["pedidos pagos e reembolsados com valor", "taxa real (conciliação) ou estimada", "pelo percentual da edição", "por conta de recebimento", "(CPF ou CNPJ, inclusive conta trocada)", "pedido gratuito e importado ficam fora"], "backend", size=9.6))
b.append(box(300, 270, 500, 180, "Finance: calculado na hora, nunca guardado", ["receita de ingressos = pagos − reembolsos − taxas", "receita total = ingressos líquido + patrocínio recebido", "saldo = receita − despesas pagas", "previsão = saldo + patrocínio a receber − despesas a pagar", "orçamento por categoria × comprometido × pago", "despesas com fornecedor e comprovante por URL assinada", "planilha XLSX (4 abas) e CSV"], "edition", size=9.8))
b.append(arrow([(175, 200), (400, 270)], "backend", label="SponsorRevenue", at=0, dy=-2))
b.append(arrow([(550, 200), (550, 270)], "backend", label="EditionSuppliers", at=0, dy=4, label_anchor="start"))
b.append(arrow([(925, 200), (700, 270)], "backend", label="TicketRevenue", at=0, dy=-2))
b.append(box(10, 470, 1080, 60, "ADR 0009: a plataforma não recebe nem repassa dinheiro", ["Cada edição cadastra a conta do próprio gateway; a API valida as chaves e um webhook assinado antes de abrir vendas. Credenciais cifradas; a API só mostra os 4 últimos caracteres."], "alert", size=9.8))
pages.append(page(12, "Fluxo", "Dinheiro: patrocínio, fornecedores e finanças",
    "Patrocínio, fornecedores e ingressos expõem os números por contratos; o Finance só soma. O teste de propriedade confere, em 25 rodadas aleatórias, que a receita bate com os pedidos pagos menos reembolsos.",
    svg("\n".join(b), h=540)))

# ================================================== 13 fila, rotina, email, auditoria
sched = [
    ("* * * * *", "cfp:sync-status", "abre e fecha o CFP pela janela"),
    ("0 * * * *", "cfp:confirmation-deadlines", "lembra 48 h antes e marca sem resposta"),
    ("* * * * *", "ticketing:expire-orders", "expira reserva vencida e devolve estoque"),
    ("* * * * *", "ticketing:rotate-batches", "encerra lote esgotado e abre o próximo"),
    ("*/5 * * * *", "ticketing:resume-imports", "retoma importação parada"),
    ("04:00 BRT", "ticketing:reconcile", "confere pedidos com o gateway"),
    ("* * * * *", "payments:retry-webhooks", "reenfileira webhook com erro (1, 5, 15, 60 min)"),
    ("08:00 BRT", "tasks:due-reminders", "avisa o responsável da tarefa que vence até amanhã"),
    ("03:30", "audit:prune", "apaga trilha além de AUDIT_RETENTION_DAYS"),
]
content = '<div class="two">' + table(["Quando", "Comando", "Para quê"], [(f"<code>{a}</code>", f"<code>{c}</code>", d) for a, c, d in sched], "cmp sched") + """
<div class="side">
<h3>Fila (Redis + Horizon)</h3>
<ul>
<li>Job recebe <code>edition_id</code> explícito e roda em <code>CurrentEdition::run</code>.</li>
<li><code>afterCommit()</code>: o job só entra na fila se a transação gravou.</li>
<li>Importação grande: um lote de linhas por job, posição em bytes gravada com o lote, retoma do ponto exato.</li>
</ul>
<h3>E-mail (Notifications)</h3>
<ul>
<li>Todo e-mail sai por <code>EditionMailer::send</code>: registra em <code>email_deliveries</code> e enfileira com 5 tentativas.</li>
<li>Modelo editável por edição, variáveis <code>{nome}</code> sem Blade, HTML escapado, layout com o tema publicado.</li>
<li>Não transacional leva descadastro com link assinado e <code>List-Unsubscribe</code>.</li>
<li>Webhook do Resend (assinatura Svix): entregue, bounce e spam. Bounce permanente ou spam bloqueia o endereço na edição, até para transacional, até a organização liberar.</li>
</ul>
<h3>Auditoria</h3>
<ul>
<li>Evento que implementa <code>Auditable</code> vira linha em <code>audit_logs</code> pelo listener genérico. 23 eventos hoje.</li>
<li>Tela que mostra histórico (ex.: o pedido) lê pelo contrato <code>AuditTrail::forSubject</code>, sem consultar a tabela de outro módulo.</li>
<li>Segredo removido, dado pessoal mascarado; o model recusa update e delete.</li>
</ul></div></div>"""
pages.append(page(13, "Operação", "Fila, rotinas agendadas, e-mail e auditoria", "O que roda sem ninguém clicar. Todas as rotinas usam <code>withoutOverlapping()</code> e são idempotentes: rodar duas vezes não muda o resultado.", content))

# ================================================== 14 segurança
sec = [
    ("Autenticação", "Sanctum Bearer, um token por dispositivo com expiração (<code>SANCTUM_EXPIRATION_MINUTES</code>); revogar é imediato. Senha mínima de 10 e checagem de vazamento."),
    ("Isolamento", "Escopo global por edição; outra comunidade recebe 404; arch test impede módulo de depender de <code>App\\</code> e controller de usar <code>$request-&gt;all()</code>."),
    ("Rate limit", "Limiters nomeados: <code>login</code>, <code>password-reset</code>, <code>checkout</code>, <code>webhooks</code>, configuráveis por <code>.env</code>."),
    ("Headers e CORS", "CSP, HSTS, nosniff, Referrer-Policy, Permissions-Policy. CORS por ambiente e, em <code>/public/*</code>, só a origem do site próprio da edição."),
    ("Dados sensíveis", "Cifrados no banco (CPF, credenciais, acessibilidade, webhooks). Logs JSON com <code>password</code>, <code>token</code>, <code>secret</code> e cartão redigidos automaticamente."),
    ("Arquivos", "Disco privado; download só por URL assinada que expira (10 min), sempre como anexo, com nosniff e CSP sandbox. SVG sanitizado."),
    ("Pagamento", "Sem dado de cartão na API; webhook com assinatura do driver e reconsulta ao gateway; idempotência no banco (<code>unique(edition_id, idempotency_key)</code>)."),
    ("Segredos", "ADR 0006: nada de segredo no repositório; <code>APP_PREVIOUS_KEYS</code> para trocar a chave sem perder o que já foi cifrado."),
    ("LGPD", "Encarregado e política por edição, anonimização após o evento (B43 em aberto). Decisão em aberto: comunidade controladora, PHPeste operador."),
]
pages.append(page(14, "Segurança", "Segurança", "As travas que valem para todo módulo. A maioria é verificada por teste.", table(["Tema", "Como está"], sec, "cmp sec")))

# ================================================== 15 dev, CI, deploy
b = []
b.append(group(10, 20, 520, 270, "Desenvolvimento (infra/docker-compose.yml)", "infra"))
b.append(box(25, 52, 160, 64, "backend", ["Sail PHP 8.4", "porta 8000"], "backend"))
b.append(box(195, 52, 160, 64, "horizon", ["mesma imagem"], "backend"))
b.append(box(365, 52, 150, 64, "scheduler", ["mesma imagem"], "backend"))
b.append(box(25, 128, 160, 64, "MySQL 8", ["volume local"], "data"))
b.append(box(195, 128, 160, 64, "Redis", ["cache e fila"], "data"))
b.append(box(365, 128, 150, 64, "Mailpit", ["e-mail em :8025"], "external"))
b.append(box(25, 204, 490, 74, "infra/dev.sh up | down | reset | shell | logs", ["sobe os seis serviços (cria infra/.env na primeira vez)", "front-admin e front-public rodam com npm run dev"], "infra"))
b.append(group(560, 20, 530, 270, "CI (GitHub Actions)", "infra"))
b.append(box(575, 52, 245, 105, "backend-ci", ["Pint (estilo)", "Larastan nível 8", "Pest (~1070 testes)", "composer audit"], "infra"))
b.append(box(830, 52, 245, 105, "front-admin-ci · front-public-ci", ["lint, typecheck, testes", "unitários e build; e2e na CI", "do front-public; o do painel", "(82 testes) roda local"], "infra", title_size=11))
b.append(box(575, 170, 500, 108, "composer check (local, antes de abrir PR)", ["pint + phpstan + rector --dry-run + pest", "Teste de concorrência com processos PHP reais (estoque, cupom,", "reembolso, idempotência), SQLite com transaction_mode IMMEDIATE"], "infra", size=9.8))
b.append(group(10, 310, 1080, 220, "Produção (planejado, B45)", "todo"))
b.append(box(25, 342, 250, 110, "Caddy", ["TLS automático do domínio", "da plataforma e on-demand", "para domínios de edição", "(pergunta à API: tls-check)"], "infra"))
b.append(box(290, 342, 250, 110, "API + Horizon + scheduler", ["mesma imagem", "variáveis por ambiente", "Sentry com DSN"], "backend"))
b.append(box(555, 342, 250, 110, "MySQL + Redis", ["backup e restauração", "ainda a definir"], "data", dashed=True))
b.append(box(820, 342, 255, 110, "Resend + gateways", ["RESEND_API_KEY", "contas de produção", "de cada edição"], "external"))
b.append(box(25, 464, 1050, 54, "Em aberto para o evento", ["teste de carga, backups, runbook do dia do evento (B45) · dados de demonstração e documentação publicada (B46)"], "todo", dashed=True, size=9.8))
pages.append(page(15, "Operação", "Desenvolvimento, CI e deploy",
    "Ambiente local em Docker Compose (ADR 0003); a CI roda o mesmo <code>composer check</code>. O deploy de produção ainda está no plano do Marco 4.",
    svg("\n".join(b), h=540)))

# ================================================== 16 telas design
pages.append(page(16, "Telas", "Telas: o design do painel",
    "Referências de design do painel (tema Sertão, cores, tipografia Lexend) que o front-admin segue; o protótipo completo está no link de design do README. O tema é por edição: a comunidade escolhe um preset (Sertão, Maré, Brasa) e ajusta as cores com checagem de contraste AA.",
    """<div class="shots two-shots">
<figure><img src="../../design/admin-visao-geral.png" alt="Design da Visão geral do painel"><figcaption>Design: Visão geral da edição</figcaption></figure>
<figure><img src="../../design/tema-sertao-tokens.png" alt="Tokens do tema Sertão"><figcaption>Design: tokens do tema Sertão</figcaption></figure>
</div>"""))

# ================================================== 17 telas painel
pages.append(page(17, "Telas", "Telas: o painel em uso",
    "Capturas do front-admin rodando, com a API simulada (<code>front-admin/e2e/capturas-arquitetura.spec.ts</code>). O menu muda pelo papel: o avaliador só vê Visão geral e Avaliar propostas.",
    """<div class="shots mosaic">
<figure class="big"><img src="../telas/painel-visao-geral.png" alt="Visão geral"><figcaption>Organizador: Visão geral, com etapa, indicadores, pendências e meta do Nordeste</figcaption></figure>
<figure><img src="../telas/painel-avaliacao-cfp.png" alt="Avaliação de propostas"><figcaption>Avaliador: fila de propostas com nota por critério, sem ver o autor</figcaption></figure>
<figure><img src="../telas/painel-login.png" alt="Login"><figcaption>Entrar com e-mail e senha ou GitHub</figcaption></figure>
</div>"""))

# ================================================== 18 telas de vendas
# Cada tela do Marco 2 que fica pronta entra aqui (prints de front-admin/e2e/capturas-arquitetura.spec.ts).
SALES_SCREENS = [
    ("painel-ingressos.png", "Ingressos: tipos e lotes com o preço, o que a edição recebe depois da taxa do gateway, vendidos e situação. Cupons e regras da venda nas outras abas."),
    ("painel-pedidos.png", "Pedidos: números da edição no topo (pagos, aguardando, reembolsos, taxa do gateway), busca e filtro por situação, e o detalhe com itens, pagamento, reembolsos e histórico (auditoria). Reembolso com aviso de taxa e confirmação explícita; marcar como pago exige justificativa."),
    ("painel-pagamentos.png", "Pagamentos (só a organização): gateway da comunidade com chave mascarada, etapas para abrir vendas e Testar conexão, prévia do recebedor no checkout, métodos aceitos, webhook com o último evento e os últimos eventos do gateway com reprocessar."),
]
pages.append(page(18, "Telas", "Telas: vendas e organização",
    "As telas do Marco 2 conforme ficam prontas. Mesma fonte dos outros prints: o painel rodando com a API simulada.",
    '<div class="shots sales">' + "".join(f'<figure><img src="../telas/{img}" alt=""><figcaption>{escape(caption)}</figcaption></figure>' for img, caption in SALES_SCREENS) + "</div>"))

# ================================================== 19 status
status = [
    ("Marco 0 · Fundação", "Concluído", "esqueleto Laravel 13, módulos, Docker, convenções de API, segurança base, painel e componentes"),
    ("Marco 1 · Núcleo e CFP", "Backend concluído", "Identity, Community, Branding, Notifications, CFP inteiro, Audit; telas A05–A12, A24, A25; site P01, P03"),
    ("Marco 2 · Ingressos", "Backend concluído, telas em andamento", "backend: ingressos, cupons, pedidos, Asaas, Mercado Pago, webhooks, reembolso, conciliação, QR, importação, patrocínio, fornecedores, finanças, tarefas, webhooks do Resend; telas prontas: A14 (ingressos), A15 (pedidos e reembolso), A16 (pagamentos e gateway); faltam A17–A23 e P04–P06"),
    ("Marco 3 · Evento pronto", "A fazer", "grade e programação (B35–B37), check-in com modo offline (B38–B39), push (B40), API pública e checkout embutível (B41), site da edição (P02, P07–P09)"),
    ("Marco 4 · Operação", "A fazer", "engajamento (B42), LGPD (B43), relatórios (B44), carga, backups e runbook (B45), dados de demonstração (B46), acessibilidade e e2e (A26, A27)"),
]
pages.append(page(19, "Roadmap", "Onde estamos e o que falta",
    "Os milestones do GitHub e o board <b>PHPeste Platform</b> são a fonte da verdade do andamento; esta página é o retrato no dia da geração.",
    table(["Marco", "Situação", "O que tem"], [(f"<b>{a}</b>", b_, c) for a, b_, c in status], "cmp roadmap")))

# =================================================================== HTML
CSS = """
@page { size: A4 landscape; margin: 0; }
* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; background: #fff; }
body { font-family: 'Lexend', 'Inter', -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; color: #2B1408; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
code { font-family: 'JetBrains Mono', 'SF Mono', Menlo, monospace; font-size: .88em; background: #F7EFE3; padding: 0 3px; border-radius: 3px; }
.page { width: 297mm; height: 210mm; padding: 10mm 13mm 8mm; display: flex; flex-direction: column; page-break-after: always; break-after: page; position: relative; overflow: hidden; }
.page::before { content: ""; position: absolute; left: 0; right: 0; top: 0; height: 2.2mm; background: repeating-linear-gradient(90deg, #E8B23A 0 6mm, #B4441B 6mm 12mm); }
.page:last-child { page-break-after: auto; break-after: auto; }
header { margin-bottom: 3mm; }
.kicker { font-size: 9.5pt; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #B4441B; }
h1 { font-size: 19pt; margin: 1.5mm 0 1mm; letter-spacing: -.01em; color: #3A1708; }
.sub { margin: 0; font-size: 10.3pt; color: #5B4636; max-width: 262mm; line-height: 1.4; }
.content { flex: 1; min-height: 0; display: flex; align-items: center; justify-content: center; }
.content svg { width: 100%; height: 100%; max-height: 100%; }
.notes { margin: 2mm 0 0; padding: 0 0 0 4.5mm; font-size: 8.8pt; color: #5B4636; line-height: 1.38; }
.notes li { margin: .4mm 0; }
footer { display: flex; justify-content: space-between; font-size: 8pt; color: #A08B78; border-top: 1px solid #EADBC8; padding-top: 2mm; margin-top: 2.5mm; }
.cover { background: linear-gradient(135deg, #FBF4E6 0%, #FFFFFF 60%); }
.cover-top { margin-top: 4mm; }
.cover-title { font-size: 28pt; line-height: 1.12; margin: 3mm 0 3mm; }
.cover-sub { font-size: 11pt; color: #5B4636; max-width: 240mm; line-height: 1.45; margin: 0; }
.cover-grid { display: grid; grid-template-columns: 1.4fr 1fr; gap: 6mm; margin-top: 5mm; }
.cover-card { border: 1px solid #EADBC8; border-radius: 12px; padding: 4mm 5mm; background: #fff; }
.cover-card h3, .cover-legend h3 { margin: 0 0 2mm; font-size: 11pt; }
.cover-card ol { margin: 0; padding-left: 5mm; font-size: 9.3pt; line-height: 1.4; }
.cover-card ol li { margin: .8mm 0; }
.toc { font-size: 8.8pt !important; }
.toc li { margin: .2mm 0 !important; }
.toc li span:first-child { display: inline-block; width: 85%; }
.toc li span:last-child { float: right; color: #A08B78; font-weight: 600; }
.cover-legend { margin-top: 4mm; }
.cover-legend svg { width: 100%; height: auto; max-height: 26mm; }
.cover footer { margin-top: auto; }
table.cmp { width: 100%; border-collapse: collapse; font-size: 9.4pt; align-self: flex-start; }
table.cmp th, table.cmp td { text-align: left; padding: 1.6mm 2.4mm; border-bottom: 1px solid #EADBC8; vertical-align: top; line-height: 1.35; }
table.cmp th { background: #3A1708; color: #FBF4E6; font-weight: 700; font-size: 9pt; }
table.cmp tr:nth-child(even) td { background: #FBF6EE; }
table.perms { font-size: 8.6pt; }
table.perms th, table.perms td { padding: 1.1mm 2mm; }
table.perms td:nth-child(n+3), table.perms th:nth-child(n+3) { text-align: center; width: 17mm; }
table.perms td:nth-child(n+3) { color: #6B2A14; font-weight: 700; }
table.perms td:nth-child(2) { width: 58mm; }
table.sec td:first-child, table.roadmap td:first-child { width: 42mm; font-weight: 700; }
table.roadmap td:nth-child(2) { width: 42mm; color: #6B2A14; font-weight: 600; }
.two { display: grid; grid-template-columns: 1.85fr 1fr; gap: 7mm; width: 100%; align-self: flex-start; }
.side { font-size: 9.2pt; line-height: 1.4; }
.side h3 { font-size: 10.5pt; margin: 0 0 1mm; color: #6B2A14; }
.side ul { margin: 0 0 3mm; padding-left: 4.5mm; }
.side li { margin: .6mm 0; }
.shots { display: grid; gap: 5mm; width: 100%; height: 100%; align-items: start; }
.two-shots { grid-template-columns: 1fr 1fr; }
.mosaic { grid-template-columns: 1.05fr 1fr; grid-template-rows: 1fr 1fr; }
.sales { grid-template-columns: repeat(3, 1fr); }
.sales figure img { aspect-ratio: 1440 / 1000; object-fit: cover; object-position: top; }
.mosaic .big { grid-row: span 2; }
.mosaic figure:not(.big) img { max-height: 64mm; }
figure { margin: 0; display: flex; flex-direction: column; gap: 1.5mm; min-height: 0; }
figure img { width: 100%; max-height: 145mm; object-fit: contain; object-position: top; border: 1px solid #EADBC8; border-radius: 8px; background: #FBF4E6; }
figcaption { font-size: 8.8pt; color: #5B4636; }
"""

OUT.write_text(f"""<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8">
<title>Arquitetura PHPeste Platform</title>
<style>{CSS}</style></head>
<body>
{"".join(pages)}
</body></html>
""", encoding="utf-8")
print(f"ok: {OUT} ({len(pages)} páginas)")
