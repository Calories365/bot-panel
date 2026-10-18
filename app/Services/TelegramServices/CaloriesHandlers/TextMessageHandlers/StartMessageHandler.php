<?php

namespace App\Services\TelegramServices\CaloriesHandlers\TextMessageHandlers;

use App\Models\BotUser;
use App\Models\Subscription;
use App\Services\DiaryApiService;
use App\Services\TelegramServices\BaseHandlers\MessageHandlers\MessageHandlerInterface;
use App\Traits\BasicDataExtractor;
use App\Utilities\Utilities;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Telegram\Bot\FileUpload\InputFile;
use Telegram\Bot\Keyboard\Keyboard;

class StartMessageHandler implements MessageHandlerInterface
{
    use BasicDataExtractor;

    protected DiaryApiService $diaryApiService;

    public function __construct(DiaryApiService $diaryApiService)
    {
        $this->diaryApiService = $diaryApiService;
    }

    public function handle($bot, $telegram, $message, $botUser)
    {
        $text = $message->getText();
        $commonData = self::extractCommonData($message);
        $chatId = $commonData['chatId'];

        $parts = explode(' ', $text);
        $codePart = $parts[1] ?? null;

        $code = null;
        $locale = null;

        if ($codePart) {
            $codeAndLang = explode('_', $codePart);
            $code = $codeAndLang[0] ?? null;
            $locale = $codeAndLang[1] ?? $commonData['locale'];

            if ($locale) {
                App::setLocale($locale);
            }

            $result = $this->diaryApiService->checkTelegramCode($code, $chatId);

            if (! empty($result['success']) && $result['success'] === true) {

                // from calories

                $caloriesUserId = $result['user_id'];

                $botUser = Utilities::saveAndNotify(
                    $commonData['chatId'],
                    $commonData['firstName'],
                    $commonData['lastName'],
                    $commonData['username'],
                    $bot,
                    $commonData['premium'],
                    'calories',
                    $result,
                    $locale
                );

                Subscription::updateOrCreate(
                    ['user_id' => $caloriesUserId],
                    [
                        'premium_until' => Carbon::parse($result['premium_until'])
                            ->setTimezone('Europe/Kyiv'),
                    ],
                );

                $botUser->calories_id = $caloriesUserId;
                $botUser->locale = $locale;
                $botUser->save();

                $this->sendWelcome($bot, $telegram, $message, $commonData);

                if ($botUser && $botUser->big_font === null) {
                    $this->askBigFont($telegram, $chatId);
                }

                return;
            } else {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text' => __('calories365-bot.invalid_or_used_code'),
                ]);

                if ($botUser && $botUser->big_font === null) {
                    $this->askBigFont($telegram, $chatId);
                }

                return;
            }
        }

        if ($botUser && $botUser->calories_id) {

            $this->sendWelcome($bot, $telegram, $message, $commonData);

            if ($botUser->big_font === null) {
                $this->askBigFont($telegram, $chatId);
            }

            // already existed acc

        } else {

            // from bot
            $savedUser = Utilities::saveAndNotify(
                $commonData['chatId'],
                $commonData['firstName'],
                $commonData['lastName'],
                $commonData['username'],
                $bot,
                $commonData['premium'],
                'bot_link',
                null,
                $commonData['locale'],
            );

            App::setLocale($commonData['locale']);

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => __('calories365-bot.seems_you_are_new', ['lang' => App::getLocale()]),
            ]);

            if ($savedUser && $savedUser->big_font === null) {
                $this->askBigFont($telegram, $chatId);
            }
        }
    }

    protected function askBigFont($telegram, $chatId): void
    {
        $inlineKeyboard = Keyboard::make([
            'inline_keyboard' => [
                [
                    ['text' => __('calories365-bot.yes'), 'callback_data' => 'bigfont_yes'],
                    ['text' => __('calories365-bot.no'),  'callback_data' => 'bigfont_no'],
                ],
            ],
        ]);

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => __('calories365-bot.big_font_question'),
            'reply_markup' => $inlineKeyboard,
        ]);
    }

    /**
     * The menu button of this chat opens the Mini App, whatever the bot's default
     * menu button is: a user who sends /start always gets the way into the diary.
     */
    protected function showMiniAppMenuButton($telegram, int $chatId, string $url): void
    {
        try {
            $telegram->post('setChatMenuButton', [
                'chat_id' => $chatId,
                'menu_button' => json_encode([
                    'type' => 'web_app',
                    'text' => __('calories365-bot.open_mini_app'),
                    'web_app' => ['url' => $url],
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::info('Could not set the menu button of the chat: '.$e->getMessage());
        }
    }

    protected function sendWelcome($bot, $telegram, $message, array $commonData): void
    {
        $imagePath = $bot->message_image;

        $guide = $bot->name === 'calories365KNU_bot' ? 'welcome_guide_KNU' : 'welcome_guide';
        $messageText = __('calories365-bot.'.$guide, ['open' => __('calories365-bot.open_mini_app')]);

        $keyboard = Keyboard::make([
            'resize_keyboard' => true,
        ]);

        // No Mini App button in this keyboard: Telegram opens a Mini App from a
        // keyboard button without the user's data, so it cannot sign in. The menu
        // button and the inline button below open it with the data.
        $keyboard
            ->row([
                ['text' => __('calories365-bot.how_to_use')],
                ['text' => __('calories365-bot.statistics')],
            ])
            ->row([
                ['text' => __('calories365-bot.menu')],
            ])
            ->row([
                ['text' => __('calories365-bot.choose_language')],
                ['text' => __('calories365-bot.font')],
            ])
            ->row([
                ['text' => __('calories365-bot.feedback')],
            ]);

        if ($imagePath) {
            $storagePath = parse_url($imagePath, PHP_URL_PATH);
            if ($storagePath) {
                $relativeImagePath = ltrim($storagePath, '/');
                $relativeImagePath = preg_replace('#^storage/#', 'public/', $relativeImagePath);

                if (Storage::exists($relativeImagePath)) {
                    $absoluteImagePath = Storage::path($relativeImagePath);
                    $photo = InputFile::create($absoluteImagePath, basename($absoluteImagePath));

                    try {
                        $telegram->sendPhoto([
                            'chat_id' => $commonData['chatId'],
                            'photo' => $photo,
                            'caption' => $messageText,
                            'reply_markup' => $keyboard,
                        ]);
                    } catch (\Telegram\Bot\Exceptions\TelegramOtherException $e) {
                        if ($e->getMessage() === 'Forbidden: bot was blocked by the user') {
                            $userModel = BotUser::where('telegram_id', $commonData['chatId'])->firstOrFail();
                            $userModel->is_banned = 1;
                            $userModel->save();
                        } else {
                            Log::info($e->getMessage());
                        }
                    }
                } else {
                    Log::error('Image file not found: '.$relativeImagePath);
                }
            } else {
                Log::error('Invalid image path provided: '.$imagePath);
            }
        } else {
            $telegram->sendMessage([
                'chat_id' => $commonData['chatId'],
                'text' => $messageText,
                'reply_markup' => $keyboard,
            ]);
        }

        if ($bot->web_app_url) {
            $this->showMiniAppMenuButton($telegram, $commonData['chatId'], $bot->web_app_url);

            $telegram->sendMessage([
                'chat_id' => $commonData['chatId'],
                'text' => __('calories365-bot.open_mini_app_hint'),
                'reply_markup' => json_encode(['inline_keyboard' => [[
                    ['text' => __('calories365-bot.open_mini_app'), 'web_app' => ['url' => $bot->web_app_url]],
                ]]]),
            ]);
        }
    }
}
