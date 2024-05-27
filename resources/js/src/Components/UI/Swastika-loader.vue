<script setup>
</script>

<template>
    <div class="design-wrapper">
        <div class="swastik-wrapper">
            <div class="swastik1"></div>
            <div class="swastik1 rotate-90"></div>
        </div>
    </div>
</template>

<script>
export default {
    name: "SwastikaLoader",
};
</script>

<style scoped>
body {
    background-color: #3f3c3b;
}

.swastik-wrapper {
    width: 100%;
    height: 33%;
    display: flex;
    justify-content: center;
    align-items: center;
}

.design-wrapper {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    transform: scale(3) translate(-50%, -50%);
    margin: 0 4rem;
    width: 50px;
    height: 100px;
    position: fixed;
    top: 50%;
    left: 50%;
    z-index: 9999;
    animation: rotate 2s linear infinite;
}

@keyframes rotate {
    0% {
        transform: scale(3) translate(-50%, -50%) rotate(0deg);
    }
    100% {
        transform: scale(3) translate(-50%, -50%) rotate(360deg);
    }
}

.swastik1 {
    width: 30px;
    height: 2px;
    background-color: rgb(0, 0, 0);
    position: absolute;
}

.swastik1:before {
    content: "";
    width: 2px;
    height: 15px;
    background-color: rgb(0, 0, 0);
    top: -14px;
    position: absolute;
}

.swastik1:after {
    content: "";
    width: 2px;
    height: 15px;
    background-color: rgb(0, 0, 0);
    left: 28px;
    position: absolute;
}

.rotate-90 {
    transform: rotate(90deg);
}
</style>
