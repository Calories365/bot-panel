<script setup>
import BotsTable from "@/Components/BotsTable.vue";
import {computed, defineEmits, onMounted, ref} from 'vue';
import {useStore} from "vuex";
import {actionTypes, getterTypes} from "@/store/modules/bots.js";
import router from "@/router/router.js";
import {botsTableConfig} from "@/ComponentConfigs/TableConfigs.js";
import BotsConfirmatiomModal from "@/Components/UI/BotsConfirmatiomModal.vue";
import SwastikaLoader from "@/Components/UI/Swastika-loader.vue"; // Импортируем компонент загрузки

const store = useStore();
const bots = computed(() => store.getters[getterTypes.bots]);
const isSubmitting = computed(() => store.getters[getterTypes.isSubmitting]);
const pagination = computed(() => store.getters[getterTypes.pagination]);

const sizeOptions = [10, 20, 30, 40, 50];

const prePageText = 'Количество ботов на странице';
const currentPage = ref(1);
const pageSize = ref(10);
const emit = defineEmits(['handle']);
const showModal = ref(false);
const selectedBotId = ref(null);

const handlePageChange = (page) => {
    currentPage.value = page;
    store.dispatch(actionTypes.changePage, {page});
    window.scrollTo({
        top: 0,
        left: 0,
        behavior: 'smooth'
    });
};

const handlePageSizeChange = (size) => {
    pageSize.value = size;
    store.dispatch(actionTypes.setPageSize, {size});
    window.scrollTo({
        top: 0,
        left: 0,
        behavior: 'smooth'
    });
};

function handleEvent(event) {
    if (event.action === 'delete') {
        selectedBotId.value = event.id;
        showModal.value = true;
    }
    if (event.action === 'show') {
        router.push({name: 'showBot', params: {id: event.id}});
    }
}

const confirmDelete = () => {
    store.dispatch(actionTypes.deleteBot, {id: selectedBotId.value});
    selectedBotId.value = null;
};

onMounted(() => {
    store.dispatch(actionTypes.getAllBots).then(allBots => {
    }).catch(error => {
        console.error('Failed to load bots:', error);
    });
});
</script>

<template>
    <swastika-loader v-if="isSubmitting"/>

    <div :class="{'loading': isSubmitting}" class="col-12">
        <div class="card">
            <BotsTable
                :per-page-text="prePageText"
                :columns="botsTableConfig"
                :data="bots"
                :total-pages="pagination.totalPages"
                :current-page="pagination.currentPage"
                :page-size-options="sizeOptions"
                :per-page="pagination.perPage"
                @update:page-change="handlePageChange"
                @update:page-size-change="handlePageSizeChange"
                @handle="handleEvent"/>
        </div>
    </div>

    <BotsConfirmatiomModal
        title="Подтверждение действия"
        message="Вы уверены, что хотите удалить этого бота?"
        :showModal="showModal"
        @update:showModal="showModal = $event"
        @confirm="confirmDelete"
    />
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
