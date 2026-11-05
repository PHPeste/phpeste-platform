<script setup lang="ts">
import { ref } from 'vue'
import { Inbox } from '@lucide/vue'
import { useToast } from '@/application/shared/composables/useToast'

definePageMeta({ layout: 'blank', auth: false })

// Catálogo do kit de UI. Só existe em desenvolvimento (ou com NUXT_PUBLIC_UI_CATALOG=true,
// usado pelos testes e2e): em produção devolve 404.
if (!import.meta.dev && !useRuntimeConfig().public.uiCatalog) {
  throw createError({ statusCode: 404, statusMessage: 'Página não encontrada', fatal: true })
}

const toast = useToast()

const tab = ref('geral')
const tabs = [
  { id: 'geral', label: 'Geral' },
  { id: 'trilhas', label: 'Trilhas' },
  { id: 'criterios', label: 'Critérios' },
]
const page = ref(2)
const modalOpen = ref(false)
const drawerOpen = ref(false)
const chipA = ref(true)
const chipB = ref(false)
const text = ref('')
const textError = ref('')
const email = ref('livia@comunidade')
const area = ref('')
const choice = ref('')
const check = ref(true)
const date = ref('2027-10-08')
const time = ref('09:00')
const options = [
  { value: 'cfp', label: 'CFP aberto' },
  { value: 'vendas', label: 'Vendas abertas' },
  { value: 'evento', label: 'Em andamento' },
]
const statuses = ['ok', 'warn', 'danger', 'info', 'neutral'] as const
const statusText = { ok: 'Pago', warn: 'Aguardando', danger: 'Recusado', info: 'Rascunho', neutral: 'Encerrado' }
</script>

<template>
  <main class="mx-auto flex max-w-5xl flex-col gap-10 p-6 pb-24">
    <PhStripe />
    <header class="flex flex-col gap-1">
      <h1 class="text-3xl font-semibold">Componentes do tema Sertão</h1>
      <p class="text-(--ph-ink-2)">
        Catálogo de desenvolvimento. Cada bloco mostra os estados normal, erro e desabilitado.
      </p>
    </header>

    <section aria-labelledby="sec-botoes" class="flex flex-col gap-4">
      <h2 id="sec-botoes" class="text-xl font-semibold">Botões</h2>
      <div class="flex flex-wrap items-center gap-3">
        <PhButton>Primário</PhButton>
        <PhButton variant="secondary">Secundário</PhButton>
        <PhButton variant="ghost">Ghost</PhButton>
        <PhButton variant="danger">Perigo</PhButton>
        <PhButton loading>Salvando</PhButton>
        <PhButton disabled>Desabilitado</PhButton>
        <PhButton to="/">Como link</PhButton>
      </div>
    </section>

    <section aria-labelledby="sec-campos" class="flex flex-col gap-4">
      <h2 id="sec-campos" class="text-xl font-semibold">Campos</h2>
      <div class="grid gap-5 md:grid-cols-2">
        <PhInput v-model="text" label="Nome da edição" hint="Aparece no site e nos e-mails." />
        <PhInput v-model="email" label="E-mail" type="email" error="Informe um e-mail completo, como nome@dominio.dev." />
        <PhInput v-model="textError" label="Campo desabilitado" disabled />
        <PhInput v-model="date" label="Data" type="date" required />
        <PhInput v-model="time" label="Hora" type="time" />
        <PhSelect v-model="choice" label="Etapa" :options="options" placeholder="Selecione…" />
        <PhTextarea v-model="area" label="Mini bio" hint="Até 400 caracteres." class="md:col-span-2" />
        <PhCheckbox v-model="check" label="Mostrar no site e no app" hint="Pode ser alterado depois." />
        <PhCheckbox :model-value="false" label="Opção desabilitada" disabled />
      </div>
    </section>

    <section aria-labelledby="sec-status" class="flex flex-col gap-4">
      <h2 id="sec-status" class="text-xl font-semibold">Badges e filtros</h2>
      <div class="flex flex-wrap items-center gap-3">
        <PhBadge v-for="s in statuses" :key="s" :status="s">{{ statusText[s] }}</PhBadge>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <PhChip v-model="chipA">Todos</PhChip>
        <PhChip v-model="chipB">Pagos</PhChip>
      </div>
    </section>

    <section aria-labelledby="sec-cards" class="flex flex-col gap-4">
      <h2 id="sec-cards" class="text-xl font-semibold">Cartão e indicadores</h2>
      <div class="grid gap-4 md:grid-cols-3">
        <PhKpi label="Ingressos vendidos" :value="128" hint="de 400 disponíveis" />
        <PhKpi label="Receita bruta" value="R$ 19.200" />
        <PhKpi label="Propostas no CFP" :value="47" hint="31 já avaliadas" />
      </div>
      <PhCard title="Regras de seleção">
        <p class="text-sm text-(--ph-ink-2)">Conteúdo do cartão.</p>
        <template #actions><PhButton variant="ghost">Editar</PhButton></template>
      </PhCard>
    </section>

    <section aria-labelledby="sec-abas" class="flex flex-col gap-4">
      <h2 id="sec-abas" class="text-xl font-semibold">Abas</h2>
      <PhTabs v-model="tab" :items="tabs" label="Seções do CFP">
        <template #default="{ active }"><p class="text-sm">Painel ativo: {{ active }}</p></template>
      </PhTabs>
    </section>

    <section aria-labelledby="sec-tabela" class="flex flex-col gap-4">
      <h2 id="sec-tabela" class="text-xl font-semibold">Tabela e paginação</h2>
      <PhCard>
        <PhTable caption="Pedidos recentes">
          <thead>
            <tr><th scope="col">Pedido</th><th scope="col">Comprador</th><th scope="col">Status</th></tr>
          </thead>
          <tbody>
            <tr><td>#1042</td><td>Marina Alves</td><td><PhBadge status="ok">Pago</PhBadge></td></tr>
            <tr><td>#1040</td><td>Luana Pires</td><td><PhBadge status="warn">Aguardando Pix</PhBadge></td></tr>
          </tbody>
        </PhTable>
        <PhPagination v-model:page="page" :page-count="9" />
      </PhCard>
    </section>

    <section aria-labelledby="sec-camadas" class="flex flex-col gap-4">
      <h2 id="sec-camadas" class="text-xl font-semibold">Modal, painel lateral e avisos</h2>
      <div class="flex flex-wrap gap-3">
        <PhButton variant="secondary" @click="modalOpen = true">Abrir modal</PhButton>
        <PhButton variant="secondary" @click="drawerOpen = true">Abrir painel lateral</PhButton>
        <PhButton variant="ghost" @click="toast.success('Identidade publicada.', { label: 'Desfazer', run: () => {} })">Aviso de sucesso</PhButton>
        <PhButton variant="ghost" @click="toast.warning('Webhook do gateway sem teste.')">Aviso de atenção</PhButton>
        <PhButton variant="ghost" @click="toast.error('Falha ao enviar 3 e-mails.')">Aviso de erro</PhButton>
      </div>
      <PhModal v-model="modalOpen" title="Reembolsar pedido #1037">
        <p>Esta ação pede o estorno ao gateway.</p>
        <template #footer>
          <PhButton variant="ghost" @click="modalOpen = false">Voltar</PhButton>
          <PhButton variant="danger" @click="modalOpen = false">Confirmar</PhButton>
        </template>
      </PhModal>
      <PhDrawer v-model="drawerOpen" title="Editar sessão">
        <PhInput v-model="text" label="Título" />
        <template #footer>
          <PhButton variant="ghost" @click="drawerOpen = false">Cancelar</PhButton>
          <PhButton @click="drawerOpen = false">Salvar</PhButton>
        </template>
      </PhDrawer>
    </section>

    <section aria-labelledby="sec-estados" class="flex flex-col gap-4">
      <h2 id="sec-estados" class="text-xl font-semibold">Carregando e vazio</h2>
      <div class="grid gap-4 md:grid-cols-2">
        <PhCard title="Carregando"><PhSkeleton :lines="4" /></PhCard>
        <PhCard>
          <PhEmptyState title="Nenhuma proposta ainda" description="Quando o CFP receber a primeira proposta, ela aparece aqui.">
            <template #icon><Inbox :size="24" :stroke-width="1.75" /></template>
            <PhButton variant="ghost">Ver formulário público</PhButton>
          </PhEmptyState>
        </PhCard>
      </div>
    </section>
  </main>
</template>
