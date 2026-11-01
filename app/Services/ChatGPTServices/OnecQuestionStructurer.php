<?php

namespace App\Services\ChatGPTServices;

use App\Models\OnecMessage;
use App\Services\TelegramServices\OnecHelperHandlers\OnecTexts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * The title, summary and entities of a closed question of the 1C helper bot,
 * read by an OpenAI model from the texts of the question in their order. The
 * question goes to the queue whatever happens here: without an answer of the
 * model its title is the first words of its first text.
 */
class OnecQuestionStructurer
{
    public const TITLE_LIMIT = 80;

    public const ENTITY_KINDS = ['documents', 'dates', 'counterparties', 'accounts', 'amounts'];

    /**
     * @param  Collection<int, OnecMessage>  $messages  in the order of the question
     * @return array{title: string, summary: string, entities: array<string, list<string>>}
     */
    public function structure(Collection $messages): array
    {
        $firstText = $messages->first(fn (OnecMessage $message) => trim((string) $message->text) !== '')?->text;

        if ($firstText === null) {
            return $this->fallback(null);
        }

        try {
            $keys = (array) config('services.openai.keys');

            $result = Http::timeout(45)
                ->withToken((string) ($keys['ru'] ?? collect($keys)->filter()->first() ?? ''))
                ->post(rtrim((string) config('services.openai.base_url'), '/').'/v1/chat/completions', [
                    'model' => config('onec_helper.model'),
                    'reasoning_effort' => config('onec_helper.reasoning_effort'),
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => ['name' => 'onec_question', 'strict' => true, 'schema' => $this->schema()],
                    ],
                    'messages' => [
                        ['role' => 'system', 'content' => $this->instructions()],
                        ['role' => 'user', 'content' => $this->lines($messages)],
                    ],
                ])
                ->throw()
                ->json();

            $content = (string) ($result['choices'][0]['message']['content'] ?? '');
            $structure = $this->valid(json_decode($content, true));

            if ($structure === null) {
                Log::warning('The model gave no valid structure of the 1C question', ['content' => $content]);

                return $this->fallback($firstText);
            }

            return $structure;
        } catch (\Throwable $e) {
            Log::warning('The 1C question could not be structured', ['error' => $e->getMessage()]);

            return $this->fallback($firstText);
        }
    }

    /**
     * @return array{title: string, summary: string, entities: array<string, list<string>>}
     */
    public function fallback(?string $firstText): array
    {
        return [
            'title' => $firstText === null ? OnecTexts::get('untitled') : self::firstWords($firstText),
            'summary' => '',
            'entities' => array_fill_keys(self::ENTITY_KINDS, []),
        ];
    }

    /**
     * The words from the start of the text that fit in a title.
     */
    public static function firstWords(string $text, int $limit = self::TITLE_LIMIT): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $cut = mb_substr($text, 0, $limit + 1);
        $space = mb_strrpos($cut, ' ');

        $words = $space > 0 ? mb_substr($cut, 0, $space) : mb_substr($text, 0, $limit);

        return (string) preg_replace('/[\s,.;:—–-]+$/u', '', $words);
    }

    /**
     * @return array{title: string, summary: string, entities: array<string, list<string>>}|null
     */
    private function valid(mixed $decoded): ?array
    {
        if (! is_array($decoded) || ! is_string($decoded['title'] ?? null) || trim($decoded['title']) === ''
            || ! is_string($decoded['summary'] ?? null) || ! is_array($decoded['entities'] ?? null)) {
            return null;
        }

        $entities = [];
        foreach (self::ENTITY_KINDS as $kind) {
            $values = $decoded['entities'][$kind] ?? null;

            if (! is_array($values)) {
                return null;
            }

            $entities[$kind] = array_values(array_filter(
                array_map(fn ($value) => is_string($value) ? trim($value) : '', $values),
                fn (string $value) => $value !== ''
            ));
        }

        return [
            'title' => self::firstWords($decoded['title']),
            'summary' => trim($decoded['summary']),
            'entities' => $entities,
        ];
    }

    private function instructions(): string
    {
        return 'Ты помогаешь программисту 1С. Главный бухгалтер компании присылает ему вопросы по базе 1С: '
            .'несколько коротких сообщений, обычно по-украински, скриншоты, пересланные сообщения сотрудников. '
            .'Ниже — все сообщения одного вопроса в порядке следования, скриншоты и файлы отмечены в квадратных скобках.'
            ."\n\n"
            .'Верни JSON:'."\n"
            .'- title — заголовок вопроса по-русски, не длиннее '.self::TITLE_LIMIT.' знаков;'."\n"
            .'- summary — краткое содержание по-русски в 2–4 предложениях: что спрашивают и про что;'."\n"
            .'- entities — что названо в сообщениях: documents — номера документов, dates — даты, '
            .'counterparties — контрагенты, accounts — счета, amounts — суммы; как написано в сообщениях, '
            .'пустой список, если ничего такого нет.'."\n\n"
            .'Не добавляй того, чего нет в сообщениях.';
    }

    /**
     * @param  Collection<int, OnecMessage>  $messages
     */
    private function lines(Collection $messages): string
    {
        $screen = 0;
        $lines = [];

        foreach ($messages->values() as $index => $message) {
            $mark = match (true) {
                $message->is_screen => '[скриншот '.(++$screen).']',
                $message->type === OnecMessage::TYPE_DOCUMENT => '[файл '.$message->file_name.']',
                $message->type === OnecMessage::TYPE_VOICE => '[голосовое]',
                default => '',
            };

            $lines[] = ($index + 1).'. '.($message->author ?? '?').': '
                .implode(' ', array_filter([$mark, trim((string) $message->text)], fn (string $part) => $part !== ''));
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        $list = ['type' => 'array', 'items' => ['type' => 'string']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['title', 'summary', 'entities'],
            'properties' => [
                'title' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'entities' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => self::ENTITY_KINDS,
                    'properties' => array_fill_keys(self::ENTITY_KINDS, $list),
                ],
            ],
        ];
    }
}
