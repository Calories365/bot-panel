<script>
import CaloriesErrorNotification from "@/Components/CaloriesErrorNotification.vue";
import CaloriesSuccessNotification from "@/Components/CaloriesSuccessNotification.vue";
import BotsContentHeader from "@/Components/BotsContentHeader.vue";
import BotsLeftMenu from "@/Components/MainLayout/BotsLeftMenu.vue";

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
        CaloriesSuccessNotification, CaloriesErrorNotification,
    },
    computed: {
        successMessage() {
            return this.$store.getters.isSuccess;
        },
        errorMessage() {
            return this.$store.getters.isError;
        }
    },
}
</script>

<template>
    <body class="hold-transition sidebar-mini sidebar-collapse">

    <div class="wrapper">

        <bots-left-menu/>
        <!--        <div class="content-wrapper" style="min-height: 757px">-->
        <div class="content-wrapper">

            <BotsContentHeader/>

            <!--            <section class="content">-->
            <!--                <div class="row">-->
            <!--                </div>-->
            <!--            </section>-->

            <section class="content">

                <router-view/>

            </section>


        </div>


    </div>


    </body>

</template>

<style lang="scss">


</style>
