<script setup>
import { RouterLink } from 'vue-router'
</script>

<template>
  <section class="card text-center py-16">
    <div class="text-6xl font-bold text-brand-700">404</div>
    <p class="mt-2 text-slate-500">That page doesn't exist.</p>
    <RouterLink to="/" class="btn-primary mt-6">Back to search</RouterLink>
  </section>
</template>
