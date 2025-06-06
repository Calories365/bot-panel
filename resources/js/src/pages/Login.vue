<script setup>
import { computed, onBeforeUnmount, reactive } from "vue";
import { useStore } from "vuex";
import { useRouter } from "vue-router";
import { actionTypes } from "@/store/modules/auth.js";
import Loader from "@/Components/UI/Loader.vue";

const store = useStore();
const router = useRouter();

const formState = reactive({
    email: "",
    password: "",
    //
});

const isSubmitting = computed(() => store.state.auth.isSybmiting);
const validationErrors = computed(() => store.state.auth.validationErrors);

onBeforeUnmount(() => {
    store.dispatch(actionTypes.destroyErrors);
});
const onSubmit = () => {
    store
        .dispatch(actionTypes.login, {
            email: formState.email,
            password: formState.password,
            remember: true,
        })
        .then(() => {
            router.push({ name: "showBots" });
            formState.email = "";
            formState.password = "";
        })
        .catch((e) => {
            console.error("Login failed:", e);
        });
};
</script>

<template>
    <loader v-if="isSubmitting" />

    <div
        :class="{ loading: isSubmitting }"
        class="login-page"
        style="min-height: 495.6px"
    >
        <div class="login-box">
            <div class="login-logo">
                <a href="#" class="logo-container">
                    <div class="logo-text"><b>TG ADM</b></div>
                </a>
            </div>
            <div class="card">
                <div class="card-body login-card-body">
                    <p class="login-box-msg">Войдите чтобы начать</p>
                    <p class="login-box-msg"></p>
                    <form @submit.prevent="onSubmit">
                        <div class="input-group mb-3">
                            <input
                                type="email"
                                class="form-control"
                                placeholder="email"
                                autocomplete="email"
                                required
                                v-model="formState.email"
                            />
                            <div class="input-group-append">
                                <div class="input-group-text"></div>
                            </div>
                        </div>
                        <div class="input-group mb-3">
                            <input
                                type="password"
                                class="form-control"
                                placeholder="Пароль"
                                v-model="formState.password"
                            />
                            <div class="input-group-append">
                                <div class="input-group-text"></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col text-center">
                                <button type="submit" class="btn btn-primary">
                                    Войти
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">
.loading {
    opacity: 0.5;
    pointer-events: none;
}
</style>
