
<script>
import BotsContentHeader from "@/Components/BotsContentHeader.vue";
import BotsLeftMenu from "@/Components/MainLayout/BotsLeftMenu.vue";
import BotsToastSuccess from "@/Components/UI/BotsToastSuccess.vue";
import BotsToastError from "@/Components/UI/BotsToastError.vue";

export default {
    data() {
        return {
            username: 'Пена',
            locale: localStorage.getItem('locale') || 'en',
        };
    },
    components: {
        BotsLeftMenu,
        BotsContentHeader,
        BotsToastSuccess,
        BotsToastError,
    },
    computed: {
        successMessages() {
            return this.$store.getters.successMessages;
        },
        errorMessages() {
            return this.$store.getters.errorMessages;
        }
    },
}
</script>
<template>
    <body class="hold-transition sidebar-mini sidebar-collapse">
    <div v-if="successMessages.length">
        <div v-for="message in successMessages" :key="message">
            <BotsToastSuccess :message="message"/>
        </div>
    </div>
    <div v-if="errorMessages.length">
        <BotsToastError :messages="errorMessages"/>
    </div>
    <div class="wrapper">
        <BotsLeftMenu/>
        <div class="content-wrapper">
            <BotsContentHeader/>
            <section class="content">
                <router-view/>
            </section>
        </div>
    </div>
    </body>
</template>


<style scoped>

</style>
