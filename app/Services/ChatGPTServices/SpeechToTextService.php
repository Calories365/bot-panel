<?php

namespace App\Services\ChatGPTServices;

use App\Services\Benchmark\BenchmarkContext;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SpeechToTextService
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client;
    }

    /**
     * Определяем нужный ключ в зависимости от текущей локали
     */
    private function getApiKey(): string
    {
        $locale = app()->getLocale();

        $primaryKey = match ($locale) {
            'ua' => $this->pickRandomApiKey([
                env('OPENAI_API_KEY_UA'),
                env('OPENAI_API_KEY_UA_2'),
                env('OPENAI_API_KEY_UK'), // backward compatibility
            ]) ?? env('OPENAI_API_KEY_RU'),
            'en' => env('OPENAI_API_KEY_EN'),
            default => env('OPENAI_API_KEY_RU'),
        };

        return $primaryKey;
    }

    /**
     * Picks a random non-empty API key for simple load distribution.
     */
    private function pickRandomApiKey(array $keys): ?string
    {
        $availableKeys = array_values(array_filter(
            $keys,
            static fn ($key) => is_string($key) && $key !== ''
        ));

        if ($availableKeys === []) {
            return null;
        }

        return $availableKeys[random_int(0, count($availableKeys) - 1)];
    }

    private function useLocalModels(): bool
    {
        return filter_var(config('services.openai.local_models', false), FILTER_VALIDATE_BOOL);
    }

    private function normalizeBaseUrl(?string $baseUrl): string
    {
        return rtrim((string) ($baseUrl ?: config('services.openai.base_url', 'https://api.openai.com')), '/');
    }

    private function joinBaseAndPath(string $baseUrl, string $path): string
    {
        $base = rtrim($baseUrl, '/');
        $normalizedPath = '/'.ltrim($path, '/');

        // Prevent duplicated /v1 segment when base already ends with /v1.
        if (str_ends_with($base, '/v1') && str_starts_with($normalizedPath, '/v1/')) {
            $normalizedPath = substr($normalizedPath, 3);
        }

        return $base.$normalizedPath;
    }

    private function getEndpoint(string $type): string
    {
        if ($type === 'audio') {
            $baseUrl = $this->useLocalModels()
                ? ($this->normalizeBaseUrl(config('services.openai.proxy_base_url')
                    ?: config('services.openai.local_audio_base_url')))
                : $this->normalizeBaseUrl(config('services.openai.base_url'));

            return $this->joinBaseAndPath(
                $baseUrl,
                config('services.openai.audio_path', '/v1/audio/transcriptions')
            );
        }

        $baseUrl = $this->useLocalModels()
            ? ($this->normalizeBaseUrl(config('services.openai.proxy_base_url')
                ?: config('services.openai.local_chat_base_url')))
            : $this->normalizeBaseUrl(config('services.openai.base_url'));

        return $this->joinBaseAndPath(
            $baseUrl,
            config('services.openai.chat_path', '/v1/chat/completions')
        );
    }

    private function getAuthorizationToken(string $type): string
    {
        if (! $this->useLocalModels()) {
            return $this->getApiKey();
        }

        if ($type === 'audio') {
            return config('services.openai.proxy_token')
                ?: config('services.openai.local_audio_token')
                ?: $this->getApiKey();
        }

        return config('services.openai.proxy_token')
            ?: config('services.openai.local_chat_token')
            ?: $this->getApiKey();
    }

    /* ---------- convertSpeechToText ---------- */
    public function convertSpeechToText(string $filePath)
    {
        $locale = app()->getLocale();
        $languageCode = match ($locale) {
            'ua' => 'uk',
            'ru' => 'ru',
            'en' => 'en',
            default => 'uk',
        };

        $t0 = microtime(true);

        $response = Http::timeout(45)
            ->attach('file', fopen($filePath, 'r'), basename($filePath))
            ->attach('model', config('services.openai.audio_model', 'whisper-1'))
            ->attach('language', $languageCode)
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->getAuthorizationToken('audio'),
            ])
            ->post($this->getEndpoint('audio'))
            ->throw();

        $whisperMs = (microtime(true) - $t0) * 1000;

        $data = $response->json();

        Log::info('-----------------------');
        Log::info('Сконвертированый текст с помощью Whisper: ');
        Log::info(print_r($data['text'], true));
        Log::info('-----------------------');

        if (BenchmarkContext::$currentRequestId) {
            BenchmarkContext::recordTiming('whisper_ms', $whisperMs);
            BenchmarkContext::recordData('whisper_text', $data['text'] ?? '');
        }

        if (! isset($data['text'])) {
            return false;
        }

        $result = $this->analyzeFoodIntake($data['text']);

        return is_string($result) ? $result : null;
    }

    /* ---------- analyzeFoodIntake ---------- */
    public function analyzeFoodIntake(string $text)
    {
        $useShortPrompt = config('services.openai.use_short_prompt', false);
        $locale = app()->getLocale();

        if ($useShortPrompt) {
            $requestData = [
                'model' => config('services.openai.chat_model', 'gpt-4o'),
                'messages' => [
                    ['role' => 'system', 'content' => $this->getShortSystemPrompt($locale)],
                    ['role' => 'user', 'content' => $text],
                ],
                'temperature' => 0,
                'max_completion_tokens' => 256,
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => [
                        'name' => 'food_grams',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'none' => ['type' => 'boolean'],
                                'items' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => ['type' => 'string'],
                                            'grams' => ['type' => 'integer'],
                                        ],
                                        'required' => ['name', 'grams'],
                                        'additionalProperties' => false,
                                    ],
                                ],
                            ],
                            'required' => ['none', 'items'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
            ];
        } else {
            $requestData = [
                'model' => config('services.openai.chat_model', 'gpt-4o'),
                'messages' => [['role' => 'user', 'content' => __('calories365-bot.prompt_analyze_food_intake', ['text' => $text])]],
                'temperature' => 0,
            ];
        }

        try {
            $t0 = microtime(true);

            $result = Http::timeout(45)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->getAuthorizationToken('chat'),
                ])
                ->post($this->getEndpoint('chat'), $requestData)
                ->throw()
                ->json();

            $llmMs = (microtime(true) - $t0) * 1000;

            $rawContent = $result['choices'][0]['message']['content']
                ?? __('calories365-bot.data_not_extracted');

            $final_result = $useShortPrompt
                ? $this->renderShortPromptResponse($rawContent, $locale)
                : $rawContent;

            Log::info('-----------------------');
            Log::info('Проанализированый текст с помощью LLM: ');
            Log::info(print_r($final_result, true));
            Log::info('-----------------------');

            if (BenchmarkContext::$currentRequestId) {
                BenchmarkContext::recordTiming('llm_analyze_ms', $llmMs);
                BenchmarkContext::recordData('llm_analyze_response', $final_result);
                BenchmarkContext::recordTiming('llm_analyze_prompt_tokens', $result['usage']['prompt_tokens'] ?? 0);
                BenchmarkContext::recordTiming('llm_analyze_completion_tokens', $result['usage']['completion_tokens'] ?? 0);
            }

            return $final_result;
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getShortSystemPrompt(string $locale): string
    {
        return match ($locale) {
            'ua' => "Знаходь ВСІ продукти, страви та напої в тексті. Відповідай JSON.\nПравила:\n- none=false якщо є їжа, none=true якщо немає; items — список {name, grams}\n- Якщо вага не вказана: страви 250-350г, хліб/випічка 80г, фрукти/овочі 120г, напої 250г\n- Напої: мл = грами (латте 300 мл → name=\"Латте\", grams=300); \"на молоці/з цукром\" — опис, не окремий продукт\n- Страви з доповненнями (Салат Цезар з куркою) — ОДИН продукт, не розбивай на складники\n- Знаходь ВСІ продукти без винятку, навіть якщо кількість не вказана — застосовуй типову порцію\nПриклад: \"Салат Цезар і 30г пармезану\" → [{\"name\":\"Салат Цезар\",\"grams\":300},{\"name\":\"Пармезан\",\"grams\":30}]",
            'en' => "Find ALL products, dishes and drinks in the text. Reply JSON.\nRules:\n- none=false if food present, none=true if absent; items — list of {name, grams}\n- If weight not specified: dishes 250-350g, bread/pastry 80g, fruits/veggies 120g, drinks 250g\n- Drinks: ml = grams (latte 300 ml → name=\"Latte\", grams=300); \"with milk/sugar\" — description, not a separate product\n- Dishes with additions (Caesar salad with chicken) — ONE product, do not split into components\n- Find ALL products without exception, even if amount not specified — use typical portion\nExample: \"Caesar salad and 30g parmesan\" → [{\"name\":\"Caesar salad\",\"grams\":300},{\"name\":\"Parmesan\",\"grams\":30}]",
            default => "Находи ВСЕ продукты, блюда и напитки в тексте. Отвечай JSON.\nПравила:\n- none=false если есть еда, none=true если нет; items — список {name, grams}\n- Если вес не указан: блюда 250-350г, хлеб/выпечка 80г, фрукты/овощи 120г, напитки 250г\n- Напитки: мл = граммы (латте 300 мл → name=\"Латте\", grams=300); \"на молоке/с сахаром\" — описание, не отдельный продукт\n- Блюда с добавками (Салат Цезарь с курицей) — ОДИН продукт, не разбивай на составляющие\n- Находи ВСЕ продукты без исключения, даже если количество не указано — применяй типичную порцию\nПример: \"Салат Цезарь и 30г пармезана\" → [{\"name\":\"Салат Цезарь\",\"grams\":300},{\"name\":\"Пармезан\",\"grams\":30}]",
        };
    }

    private function renderShortPromptResponse(string $content, string $locale): string
    {
        $obj = json_decode($content, true);
        if (! is_array($obj)) {
            return $content;
        }

        if ($obj['none'] ?? false) {
            return match ($locale) {
                'ua' => 'продуктів немає',
                'en' => 'no products',
                default => 'продуктов нет',
            };
        }

        $unit = match ($locale) {
            'ua' => 'грамів',
            'en' => 'grams',
            default => 'граммов',
        };

        $lines = [];
        foreach ($obj['items'] ?? [] as $item) {
            $name = ucfirst((string) ($item['name'] ?? ''));
            $grams = (int) ($item['grams'] ?? 0);
            if ($name) {
                $lines[] = "{$name} - {$grams} {$unit};";
            }
        }

        return $lines
            ? implode("\n", $lines)
            : match ($locale) {
                'ua' => 'продуктів немає',
                'en' => 'no products',
                default => 'продуктов нет',
            };
    }

    /* ---------- generateNewProductData ---------- */
    public function generateNewProductData(string $text)
    {
        $prompt = __('calories365-bot.prompt_generate_new_product_data', [
            'text' => $text,
        ]);

        try {
            $t0 = microtime(true);

            $result = Http::timeout(45)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->getAuthorizationToken('chat'),
                ])
                ->post($this->getEndpoint('chat'), [
                    'model' => config('services.openai.chat_model', 'gpt-4o'),
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'temperature' => 0,
                ])
                ->throw()
                ->json();

            $llmMs = (microtime(true) - $t0) * 1000;

            $final_result = $result['choices'][0]['message']['content']
                ?? __('calories365-bot.data_not_extracted');

            Log::info('-----------------------');
            Log::info('Сгенерированые данные продукта помощью gpt-4o: ');
            Log::info(print_r($final_result, true));
            Log::info('-----------------------');

            if (BenchmarkContext::$currentRequestId) {
                BenchmarkContext::accumulateTiming('llm_generate_ms', $llmMs);
                BenchmarkContext::appendData('llm_generate_raw', is_string($final_result) ? $final_result : json_encode($final_result));
                BenchmarkContext::accumulateTiming('llm_generate_prompt_tokens', $result['usage']['prompt_tokens'] ?? 0);
                BenchmarkContext::accumulateTiming('llm_generate_completion_tokens', $result['usage']['completion_tokens'] ?? 0);
            }

            return $final_result;
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function chooseTheMostRelevantProduct(array $products)
    {
        try {
            $chosenProductName = '';

            for ($i = 0; $i < count($products); $i += 5) {
                $prompt = '';

                for ($j = $i; $j < $i + 5 && $j < count($products); $j++) {
                    $name = $products[$j]['name'];
                    $details = $products[$j]['details'];

                    $productNames = array_map(function ($detail) {
                        return $detail['id'].' - '.$detail['name'];
                    }, $details);

                    $prompt .= __('calories365-bot.prompt_choose_relevant_products_part', [
                        'name' => $name,
                        'productNames' => implode(', ', $productNames),
                    ]).' ';
                }

                $prompt .= __('calories365-bot.prompt_choose_relevant_products_footer');

                $chosenProductName .= $this->askGPTForRelevance($prompt);
            }

        } catch (\Exception $e) {
            Log::error('Error in choosing product: '.$e->getMessage());

            return ['error' => $e->getMessage()];
        }

        return true;
    }

    /* ---------- askGPTForRelevance ---------- */
    private function askGPTForRelevance(string $prompt)
    {
        try {
            $result = Http::timeout(45)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->getAuthorizationToken('chat'),
                ])
                ->post($this->getEndpoint('chat'), [
                    'model' => config('services.openai.chat_model', 'gpt-4o'),
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'temperature' => 0,
                ])
                ->throw()
                ->json();

            return $result['choices'][0]['message']['content']
                ?? __('calories365-bot.data_not_extracted');
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
