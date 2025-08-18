<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import axios from 'axios'
import { useRouter } from 'vue-router'

const router = useRouter()
const api = axios.create({ baseURL: 'http://localhost:8000/api' })
const token = localStorage.getItem('token')
if (token) api.defaults.headers.common.Authorization = `Bearer ${token}`

async function ensureAdmin() {
  try {
    const { data } = await api.get('/auth/me')
    if (data?.user?.role !== 'admin') router.replace('/')
  } catch {
    router.replace('/')
  }
}

const typeOptions = [
  { label: 'Membro', value: 'membro' },
  { label: 'Concurso', value: 'concurso' },
  { label: 'Naturalidade', value: 'naturalidade' },
  { label: 'Titularidade', value: 'titularidade' },
  { label: 'Cargo efetivo', value: 'cargo_efetivo' },
  { label: 'Cargo Especial', value: 'cargo_especial' },
  { label: 'Comarca Lotação', value: 'comarca_lotacao' },
  { label: 'Unidade Lotação', value: 'unidade_lotacao' },
]

const currentType = ref<string>(typeOptions[0].value)
const items = ref<any[]>([])
const nameInput = ref('')
const editId = ref<number | null>(null)
const loading = ref(false)
const errorMsg = ref('')

const currentLabel = computed(() => typeOptions.find(t => t.value === currentType.value)?.label || '')

async function loadAll() {
  loading.value = true
  errorMsg.value = ''
  items.value = []
  try {
    // Carrega até 2000 itens em lotes de 500
    const perPage = 500
    for (let page = 1; page <= 4; page++) {
      const { data } = await api.get('/lookups', { params: { type: currentType.value, per_page: perPage, page } })
      items.value.push(...(data.data || []))
      if (!data.data || data.data.length < perPage) break
    }
  } catch (e: any) {
    errorMsg.value = e?.response?.data?.message || 'Falha ao carregar'
  } finally {
    loading.value = false
  }
}

async function save() {
  if (!nameInput.value.trim()) return
  loading.value = true
  errorMsg.value = ''
  try {
    if (editId.value) {
      await api.put(`/lookups/${editId.value}`, { type: currentType.value, name: nameInput.value.trim() })
    } else {
      await api.post('/lookups', { type: currentType.value, name: nameInput.value.trim() })
    }
    nameInput.value = ''
    editId.value = null
    await loadAll()
  } catch (e: any) {
    errorMsg.value = e?.response?.data?.message || 'Erro ao salvar'
  } finally {
    loading.value = false
  }
}

function edit(item: any) {
  editId.value = item.id
  nameInput.value = item.name
}

async function removeItem(id: number) {
  if (!confirm('Excluir este item?')) return
  await api.delete(`/lookups/${id}`)
  await loadAll()
}

onMounted(async () => {
  await ensureAdmin()
  await loadAll()
})
</script>

<template>
  <div class="card">
    <div class="card-body" style="display:grid; gap:12px">
      <h2 style="margin:0">Cadastros (Lookups)</h2>

      <div class="toolbar">
        <label>Tipo:</label>
        <select v-model="currentType" class="input" style="max-width: 320px" @change="loadAll">
          <option v-for="t in typeOptions" :key="t.value" :value="t.value">{{ t.label }}</option>
        </select>
      </div>

      <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap">
        <input v-model="nameInput" class="input" :placeholder="`Nome do ${currentLabel}`" style="max-width:420px" />
        <button class="btn" :disabled="loading" @click="save">{{ editId ? 'Atualizar' : 'Adicionar' }}</button>
        <button class="btn btn-outline" type="button" @click="nameInput=''; editId=null">Limpar</button>
        <div v-if="errorMsg" style="color:#c00">{{ errorMsg }}</div>
      </div>

      <div class="table-wrap card">
        <table class="table table-tight table-compact" style="min-width: 560px">
          <thead>
            <tr>
              <th style="width:80px">#</th>
              <th>Nome</th>
              <th style="width:200px">Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="it in items" :key="it.id">
              <td>{{ it.id }}</td>
              <td>{{ it.name }}</td>
              <td style="display:flex; gap:8px">
                <button class="btn btn-outline" @click="edit(it)">Editar</button>
                <button class="btn btn-outline" @click="removeItem(it.id)">Excluir</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template> 