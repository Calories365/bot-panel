<script setup>
import {computed, ref} from 'vue';
import {actionTypes, getterTypes} from '@/store/modules/managers.js';
import {useStore} from "vuex";
import {useRoute} from "vue-router";
import {manager_Rows} from "@/ComponentConfigs/FormConfigs.js";
import BotsForm from "@/Components/BotsForm.vue";
import router from "@/router/router.js";
import SwastikaLoader from "@/Components/UI/Swastika-loader.vue";
import {useHandleEvent} from "@/Composables/useHandleEvent.js";

const store = useStore();
const route = useRoute();
const localManagerData = ref({});
const isSubmitting = computed(() => store.getters[getterTypes.isSubmitting]);

const { handleEvent } = useHandleEvent({
    localData: localManagerData,
    actions: { submit: createManager }
});

function createManager() {
    store.dispatch(actionTypes.createManager, localManagerData.value).then((id) => {
        router.push(`/showManagers/${id}`);
    });
}

</script>

<template>
    <swastika-loader v-if="isSubmitting"/>

    <div :class="{'loading': isSubmitting}" class="row">
        <div class="col-md-12">
            <div class="card card-primary">
                <div class="card-header">
                    Добавить Менеджера
                </div>
                <bots-form
                    :data="localManagerData"
                    :rows="manager_Rows"
                    @handle="handleEvent"/>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">
.loading {
    opacity: 0.5;
    pointer-events: none;
}
</style>
