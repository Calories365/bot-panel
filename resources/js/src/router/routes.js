import MainLayout from "@/Layouts/MainLayout.vue";
import Login from "@/pages/Login.vue";
import ShowBots from "@/pages/ShowBots.vue";
import AddBot from "@/pages/AddBot.vue";
import ShowAdmins from "@/pages/ShowAdmins.vue";
import AddAdmin from "@/pages/AddAdmin.vue";
import ShowBot from "@/pages/ShowBot.vue";
import ShowUsers from "@/pages/ShowUsers.vue";
import showAdmin from "@/pages/ShowAdmin.vue";

const routes = [
    {
        path: '/',
        component: MainLayout,
        children: [
            {
                path: '/showBots',
                name: 'showBots',
                component: ShowBots,
                meta: {
                    needAuth: true,
                    breadcrumb: 'Все боты',
                },
            },
            {
                path: '/showBots/:id',
                name: 'showBot',
                component: ShowBot,
                meta: {
                    needAuth: true,
                    breadcrumb: 'Бот',
                },
            },
            {
                path: '/addBot',
                name: 'addBot',
                component: AddBot,
                meta: {
                    needAuth: true,
                    breadcrumb: 'Добавить бота',
                },
            }, {
                path: '/showUsers',
                name: 'showUsers',
                component: ShowUsers,
                meta: {
                    needAuth: true,
                    breadcrumb: 'Все пользователи',
                },
            },
            {
                path: '/addAdmin',
                name: 'addAdmin',
                component: AddAdmin,
                meta: {
                    needAuth: true,
                    breadcrumb: 'Добавить Админа',
                },
            }, {
                path: '/showAdmins',
                name: 'showAdmins',
                component: ShowAdmins,
                meta: {
                    needAuth: true,
                    breadcrumb: 'Все Админы',
                },
            }, {
                path: '/showAdmins/:id',
                name: 'showAdmin',
                component: showAdmin,
                meta: {
                    needAuth: true,
                    breadcrumb: 'Админ',
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
