export const rows = [
    {label: 'Имя', key: 'name', type: 'default', emit_name: null, placeholder: 'введите имя', action: null},
    {label: 'Токен', key: 'token', type: 'default', emit_name: null, placeholder: 'введите токен', action: null},
    {
        label: 'Тип бота',
        key: 'bot_types',
        type: 'dropdown',
        emit_name: 'type_id',
        placeholder: 'выберите тип',
        action: null
    },
    {
        label: 'Сообщение',
        key: 'message',
        type: 'textarea',
        emit_name: null,
        placeholder: 'введите сообщение',
        action: null
    },
    {label: 'Фото', key: 'message_image', type: 'picture', emit_name: null, placeholder: 'выберите фото', action: null},
    {label: 'Активен', key: 'active', type: 'checkbox', emit_name: null, placeholder: 'активен ли бот', action: null},
    {
        label: 'Действия', key: 'actions', type: 'buttons', emit_name: null, placeholder: null,
        options: [
            {text: 'Сохранить', button_type: 'default', action: 'save'},
            {text: 'Удалить', button_type: 'danger', action: 'delete'},
            {text: 'Обновить вебхук', button_type: 'warning', action: 'updateWebhook'}
        ]
    }
];
export const rows_approval = [
    {label: 'Имя', key: 'name', type: 'default', emit_name: null, placeholder: 'введите имя', action: null},
    {label: 'Токен', key: 'token', type: 'default', emit_name: null, placeholder: 'введите токен', action: null},
    {
        label: 'Тип бота',
        key: 'bot_types',
        type: 'dropdown',
        emit_name: 'type_id',
        placeholder: 'выберите тип',
        action: null
    },
    {label: 'Вебхук', key: 'web_hook', type: 'default', emit_name: null, placeholder: 'выберите вебхук', action: null},
    {label: 'Активен', key: 'active', type: 'checkbox', emit_name: null, placeholder: 'активен ли бот', action: null},
    {
        label: 'Действия', key: 'actions', type: 'buttons', emit_name: null, placeholder: null,
        options: [
            {text: 'Сохранить', button_type: 'default', action: 'save'},
            {text: 'Удалить', button_type: 'danger', action: 'delete'},
            {text: 'Обновить вебхук', button_type: 'warning', action: 'updateWebhook'}
        ]
    }
];
