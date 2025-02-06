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
                    breadcrumb: 'All bots',
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
                    breadcrumb: 'Bot',
                },
            },
            {
                path: '/addBot',
                name: 'addBot',
                component: () => import('@/pages/AddBot.vue'),
                meta: {
                    needAuth: true,
                    breadcrumb: 'Add bot',
                },
            }, {
                path: '/showUsers/:id?',
                name: 'showUsers',
                component: () => import('@/pages/ShowUsers.vue'),
                meta: {
                    needAuth: true,
                    breadcrumb: 'All users',
                },
            },
            {
                path: '/addAdmin',
                name: 'addAdmin',
                component: () => import('@/pages/AddAdmin.vue'),
                meta: {
                    needAuth: true,
                    breadcrumb: 'Add admin',
                },
            }, {
                path: '/showAdmins',
                name: 'showAdmins',
                component: () => import('@/pages/ShowAdmins.vue'),
                meta: {
                    needAuth: true,
                    breadcrumb: 'All Admins',
                },
            }, {
                path: '/showAdmins/:id',
                name: 'showAdmin',
                component: () => import('@/pages/ShowAdmin.vue'),
                meta: {
                    needAuth: true,
                    breadcrumb: 'Admin',
                },
            }, {
                path: '/addManager', name: 'addManager', component: () => import('@/pages/AddManager.vue'), meta: {
                    needAuth: true, breadcrumb: 'Add a Manager',
                },
            }, {
                path: '/showManagers',
                name: 'showManagers',
                component: () => import('@/pages/ShowManagers.vue'),
                meta: {
                    needAuth: true, breadcrumb: 'All Managers',
                },
            }, {
                path: '/showManagers/:id',
                name: 'showManager',
                component: () => import('@/pages/ShowManager.vue'),
                meta: {
                    needAuth: true, breadcrumb: 'Manager',
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
