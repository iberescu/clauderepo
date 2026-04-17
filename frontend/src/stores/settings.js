import { defineStore } from 'pinia'
import api from '@/services/api'

export const useSettingsStore = defineStore('settings', {
  state: () => ({
    sections: null,
    loading: false,
    error: null,
  }),
  actions: {
    async load() {
      this.loading = true
      this.error = null
      try {
        this.sections = await api.getSettings()
      } catch (e) {
        this.error = e.message || String(e)
      } finally {
        this.loading = false
      }
    },
    async save(section, values) {
      this.loading = true
      this.error = null
      try {
        const merged = await api.updateSettings(section, values)
        if (this.sections) this.sections[section] = merged
        return merged
      } catch (e) {
        this.error = e.message || String(e)
        throw e
      } finally {
        this.loading = false
      }
    },
  },
})
