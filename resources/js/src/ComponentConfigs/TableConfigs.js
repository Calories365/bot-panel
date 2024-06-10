export const botsTableConfig = [
    {label: 'ID', key: 'id', type: 'default', action: null, limit: 40},
    {label: 'Юзернейм', key: 'name', type: 'link', action: 'show', limit: 40},
    {label: 'Токен', key: 'token', type: 'default', action: null, limit: 100},
    {label: 'Сообщение', key: 'message', type: 'default', action: null, limit: 40},
    {label: 'Активный', key: 'active', type: 'checkbox', action: null, limit: 40},
    {label: 'Удаление', key: 'delete', type: 'button', action: 'delete', limit: 40},
];

export const adminsTableConfig = [
    {label: 'ID', key: 'id', type: 'default', action: null, limit: 40},
    {label: 'Юзернейм', key: 'name', type: 'link', action: 'show', limit: 40},
    {label: 'Удаление', key: 'delete', type: 'button', action: 'delete', limit: 40},
];

export const managersTableConfig = [
    {label: 'ID', key: 'id', type: 'default', action: null, limit: 40},
    {label: 'Юзернейм', key: 'name', type: 'link', action: 'show', limit: 40},
    {label: 'Удаление', key: 'delete', type: 'button', action: 'delete', limit: 40},
];
export const usersTableConfig = [
    {label: 'ID', key: 'id', type: 'default', action: null, limit: 40},
    {label: 'Имя', key: 'name', type: 'link', action: 'show', limit: 40},
    {label: 'Юзернейм', key: 'username', type: 'link', action: 'telegram', limit: 100},
    {label: 'Telegram ID', key: 'telegram_id', type: 'default', action: null, limit: 40},
    {label: 'Премиум', key: 'premium', type: 'checkbox', action: null, limit: 40},
    {label: 'Забанил Бота', key: 'delete', type: 'checkbox', action: null, limit: 40},
    {label: 'Боты пользователя', key: 'bot_ids', type: 'arrayLink', action: 'showBot', limit: 40},
    {label: 'Удаление', key: 'delete', type: 'button', action: 'delete', limit: 40},
];
