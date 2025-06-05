<script setup>
defineProps({
    value: Number,
    text: String,
});
</script>

<template>
    <option :value="value">{{ text }}</option>
</template>
