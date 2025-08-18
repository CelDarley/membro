<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const api = axios.create({ baseURL: 'http://localhost:8000/api' })

const token = ref<string | null>(localStorage.getItem('token'))
function setAuthHeader() {
  if (token.value) api.defaults.headers.common.Authorization = `Bearer ${token.value}`
}
setAuthHeader()

const q = ref('')
const perPage = ref(20)
const page = ref(1)
const items = ref<any[]>([])
const total = ref(0)
const showFullscreen = ref(false)

const showModal = ref(false)
const editingId = ref<number | null>(null)
const formData = ref<Record<string, any>>({})

// FILTROS
const activeFilters = ref<Record<string, string[]>>({})
const showFilterModal = ref(false)
const filterForHeader = ref<string>('')
const filterOptions = ref<string[]>([])
const tempSelected = ref<Record<string, boolean>>({})

function openFilter(header: string) {
  filterForHeader.value = header
  // opções: de lookups quando existir; senão, derivar dos resultados carregados
  const type = lookupTypesByHeader[header]
  if (type && lookupOptions.value[type]?.length) {
    filterOptions.value = [...lookupOptions.value[type]]
  } else {
    const set = new Set<string>()
    for (const row of items.value) {
      const v = row?.data?.[header]
      if (v != null && String(v).trim() !== '') set.add(String(v))
    }
    filterOptions.value = Array.from(set)
  }
  const current = new Set(activeFilters.value[header] || [])
  const temp: Record<string, boolean> = {}
  for (const opt of filterOptions.value) temp[opt] = current.has(opt)
  tempSelected.value = temp
  showFilterModal.value = true
}

function applyFilter() {
  const header = filterForHeader.value
  const selected = Object.keys(tempSelected.value).filter(k => !!tempSelected.value[k])
  if (selected.length) activeFilters.value = { ...activeFilters.value, [header]: selected }
  else {
    const next = { ...activeFilters.value }
    delete next[header]
    activeFilters.value = next
  }
  showFilterModal.value = false
  page.value = 1
  fetchReports()
}

function clearAllFilters() {
  activeFilters.value = {}
  page.value = 1
  fetchReports()
}

const mgMunicipios = ref<string[]>([])
let municipiosLoaded = false
async function ensureMunicipiosMG() {
  if (municipiosLoaded) return
  try {
    const { data } = await axios.get('https://servicodados.ibge.gov.br/api/v1/localidades/estados/MG/municipios')
    mgMunicipios.value = (data || []).map((m: any) => m?.nome).filter(Boolean)
  } catch {
    mgMunicipios.value = []
  } finally {
    municipiosLoaded = true
  }
}

// Lookups controlados pelo Admin
const lookupTypesByHeader: Record<string, string> = {
  'Membro': 'membro',
  'Concurso': 'concurso',
  'Naturalidade': 'naturalidade',
  'Titularidade': 'titularidade',
  'Cargo efetivo': 'cargo_efetivo',
  'Cargo Especial': 'cargo_especial',
  'Unidade Lotação': 'unidade_lotacao',
}
const lookupOptions = ref<Record<string, string[]>>({})
async function ensureLookupOptions() {
  const neededTypes = Array.from(new Set(Object.values(lookupTypesByHeader)))
  const perPage = 1000
  for (const type of neededTypes) {
    if (lookupOptions.value[type] && lookupOptions.value[type].length) continue
    const all: string[] = []
    for (let p = 1; p <= 5; p++) {
      const { data } = await api.get('/lookups', { params: { type, per_page: perPage, page: p } })
      const names = (data?.data || []).map((it: any) => it.name)
      all.push(...names)
      if (!data?.data || data.data.length < perPage) break
    }
    lookupOptions.value[type] = all
  }
}

const headers = computed<string[]>(() => {
  const set = new Set<string>()
  for (const row of items.value) {
    if (row && row.data && typeof row.data === 'object') {
      for (const key of Object.keys(row.data)) set.add(key)
    }
  }
  return Array.from(set)
})

const baseWidth = computed(() => Math.max(1600, headers.value.length * 200))
const minWidthPx = computed(() => `${baseWidth.value}px`)

async function fetchReports() {
  const params: any = { q: q.value, page: page.value, per_page: perPage.value }
  if (Object.keys(activeFilters.value).length) params.filters_json = JSON.stringify(activeFilters.value)
  const { data } = await api.get('/reports', { params })
  items.value = data.data
  total.value = data.total
}

async function openCreate() {
  editingId.value = null
  const initial: Record<string, any> = {}
  for (const h of headers.value) initial[h] = ''
  formData.value = initial
  await Promise.all([ensureMunicipiosMG(), ensureLookupOptions()])
  showModal.value = true
}

async function openEdit(row: any) {
  editingId.value = row.id
  formData.value = { ...(row.data || {}) }
  await Promise.all([ensureMunicipiosMG(), ensureLookupOptions()])
  showModal.value = true
}

async function save() {
  const payload = { data: formData.value }
  if (editingId.value) {
    await api.put(`/reports/${editingId.value}`, payload)
  } else {
    await api.post('/reports', payload)
  }
  showModal.value = false
  await fetchReports()
}

function closeModal() {
  showModal.value = false
}

onMounted(fetchReports)
</script>

<template>
  <div class="report-area">
    <div class="card">
      <div class="card-body" style="display:grid; gap:12px">

        <div class="report-scroll">
          <div class="header-row" :style="{ minWidth: minWidthPx }">
            <input v-model="q" class="input" placeholder="Buscar..." @keyup.enter="page = 1; fetchReports()" />
            <button class="btn" @click="page = 1; fetchReports()">Pesquisar</button>
            <button class="btn btn-outline" @click="q = ''; page = 1; fetchReports()">Limpar</button>
            <div style="opacity:.8; margin-left:auto">Total: {{ total }}</div>
            <button class="btn" @click="openCreate">Novo registro</button>
            <button class="btn" @click="showFullscreen = true">Tela cheia</button>
            <button class="btn btn-outline" @click="clearAllFilters">Limpar filtros</button>
          </div>
        </div>

        <div class="table-wrap card">
          <table class="table table-tight table-compact" :style="{ minWidth: minWidthPx }">
            <thead>
              <tr>
                <th>#</th>
                <th v-for="h in headers" :key="h">
                  <span @click.stop="openFilter(h)" style="cursor:pointer; text-decoration:underline">{{ h }}</span>
                  <span v-if="activeFilters[h]?.length" style="opacity:.7"> ({{ activeFilters[h].length }})</span>
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in items" :key="row.id" @click="openEdit(row)" style="cursor:pointer">
                <td>{{ row.id }}</td>
                <td v-for="h in headers" :key="h">
                  {{ (row.data && row.data[h] != null) ? row.data[h] : '' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="toolbar">
          <button class="btn btn-outline" :disabled="page <= 1" @click="page--; fetchReports()">Anterior</button>
          <span>Página {{ page }}</span>
          <button class="btn btn-outline" :disabled="items.length < perPage" @click="page++; fetchReports()">Próxima</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal filtros por coluna -->
  <div v-if="showFilterModal" class="fullscreen-overlay" @click.self="showFilterModal = false">
    <div class="fullscreen-inner" style="max-width: 720px; width: auto; height: auto">
      <div class="fullscreen-header">
        <strong>Filtrar por {{ filterForHeader }}</strong>
        <button class="btn btn-outline" @click="showFilterModal = false">Fechar</button>
      </div>
      <div class="fullscreen-body">
        <div class="fullscreen-scroll" style="padding: 12px 0">
          <div style="display:grid; gap:8px; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); align-items:start">
            <label v-for="opt in filterOptions" :key="opt" style="display:flex; gap:8px; align-items:center">
              <input type="checkbox" :checked="!!tempSelected[opt]" @change="tempSelected[opt] = ($event.target as HTMLInputElement).checked" />
              <span>{{ opt }}</span>
            </label>
          </div>
        </div>
      </div>
      <div class="fullscreen-header" style="border-top:1px solid var(--border)">
        <div class="toolbar" style="width:100%">
          <button class="btn" @click="applyFilter">Filtrar</button>
          <button class="btn btn-outline" style="margin-left:auto" @click="showFilterModal = false">Cancelar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal edição/criação já existente -->
  <div v-if="showModal" class="fullscreen-overlay" @click.self="closeModal">
    <div class="fullscreen-inner">
      <div class="fullscreen-header">
        <strong>{{ editingId ? 'Editar registro' : 'Novo registro' }}</strong>
        <button class="btn btn-outline" @click="closeModal">Fechar</button>
      </div>
      <div class="fullscreen-body">
        <div class="fullscreen-scroll">
          <form @submit.prevent="save" style="display:grid; gap:12px; max-width:960px">
            <div v-for="h in headers" :key="h" class="card" style="border-color:#e2e8f0">
              <div class="card-body" style="display:grid; gap:6px">
                <label :for="'f-'+h" style="font-weight:600">{{ h }}</label>
                <template v-if="h === 'Sexo'">
                  <select :id="'f-'+h" class="input" v-model="formData[h]">
                    <option value="">Selecione</option>
                    <option value="Masculino">Masculino</option>
                    <option value="Feminino">Feminino</option>
                  </select>
                </template>
                <template v-else-if="h === 'Cargo efetivo'">
                  <select :id="'f-'+h" class="input" v-model="formData[h]">
                    <option value="">Selecione</option>
                    <option v-for="opt in (lookupOptions['cargo_efetivo'] || [])" :key="opt" :value="opt">{{ opt }}</option>
                  </select>
                </template>
                <template v-else-if="h === 'Comarca Lotação'">
                  <select :id="'f-'+h" class="input" v-model="formData[h]">
                    <option value="">Selecione</option>
                    <option v-for="m in mgMunicipios" :key="m" :value="m">{{ m }}</option>
                  </select>
                </template>
                <template v-else-if="lookupTypesByHeader[h]">
                  <select :id="'f-'+h" class="input" v-model="formData[h]">
                    <option value="">Selecione</option>
                    <option v-for="opt in (lookupOptions[lookupTypesByHeader[h]] || [])" :key="opt" :value="opt">{{ opt }}</option>
                  </select>
                </template>
                <template v-else>
                  <input :id="'f-'+h" class="input" v-model="formData[h]" />
                </template>
              </div>
            </div>
            <div style="display:flex; gap:8px">
              <button type="submit" class="btn">Salvar</button>
              <button type="button" class="btn btn-outline" @click="closeModal">Cancelar</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Fullscreen tabela existente -->
  <div v-if="showFullscreen" class="fullscreen-overlay" @click.self="showFullscreen = false">
    <div class="fullscreen-inner">
      <div class="fullscreen-header">
        <strong>Relatório</strong>
        <button class="btn btn-outline" @click="showFullscreen = false">Fechar</button>
      </div>
      <div class="fullscreen-body">
        <div class="fullscreen-scroll">
          <div class="header-row" :style="{ minWidth: minWidthPx }" style="margin-bottom:8px">
            <input v-model="q" class="input" placeholder="Buscar..." @keyup.enter="page = 1; fetchReports()" />
            <button class="btn" @click="page = 1; fetchReports()">Pesquisar</button>
            <button class="btn btn-outline" @click="q = ''; page = 1; fetchReports()">Limpar</button>
            <div style="opacity:.8; margin-left:auto">Total: {{ total }}</div>
          </div>
          <table class="table table-tight table-compact" :style="{ minWidth: minWidthPx }">
            <thead>
              <tr>
                <th>#</th>
                <th v-for="h in headers" :key="h">{{ h }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in items" :key="row.id" @click="openEdit(row)" style="cursor:pointer">
                <td>{{ row.id }}</td>
                <td v-for="h in headers" :key="h">
                  {{ (row.data && row.data[h] != null) ? row.data[h] : '' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="fullscreen-header" style="border-top:1px solid var(--border)">
        <div class="toolbar" style="width:100%">
          <button class="btn btn-outline" :disabled="page <= 1" @click="page--; fetchReports()">Anterior</button>
          <span style="margin-left:8px">Página {{ page }}</span>
          <button class="btn btn-outline" :disabled="items.length < perPage" @click="page++; fetchReports()" style="margin-left:auto">Próxima</button>
        </div>
      </div>
    </div>
  </div>
</template> 