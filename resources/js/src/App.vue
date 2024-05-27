<script>
//font awesome
import CaloriesErrorNotification from "@/Components/CaloriesErrorNotification.vue";
import CaloriesSuccessNotification from "@/Components/CaloriesSuccessNotification.vue";


export default {
    data() {
        return {
            username: 'Пена',
            locale: localStorage.getItem('locale') || 'en',
        };
    },
    components: {
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
    // mounted() {
    //     this.$store.dispatch(actionTypes.setLocale, {locale: this.locale, i18n: this.$i18n})
    // }
}
</script>

<template>
    <router-view/>
</template>

<style lang="scss">


</style>
