import './bootstrap';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';

// Create Vue app
const app = createApp(App);

// Use Pinia for state management
const pinia = createPinia();
app.use(pinia);

// Mount the app
app.mount('#app');
