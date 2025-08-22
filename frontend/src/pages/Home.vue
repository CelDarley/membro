<script setup lang="ts">
import { ref, computed, onMounted, watch, nextTick } from 'vue'
import axios from 'axios'
import { api, setAuthTokenFromStorage } from '@/api'
import * as echarts from 'echarts'

setAuthTokenFromStorage()

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
    const { data } = await api.get('/membros', { params: { ...paramsBase, per_page: per, page: p } })
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

// controle de permissão
const isAdmin = ref(false)
async function ensureMe() {
  try {
    const { data } = await api.get('/auth/me')
    isAdmin.value = data?.user?.role === 'admin'
  } catch {
    isAdmin.value = false
  }
}

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

// Novos campos adicionais
const ufList = [
  'AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'
]
const memberOptions = ref<Array<{ id:number, name:string }>>([])
const memberIdToName = computed<Record<number,string>>(() => {
  const map: Record<number,string> = {}
  for (const m of memberOptions.value) map[m.id] = m.name
  return map
})
const friendsSearch = ref('')
const friendsSelectId = ref<number | null>(null)
const friendsFiltered = computed(() => {
  const selected = new Set<number>(Array.isArray(formData.value['Amigos no MP (IDs)']) ? formData.value['Amigos no MP (IDs)'] : [])
  const term = friendsSearch.value.trim().toLowerCase()
  return memberOptions.value
    .filter(m => !selected.has(m.id))
    .filter(m => !term || m.name.toLowerCase().includes(term) || String(m.id).includes(term))
    .slice(0, 100)
})
async function ensureMemberOptions() {
  if (memberOptions.value.length) return
  // Busca as primeiras 2000 entradas para opções (pode ajustar conforme volume)
  const per = 500
  let p = 1
  const all: Array<{id:number,name:string}> = []
  while (p <= 4) {
    const { data } = await api.get('/membros', { params: { per_page: per, page: p } })
    const rows = data?.data || []
    for (const r of rows) {
      const name = (r?.data?.['Membro']) || (r?.data?.['Nome']) || `#${r.id}`
      all.push({ id: r.id, name: String(name) })
    }
    if (!rows.length || rows.length < per) break
    p++
  }
  memberOptions.value = all
}
function addFriend(id: number) {
  if (!Array.isArray(formData.value['Amigos no MP (IDs)'])) formData.value['Amigos no MP (IDs)'] = []
  const arr: number[] = formData.value['Amigos no MP (IDs)']
  if (!arr.includes(id)) arr.push(id)
}
function removeFriend(id: number) {
  if (!Array.isArray(formData.value['Amigos no MP (IDs)'])) return
  formData.value['Amigos no MP (IDs)'] = formData.value['Amigos no MP (IDs)'].filter((x:number)=>x!==id)
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

// Filtra o campo técnico da modal
const formHeaders = computed<string[]>(() => {
  const exclude = new Set([
    'Amigos no MP (IDs)',
    'Data da pretensão',
    'Time de futebol e outros grupos extraprofissionais',
    'Quantidade de filhos',
    'Nome dos filhos',
    'Acadêmico',
    'Pretensão de movimentação na carreira',
    'Carreira anterior',
    'Liderança',
    'Grupos identitários',
  ])
  return headers.value.filter(h => !exclude.has(String(h).trim()))
})

// Colunas visíveis inicialmente na tabela principal
const visibleColumns = [
  'Mamp',
  'Sexo',
  'Membro',
  'Concurso',
  'Cargo efetivo',
  'Titularidade',
  'eMail pessoal',
  'Cargo Especial',
  'Telefone Unidade',
  'Telefone celular',
]
const tableHeaders = computed<string[]>(() => visibleColumns.filter(h => headers.value.includes(h)))
const baseWidth = computed(() => Math.max(1600, headers.value.length * 200))
const minWidthPx = computed(() => `${baseWidth.value}px`)
const displayedMinWidthPx = computed(() => `${Math.max(1000, (tableHeaders.value.length + 2) * 200)}px`)

async function fetchReports() {
  const params: any = { q: q.value, page: page.value, per_page: perPage.value }
  if (Object.keys(activeFilters.value).length) params.filters_json = JSON.stringify(activeFilters.value)
  const { data } = await api.get('/membros', { params })
  items.value = data.data
  total.value = data.total
}

async function openCreate() {
  editingId.value = null
  const initial: Record<string, any> = {}
  for (const h of headers.value) initial[h] = ''
  // garantir campos adicionais
  initial['Amigos no MP (IDs)'] = []
  initial['Time de futebol e outros grupos extraprofissionais'] = ''
  initial['Quantidade de filhos'] = ''
  initial['Nome dos filhos'] = ''
  initial['Estado de origem'] = ''
  initial['Acadêmico'] = ''
  initial['Pretensão de movimentação na carreira'] = ''
  initial['Carreira anterior'] = ''
  initial['Liderança'] = ''
  initial['Grupos identitários'] = ''
  formData.value = initial
  await Promise.all([ensureMunicipiosMG(), ensureLookupOptions(), ensureMemberOptions()])
  showModal.value = true
}

async function openEdit(row: any) {
  editingId.value = row.id
  const next = { ...(row.data || {}) }
  if (!Array.isArray(next['Amigos no MP (IDs)'])) next['Amigos no MP (IDs)'] = []
  next['Time de futebol e outros grupos extraprofissionais'] = next['Time de futebol e outros grupos extraprofissionais'] || ''
  next['Quantidade de filhos'] = next['Quantidade de filhos'] || ''
  next['Nome dos filhos'] = next['Nome dos filhos'] || ''
  next['Estado de origem'] = next['Estado de origem'] || ''
  next['Acadêmico'] = next['Acadêmico'] || ''
  next['Pretensão de movimentação na carreira'] = next['Pretensão de movimentação na carreira'] || ''
  next['Carreira anterior'] = next['Carreira anterior'] || ''
  next['Liderança'] = next['Liderança'] || ''
  next['Grupos identitários'] = next['Grupos identitários'] || ''
  formData.value = next
  await Promise.all([ensureMunicipiosMG(), ensureLookupOptions(), ensureMemberOptions()])
  showModal.value = true
}

async function save() {
  const payload = { data: formData.value }
  if (editingId.value) {
    await api.put(`/membros/${editingId.value}`, payload)
  } else {
    await api.post('/membros', payload)
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
  const { data } = await api.get('/membros/stats', { params })
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
let mgGeoFeatures: any[] = []

async function loadChart() {
  if (!chartEl.value) return
  if (!chart) chart = echarts.init(chartEl.value)
  const params: any = { field: 'Comarca Lotação', limit: 20 }
  if (q.value) params.q = q.value
  if (Object.keys(activeFilters.value).length) params.filters_json = JSON.stringify(activeFilters.value)
  const { data } = await api.get('/membros/aggregate', { params })
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
  const { data } = await api.get('/membros/aggregate', { params })
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
  // Rota de ano não existe em /membros; opcionalmente manteremos usando legacy se disponível
  try {
    const { data } = await api.get('/reports/aggregate-by-year', { params })
    const years = data.data.map((r:any)=>r.year)
    const counts = data.data.map((r:any)=>r.count)
    chartYear.setOption({ tooltip:{}, grid:{ left:8,right:8,top:24,bottom:8,containLabel:true }, xAxis:{ type:'category', data:years }, yAxis:{ type:'value' }, series:[{ type:'line', data:counts, smooth:true, itemStyle:{ color:'#f59e0b' } }] })
  } catch {
    chartYear.setOption({ series:[{ type:'line', data:[] }] })
  }
}

async function loadMap() {
  if (!chartMapEl.value) return
  if (!chartMap) chartMap = echarts.init(chartMapEl.value)
  try {
    if (!mgGeoLoaded) {
      // tenta arquivo local (se existir e com features)
      let geo: any | null = null
      try {
        // @ts-ignore - JSON import dinâmico suportado pelo Vite
        const mod = await import('@/assets/mg.geo.json')
        geo = mod?.default || mod
      } catch {}
      if (!geo || !Array.isArray(geo.features) || geo.features.length === 0) {
        const resp = await fetch('https://servicodados.ibge.gov.br/api/v3/malhas/municipios/31?formato=application/vnd.geo+json')
        if (resp.ok) {
          const remote = await resp.json()
          if (remote && Array.isArray(remote.features) && remote.features.length > 0) {
            geo = remote
          }
        }
      }
      if (geo && Array.isArray(geo.features) && geo.features.length > 0) {
        echarts.registerMap('mg_municipios', geo as any)
        mgGeoLoaded = true
        mgGeoFeatures = geo.features
      } else {
        mgGeoLoaded = false
        mgGeoFeatures = []
      }
    }
    const params: any = { field: 'Comarca Lotação', limit: 9999 }
    if (q.value) params.q = q.value
    if (Object.keys(activeFilters.value).length) params.filters_json = JSON.stringify(activeFilters.value)
    const { data } = await api.get('/membros/aggregate', { params })
    const entries: Record<string, number> = {}
    for (const r of data.data || []) entries[String(r.v).toUpperCase()] = r.c

    const seriesData: any[] = []
    const features = mgGeoFeatures && mgGeoFeatures.length ? mgGeoFeatures : ((echarts as any).getMap ? (echarts as any).getMap('mg_municipios')?.geoJson?.features : [])
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
    chartMap.setOption({ series: [{ type: 'map', map: 'mg_municipios', data: [] }] })
  }
}

// Grafo de relacionamentos (Amigos no MP)
const graphEl = ref<HTMLDivElement | null>(null)
let graph: echarts.ECharts | null = null
const graphStats = ref<{ nodes:number, edges:number }>({ nodes: 0, edges: 0 })

function normalizeName(s: string): string {
  return String(s || '')
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .trim()
    .toUpperCase()
}

async function loadGraph() {
  if (!graphEl.value) return
  if (!graph) graph = echarts.init(graphEl.value)
  graphStats.value = { nodes: 0, edges: 0 }
  const per = 500
  let p = 1
  const maxPages = 20
  const all: Array<{ id:number, data:any }> = []
  const paramsBase: any = {}
  if (q.value) paramsBase.q = q.value
  if (Object.keys(activeFilters.value).length) paramsBase.filters_json = JSON.stringify(activeFilters.value)

  try {
    while (p <= maxPages) { // até ~10000 registros
      const { data } = await api.get('/membros', { params: { ...paramsBase, per_page: per, page: p } })
      const rows = data?.data || []
      for (const r of rows) all.push({ id: r.id, data: r.data || {} })
      if (!rows.length || rows.length < per) break
      p++
    }
  } catch (e) {
    // Fallback: usa itens já carregados na tabela
    for (const r of items.value || []) all.push({ id: r.id, data: r.data || {} })
  }

  if (all.length === 0) {
    graph.setOption({ series: [{ type: 'graph', data: [], links: [] }] })
    graphStats.value = { nodes: 0, edges: 0 }
    return
  }

  // Mapear nomes dos membros
  const idToName: Record<number,string> = {}
  const nameToId: Record<string, number> = {}
  for (const r of all) {
    const nm = String(r.data['Membro'] || r.data['Nome'] || `#${r.id}`)
    idToName[r.id] = nm
    const key = normalizeName(nm)
    if (key && !nameToId[key]) nameToId[key] = r.id
  }

  // Construir arestas (não direcionadas) a partir de "Amigos no MP (IDs)"
  const edgeSet = new Set<string>()
  const degree: Record<number, number> = {}
  const idSet = new Set<number>(all.map(r => r.id))
  for (const r of all) {
    const raw: any = r.data['Amigos no MP (IDs)']
    let friendIds: number[] = []
    if (Array.isArray(raw)) friendIds = raw.map((x:any)=>Number(x)).filter((n)=>Number.isFinite(n))
    else if (typeof raw === 'string') {
      const ids = raw.split(/[^0-9]+/).map(s=>Number(s)).filter(n=>Number.isFinite(n))
      if (ids.length) friendIds = ids
      else {
        // tentar casar por nome
        const tokens = raw.split(/[\n,;]+/).map(t => normalizeName(t)).filter(Boolean)
        const resolved: number[] = []
        for (const t of tokens) {
          if (nameToId[t]) resolved.push(nameToId[t])
        }
        friendIds = resolved
      }
    }
    for (const fid of friendIds) {
      if (!idSet.has(fid)) continue
      const a = Math.min(r.id, fid)
      const b = Math.max(r.id, fid)
      const key = `${a}-${b}`
      if (!edgeSet.has(key) && a !== b) {
        edgeSet.add(key)
        degree[a] = (degree[a] ?? 0) + 1
        degree[b] = (degree[b] ?? 0) + 1
      }
    }
  }

  // Selecionar até 300 nós com maior grau (inclui nós isolados se necessário)
  const nodesAll = Array.from(Object.keys(degree).map(Number)).map(id => ({
    id: String(id),
    name: idToName[id] || `#${id}`,
    value: degree[id] ?? 0,
  }))
  nodesAll.sort((a,b) => (b.value - a.value))
  const nodesCap = nodesAll.slice(0, 300)
  const allowed = new Set(nodesCap.map(n => Number(n.id)))

  const links = Array.from(edgeSet).map(k => {
    const [a,b] = k.split('-').map(n => Number(n))
    return { source: String(a), target: String(b) }
  }).filter(e => allowed.has(Number(e.source)) && allowed.has(Number(e.target)))

  const nodes = nodesCap.map(n => ({
    ...n,
    symbolSize: Math.max(8, 8 + (n.value || 0) * 2),
  }))

  graphStats.value = { nodes: nodes.length, edges: links.length }

  graph.setOption({
    tooltip: { formatter: (p:any) => p.data?.name || '' },
    series: [{
      type: 'graph',
      layout: 'force',
      roam: true,
      label: { show: true, position: 'right', formatter: '{b}', fontSize: 10 },
      data: nodes,
      links,
      force: { repulsion: 120, edgeLength: [30, 120] },
      lineStyle: { color: '#94a3b8' },
      itemStyle: { color: '#2563eb' },
    }],
  })
}

watch([q, activeFilters], () => { loadKpis(); loadChart(); loadChartCargo(); loadChartYear(); loadMap(); loadGraph() }, { deep: true })

watch(() => activeTab.value, async (tab) => {
  if (tab === 'graficos') {
    await nextTick()
    await loadKpis()
    await loadChart()
    await loadChartCargo()
    await loadChartYear()
    await loadMap()
    await loadGraph()
    setTimeout(() => {
      chart?.resize()
      chartCargo?.resize()
      chartYear?.resize()
      chartMap?.resize()
      graph?.resize()
    }, 0)
  }
})

onMounted(() => {
  ensureMe()
  fetchReports(); loadKpis(); setTimeout(()=>{ loadChart(); loadChartCargo(); loadChartYear(); loadMap(); loadGraph() }, 0)
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
            <button v-if="isAdmin" class="btn" @click="openCreate">Novo registro</button>
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
              <div v-if="!mgGeoLoaded" style="opacity:.8; font-size: 12px">Não foi possível carregar o mapa do IBGE agora. Tente recarregar a página.</div>
            </div>
          </div>

          <!-- Gráfico: Relacionamentos (Amigos no MP) -->
          <div class="card">
            <div class="card-body" style="display:grid; gap:12px">
              <div style="display:flex; align-items:center; justify-content:space-between">
                <div style="font-weight:600">Relacionamentos (Amigos no MP)</div>
                <div style="display:flex; gap:8px; align-items:center">
                  <small style="opacity:.8">Nós: {{ graphStats.nodes }} · Arestas: {{ graphStats.edges }}</small>
                  <button class="btn btn-outline" @click="exportChartPng(graph, 'relacionamentos.png')">PNG</button>
                </div>
              </div>
              <div ref="graphEl" style="width: 100%; height: 480px"></div>
              <div v-if="graphStats.nodes === 0" style="opacity:.8; font-size: 12px">Sem dados para exibir. Ajuste os filtros ou adicione registros.</div>
            </div>
          </div>
        </div>

        <!-- Aba: Tabela -->
        <div v-if="activeTab==='tabela'" style="display:grid; gap:12px">
          <div class="table-wrap card">
            <table class="table table-tight table-compact" :style="{ minWidth: displayedMinWidthPx }">
              <thead>
                <tr>
                  <th>#</th>
                  <th v-for="h in tableHeaders" :key="h">
                    <span @click.stop="openFilter(h)" style="cursor:pointer; text-decoration:underline">{{ h }}</span>
                    <span v-if="activeFilters[h]?.length" style="opacity:.7"> ({{ activeFilters[h].length }})</span>
                  </th>
                  <th style="width:80px">Ações</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in items" :key="row.id">
                  <td>{{ row.id }}</td>
                  <td v-for="h in tableHeaders" :key="h">
                    {{ (row.data && row.data[h] != null) ? row.data[h] : '' }}
                  </td>
                  <td>
                    <button class="btn btn-outline" title="Detalhes" @click="openEdit(row)">
                      <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z"></path></svg>
                    </button>
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
            <div v-for="h in formHeaders" :key="h" class="card" style="border-color:#e2e8f0">
              <div class="card-body" style="display:grid; gap:6px">
                <label :for="'f-'+h" style="font-weight:600">{{ h }}</label>
                <template v-if="h === 'Sexo'">
                  <select :id="'f-'+h" class="input" v-model="formData[h]" :disabled="!isAdmin">
                    <option value="">Selecione</option>
                    <option value="Masculino">Masculino</option>
                    <option value="Feminino">Feminino</option>
                  </select>
                </template>
                <template v-else-if="h === 'Cargo efetivo'">
                  <select :id="'f-'+h" class="input" v-model="formData[h]" :disabled="!isAdmin">
                    <option value="">Selecione</option>
                    <option v-for="opt in (lookupOptions['cargo_efetivo'] || [])" :key="opt" :value="opt">{{ opt }}</option>
                  </select>
                </template>
                <template v-else-if="h === 'Comarca Lotação'">
                  <select :id="'f-'+h" class="input" v-model="formData[h]" :disabled="!isAdmin">
                    <option value="">Selecione</option>
                    <option v-for="m in mgMunicipios" :key="m" :value="m">{{ m }}</option>
                  </select>
                </template>
                <template v-else-if="h === 'Estado de origem'">
                  <select :id="'f-'+h" class="input" v-model="formData[h]" :disabled="!isAdmin">
                    <option value="">Selecione</option>
                    <option v-for="uf in ufList" :key="uf" :value="uf">{{ uf }}</option>
                  </select>
                </template>
                <template v-else-if="lookupTypesByHeader[h]">
                  <select :id="'f-'+h" class="input" v-model="formData[h]" :disabled="!isAdmin">
                    <option value="">Selecione</option>
                    <option v-for="opt in (lookupOptions[lookupTypesByHeader[h]] || [])" :key="opt" :value="opt">{{ opt }}</option>
                  </select>
                </template>
                <template v-else>
                  <input :id="'f-'+h" class="input" v-model="formData[h]" :disabled="!isAdmin" />
                </template>
              </div>
            </div>

            <!-- Campos adicionais solicitados -->
            <div class="card" style="border-color:#e2e8f0">
              <div class="card-body" style="display:grid; gap:10px">
                <div style="font-weight:700">Campos adicionais</div>

                <!-- Amigos no MP (multi) -->
                <div>
                  <label style="font-weight:600; display:block; margin-bottom:4px">Amigos no MP</label>
                  <div v-if="Array.isArray(formData['Amigos no MP (IDs)']) && formData['Amigos no MP (IDs)'].length">
                    <div style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:6px">
                      <span v-for="fid in formData['Amigos no MP (IDs)']" :key="fid" class="badge">
                        {{ memberIdToName[fid] || ('#'+fid) }}
                        <button v-if="isAdmin" type="button" class="btn btn-outline" style="padding:2px 6px; margin-left:6px" @click="removeFriend(fid)">x</button>
                      </span>
                    </div>
                  </div>
                  <div v-if="isAdmin" style="display:flex; gap:8px; align-items:center">
                    <input class="input" placeholder="Buscar por nome/ID" v-model="friendsSearch" @focus="ensureMemberOptions" style="max-width: 320px" />
                    <select class="input" v-model.number="friendsSelectId" @focus="ensureMemberOptions" style="max-width: 360px">
                      <option :value="null">Selecione um membro</option>
                      <option v-for="m in friendsFiltered" :key="m.id" :value="m.id">{{ m.name }} ({{ m.id }})</option>
                    </select>
                    <button type="button" class="btn" :disabled="!friendsSelectId" @click="friendsSelectId && addFriend(friendsSelectId)">Adicionar</button>
                  </div>
                  <div v-else-if="!(Array.isArray(formData['Amigos no MP (IDs)']) && formData['Amigos no MP (IDs)'].length)" style="opacity:.8">Nenhum amigo selecionado.</div>
                </div>

                <!-- Time de futebol e outros grupos extraprofissionais -->
                <div>
                  <label style="font-weight:600" for="f-time">Time de futebol e outros grupos extraprofissionais</label>
                  <input id="f-time" class="input" v-model="formData['Time de futebol e outros grupos extraprofissionais']" :disabled="!isAdmin" />
                </div>

                
                <div>
                  <label style="font-weight:600" for="f-filhos-qtd">Quantidade de filhos</label>
                  <input id="f-filhos-qtd" class="input" type="number" min="0" v-model="formData['Quantidade de filhos']" :disabled="!isAdmin" />
                </div>

                <div>
                  <label style="font-weight:600" for="f-nome-filhos">Nome dos filhos</label>
                  <input id="f-nome-filhos" class="input" v-model="formData['Nome dos filhos']" :disabled="!isAdmin" />
                </div>
                <div>
                  <label style="font-weight:600" for="f-academico">Acadêmico</label>
                  <input id="f-academico" class="input" v-model="formData['Acadêmico']" :disabled="!isAdmin" />
                </div>
                <div>
                  <label style="font-weight:600" for="f-pret">Pretensão de movimentação na carreira</label>
                  <input id="f-pret" class="input" v-model="formData['Pretensão de movimentação na carreira']" :disabled="!isAdmin" />
                </div>
                <div>
                  <label style="font-weight:600" for="f-carreira-ant">Carreira anterior</label>
                  <input id="f-carreira-ant" class="input" v-model="formData['Carreira anterior']" :disabled="!isAdmin" />
                </div>
                <div>
                  <label style="font-weight:600" for="f-lideranca">Liderança</label>
                  <input id="f-lideranca" class="input" v-model="formData['Liderança']" :disabled="!isAdmin" />
                </div>
                <div>
                  <label style="font-weight:600" for="f-grupos">Grupos identitários</label>
                  <input id="f-grupos" class="input" v-model="formData['Grupos identitários']" :disabled="!isAdmin" />
                </div>

              </div>
            </div>
            <!-- fim campos adicionais -->

            <div style="display:flex; gap:8px">
              <button v-if="isAdmin" type="submit" class="btn">Salvar</button>
              <button type="button" class="btn btn-outline" @click="closeModal">{{ isAdmin ? 'Cancelar' : 'Fechar' }}</button>
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