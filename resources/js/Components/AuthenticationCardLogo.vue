<script setup>
import { Link } from '@inertiajs/inertia-vue3';
</script>

<template>
    <Link :href="'/'">
        <img :src="'/images/logo.png'" style="width: 230px; height: 57px" />
    </Link>
    <span>Radenviro</span>
</template>
