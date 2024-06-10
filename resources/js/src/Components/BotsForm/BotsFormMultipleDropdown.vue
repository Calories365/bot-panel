<script setup>
import {defineEmits, defineProps, ref} from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

const props = defineProps({
    placeholder: String,
    data: {
        type: Object,
        default: () => ({})
    },
    name: String,
});

const emit = defineEmits(['handle']);

const options = ref(props.data.allManagers || []);
const value = ref(props.data.managers || []);

const handleSelectionChange = (newValues) => {
    const dataToEmit = {
        managers: newValues,
        allManagers: props.data.allManagers
    };
    emit('handle', {key: props.name, value: dataToEmit});
};
</script>

<template>
    <div>
        <multiselect
            v-model="value"
            :placeholder="'Выберите значение'"
            :tag-placeholder="'Добавить тег'"
            label="name"
            track-by="id"
            :options="options"
            :multiple="true"
            :taggable="true"
            :open-direction="'bottom'"
            :select-label="'Нажмите для выбора'"
            :selected-label="'Выбрано'"
            :deselect-label="'Нажмите для удаления'"
            :show-labels="true"
            @update:modelValue="handleSelectionChange"
        ></multiselect>

    </div>
</template>

<style>
.form-group {
    margin-bottom: 1rem;
}

.multiselect__tag {
    position: relative;
    display: inline-block;
    padding: 4px 26px 4px 10px;
    border-radius: 5px;
    margin-right: 10px;
    color: #fff;
    line-height: 1;
    background: #007bff;
    margin-bottom: 5px;
    white-space: nowrap;
    overflow: hidden;
    max-width: 100%;
    text-overflow: ellipsis;
}

.multiselect__tag-icon::after {
    content: "×";
    color: #266d4d;
    font-size: 14px;
}

.multiselect__option--highlight {
    background: #007bff;
    outline: none;
    color: white;
}

.multiselect__option--highlight::after {
    content: attr(data-select);
    background: #007bff;
    color: white;
}

.multiselect__tag-icon::after {
    content: "×";
    color: #ffffff;
    font-size: 14px;
}

.multiselect__tag-icon:hover::after {
    color: red;
}

</style>
