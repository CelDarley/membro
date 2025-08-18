<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

const router = useRouter()
const api = axios.create({ baseURL: 'http://localhost:8000/api' })
const token = localStorage.getItem('token')
if (token) api.defaults.headers.common.Authorization = `Bearer ${token}`

const users = ref<any[]>([])
const form = ref({ id: null as number | null, name: '', email: '', phone: '', role: 'user', password: '', password_confirmation: '' })
const message = ref('')
const errorMsg = ref('')

async function ensureAdmin() {
  try {
    const { data } = await api.get('/auth/me')
    if (data?.user?.role !== 'admin') router.replace('/')
  } catch {
    router.replace('/')
  }
}

async function loadUsers() {
  const { data } = await api.get('/users')
  users.value = data.data
}

function edit(u: any) {
  form.value = { id: u.id, name: u.name, email: u.email, phone: u.phone || '', role: u.role, password: '', password_confirmation: '' }
  message.value = ''
  errorMsg.value = ''
}

function reset() {
  form.value = { id: null, name: '', email: '', phone: '', role: 'user', password: '', password_confirmation: '' }
  message.value = ''
  errorMsg.value = ''
}

function onlyDigits(value: string) {
  return (value || '').replace(/\D+/g, '')
}

function maskPhone(value: string) {
  const digits = onlyDigits(value)
  if (!digits) return ''
  if (digits.length <= 10) {
    // (XX) XXXX-XXXX
    const d = digits.padEnd(10, '_')
    return `(${d.slice(0,2)}) ${d.slice(2,6)}-${d.slice(6,10)}`.replace(/[_-]+$/,'').trim()
  }
  // (XX) XXXXX-XXXX
  const d = digits.padEnd(11, '_')
  return `(${d.slice(0,2)}) ${d.slice(2,7)}-${d.slice(7,11)}`.replace(/[_-]+$/,'').trim()
}

function onPhoneInput(e: Event) {
  const target = e.target as HTMLInputElement
  form.value.phone = maskPhone(target.value)
}

function validate() {
  // Campos obrigatórios básicos
  if (!form.value.name.trim()) {
    errorMsg.value = 'Nome é obrigatório.'
    return false
  }
  if (!form.value.email.trim()) {
    errorMsg.value = 'Email é obrigatório.'
    return false
  }
  // Telefone opcional, mas quando preenchido deve ter 10 ou 11 dígitos
  const digits = onlyDigits(form.value.phone)
  if (digits && !(digits.length === 10 || digits.length === 11)) {
    errorMsg.value = 'Telefone inválido. Informe DDD + número (10 ou 11 dígitos).'
    return false
  }
  // Senha: se informada, confirmar
  if (form.value.password && form.value.password !== form.value.password_confirmation) {
    errorMsg.value = 'Confirmação de senha não confere.'
    return false
  }
  errorMsg.value = ''
  return true
}

function buildPayload() {
  const base: any = { name: form.value.name, email: form.value.email, phone: onlyDigits(form.value.phone), role: form.value.role }
  if (form.value.password && form.value.password.trim().length > 0) {
    base.password = form.value.password
    base.password_confirmation = form.value.password_confirmation
  }
  return base
}

async function save() {
  message.value = ''
  errorMsg.value = ''
  if (!validate()) return
  try {
    const payload = buildPayload()
    if (form.value.id) {
      await api.put(`/users/${form.value.id}`, payload)
      message.value = 'Usuário atualizado.'
    } else {
      await api.post('/users', payload)
      message.value = 'Usuário criado.'
    }
    await loadUsers()
    reset()
  } catch (e: any) {
    const errors = e?.response?.data?.errors
    if (errors) {
      const flat = Object.values(errors).flat() as string[]
      errorMsg.value = flat.join(' ')
    } else {
      errorMsg.value = e?.response?.data?.message || 'Erro ao salvar.'
    }
  }
}

async function removeUser(id: number) {
  if (!confirm('Excluir usuário?')) return
  await api.delete(`/users/${id}`)
  await loadUsers()
  message.value = 'Usuário excluído com sucesso.'
}

onMounted(async () => {
  await ensureAdmin()
  await loadUsers()
})
</script>

<template>
  <div class="admin-page">
    <h2>Administração de Usuários</h2>

    <div style="display:grid; gap:8px; max-width: 520px; margin-bottom: 16px">
      <input v-model="form.name" placeholder="Nome" />
      <input v-model="form.email" placeholder="Email" type="email" />
      <input :value="form.phone" @input="onPhoneInput" placeholder="Telefone (DDD + número)" />
      <div style="font-size: 12px; color: #666">Ex.: (11) 98765-4321</div>
      <select v-model="form.role">
        <option value="admin">Administrador</option>
        <option value="user">Comum</option>
      </select>
      <input v-model="form.password" type="password" placeholder="Senha (deixe em branco para não alterar)" />
      <input v-model="form.password_confirmation" type="password" placeholder="Confirmar Senha" />
      <div style="display:flex; gap:8px">
        <button @click="save">Salvar</button>
        <button @click="reset" type="button">Limpar</button>
      </div>
      <div v-if="message" style="color:#0a0">{{ message }}</div>
      <div v-if="errorMsg" style="color:#c00">{{ errorMsg }}</div>
    </div>

    <div style="overflow:auto; border:1px solid #ddd; border-radius:6px">
      <table style="width:100%; border-collapse:collapse; min-width:720px">
        <thead style="background:#f6f6f6">
          <tr>
            <th style="text-align:left; padding:8px">#</th>
            <th style="text-align:left; padding:8px">Nome</th>
            <th style="text-align:left; padding:8px">Email</th>
            <th style="text-align:left; padding:8px">Telefone</th>
            <th style="text-align:left; padding:8px">Função</th>
            <th style="text-align:left; padding:8px">Ações</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="u in users" :key="u.id" style="border-top: 1px solid #eee">
            <td style="padding:8px">{{ u.id }}</td>
            <td style="padding:8px">{{ u.name }}</td>
            <td style="padding:8px">{{ u.email }}</td>
            <td style="padding:8px">{{ u.phone || '-' }}</td>
            <td style="padding:8px">{{ u.role }}</td>
            <td style="padding:8px; display:flex; gap:8px">
              <button @click="edit(u)">Editar</button>
              <button @click="removeUser(u.id)">Excluir</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<style scoped>
.admin-page input:not([type="checkbox"]):not([type="radio"]),
.admin-page select,
.admin-page textarea {
  min-height: 40px;
  padding: 8px 10px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: #fff;
  outline: none;
}

.admin-page input[type="text"]:focus,
.admin-page input[type="email"]:focus,
.admin-page input[type="password"]:focus,
.admin-page input[type="tel"]:focus,
.admin-page select:focus,
.admin-page textarea:focus {
  border-color: var(--ring);
  box-shadow: 0 0 0 3px rgba(147, 197, 253, .35);
}
</style> 