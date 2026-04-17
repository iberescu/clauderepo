import { defineStore } from 'pinia'
import api from '@/services/api'

const STATUS_ORDER = [
  'created',
  'searching',
  'candidates_ready',
  'candidate_selected',
  'analyzing',
  'analysis_ready',
  'layout_ready',
  'rendering',
  'render_ready',
  'pricing_ready',
  'proposal_ready',
  'completed',
]

export const useProjectStore = defineStore('project', {
  state: () => ({
    id: null,
    status: null,
    summary: null,
    candidates: [],
    selectedCandidate: null,
    analysis: null,
    layout: null,
    render: null,
    pricing: null,
    proposal: null,
    loading: false,
    error: null,
  }),

  getters: {
    statusRank: (s) => (s.status ? STATUS_ORDER.indexOf(s.status) : -1),
    hasReached: (s) => (target) => STATUS_ORDER.indexOf(s.status) >= STATUS_ORDER.indexOf(target),
  },

  actions: {
    reset() {
      this.$reset()
    },

    async handle(label, fn) {
      this.loading = true
      this.error = null
      try {
        const result = await fn()
        return result
      } catch (e) {
        this.error = `${label}: ${e.message || e}`
        throw e
      } finally {
        this.loading = false
      }
    },

    async createProject(payload) {
      const data = await this.handle('create project', () => api.createProject(payload))
      this.id = data.project_id
      this.status = data.status?.status || null
      this.candidates = data.candidates || []
      this.selectedCandidate = data.selected_candidate || null
      this.summary = data
      return data
    },

    async loadProject(id) {
      this.id = id
      const summary = await this.handle('load project', () => api.showProject(id))
      this.summary = summary
      this.status = summary.status?.status || null
      this.candidates = summary.candidates || []
      this.selectedCandidate = summary.selected_candidate || null
      return summary
    },

    async refreshStatus() {
      if (!this.id) return
      const s = await api.projectStatus(this.id)
      this.status = s?.current?.status || null
      return s
    },

    async loadCandidates(id) {
      this.id = id
      const raw = await this.handle('load candidates', () => api.candidates(id))
      const list = Array.isArray(raw) ? raw : (raw.data || raw.candidates || [])
      this.candidates = list
      return this.candidates
    },

    async selectCandidate(index) {
      const candidate = await this.handle('select candidate', () => api.selectCandidate(this.id, index))
      this.selectedCandidate = candidate || this.candidates.find((c) => c.index === index) || null
      await this.refreshStatus()
    },

    async runAnalysis() {
      const data = await this.handle('analyze building', () => api.analyzeBuilding(this.id))
      this.analysis = data.analysis || data
      await this.refreshStatus()
      return this.analysis
    },

    async runLayout() {
      const data = await this.handle('generate layout', () => api.generateLayout(this.id))
      this.layout = data.layout || data
      await this.refreshStatus()
      return this.layout
    },

    async runRender() {
      const data = await this.handle('generate render', () => api.generateRender(this.id))
      this.render = data
      await this.refreshStatus()
      return data
    },

    async runPricing() {
      const data = await this.handle('generate pricing', () => api.generatePricing(this.id))
      this.pricing = data.pricing || data
      await this.refreshStatus()
      return this.pricing
    },

    async runProposal() {
      const data = await this.handle('generate proposal', () => api.generateProposal(this.id))
      this.proposal = data.summary || data
      await this.refreshStatus()
      return data
    },

    async loadProposal(id) {
      this.id = id
      const data = await this.handle('load proposal', () => api.proposal(id))
      this.proposal = data.summary || data
      return data
    },
  },
})
