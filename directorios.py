import os

def create_directory_structure():
    # Directorio base
    base_dir = 'hiker_ai'
    
    # Lista de subdirectorios
    directories = [
        os.path.join('assets', 'css'),
        os.path.join('assets', 'js'),
        os.path.join('assets', 'images'),
        'config',
        'exceptions',
        'models',
        'services',
        'utils'
    ]
    
    # Crear los directorios
    for directory in directories:
        full_path = os.path.join(base_dir, directory)
        os.makedirs(full_path, exist_ok=True)
        print(f'Directorio creado: {full_path}')
    
    # Lista de archivos a crear
    files = [
        os.path.join('config', 'ai_config.php'),
        os.path.join('config', 'chat_config.php'),
        os.path.join('config', 'weather_config.php'),
        os.path.join('exceptions', 'AIException.php'),
        os.path.join('models', 'UserModel.php'),
        os.path.join('models', 'RouteModel.php'),
        os.path.join('models', 'WeatherModel.php'),
        os.path.join('services', 'AIService.php'),
        os.path.join('services', 'WeatherService.php'),
        os.path.join('services', 'LoggerService.php'),
        os.path.join('services', 'UserPreferenceService.php'),
        os.path.join('services', 'AIInteractionHandler.php'),
        os.path.join('utils', 'DateUtils.php'),
        os.path.join('utils', 'ValidationUtils.php'),
        'ia_integration.php',
        os.path.join('assets', 'css', 'chat.css'),
        os.path.join('assets', 'js', 'chat.js')
    ]
    
    # Crear los archivos
    for file in files:
        full_path = os.path.join(base_dir, file)
        with open(full_path, 'a') as f:
            pass  # Solo crear el archivo vacío
        print(f'Archivo creado: {full_path}')

if __name__ == '__main__':
    try:
        create_directory_structure()
        print('\nEstructura de directorios y archivos creada exitosamente.')
    except Exception as e:
        print(f'\nError al crear la estructura: {str(e)}')