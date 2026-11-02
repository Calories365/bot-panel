<?php

/*
| The 1C helper bot speaks only Russian: it has one owner. Its classes call these
| with the locale "ru" (OnecTexts), whatever the locale of the request is.
*/

return [
    'messages' => ':count сообщение|:count сообщения|:count сообщений',
    'screens' => ':count скриншот|:count скриншота|:count скриншотов',

    'collecting' => 'Собираю вопрос: :counts…',
    'collecting_followup' => 'Собираю уточнение: :counts…',
    'accepted' => 'Принял: «:title». :messages, :screens. В очереди на разбор.',
    'accepted_followup' => 'Принял уточнение к «:title». :messages, :screens. В очереди на разбор.',
    'in_work' => 'Принял: «:title». :messages, :screens. Разбираю: :note',
    'in_work_followup' => 'Принял уточнение к «:title». :messages, :screens. Разбираю: :note',
    'accepted_window' => 'Принял: «:title». :messages, :screens. Открываю на сервере.',
    'in_work_window' => 'Принял: «:title». :messages, :screens. Открываю на сервере: :note',
    'cancelled' => 'Вопрос отменён.',
    'cancelled_followup' => 'Уточнение отменено.',
    'analyze_now' => 'Разобрать сейчас',
    'open_on_server' => 'Открыть на сервере',
    'cancel' => 'Отменить',

    'answer_heading' => 'Разбор:',
    'continue_on_server' => 'Продолжить на сервере',
    'opening_on_server' => 'Открываю на сервере',
    'already_opening' => 'Уже открываю',
    'no_session' => 'Нет сессии разбора',

    // HTML: the commands are monospace, so that a tap copies them.
    'window_opened' => "Сессия Claude на сервере запущена, окно открыто.\nПодключиться: <code>claude attach :id</code>\nВсе сессии: <code>claude agents</code>",
    'window_not_opened' => 'Сессия Claude на сервере запущена, окно открыть не удалось. Подключиться: <code>claude attach :id</code>',
    'opened_on_server' => 'Этот вопрос открыт на сервере, продолжай там: <code>claude attach :id</code>',
    'opened_on_server_without_id' => 'Этот вопрос открыт на сервере, продолжай там.',

    'failed' => 'Не удалось разобрать: :error',
    'failed_on_server' => 'Не удалось открыть на сервере: :error',
    'not_taken' => 'Сервер 1С не забирает вопросы уже 5 минут: «:title» в очереди.',
    'no_heartbeat' => 'сервер 1С не сообщал о работе 15 минут, попыток: :attempts',
    'question_not_analyzed' => 'вопрос, к которому это уточнение, не разобран',
    'retry' => 'Повторить',
    'retrying' => 'Повторяю',
    'already_retried' => 'Уже повторено',
    'retried' => 'Повторяю разбор.',
    'retried_on_server' => 'Открываю на сервере ещё раз.',
    'untitled' => 'Вопрос без текста',

    'start' => 'Пересылайте сюда вопросы по базе 1С: тексты, скриншоты, файлы, голосовые. Я соберу их в один вопрос и поставлю в очередь на разбор. Чтобы уточнить вопрос, ответьте (reply) на любое моё сообщение о нём.',
    'unsupported' => 'Такое сообщение не принимаю: пришлите текст, фото, файл или голосовое.',
    'file_failed' => 'Не удалось скачать файл из Telegram. Пришлите его ещё раз.',
    'voice_failed' => 'Не удалось распознать голосовое. Пришлите его ещё раз или текстом.',
    'no_question' => 'Не нашёл вопрос, к которому относится это сообщение. Пришлите его без ответа (reply) — оно войдёт в новый вопрос.',
    'no_followup' => 'К этому вопросу уточнение не добавить: он отменён или не разобран. Пришлите вопрос заново.',
];
