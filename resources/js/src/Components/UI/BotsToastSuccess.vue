<template>
    <div id="toastsContainerTopRight" class="toasts-top-right fixed">
        <div class="toast bg-success fade show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-body">{{message}}</div>
        </div>
    </div>
</template>

<script setup>
import {computed, ref} from 'vue';
import {useStore} from 'vuex';

const store = useStore();
const message = computed(() => store.getters.isSuccess);

const toastStyle = ref({
    position: 'fixed',
    top: '20px',
    right: '20px',
    zIndex: 1000,
});
</script>

<style scoped>
.toast {
    padding: 15px;
    margin-bottom: 10px;
    border: 1px solid transparent;
    border-radius: 4px;
    box-shadow: 0 3px 5px rgba(0, 0, 0, 0.2);
    color: #fff;
    font-size: 14px;
    font-weight: 400;
}

.toast-success {
    background-color: #28a745;
    border-color: #28a745;
}

.toast-message {
    margin: 0;
}
</style>
