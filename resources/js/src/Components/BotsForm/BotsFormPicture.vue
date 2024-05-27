<script setup>
import {defineEmits, defineProps, ref, watch} from 'vue';

const props = defineProps({
    data: String,
    name: String
});
const emit = defineEmits(['handle']);

const imageUrl = ref('');
const file = ref(null);

if (props.data) {
    imageUrl.value = props.data;
}

function onFileChange(event) {
    const uploadedFile = event.target.files[0];
    if (uploadedFile && uploadedFile.type.startsWith('image')) {
        imageUrl.value = URL.createObjectURL(uploadedFile);
        file.value = uploadedFile;
        emit('handle', {key: props.name, value: uploadedFile});
    }
}

watch(() => props.data, (newVal) => {
    if (newVal) {
        imageUrl.value = newVal;
    }
});

</script>

<template>
    <div class="form-group">
        <input type="file" class="form-control-file" id="imageUpload" accept="image/*" @change="onFileChange">
        <img v-if="imageUrl" :src="imageUrl" alt="Загруженное изображение" style="margin-top: 20px; max-width: 200px;">
    </div>
</template>

<style scoped>
.form-group {
    margin-bottom: 1rem;
}
</style>
