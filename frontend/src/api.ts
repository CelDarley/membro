import axios from 'axios'

const baseURL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api'

export const api = axios.create({ baseURL })

export function setAuthTokenFromStorage() {
	const token = localStorage.getItem('token')
	if (token) api.defaults.headers.common.Authorization = `Bearer ${token}`
	else delete api.defaults.headers.common.Authorization
}

setAuthTokenFromStorage() 