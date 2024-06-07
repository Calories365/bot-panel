<script>

export default {
    name: "Home",
    beforeRouteEnter(to, from, next) {
        next({name: 'showBots'});
    },
}
</script>

<template>
    <h1>Home</h1>
</template>


<style lang="scss">

</style>
