<?php

namespace Tests\Unit;

use App\Services\ChatGPTServices\SpeechToTextService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Tests\TestFixtures;

class SpeechToTextServiceTest extends TestCase
{
    use DatabaseTransactions, TestFixtures;

    protected SpeechToTextService $speechService;

    protected function setUp(): void
    {
        parent::setUp();

        // Устанавливаем английскую локаль для тестов
        app()->setLocale('en');

        $this->speechService = new SpeechToTextService;
    }

    /** @test */
    public function transcribes_audio_successfully(): void
    {
        // Загружаем фикстуру Whisper успеха
        $whisperFixture = file_get_contents(base_path('tests/Fixtures/OpenAI/whisper_success_english.json'));
        $whisperArray = json_decode($whisperFixture, true);
        $text = $whisperArray['text'];

        // Формируем фикстуру GPT-ответа на основе текста
        $gptFixture = json_encode([
            'choices' => [
                [
                    'message' => [
                        'content' => $text,
                    ],
                ],
            ],
        ]);

        // Создаём последовательность мок-ответов: Whisper → GPT
        $mock = new MockHandler([
            new Response(200, [], $whisperFixture),
            new Response(200, [], $gptFixture),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $mockClient = new Client(['handler' => $handlerStack]);

        // Инициализируем сервис и инжектим мок-клиент
        $service = new SpeechToTextService;
        $ref = new \ReflectionClass($service);
        $prop = $ref->getProperty('client');
        $prop->setAccessible(true);
        $prop->setValue($service, $mockClient);

        // Реальный mp3 файл для MultipartStream
        $audioPath = base_path('tests/Fixtures/Audio/file.mp3');

        // Выполняем метод
        $result = $service->convertSpeechToText($audioPath);

        // Проверяем, что вернулся ожидаемый текст
        $this->assertEquals($text, $result);
    }
}
