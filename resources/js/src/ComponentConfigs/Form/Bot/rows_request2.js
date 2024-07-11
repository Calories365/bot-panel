export const rows_request2 = [
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
    {label: 'Активен', key: 'active', type: 'checkbox', emit_name: null, placeholder: 'активен ли бот', action: null},
    {
        label: 'Действия', key: 'actions', type: 'buttons', emit_name: null, placeholder: null,
        options: [
            {text: 'Сохранить', button_type: 'default', action: 'submit'},
            {text: 'Удалить', button_type: 'danger', action: 'delete'},
        ]
    }
];
