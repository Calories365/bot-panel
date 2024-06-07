<script setup>
import {defineEmits, defineProps} from 'vue';
import BotsButtonDefault from "@/Components/UI/BotsButtonDefault.vue";
import BotsButtonWarning from "@/Components/UI/BotsButtonWarning.vue";
import BotsButtonDanger from "@/Components/UI/BotsButtonDanger.vue";

const props = defineProps({
    options: Array
});
const emit = defineEmits(['handle']);

const componentMap = {
    default: BotsButtonDefault,
    warning: BotsButtonWarning,
    danger: BotsButtonDanger
};

function getComponentType(type) {
    return componentMap[type] || componentMap['default'];
}

function handle(action) {
    emit('handle', {action: action});
}
</script>

<template>
    <div class="button-container">
        <div v-for="(option, index) in options" :key="index" class="button-item">
            <component :is="getComponentType(option.button_type)" @click.prevent="handle(option.action)">
                {{ option.text }}
            </component>
        </div>
    </div>
</template>

<style scoped>
.button-container {
    display: flex;
    flex-wrap: nowrap;
    justify-content: flex-start;
}

.button-item {
    margin-right: 10px;
}

.button-item:last-child {
    margin-right: 0;
}
</style>
