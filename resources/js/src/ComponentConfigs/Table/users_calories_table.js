export const users_calories_table = [
    {label: 'ID', key: 'id', type: 'default', action: null, limit: 40},
    {label: 'Имя', key: 'name', type: 'link', action: 'show', limit: 40},
    {label: 'Юзернейм тг', key: 'username', type: 'link', action: 'telegram', limit: 100},
    {label: 'Юзернейм калорий', key: 'username_calories', type: 'default', action: null, limit: 100},
    {label: 'Почта', key: 'email', type: 'default', action: null, limit: 100},
    {label: 'Telegram ID', key: 'telegram_id', type: 'default', action: null, limit: 40},
    {label: 'Премиум', key: 'premium_calories', type: 'checkbox', action: null, limit: 40},
    {label: 'Источник', key: 'source', type: 'default', action: null, limit: 40},
    {label: 'Забанил Бота', key: 'delete', type: 'checkbox', action: null, limit: 40},
    {label: 'Дата регистрации', key: 'created_at', type: 'default', action: null, limit: 40},
    {label: 'Удаление', key: 'delete', type: 'button', action: 'delete', limit: 40},
];
