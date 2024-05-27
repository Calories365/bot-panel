<script setup>
import {computed, defineEmits, defineProps} from 'vue';

const props = defineProps({
    placeholder: String,
    data: String,
    name: String,
});

const emit = defineEmits(['handle']);

const emailValue = computed({
    get: () => props.data,
    set: (newValue) => {
        emit('handle', {key: props.name, value: newValue});
    }
});
</script>

<template>
    <div class="form-group">
        <input class="form-control" id="exampleInputEmail1"
               :placeholder="placeholder" v-model="emailValue">
    </div>
</template>

<style scoped>
.form-group {
    margin-bottom: 1rem;
}
</style>
