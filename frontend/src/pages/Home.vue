<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import axios from 'axios'
import * as echarts from 'echarts'

const api = axios.create({ baseURL: 'http://localhost:8000/api' })

function downloadBlob(content: BlobPart, filename: string, type: string) {
  const blob = new Blob([content], { type })
  const url = window.URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = filename
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
  window.URL.revokeObjectURL(url)
}

async function exportCsvAll() {
  const per = 1000
  let p = 1
  const all: any[] = []
  const paramsBase: any = {}
  if (q.value) paramsBase.q = q.value
  if (Object.keys(activeFilters.value).length) paramsBase.filters_json = JSON.stringify(activeFilters.value)
  while (true) {
    const { data } = await api.get('/reports', { params: { ...paramsBase, per_page: per, page: p } })
    all.push(...(data?.data || []))
    if (!data?.data || data.data.length < per) break
    p++
  }
  const hdrs = headers.value
  const csvRows: string[] = []
  csvRows.push(['id', ...hdrs].map(v => '"'+String(v).replace(/"/g,'""')+'"').join(','))
  for (const r of all) {
    const row = [r.id, ...hdrs.map(h => (r.data && r.data[h] != null) ? r.data[h] : '')]
    csvRows.push(row.map(v => '"'+String(v).replace(/"/g,'""')+'"').join(','))
  }
  downloadBlob(csvRows.join('\n'), 'relatorio.csv', 'text/csv;charset=utf-8;')
}

function exportChartPng(instance: echarts.ECharts | null, filename: string) {
  if (!instance) return
  const dataUrl = instance.getDataURL({ pixelRatio: 2, backgroundColor: '#ffffff' })
  // converter base64 para blob
  const b64 = dataUrl.split(',')[1]
  const bin = atob(b64)
  const arr = new Uint8Array(bin.length)
  for (let i=0;i<bin.length;i++) arr[i] = bin.charCodeAt(i)
  downloadBlob(arr, filename, 'image/png')
}

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

// Abas
const activeTab = ref<'tabela' | 'graficos'>('tabela')

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

const kpi = ref<{ total:number|null, female_count:number|null, female_pct:number|null, median_age_years:number|null, median_tenure_years:number|null}>({ total:null, female_count:null, female_pct:null, median_age_years:null, median_tenure_years:null })

async function loadKpis() {
  const params: any = {}
  if (q.value) params.q = q.value
  if (Object.keys(activeFilters.value).length) params.filters_json = JSON.stringify(activeFilters.value)
  const { data } = await api.get('/reports/stats', { params })
  kpi.value = data
}

const chartEl = ref<HTMLDivElement | null>(null)
let chart: echarts.ECharts | null = null
const chartCargoEl = ref<HTMLDivElement | null>(null)
let chartCargo: echarts.ECharts | null = null
const chartYearEl = ref<HTMLDivElement | null>(null)
let chartYear: echarts.ECharts | null = null
const chartMapEl = ref<HTMLDivElement | null>(null)
let chartMap: echarts.ECharts | null = null
let mgGeoLoaded = false

async function loadChart() {
  if (!chartEl.value) return
  if (!chart) chart = echarts.init(chartEl.value)
  const params: any = { field: 'Comarca Lotação', limit: 20 }
  if (q.value) params.q = q.value
  if (Object.keys(activeFilters.value).length) params.filters_json = JSON.stringify(activeFilters.value)
  const { data } = await api.get('/reports/aggregate', { params })
  const labels = data.data.map((r: any) => r.v)
  const values = data.data.map((r: any) => r.c)
  chart.setOption({ tooltip:{}, grid:{ left:8,right:8,top:24,bottom:8,containLabel:true }, xAxis:{ type:'value' }, yAxis:{ type:'category', data:labels, axisLabel:{ interval:0 } }, series:[{ type:'bar', data:values, itemStyle:{ color:'#2563eb' } }] })
  chart.off('click')
  chart.on('click', (p) => {
    const label = labels[p.dataIndex]
    if (!label) return
    const list = new Set(activeFilters.value['Comarca Lotação'] || [])
    list.add(label)
    activeFilters.value = { ...activeFilters.value, ['Comarca Lotação']: Array.from(list) }
    page.value = 1
    fetchReports(); loadKpis(); loadChart(); loadChartCargo(); loadChartYear(); loadMap()
  })
}

async function loadChartCargo() {
  if (!chartCargoEl.value) return
  if (!chartCargo) chartCargo = echarts.init(chartCargoEl.value)
  const params: any = { field: 'Cargo efetivo', limit: 10 }
  if (q.value) params.q = q.value
  if (Object.keys(activeFilters.value).length) params.filters_json = JSON.stringify(activeFilters.value)
  const { data } = await api.get('/reports/aggregate', { params })
  const labels = data.data.map((r: any) => r.v)
  const values = data.data.map((r: any) => r.c)
  chartCargo.setOption({ tooltip:{}, grid:{ left:8,right:8,top:24,bottom:8,containLabel:true }, xAxis:{ type:'category', data:labels, axisLabel:{ interval:0, rotate:20 } }, yAxis:{ type:'value' }, series:[{ type:'bar', data:values, itemStyle:{ color:'#16a34a' } }] })
  chartCargo.off('click')
  chartCargo.on('click', (p) => {
    const label = labels[p.dataIndex]
    const list = new Set(activeFilters.value['Cargo efetivo'] || [])
    list.add(label)
    activeFilters.value = { ...activeFilters.value, ['Cargo efetivo']: Array.from(list) }
    page.value = 1
    fetchReports(); loadKpis(); loadChart(); loadChartCargo(); loadChartYear(); loadMap()
  })
}

async function loadChartYear() {
  if (!chartYearEl.value) return
  if (!chartYear) chartYear = echarts.init(chartYearEl.value)
  const params: any = { field: 'Data da posse' }
  if (q.value) params.q = q.value
  if (Object.keys(activeFilters.value).length) params.filters_json = JSON.stringify(activeFilters.value)
  const { data } = await api.get('/reports/aggregate-by-year', { params })
  const years = data.data.map((r:any)=>r.year)
  const counts = data.data.map((r:any)=>r.count)
  chartYear.setOption({ tooltip:{}, grid:{ left:8,right:8,top:24,bottom:8,containLabel:true }, xAxis:{ type:'category', data:years }, yAxis:{ type:'value' }, series:[{ type:'line', data:counts, smooth:true, itemStyle:{ color:'#f59e0b' } }] })
}

async function loadMap() {
  if (!chartMapEl.value) return
  if (!chartMap) chartMap = echarts.init(chartMapEl.value)
  try {
    if (!mgGeoLoaded) {
      // GeoJSON simplificado de MG (municipios) – tentativa via IBGE; se falhar, não quebra a tela
      const resp = await fetch('https://servicodados.ibge.gov.br/api/v3/malhas/municipios/31?formato=application/json')
      if (resp.ok) {
        const geo = await resp.json()
        echarts.registerMap('mg_municipios', geo as any)
        mgGeoLoaded = true
      }
    }
    const params: any = { field: 'Comarca Lotação', limit: 9999 }
    if (q.value) params.q = q.value
    if (Object.keys(activeFilters.value).length) params.filters_json = JSON.stringify(activeFilters.value)
    const { data } = await api.get('/reports/aggregate', { params })
    const entries: Record<string, number> = {}
    for (const r of data.data || []) entries[String(r.v).toUpperCase()] = r.c

    const seriesData: any[] = []
    // Sem mapeamento exato, tentamos casar pelo nome do município nas features
    // Fallback: exibe apenas tooltips vazios quando não encontra
    const features = (echarts as any).getMap ? (echarts as any).getMap('mg_municipios')?.geoJson?.features : []
    if (features && Array.isArray(features)) {
      for (const f of features) {
        const name: string = (f.properties?.name || f.properties?.NM_MUN || f.properties?.nm_mun || '').toUpperCase()
        const val = entries[name] || 0
        seriesData.push({ name: f.properties?.name || name, value: val })
      }
    }

    chartMap.setOption({
      tooltip: { trigger: 'item', formatter: (p:any) => `${p.name}: ${p.value ?? 0}` },
      visualMap: { min: 0, max: Math.max(1, ...seriesData.map(d=>d.value||0)), left: 'left', top: 'bottom', text: ['Alto','Baixo'], calculable: true },
      series: [{ type: 'map', map: 'mg_municipios', roam: true, data: seriesData }]
    })
  } catch (e) {
    // Silencia erros de carregamento do mapa
  }
}

watch([q, activeFilters], () => { loadKpis(); loadChart(); loadChartCargo(); loadChartYear(); loadMap() }, { deep: true })

watch(() => activeTab.value, async (tab) => {
  if (tab === 'graficos') {
    await nextTick()
    await loadKpis()
    await loadChart()
    await loadChartCargo()
    await loadChartYear()
    await loadMap()
    setTimeout(() => {
      chart?.resize()
      chartCargo?.resize()
      chartYear?.resize()
      chartMap?.resize()
    }, 0)
  }
})

onMounted(() => {
  fetchReports(); loadKpis(); setTimeout(()=>{ loadChart(); loadChartCargo(); loadChartYear(); loadMap() }, 0)
})
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
            <button class="btn btn-outline" @click="exportCsvAll">Exportar CSV</button>
          </div>
        </div>

        <!-- Abas -->
        <div class="toolbar" style="gap:6px">
          <button class="btn btn-outline" :class="{ 'btn': activeTab==='tabela' }" @click="activeTab='tabela'">Tabela</button>
          <button class="btn btn-outline" :class="{ 'btn': activeTab==='graficos' }" @click="activeTab='graficos'">Gráficos</button>
        </div>

        <!-- Aba: Gráficos -->
        <div v-if="activeTab==='graficos'" style="display:grid; gap:12px">
          <!-- KPIs -->
          <div class="card">
            <div class="card-body" style="display:grid; gap:8px; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); align-items:stretch">
              <div class="card" style="border-color:#e2e8f0"><div class="card-body"><div>Total</div><div style="font-size:22px; font-weight:700">{{ kpi.total ?? '-' }}</div></div></div>
              <div class="card" style="border-color:#e2e8f0"><div class="card-body"><div>% Feminino</div><div style="font-size:22px; font-weight:700">{{ kpi.female_pct ?? '-' }}%</div></div></div>
              <div class="card" style="border-color:#e2e8f0"><div class="card-body"><div>Mediana Idade</div><div style="font-size:22px; font-weight:700">{{ kpi.median_age_years ?? '-' }}</div></div></div>
              <div class="card" style="border-color:#e2e8f0"><div class="card-body"><div>Mediana Tempo na Promotoria (anos)</div><div style="font-size:22px; font-weight:700">{{ kpi.median_tenure_years ?? '-' }}</div></div></div>
            </div>
          </div>

          <!-- Gráfico: Comarca -->
          <div class="card">
            <div class="card-body" style="display:grid; gap:12px">
              <div style="display:flex; align-items:center; justify-content:space-between">
                <div style="font-weight:600">Top 20 por Comarca Lotação</div>
                <button class="btn btn-outline" @click="exportChartPng(chart, 'comarca.png')">PNG</button>
              </div>
              <div ref="chartEl" style="width: 100%; height: 380px"></div>
            </div>
          </div>

          <!-- Gráfico: Cargo efetivo -->
          <div class="card">
            <div class="card-body" style="display:grid; gap:12px">
              <div style="display:flex; align-items:center; justify-content:space-between">
                <div style="font-weight:600">Top 10 por Cargo efetivo</div>
                <button class="btn btn-outline" @click="exportChartPng(chartCargo, 'cargo_efetivo.png')">PNG</button>
              </div>
              <div ref="chartCargoEl" style="width: 100%; height: 320px"></div>
            </div>
          </div>

          <!-- Gráfico: Série por ano -->
          <div class="card">
            <div class="card-body" style="display:grid; gap:12px">
              <div style="display:flex; align-items:center; justify-content:space-between">
                <div style="font-weight:600">Ingressos por ano (Data da posse)</div>
                <button class="btn btn-outline" @click="exportChartPng(chartYear, 'posse_por_ano.png')">PNG</button>
              </div>
              <div ref="chartYearEl" style="width: 100%; height: 320px"></div>
            </div>
          </div>

          <!-- Mapa de comarcas (MG) -->
          <div class="card">
            <div class="card-body" style="display:grid; gap:12px">
              <div style="display:flex; align-items:center; justify-content:space-between">
                <div style="font-weight:600">Mapa por Comarca (MG)</div>
                <button class="btn btn-outline" @click="exportChartPng(chartMap, 'mapa_comarca.png')">PNG</button>
              </div>
              <div ref="chartMapEl" style="width: 100%; height: 420px"></div>
            </div>
          </div>
        </div>

        <!-- Aba: Tabela -->
        <div v-if="activeTab==='tabela'" style="display:grid; gap:12px">
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