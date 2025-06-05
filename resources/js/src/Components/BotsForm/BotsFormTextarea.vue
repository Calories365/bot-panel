<script setup>
import { computed, defineEmits, defineProps } from "vue";

const props = defineProps({
    placeholder: String,
    data: String,
    name: String,
    required: Boolean,
});

const emit = defineEmits(["handle"]);

const textValue = computed({
    get: () => props.data,
    set: (newValue) => {
        emit("handle", { key: props.name, value: newValue });
    },
});
</script>

<template>
    <div class="form-group">
        <textarea
            id="emailTextarea"
            class="form-control"
            :placeholder="placeholder"
            v-model="textValue"
            rows="4"
            :required="required"
        >
        </textarea>
    </div>
</template>

<style scoped>
.form-group {
    margin-bottom: 1rem;
}
</style>
