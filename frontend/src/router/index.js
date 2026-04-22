import { createRouter, createWebHistory } from 'vue-router';
import ReaderView from '../views/ReaderView.vue';

const routes = [
  {
    path: '/',
    name: 'reader',
    component: ReaderView,
  },
  {
    path: '/reader',
    redirect: '/',
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

export default router;
