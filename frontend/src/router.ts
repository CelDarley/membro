import { createRouter, createWebHistory } from 'vue-router'
import Home from './pages/Home.vue'
import Profile from './pages/Profile.vue'
import Admin from './pages/Admin.vue'
import AdminLookups from './pages/AdminLookups.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home', component: Home },
    { path: '/profile', name: 'profile', component: Profile },
    { path: '/admin', name: 'admin', component: Admin },
    { path: '/admin/lookups', name: 'admin-lookups', component: AdminLookups },
  ],
})

export default router 