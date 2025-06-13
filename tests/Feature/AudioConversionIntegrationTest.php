<?php

namespace Tests\Feature;

use App\Services\AudioConversionService;
use App\Services\ChatGPTServices\SpeechToTextService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Интеграционный тест с реальным FFMpeg
 * Запускается только если ffmpeg установлен в системе
 */
class AudioConversionIntegrationTest extends TestCase
{
    private AudioConversionService $service;

    private string $ogaPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Проверяем наличие ffmpeg
        if (! file_exists('/usr/bin/ffmpeg')) {
            $this->markTestSkipped('FFMpeg binary not installed at /usr/bin/ffmpeg');
        }

        Storage::fake('public');

        // Используем реальный сервис без моков
        $this->service = new AudioConversionService(
            $this->createMock(SpeechToTextService::class)
        );

        $this->ogaPath = 'audios/test_real.oga';
        Storage::disk('public')->put(
            $this->ogaPath,
            file_get_contents(base_path('tests/Fixtures/Audio/file.oga'))
        );
    }

    /** @test */
    public function actually_converts_oga_to_mp3_with_real_ffmpeg(): void
    {
        $fullOgaPath = Storage::disk('public')->path($this->ogaPath);

        [$convertedLocalPath, $convertedFullPath] = $this->service->convertToMp3(
            $this->ogaPath,
            $fullOgaPath
        );

        // Проверяем успешность конвертации
        $this->assertNotNull($convertedLocalPath);
        $this->assertNotNull($convertedFullPath);

        // Проверяем, что файл действительно создался
        $this->assertTrue(
            Storage::disk('public')->exists($convertedLocalPath),
            'Converted MP3 file should exist'
        );

        // Проверяем расширение
        $this->assertStringEndsWith('.mp3', $convertedLocalPath);

        // Проверяем, что файл не пустой
        $this->assertGreaterThan(
            0,
            Storage::disk('public')->size($convertedLocalPath),
            'Converted file should not be empty'
        );

        // Проверяем MP3 сигнатуру
        $this->assertTrue(
            $this->isValidMp3File($convertedFullPath),
            'File should have valid MP3 signature'
        );

        // Cleanup
        Storage::disk('public')->delete($convertedLocalPath);
    }

    /** @test */
    public function handles_invalid_audio_file(): void
    {
        // Создаем поврежденный файл
        $corruptedPath = 'audios/corrupted.oga';
        Storage::disk('public')->put($corruptedPath, 'not_audio_content');

        $fullCorruptedPath = Storage::disk('public')->path($corruptedPath);

        [$convertedLocalPath, $convertedFullPath] = $this->service->convertToMp3(
            $corruptedPath,
            $fullCorruptedPath
        );

        // Конвертация должна завершиться ошибкой
        $this->assertNull($convertedLocalPath);
        $this->assertNull($convertedFullPath);

        // Cleanup
        Storage::disk('public')->delete($corruptedPath);
    }

    /**
     * Проверяет валидность MP3 файла по сигнатуре
     */
    private function isValidMp3File(string $filePath): bool
    {
        if (! file_exists($filePath)) {
            return false;
        }

        $file = fopen($filePath, 'rb');
        if (! $file) {
            return false;
        }

        $header = fread($file, 10);
        fclose($file);

        // Проверяем ID3v2 или MPEG сигнатуру
        return str_starts_with($header, 'ID3') ||
               (strlen($header) >= 2 && substr($header, 0, 2) === "\xFF\xFB");
    }

    protected function tearDown(): void
    {
        // Дополнительная очистка всех аудио файлов
        $files = Storage::disk('public')->files('audios');
        foreach ($files as $file) {
            Storage::disk('public')->delete($file);
        }

        parent::tearDown();
    }
}
