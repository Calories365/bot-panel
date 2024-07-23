import MainLayout from "@/Layouts/MainLayout.vue";
import Login from "@/pages/Login.vue";

const routes = [
    {
        path: '/',
        component: MainLayout,
        children: [
            {
                path: '/showBots',
                name: 'showBots',
                component: () => import('@/pages/ShowBots.vue'),
                meta: {
                    needAuth: true,
                    breadcrumb: 'Все боты',
                },
            }, {
                path: '/',
                name: 'home',
                component: () => import('@/pages/Home.vue'),
                meta: {
                    needAuth: true,
                    breadcrumb: 'Home',
                },
            },
            {
                path: '/showBots/:id',
                name: 'showBot',
                component: () => import('@/pages/ShowBot.vue'),
                meta: {
                    needAuth: true,
                    breadcrumb: 'Бот',
                },
            },
            {
                path: '/addBot',
                name: 'addBot',
                component: () => import('@/pages/AddBot.vue'),
                meta: {
                    needAuth: true,
                    breadcrumb: 'Добавить бота',
                },
            }, {
                path: '/showUsers/:id?',
                name: 'showUsers',
                component: () => import('@/pages/ShowUsers.vue'),
                meta: {
                    needAuth: true,
                    breadcrumb: 'Все пользователи',
                },
            },
            {
                path: '/addAdmin',
                name: 'addAdmin',
                component: () => import('@/pages/AddAdmin.vue'),
                meta: {
                    needAuth: true,
                    breadcrumb: 'Добавить Админа',
                },
            }, {
                path: '/showAdmins',
                name: 'showAdmins',
                component: () => import('@/pages/ShowAdmins.vue'),
                meta: {
                    needAuth: true,
                    breadcrumb: 'Все Админы',
                },
            }, {
                path: '/showAdmins/:id',
                name: 'showAdmin',
                component: () => import('@/pages/ShowAdmin.vue'),
                meta: {
                    needAuth: true,
                    breadcrumb: 'Админ',
                },
            }, {
                path: '/addManager', name: 'addManager', component: () => import('@/pages/AddManager.vue'), meta: {
                    needAuth: true, breadcrumb: 'Добавить Менеджара',
                },
            }, {
                path: '/showManagers',
                name: 'showManagers',
                component: () => import('@/pages/ShowManagers.vue'),
                meta: {
                    needAuth: true, breadcrumb: 'Все Менеджеры',
                },
            }, {
                path: '/showManagers/:id',
                name: 'showManager',
                component: () => import('@/pages/ShowManager.vue'),
                meta: {
                    needAuth: true, breadcrumb: 'Менеджер',
                },
            },
        ]
    }, {
        path: '/login',
        name: 'login',
        component: Login,
        meta: {
            needNotAuth: true,
        },
    },
];


export default routes;
