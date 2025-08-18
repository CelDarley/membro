<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'

const api = axios.create({ baseURL: 'http://localhost:8000/api' })
const token = localStorage.getItem('token')
if (token) api.defaults.headers.common.Authorization = `Bearer ${token}`

const me = ref<any>(null)
const loading = ref(false)
const message = ref('')

async function loadMe() {
  const { data } = await api.get('/auth/me')
  me.value = data.user
}

async function toggle2FA(enable: boolean) {
  loading.value = true
  message.value = ''
  try {
    if (enable) await api.post('/auth/two-factor/enable')
    else await api.post('/auth/two-factor/disable')
    await loadMe()
    message.value = 'Preferência atualizada.'
  } finally {
    loading.value = false
  }
}

onMounted(loadMe)
</script>

<template>
  <div class="card" style="max-width: 560px">
    <div class="card-body" style="display:grid; gap:10px">
      <h2 style="margin:0">Perfil</h2>
      <div v-if="me">
        <div><strong>Nome:</strong> {{ me.name }}</div>
        <div><strong>Email:</strong> {{ me.email }}</div>
        <div><strong>Telefone:</strong> {{ me.phone || '-' }}</div>
        <div style="display:flex; gap:8px; align-items:center">
          <strong>2FA:</strong>
          <label style="display:flex; gap:6px; align-items:center">
            <input type="checkbox" :checked="me.two_factor_enabled" @change="toggle2FA(($event.target as HTMLInputElement).checked)" />
            <span>{{ me.two_factor_enabled ? 'Ativado' : 'Desativado' }}</span>
          </label>
        </div>
        <div v-if="message" style="color: #0a0">{{ message }}</div>
      </div>
    </div>
  </div>
</template> 