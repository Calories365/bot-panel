<script setup>
import {computed, defineEmits, defineProps} from 'vue';

const props = defineProps({
    placeholder: String,
    data: {
        type: Array,
        default: [{id: 1, name: 'Default', active: true}]
    },
    name: String,
    emit_name: String,
});

const emit = defineEmits(['handle']);

const selectedType = computed({
    get: () => {
        const active = props.data.find(type => type.active);
        return active ? active.id : (props.data.length > 0 ? props.data[0].id : undefined);
    },
    set: (newValue) => {
        emit('handle', {key: props.emit_name, value: newValue});
    }
});
</script>

<template>
    <div class="form-group">
        <select id="botTypeDropdown" class="form-control" v-model="selectedType">
            <option v-for="type in props.data" :key="type.id" :value="type.id">
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
