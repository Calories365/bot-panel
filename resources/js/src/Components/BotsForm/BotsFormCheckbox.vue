<script setup>
import {computed, defineEmits, defineProps} from 'vue';

const props = defineProps({
    label: String,
    data: [Boolean, Number],
    name: String
});

const emit = defineEmits(['updateValue']);

const checked = computed({
    get: () => props.data === 1,
    set: (newValue) => {
        emit('handle', {key: props.name, value: newValue ? 1 : 0});
    }
});
</script>

<template>
    <div class="form-group">
        <input type="checkbox" class="lead-checkbox" :name="name" :id="name" v-model="checked">
    </div>
</template>
