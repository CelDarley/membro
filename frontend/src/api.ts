import axios from 'axios'

function normalizeBaseUrl(raw?: string): string {
	let url = (raw || '').trim()
	if (!url) return 'http://localhost:8000/api'
	// remove barras à direita
	url = url.replace(/\/+$/, '')
	// garante sufixo /api
	if (!/\/(api)$/.test(url)) url = url + '/api'
	return url
}

const baseURL = normalizeBaseUrl(import.meta.env.VITE_API_BASE_URL)

export const api = axios.create({ baseURL })

export function setAuthTokenFromStorage() {
	const token = localStorage.getItem('token')
	if (token) api.defaults.headers.common.Authorization = `Bearer ${token}`
	else delete api.defaults.headers.common.Authorization
}

setAuthTokenFromStorage() 