<script setup>
import {computed, ref} from 'vue';
import {actionTypes, getterTypes} from '@/store/modules/admins.js';
import {useStore} from "vuex";
import {useRoute} from "vue-router";
import BotsForm from "@/Components/BotsForm.vue";
import router from "@/router/router.js";
import Loader from "@/Components/UI/Loader.vue";
import {useHandleEvent} from "@/Composables/useHandleEvent.js";
import {admin_rows} from "@/ComponentConfigs/Form/Admin/admin_rows.js";

const store = useStore();
const route = useRoute();
const localAdminData = ref({});
const isSubmitting = computed(() => store.getters[getterTypes.isSubmitting]);

const {handleEvent} = useHandleEvent({
    localData: localAdminData,
    actions: {submit: createAdmin}
});
function createAdmin() {
    store.dispatch(actionTypes.createAdmin, localAdminData.value).then((id) => {
        router.push(`/showAdmins/${id}`);
    });
}

</script>

<template>
    <loader v-if="isSubmitting"/>

    <div :class="{'loading': isSubmitting}" class="row">
        <div class="col-md-12">
            <div class="card card-primary">
                <div class="card-header">
                    Add admin111
                </div>
                <bots-form
                    :data="localAdminData"
                    :rows="admin_rows"
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
