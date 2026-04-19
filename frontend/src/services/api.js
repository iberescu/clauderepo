import axios from 'axios'

const client = axios.create({
  baseURL: '/api/v1',
  headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
  timeout: 45_000,
})

client.interceptors.response.use(
  (r) => r,
  (error) => {
    const msg =
      error.response?.data?.message ||
      error.response?.statusText ||
      error.message ||
      'Request failed'
    return Promise.reject(new Error(msg))
  },
)

function data(promise) {
  return promise.then((r) => r.data?.data ?? r.data)
}

export const api = {
  health:               ()             => data(client.get('/health')),

  listProjects:         ()             => data(client.get('/projects')),
  createProject:        (body)         => data(client.post('/projects', body)),
  showProject:          (id)           => data(client.get(`/projects/${id}`)),
  projectStatus:        (id)           => data(client.get(`/projects/${id}/status`)),

  candidates:           (id)           => data(client.get(`/projects/${id}/candidates`)),
  selectCandidate:      (id, index)    => data(client.post(`/projects/${id}/select-candidate`, { index })),

  analyzeBuilding:      (id)           => data(client.post(`/projects/${id}/analyze-building`)),
  analysis:             (id)           => data(client.get(`/projects/${id}/analysis`)),
  solarImageUrl:        (id, name)     => `/api/v1/projects/${id}/solar/images/${name}`,

  generateLayout:       (id)           => data(client.post(`/projects/${id}/generate-layout`)),
  layout:               (id)           => data(client.get(`/projects/${id}/layout`)),

  generateRender:       (id)           => data(client.post(`/projects/${id}/generate-render`)),
  render:               (id)           => data(client.get(`/projects/${id}/render`)),
  renderImageUrl:       (id, name)     => `/api/v1/projects/${id}/render/images/${name}`,

  generatePricing:      (id)           => data(client.post(`/projects/${id}/generate-pricing`)),
  pricing:              (id)           => data(client.get(`/projects/${id}/pricing`)),

  generateProposal:     (id)           => data(client.post(`/projects/${id}/generate-proposal`)),
  proposal:             (id)           => data(client.get(`/projects/${id}/proposal`)),
  proposalHtmlUrl:      (id)           => `/api/v1/projects/${id}/proposal/html`,
  proposalPdfUrl:       (id)           => `/api/v1/projects/${id}/proposal/pdf`,

  projectLogs:          (id)           => data(client.get(`/projects/${id}/logs`)),

  getSettings:          ()             => data(client.get('/settings')),
  updateSettings:       (section, values) => data(client.post('/settings', { section, values })),
}

export default api
