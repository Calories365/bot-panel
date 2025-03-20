<script setup>
import { computed } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { useStore } from 'vuex';
import {actionTypes} from "../store/modules/language.js";

const router = useRouter();
const route = useRoute();
const store = useStore();

const breadcrumbs = computed(() => {
    let matched = route.matched.filter(record => record.meta && record.meta.breadcrumb);
    let breadcrumbs = matched.map(record => ({
        text: record.meta.breadcrumb,
        path: record.path
    }));

    breadcrumbs.unshift({ text: 'Home', path: '/' });

    return breadcrumbs;
});

const enableRussianLanguage = async () => {
    try {
        await store.dispatch('language/toggleRussianLanguage', { enabled: true });
    } catch (error) {
        console.error('Error enabling Russian language:', error);
    }
};

const disableRussianLanguage = async () => {
    try {
        await store.dispatch('language/toggleRussianLanguage', { enabled: false });

    } catch (error) {
        console.error('Error disabling Russian language:', error);
    }
};
</script>

<template>
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1>Bot Panel</h1>
                    <div class="mt-2">
                        <button @click="enableRussianLanguage" class="btn btn-success mr-2">
                            Enable Russian Language
                        </button>
                        <button @click="disableRussianLanguage" class="btn btn-danger">
                            Disable Russian Language
                        </button>
                    </div>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li v-for="(crumb, index) in breadcrumbs" :key="index" class="breadcrumb-item">
                            <router-link :to="crumb.path">{{ crumb.text }}</router-link>
                        </li>
                    </ol>
                </div>
            </div>
        </div>
    </section>
</template>
