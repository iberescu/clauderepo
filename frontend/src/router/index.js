import { createRouter, createWebHistory } from 'vue-router'

const SearchView     = () => import('@/views/SearchView.vue')
const CandidatesView = () => import('@/views/CandidatesView.vue')
const BuildingView   = () => import('@/views/BuildingView.vue')
const ProposalView   = () => import('@/views/ProposalView.vue')
const SettingsView   = () => import('@/views/SettingsView.vue')
const NotFoundView   = () => import('@/views/NotFoundView.vue')

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/',                              name: 'search',     component: SearchView,     meta: { title: 'Search' } },
    { path: '/projects/:id/candidates',       name: 'candidates', component: CandidatesView, props: true, meta: { title: 'Pick a building' } },
    { path: '/projects/:id/building',         name: 'building',   component: BuildingView,   props: true, meta: { title: 'Rooftop analysis' } },
    { path: '/projects/:id/proposal',         name: 'proposal',   component: ProposalView,   props: true, meta: { title: 'Proposal' } },
    { path: '/settings',                      name: 'settings',   component: SettingsView,   meta: { title: 'Settings' } },
    { path: '/:pathMatch(.*)*',               name: 'not-found',  component: NotFoundView,   meta: { title: 'Not found' } },
  ],
  scrollBehavior: () => ({ top: 0 }),
})

router.afterEach((to) => {
  const base = 'Solar Proposal'
  document.title = to.meta.title ? `${to.meta.title} · ${base}` : base
})

export default router
