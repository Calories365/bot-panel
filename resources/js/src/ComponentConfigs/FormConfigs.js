export const rows = [
    {
        label: 'Имя',
        key: 'name',
        type: 'default',
        emit_name: null,
        placeholder: 'введите имя',
        action: null,
        required: true
    },
    {
        label: 'Токен', key: 'token', type: 'default', emit_name: null, placeholder: 'введите токен', action: null,
        required: true
    }, {
        label: 'Вебхук', key: 'web_hook', type: 'default', emit_name: null, placeholder: 'введите вебхук', action: null,
        required: true
    },
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
        action: null,
        required: true
    },
    {label: 'Фото', key: 'message_image', type: 'picture', emit_name: null, placeholder: 'выберите фото', action: null},
    {label: 'Активен', key: 'active', type: 'checkbox', emit_name: null, placeholder: 'активен ли бот', action: null},
    {
        label: 'Действия', key: 'actions', type: 'buttons', emit_name: null, placeholder: null,
        options: [
            {text: 'Сохранить', button_type: 'default', action: 'submit'},
            {text: 'Удалить', button_type: 'danger', action: 'delete'},
        ]
    }
];
export const rows_approval = [
    {
        label: 'Имя', key: 'name', type: 'default', emit_name: null, placeholder: 'введите имя', action: null,
        required: true
    },
    {
        label: 'Токен', key: 'token', type: 'default', emit_name: null, placeholder: 'введите токен', action: null,
        required: true
    },
    {
        label: 'Вебхук', key: 'web_hook', type: 'default', emit_name: null, placeholder: 'введите вебхук', action: null,
        required: true
    },
    {
        label: 'Тип бота',
        key: 'type_id',
        type: 'dropdown',
        emit_name: null,
        placeholder: 'выберите тип',
        action: null
    },
    {
        label: 'Вордпресс эндпоинт',
        key: 'wordpress_endpoint',
        type: 'default',
        emit_name: null,
        placeholder: 'введите вордпресс эндпоинт',
        action: null,
        required: true
    },
    {label: 'Активен', key: 'active', type: 'checkbox', emit_name: null, placeholder: 'активен ли бот', action: null},
    {
        label: 'Действия', key: 'actions', type: 'buttons', emit_name: null, placeholder: null,
        options: [
            {text: 'Сохранить', button_type: 'default', action: 'submit'},
            {text: 'Удалить', button_type: 'danger', action: 'delete'},
        ]
    }
];
export const admin_Rows = [
    {
        label: 'Имя', key: 'name', type: 'default', emit_name: null, placeholder: 'введите имя', action: null,
        required: true
    },
    {
        label: 'Telegram id',
        key: 'telegram_id',
        type: 'default',
        placeholder: 'введите telegram_id',
        action: null,
        required: true
    },
    {
        label: 'Действия', key: 'actions', type: 'buttons', emit_name: null, placeholder: null,
        options: [
            {text: 'Сохранить', button_type: 'default', action: 'submit'},
            {text: 'Удалить', button_type: 'danger', action: 'delete'},
        ]
    }
];
export const create_rows = [
    {
        label: 'Имя',
        key: 'name',
        type: 'default',
        emit_name: null,
        placeholder: 'введите имя',
        action: null,
        required: true
    },
    {label: 'Токен', key: 'token', type: 'default', emit_name: null, placeholder: 'введите токен', action: null},
    {
        label: 'Вебхук', key: 'web_hook', type: 'default', emit_name: null, placeholder: 'введите вебхук', action: null,
        required: true
    },
    {
        label: 'Тип бота',
        key: 'type_id',
        type: 'dropdown',
        emit_name: null,
        placeholder: 'выберите тип',
        action: null,
        required: true
    },
    {
        label: 'Сообщение',
        key: 'message',
        type: 'textarea',
        emit_name: null,
        placeholder: 'введите сообщение',
        action: null,
        required: true
    },
    {label: 'Фото', key: 'message_image', type: 'picture', emit_name: null, placeholder: 'выберите фото', action: null},
    {label: 'Активен', key: 'active', type: 'checkbox', emit_name: null, placeholder: 'активен ли бот', action: null},
    {
        label: 'Действия', key: 'actions', type: 'buttons', emit_name: null, placeholder: null,
        options: [
            {text: 'Сохранить', button_type: 'default', action: 'submit'},
        ]
    }
];
export const create_rows_approval = [
    {
        label: 'Имя', key: 'name', type: 'default', emit_name: null, placeholder: 'введите имя', action: null,
        required: true
    },
    {label: 'Токен', key: 'token', type: 'default', emit_name: null, placeholder: 'введите токен', action: null},
    {
        label: 'Вебхук', key: 'web_hook', type: 'default', emit_name: null, placeholder: 'введите вебхук', action: null,
        required: true
    },
    {
        label: 'Тип бота',
        key: 'type_id',
        type: 'dropdown',
        emit_name: null,
        placeholder: 'выберите тип',
        action: null,
        required: true
    },
    {
        label: 'Вордпресс эндпоинт',
        key: 'wordpress_endpoint',
        type: 'default',
        emit_name: null,
        placeholder: 'введите вордпресс эндпоинт',
        action: null,
        required: true
    },
    {label: 'Активен', key: 'active', type: 'checkbox', emit_name: null, placeholder: 'активен ли бот', action: null},
    {
        label: 'Действия', key: 'actions', type: 'buttons', emit_name: null, placeholder: null,
        options: [
            {text: 'Сохранить', button_type: 'default', action: 'submit'},
        ]
    }
];
