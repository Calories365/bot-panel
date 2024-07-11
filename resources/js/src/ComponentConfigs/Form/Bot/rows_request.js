export const rows_request = [
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
    }, {
        label: 'Менеджера',
        key: 'managers',
        type: 'multiple_dropdown',
        emit_name: null,
        placeholder: 'выберите менеджеров',
        action: null
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
