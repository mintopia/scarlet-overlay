<template><AdminLayout><Head title="Edit Journey" /><h1 class="text-[22px] font-bold mb-6">Edit Journey</h1></AdminLayout></template>
<script setup>
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
defineProps({ journey: Object });
</script>
