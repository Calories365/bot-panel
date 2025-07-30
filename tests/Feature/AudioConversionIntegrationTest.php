<?php

namespace Tests\Feature;

use App\Services\AudioConversionService;
use App\Services\ChatGPTServices\SpeechToTextService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Integration test using real FFMpeg.
 * Will only run if ffmpeg is installed on the system.
 */
class AudioConversionIntegrationTest extends TestCase
{
    private AudioConversionService $service;

    private string $ogaPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Check if ffmpeg binary exists
        if (! file_exists('/usr/bin/ffmpeg')) {
            $this->markTestSkipped('FFMpeg binary not installed at /usr/bin/ffmpeg');
        }

        Storage::fake('public');

        // Use the real service (no mocks for audio conversion)
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

        // Ensure conversion returned non-null paths
        $this->assertNotNull($convertedLocalPath);
        $this->assertNotNull($convertedFullPath);

        // Ensure converted file exists
        $this->assertTrue(
            Storage::disk('public')->exists($convertedLocalPath),
            'Converted MP3 file should exist'
        );

        // Ensure file has .mp3 extension
        $this->assertStringEndsWith('.mp3', $convertedLocalPath);

        // Ensure file is not empty
        $this->assertGreaterThan(
            0,
            Storage::disk('public')->size($convertedLocalPath),
            'Converted file should not be empty'
        );

        // Ensure MP3 header is valid
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
        // Create a corrupted audio file
        $corruptedPath = 'audios/corrupted.oga';
        Storage::disk('public')->put($corruptedPath, 'not_audio_content');

        $fullCorruptedPath = Storage::disk('public')->path($corruptedPath);

        [$convertedLocalPath, $convertedFullPath] = $this->service->convertToMp3(
            $corruptedPath,
            $fullCorruptedPath
        );

        // Conversion should fail and return null
        $this->assertNull($convertedLocalPath);
        $this->assertNull($convertedFullPath);

        // Cleanup
        Storage::disk('public')->delete($corruptedPath);
    }

    /**
     * Checks for a valid MP3 file based on header signature
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

        // Check for ID3v2 or MPEG header
        return str_starts_with($header, 'ID3') ||
            (strlen($header) >= 2 && substr($header, 0, 2) === "\xFF\xFB");
    }

    protected function tearDown(): void
    {
        // Clean up all audio files from disk
        $files = Storage::disk('public')->files('audios');
        foreach ($files as $file) {
            Storage::disk('public')->delete($file);
        }

        parent::tearDown();
    }
}
