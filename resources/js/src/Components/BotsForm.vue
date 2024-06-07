<script setup>
import {defineEmits, defineProps, ref} from 'vue';
import BotsFormInput from "@/Components/BotsForm/BotsFormInput.vue";
import BotsFormDropdown from "@/Components/BotsForm/BotsFormDropdown.vue";
import BotsFormTextarea from "@/Components/BotsForm/BotsFormTextarea.vue";
import BotsFormPicture from "@/Components/BotsForm/BotsFormPicture.vue";
import BotsFormCheckbox from "@/Components/BotsForm/BotsFormCheckbox.vue";
import BotsFormButtons from "@/Components/BotsForm/BotsFormButtons.vue";

const props = defineProps({
    rows: Array,
    data: Object,
});

const componentMap = {
    default: BotsFormInput,
    input: BotsFormInput,
    dropdown: BotsFormDropdown,
    textarea: BotsFormTextarea,
    picture: BotsFormPicture,
    checkbox: BotsFormCheckbox,
    buttons: BotsFormButtons,
};
const formRef = ref(null);
function getComponentType(type) {
    return componentMap[type] || componentMap['default'];
}

function handleEvent(payload) {
    if (payload.action === 'submit') {
        if (formRef.value.checkValidity()) {
            emit('handle', payload);
        } else {
            formRef.value.reportValidity();
        }
    } else {
        emit('handle', payload);
    }
}

const emit = defineEmits(['handle']);
const errors = ref({});
</script>

<template>
    <form ref="formRef" action="#" class="card-body">
        <div v-for="(row, index) in props.rows" :key="index" class="form-group">
            <label :for="row.key">{{ row.label }}</label>
            <component
                :emit_name="row.emit_name"
                :name="row.key"
                :required="row.required"
                :data="data[row.key]"
                :is="getComponentType(row.type)"
                :placeholder="row.placeholder"
                :options="row.options || {}"
                @handle="handleEvent"
            />
        </div>
    </form>
</template>

<style scoped>
.form-group {
    margin-bottom: 1rem;
}
.card-body {
    padding: 20px;
}

.error {
    color: red;
    font-size: 0.875rem;
}
</style>
