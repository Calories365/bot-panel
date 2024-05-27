<script setup>
import {defineEmits, defineProps, ref, watch} from 'vue';

const props = defineProps({
    options: {
        type: Array,
        required: true
    },
    value: {
        type: Number,
        default: null
    },
    perPageText: {
        type: String,
        default: null
    },
});

const emit = defineEmits(['update:value']);
const selectedValue = ref(null);

watch(() => props.value, (newValue) => {
    if (newValue !== null) {
        selectedValue.value = newValue;
    } else if (props.options.length > 0) {
        selectedValue.value = props.options[0];
        emit('update:value', selectedValue.value);
    }
}, {immediate: true});

const handleChange = (event) => {
    selectedValue.value = parseInt(event.target.value, 10);
    emit('update:page-size', selectedValue.value);
};
</script>

<template>
    <div class="text-right mb-3 mr-3 col-md-auto">
        <form method="get">
            <label>{{ perPageText }}</label>
            <select class="form-control" v-model="selectedValue" @change="handleChange">
                <option v-for="option in props.options" :key="option" :value="option">{{ option }}</option>
            </select>
        </form>
    </div>
</template>

<style scoped lang="scss">
</style>
