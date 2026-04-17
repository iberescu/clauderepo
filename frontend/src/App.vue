<script setup>
import AppHeader from '@/components/AppHeader.vue'
</script>

<template>
  <div class="min-h-full flex flex-col">
    <AppHeader />
    <main class="flex-1 max-w-6xl w-full mx-auto px-4 sm:px-6 py-8">
      <router-view v-slot="{ Component }">
        <transition name="fade" mode="out-in">
          <component :is="Component" />
        </transition>
      </router-view>
    </main>
    <footer class="border-t border-slate-200 bg-white/60 py-4 text-center text-xs text-slate-500">
      Solar Proposal MVP · deterministic layout · AI-enhanced rendering
    </footer>
  </div>
</template>

<style>
.fade-enter-active, .fade-leave-active { transition: opacity 120ms ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
