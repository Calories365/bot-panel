<script setup>
import {defineEmits, defineProps, ref, watch} from 'vue';

const props = defineProps({
    data: Object,
    name: String
});
const emit = defineEmits(['handle']);

const imageUrl = ref('');
const file = ref(null);

if (props.data && props.data.image_url) {
    imageUrl.value = props.data.image_url;
}

function onFileChange(event) {
    const uploadedFile = event.target.files[0];
    if (!uploadedFile) return;
    if (uploadedFile.type.startsWith('image') || uploadedFile.type.startsWith('video')) {
        imageUrl.value = URL.createObjectURL(uploadedFile);
        file.value = uploadedFile;
        emit('handle', { key: props.name, value: { image_url: imageUrl.value, image_file: uploadedFile } });
    }
}

watch(() => props.data, (newVal) => {
    if (newVal) {
        imageUrl.value = newVal.image_url;
        file.value = newVal.image_file;
    }
});

</script>

<template>
    <div class="form-group">
        <input type="file" class="form-control-file" id="imageUpload" @change="onFileChange">
        <img v-if="imageUrl" :src="imageUrl" alt="Загруженное изображение" style="margin-top: 20px; max-width: 200px;">
    </div>
</template>

<style scoped>
.form-group {
    margin-bottom: 1rem;
}
</style>
