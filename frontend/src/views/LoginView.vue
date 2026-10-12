<template>
    <div class="login">
        <form @submit.prevent="submit">
            <img class="logo" :src="logo" alt="" />
            <h1>casiopea</h1>
            <label>Usuario<input v-model="form.username" required autofocus autocomplete="username" /></label>
            <label>Contraseña<input type="password" v-model="form.password" required autocomplete="current-password" /></label>
            <p v-if="error" class="error">{{ error }}</p>
            <button class="primary" :disabled="busy">Entrar</button>
        </form>
        <footer>
            <p>made with love by <a href="https://github.com/sigifredo/" target="_blank" rel="noopener noreferrer">@sigifredo</a></p>
            <p>versión {{ version }}</p>
        </footer>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { auth } from '../store.js';

const version = __APP_VERSION__;
const logo = `${import.meta.env.BASE_URL}logo.svg`;
const route = useRoute();
const router = useRouter();
const form = reactive({ username: '', password: '' });
const error = ref('');
const busy = ref(false);

async function submit() {
    error.value = '';
    busy.value = true;
    try {
        await auth.login(form.username, form.password);
        const next = route.query.next;
        router.push(typeof next === 'string' && next.startsWith('/') && !next.startsWith('//') ? next : '/');
    } catch (e) {
        error.value = e.message;
    } finally {
        busy.value = false;
    }
}
</script>

<style scoped lang="scss">
@use '../styles/tokens' as *;

.login {
    margin: auto;
    width: min(360px, 92vw);

    form {
        @include panel(10px);
        padding: 24px;
    }
    .logo {
        display: block;
        width: 64px;
        height: 64px;
        margin: 0 auto 8px;
    }
    h1 {
        font-family: 'Kalam', cursive;
        font-weight: 700;
        text-align: center;
    }
    button {
        width: 100%;
        margin-top: 4px;
    }
    footer {
        margin-top: 16px;
        text-align: center;
        font-size: 12px;
        color: var(--muted);

        p {
            margin: 2px 0;
        }
    }
}
</style>
