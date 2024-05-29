<script setup>
import {computed, defineEmits, onMounted, ref} from 'vue';
import {useStore} from "vuex";
import router from "@/router/router.js";
import {actionTypes, getterTypes} from "@/store/modules/users.js";
import BotsTable from "@/Components/BotsTable.vue";
import {usersTableConfig} from "@/ComponentConfigs/TableConfigs.js";
import {useRoute} from "vue-router";

const store = useStore();
const users = computed(() => store.getters[getterTypes.users]);
const isSubmitting = computed(() => store.getters[getterTypes.isSubmitting]);
const pagination = computed(() => store.getters[getterTypes.pagination]);
const route = useRoute();
const sizeOptions = [10, 20, 30, 40, 50];

const prePageText = 'Количество пользователей на странице';
const currentPage = ref(1);
const pageSize = ref(10);
const emit = defineEmits(['handle']);

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

onMounted(() => {
    const botId = route.params.id;
    store.dispatch(actionTypes.getUsers, botId).then(() => {
    }).catch(error => {
        console.error('Failed to load users:', error);
    });
});

function handleEvent(event) {
    if (event.action === 'delete') {
        store.dispatch(actionTypes.deleteUser, {id: event.id});
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

}
</script>

<template>
    <div class="col-12">
        <div class="card">
            <botsTable
                :per-page-text="prePageText"
                :columns="usersTableConfig"
                :data="users"
                :total-pages="pagination.totalPages"
                :current-page="pagination.currentPage"
                :page-size-options="sizeOptions"
                :per-page="pagination.perPage"
                @update:page-change="handlePageChange"
                @update:page-size-change="handlePageSizeChange"
                @handle="handleEvent"/>
        </div>
    </div>
</template>

<style scoped lang="scss">
.table-wrapper {
    overflow-x: auto;
}
</style>
