<script setup>
import {defineProps} from 'vue'
import {actionTypes} from "@/store/modules/auth.js";

const props = defineProps({
    text: {
        type: String,
        required: true
    },
    icon: {
        type: Array,
        required: true
    },
    path: {
        type: String,
        required: true
    }
})

</script>

<template>
    <li class="nav-item">
        <router-link :to="path" class="nav-link">
            <font-awesome-icon :icon="icon"/>
            <p class="ml-1">{{ text }}</p>
        </router-link>
    </li>
</template>

<style scoped lang="scss">
</style>
