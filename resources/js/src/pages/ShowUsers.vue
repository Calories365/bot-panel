<script setup>
import {computed, defineEmits, onMounted, onUnmounted, ref, watch} from 'vue';
import {useStore} from "vuex";
import router from "@/router/router.js";
import {actionTypes, getterTypes} from "@/store/modules/users.js";
import BotsTable from "@/Components/BotsTable.vue";
import {useRoute} from "vue-router";
import BotsConfirmatiomModal from "@/Components/UI/BotsConfirmatiomModal.vue";
import SwastikaLoader from "@/Components/UI/Swastika-loader.vue";
import BotsButtonWarning from "@/Components/UI/BotsButtonWarning.vue";
import {users_table} from "@/ComponentConfigs/Table/users_table.js";

const store = useStore();
const route = useRoute();
const users = computed(() => store.getters[getterTypes.users]);
const isSubmitting = computed(() => store.getters[getterTypes.isSubmitting]);
const pagination = computed(() => store.getters[getterTypes.pagination]);
const downloadLink = ref('');
const sizeOptions = [10, 20, 30, 40, 50];
const prePageText = 'Количество пользователей на странице';
const currentPage = ref(1);
const pageSize = ref(10);
const emit = defineEmits(['handle']);
const showModal = ref(false);
const selectedUserId = ref(null);

function fetchData(botId) {
    const params = {
        page: currentPage.value,
        perPage: pageSize.value,
    };
    if (botId) {
        params.botId = botId;
    }
    store.dispatch(actionTypes.getUsers, params).then(() => {
        console.log('Users loaded successfully', botId ? `for bot: ${botId}` : 'for all bots');
    }).catch(error => {
        console.error('Failed to load users:', error);
    });
}

watch(() => route.params.id, (newId, oldId) => {
    if (newId !== oldId) {
        currentPage.value = 1;
        fetchData(newId);
    }
});

onMounted(() => {
    fetchData(route.params.id);
});

onUnmounted(() => {
    store.dispatch(actionTypes.destroyUsers)
});

const handlePageChange = (page) => {
    currentPage.value = page;
    fetchData(route.params.id);
    window.scrollTo({top: 0, left: 0, behavior: 'smooth'});
};

const handlePageSizeChange = (size) => {
    pageSize.value = size;
    fetchData(route.params.id);
    window.scrollTo({top: 0, left: 0, behavior: 'smooth'});
};

const handleEvent = (event) => {
    if (event.action === 'delete') {
        selectedUserId.value = event.id;
        showModal.value = true;
    }
    if (event.action === 'show') {
        router.push({name: 'showUser', params: {id: event.id}});
    }
    if (event.action === 'showBot') {
        router.push({name: 'showBot', params: {id: event.id}});
    }
    if (event.action === 'telegram') {
        window.open(`https://t.me/${event.data}`, '_blank');
    }
    if (event.action === 'usersExport') {
        const botId = route.params.id;
        store.dispatch(actionTypes.exportUsers, {botId}).then((response) => {
            downloadLink.value = response.downloadUrl;
        });
    }
};

const confirmDelete = () => {
    store.dispatch(actionTypes.deleteUser, {id: selectedUserId.value});
    selectedUserId.value = null;
};
</script>

<template>
    <swastika-loader v-if="isSubmitting"/>

    <div :class="{'loading': isSubmitting}" class="col-12">
        <div v-if="downloadLink">
            <a :href="downloadLink" download>Скачать файл</a>
        </div>
        <bots-confirmatiom-modal
            title="Подтверждение действия"
            message="Вы уверены, что хотите удалить этого пользователя?"
            :showModal="showModal"
            @update:showModal="showModal = $event"
            @confirm="confirmDelete"
        />
        <div v-if="users" class="row mb-2">
            <div class="col-2">
                <bots-button-warning @click="handleEvent({action: 'usersExport'})">
                    Экспорт
                </bots-button-warning>
            </div>
        </div>
        <div class="card">
            <botsTable
                :users=true
                :per-page-text="prePageText"
                :columns="users_table"
                :data="users"
                :total-pages="pagination.totalPages"
                :current-page="pagination.currentPage"
                :page-size-options="sizeOptions"
                :per-page="pagination.perPage"
                @update:page-change="handlePageChange"
                @update:page-size-change="handlePageSizeChange"
                @update:export="handleExport"
                @handle="handleEvent"/>
        </div>
    </div>
</template>

<style scoped lang="scss">
.loading {
    opacity: 0.5;
    pointer-events: none;
}
.table-wrapper {
    overflow-x: auto;
}
</style>
