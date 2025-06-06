<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CollectCodeCommand extends Command
{
    /**
     * Имя и сигнатура команды (то, что вводим в консоли).
     *
     * Пример: php artisan code:collect
     */
    protected $signature = 'code:collect';

    /**
     * Описание команды, отображается при php artisan list
     */
    protected $description = 'Собирает указанные в конфиге файлы/директории и объединяет их содержимое в один файл';

    public function handle()
    {
        // 1. Загружаем настройки из конфига
        $paths = config('collectcode.paths', []);
        $outputPath = config('collectcode.output_path', storage_path('collected_code.txt'));
        $extensions = config('collectcode.extensions', ['php']);

        // 2. Если output-файл существует — давайте удалим, чтобы собрать заново
        if (File::exists($outputPath)) {
            File::delete($outputPath);
        }

        // 3. Идём по всем путям в конфиге, собираем файлы
        $allFiles = [];
        foreach ($paths as $path) {
            $path = base_path($path); // Превращаем в полный путь относительно корня проекта

            if (is_dir($path)) {
                // Собираем файлы рекурсивно
                $allFiles = array_merge($allFiles, $this->getFilesFromDirectory($path, $extensions));
            } elseif (is_file($path)) {
                $allFiles[] = $path;
            } else {
                $this->warn("Путь [{$path}] не найден!");
            }
        }

        // 4. Записываем содержимое всех файлов в один output-файл
        foreach ($allFiles as $file) {
            // Можно вставлять какой-то разделитель между файлами
            File::append($outputPath, "\n\n/* --- FILE: {$file} --- */\n");
            File::append($outputPath, File::get($file));
        }

        // 5. Сообщаем об успехе
        $this->info('Файлы успешно собраны в: '.$outputPath);

        return 0;
    }

    /**
     * Рекурсивный обход директории для поиска нужных файлов.
     */
    protected function getFilesFromDirectory(string $directory, array $extensions): array
    {
        $result = [];

        // Получаем все файлы/папки внутри директории
        $files = File::allFiles($directory);

        foreach ($files as $file) {
            // Проверяем, что расширение файла — одно из нужных
            if (in_array($file->getExtension(), $extensions)) {
                $result[] = $file->getPathname();
            }
        }

        return $result;
    }
}
