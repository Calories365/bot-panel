export const rows = [
    {label: 'Имя', key: 'name', type: 'default', emit_name: null, placeholder: 'введите имя', action: null},
    {label: 'Токен', key: 'token', type: 'default', emit_name: null, placeholder: 'введите токен', action: null},
    {
        label: 'Тип бота',
        key: 'type_id',
        type: 'dropdown',
        emit_name: null,
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
        key: 'type_id',
        type: 'dropdown',
        emit_name: null,
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
export const admin_Rows = [
    {label: 'Имя', key: 'name', type: 'default', emit_name: null, placeholder: 'введите имя', action: null},
    {
        label: 'Telegram id',
        key: 'telegram_id',
        type: 'default',
        placeholder: 'введите telegram_id',
        action: null
    },
    {
        label: 'Действия', key: 'actions', type: 'buttons', emit_name: null, placeholder: null,
        options: [
            {text: 'Сохранить', button_type: 'default', action: 'save'},
            {text: 'Удалить', button_type: 'danger', action: 'delete'},
        ]
    }
];
export const create_rows = [
    {label: 'Имя', key: 'name', type: 'default', emit_name: null, placeholder: 'введите имя', action: null},
    {label: 'Токен', key: 'token', type: 'default', emit_name: null, placeholder: 'введите токен', action: null},
    {
        label: 'Тип бота',
        key: 'type_id',
        type: 'dropdown',
        emit_name: null,
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
            {text: 'Сохранить', button_type: 'default', action: 'create'},
        ]
    }
];
export const create_rows_approval = [
    {label: 'Имя', key: 'name', type: 'default', emit_name: null, placeholder: 'введите имя', action: null},
    {label: 'Токен', key: 'token', type: 'default', emit_name: null, placeholder: 'введите токен', action: null},
    {
        label: 'Тип бота',
        key: 'type_id',
        type: 'dropdown',
        emit_name: null,
        placeholder: 'выберите тип',
        action: null
    },
    {label: 'Вебхук', key: 'web_hook', type: 'default', emit_name: null, placeholder: 'выберите вебхук', action: null},
    {label: 'Активен', key: 'active', type: 'checkbox', emit_name: null, placeholder: 'активен ли бот', action: null},
    {
        label: 'Действия', key: 'actions', type: 'buttons', emit_name: null, placeholder: null,
        options: [
            {text: 'Сохранить', button_type: 'default', action: 'create'},
        ]
    }
];
