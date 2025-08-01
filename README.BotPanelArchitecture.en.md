# Bot Panel

A service layer for handling Telegram bots, implemented using the **Strategy** pattern with lazy handler initialization via factories (closures). This ensures high flexibility and modularity of the architecture.

## Core Idea

### 1. Controller and Central Handler

Incoming Telegram Webhook requests first reach the controller, after which they are forwarded to the central handler — [`TelegramHandler`](https://github.com/Maaaaxim/bot-panel/blob/main/app/Services/TelegramServices/TelegramHandler.php). Here, the request undergoes initial processing via middleware implemented through the Laravel Pipeline. The middleware handles logging, filtering, and authorization before the request is routed to a specific strategy.

### 2. Strategies

After passing through the middleware, `TelegramHandler` selects the strategy corresponding to the current bot type. A strategy is a separate configuration class that extends the base class [`BaseService`](https://github.com/Maaaaxim/bot-panel/blob/main/app/Services/TelegramServices/BaseService.php).

Each strategy inherits a standard set of handlers from `BaseService`, but can easily override or extend them as needed. For example, the calorie-bot strategy: [`CaloriesService.php`](https://github.com/Maaaaxim/bot-panel/blob/main/app/Services/TelegramServices/CaloriesService.php).

### 3. Handler Hierarchy

At the top of the hierarchy is the `getUpdateHandlers()` method of the [`BaseService`](https://github.com/Maaaaxim/bot-panel/blob/main/app/Services/TelegramServices/BaseService.php) class, whose task is to define handler factories for each update type (e.g., `message`, `my_chat_member`, `callback_query`):

```php
protected function getUpdateHandlers(): array
{
    return [
        'message' => function () {
            return app(MessageUpdateHandler::class, [
                'messageHandlers' => $this->getMessageHandlers(),
            ]);
        },
        'my_chat_member' => function () {
            return app(MyChatMemberUpdateHandler::class);
        },
        'callback_query' => function () {
            return app(CallbackQueryHandler::class, [
                'callbackQueryHandlers' => $this->getCallbackQueryHandlers(),
            ]);
        },
    ];
}
````

Each factory creates the next-level handlers, initializing them only when they are actually needed.

For example, for the `message` update type, the factory creates a `MessageUpdateHandler`, passing it factories for processing specific message types:

```php
protected function getMessageHandlers(): array
{
    return [
        'text' => function () {
            return app(TextMessageHandler::class, [
                'textMessageHandlers' => $this->getTextMessageHandlers(),
            ]);
        },
        'voice' => function () {
            return app(AudioMessageHandler::class);
        },
    ];
}
```

Thus, the request-processing hierarchy looks like this:

* **UpdateHandlers** (e.g., `MessageUpdateHandler`, `CallbackQueryHandler`)
* **MessageHandlers** (e.g., `TextMessageHandler`, `AudioMessageHandler`)
* Concrete commands and message types (e.g., `/start`, `/default`, voice messages, etc.)

### 4. Overriding and Extending Handlers

Strategies allow flexible customization of handlers at every level. For instance, the [`CaloriesService`](https://github.com/Maaaaxim/bot-panel/blob/main/app/Services/TelegramServices/CaloriesService.php) strategy can override `getMessageHandlers()` and specify its own handler for voice messages:

```php
protected function getMessageHandlers(): array
{
    $handlers = parent::getMessageHandlers();

    $handlers['voice'] = fn () => app(AudioMessageHandler::class);

    return $handlers;
}
```

Strategies can also use additional methods and utilities, such as `Utilities::applySynonyms()`, to configure commands and enhance user interaction.

## Architecture Advantages

* **Flexibility**: easily add or modify handlers for each bot.
* **Modularity**: clear separation of concerns and reuse of base logic.
* **Scalability**: rapidly scale and introduce new commands and message types.

Thanks to this approach, the project can be developed and maintained efficiently without touching the core structure, enabling the creation of Telegram bots with diverse functionality within a single application.
