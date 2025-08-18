<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const api = axios.create({ baseURL: 'http://localhost:8000/api' })

const email = ref('admin@trustme.com')
const password = ref('admin123')
const token = ref<string | null>(localStorage.getItem('token'))
const loading = ref(false)
const error = ref('')
const showPassword = ref(false)
const requires2FA = ref(false)
const twoFactorCode = ref('')
const me = ref<any>(null)

function setAuthHeader() {
  if (token.value) api.defaults.headers.common.Authorization = `Bearer ${token.value}`
  else delete api.defaults.headers.common.Authorization
}
setAuthHeader()

async function loadMe() {
  try {
    const { data } = await api.get('/auth/me')
    me.value = data.user
  } catch {}
}

if (token.value) {
  loadMe()
}

async function login() {
  loading.value = true
  error.value = ''
  requires2FA.value = false
  try {
    const { data } = await api.post('/auth/login', { email: email.value, password: password.value })
    if (data.requires_two_factor) {
      requires2FA.value = true
      return
    }
    token.value = data.token
    localStorage.setItem('token', token.value!)
    setAuthHeader()
    await loadMe()
    router.push('/')
  } catch (e: any) {
    error.value = e?.response?.data?.message || 'Falha no login'
  } finally {
    loading.value = false
  }
}

async function verify2FA() {
  if (!twoFactorCode.value) return
  loading.value = true
  error.value = ''
  try {
    const { data } = await api.post('/auth/verify-2fa', { email: email.value, code: twoFactorCode.value })
    token.value = data.token
    localStorage.setItem('token', token.value!)
    setAuthHeader()
    requires2FA.value = false
    twoFactorCode.value = ''
    await loadMe()
    router.push('/')
  } catch (e: any) {
    error.value = e?.response?.data?.message || 'Código inválido'
  } finally {
    loading.value = false
  }
}

const isLogged = computed(() => !!token.value)
const isAdmin = computed(() => me.value?.role === 'admin')

function logout() {
  token.value = null
  me.value = null
  localStorage.removeItem('token')
  setAuthHeader()
  router.push('/')
}
</script>

<template>
  <div>
    <header class="header">
      <div class="header-inner container">
        <nav class="nav">
          <router-link to="/">Home</router-link>
          <template v-if="isAdmin">
            <router-link to="/admin">Admin</router-link>
            <router-link to="/admin/lookups">Cadastros</router-link>
          </template>
        </nav>
        <div>
          <template v-if="isLogged">
            <router-link to="/profile" aria-label="Perfil" style="display:inline-flex; align-items:center; gap:8px">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>
            </router-link>
            <button class="btn btn-outline" style="margin-left: 8px" @click="logout">Sair</button>
          </template>
        </div>
      </div>
    </header>

    <main class="container-fluid page-wrap" style="padding-top:16px">
      <div v-if="!isLogged" class="card" style="max-width: 420px">
        <div class="card-body" style="display: grid; gap: 10px;">
          <input v-model="email" class="input" type="email" placeholder="Email" />
          <div style="position: relative">
            <input v-model="password" class="input" :type="showPassword ? 'text' : 'password'" placeholder="Senha" style="width: 100%; padding-right: 36px" />
            <button type="button" @click="showPassword = !showPassword" aria-label="Mostrar/ocultar senha" style="position: absolute; right: 6px; top: 50%; transform: translateY(-50%); background: transparent; border: none; cursor: pointer; padding: 4px">👁️</button>
          </div>
          <button class="btn" :disabled="loading" @click="login">Entrar</button>
          <div v-if="requires2FA" class="card" style="border-color:#dbeafe">
            <div class="card-body" style="display:grid; gap:8px">
              <div>Informe o código enviado por e-mail:</div>
              <input v-model="twoFactorCode" class="input" placeholder="Código" />
              <button class="btn" :disabled="loading || !twoFactorCode" @click="verify2FA">Verificar</button>
            </div>
          </div>
          <div v-if="error" style="color: #c00">{{ error }}</div>
        </div>
      </div>

      <router-view v-else />
    </main>
  </div>
</template>

<style scoped>
</style>
