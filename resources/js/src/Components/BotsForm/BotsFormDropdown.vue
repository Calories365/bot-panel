<script setup>
import {computed, defineEmits, defineProps} from 'vue';

const props = defineProps({
    placeholder: String,
    data: {
        type: Object,
        default: () => ({
            type_id: 2,
            types: [
                {
                    id: 1,
                    name: "Default"
                },
            ]
        })
    },
    name: String,
});

const emit = defineEmits(['handle']);

const selectedType = computed({
    get: () => props.data.type_id,
    set: (newValue) => {
        const payload = {
            type_id: newValue,
            types: props.data.types
        };
        emit('handle', {key: props.name, value: payload});
    }
});
</script>

<template>
    <div class="form-group">
        <label v-if="props.placeholder" :for="name">{{ props.placeholder }}</label>
        <select id="botTypeDropdown" class="form-control" v-model="selectedType">
            <option v-for="type in props.data.types" :key="type.id" :value="type.id">
                {{ type.name }}
            </option>
        </select>
    </div>
</template>

<style scoped>
.form-group {
    margin-bottom: 1rem;
}
</style>
