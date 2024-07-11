export const bots_table = [
    {label: 'ID', key: 'id', type: 'default', action: null, limit: 40},
    {label: 'Юзернейм', key: 'name', type: 'link', action: 'show', limit: 40},
    {label: 'Токен', key: 'token', type: 'default', action: null, limit: 100},
    {label: 'Сообщение', key: 'message', type: 'default', action: null, limit: 40},
    {label: 'Активный', key: 'active', type: 'checkbox', action: null, limit: 40},
    {label: 'Удаление', key: 'delete', type: 'button', action: 'delete', limit: 40},
];
