import os

# Директория с файлами
directory = './'

# Имя выходного файла
output_file = 'combined_output.txt'

def combine_files(output_file, directory):
    with open(output_file, 'w', encoding='utf-8') as outfile:
        for root, dirs, files in os.walk(directory):
            for filename in sorted(files):
                filepath = os.path.join(root, filename)
                if os.path.isfile(filepath):
                    relative_path = os.path.relpath(filepath, directory)
                    outfile.write(f'\n\n# Start of {relative_path}\n\n')
                    with open(filepath, 'rb') as infile:
                        content = infile.read()
                        try:
                            content = content.decode('utf-8')
                        except UnicodeDecodeError:
                            print(f'Ошибка декодирования файла {filepath}, пропуск файла.')
                            continue
                        outfile.write(content)
                    outfile.write(f'\n\n# End of {relative_path}\n\n')

combine_files(output_file, directory)
print(f'Все файлы объединены в {output_file}')
