<?php

return [

    /*
    |--------------------------------------------------------------------------
    | File Upload Limits
    |--------------------------------------------------------------------------
    |
    | Configuration for file upload size limits, specifically designed for
    | AI models and datasets which can be significantly larger than typical
    | web files.
    |
    */

    'models' => [
        /*
        | Maximum size for individual files within a model ZIP (in bytes)
        | Default: 2GB (2 * 1024 * 1024 * 1024)
        | Common AI model files can range from MB to several GB
        */
        'max_individual_file_size' => env('MODEL_MAX_FILE_SIZE', 2 * 1024 * 1024 * 1024),

        /*
        | Maximum total size for all extracted files in a model ZIP (in bytes)
        | Default: 10GB (10 * 1024 * 1024 * 1024)
        | Large AI models with multiple files can easily exceed 1GB
        */
        'max_total_extracted_size' => env('MODEL_MAX_TOTAL_SIZE', 10 * 1024 * 1024 * 1024),

        /*
        | Maximum size for the ZIP file itself (in bytes)
        | Default: 5GB (5 * 1024 * 1024 * 1024)
        | Compressed AI models can still be quite large
        */
        'max_zip_size' => env('MODEL_MAX_ZIP_SIZE', 5 * 1024 * 1024 * 1024),

        /*
        | Maximum number of files allowed in a single ZIP
        | Default: 10000 files
        | Prevents ZIP bombs and excessive processing
        */
        'max_file_count' => env('MODEL_MAX_FILE_COUNT', 10000),
    ],

    'datasets' => [
        /*
        | Maximum size for individual files within a dataset ZIP (in bytes)
        | Default: 1GB (1 * 1024 * 1024 * 1024)
        | Dataset files are typically smaller than model files
        */
        'max_individual_file_size' => env('DATASET_MAX_FILE_SIZE', 1 * 1024 * 1024 * 1024),

        /*
        | Maximum total size for all extracted files in a dataset ZIP (in bytes)
        | Default: 20GB (20 * 1024 * 1024 * 1024)
        | Datasets can contain thousands of images/data files
        */
        'max_total_extracted_size' => env('DATASET_MAX_TOTAL_SIZE', 20 * 1024 * 1024 * 1024),

        /*
        | Maximum size for the ZIP file itself (in bytes)
        | Default: 8GB (8 * 1024 * 1024 * 1024)
        | Compressed datasets can be very large
        */
        'max_zip_size' => env('DATASET_MAX_ZIP_SIZE', 8 * 1024 * 1024 * 1024),

        /*
        | Maximum number of files allowed in a single ZIP
        | Default: 50000 files
        | Datasets often contain many small files (images, annotations, etc.)
        */
        'max_file_count' => env('DATASET_MAX_FILE_COUNT', 50000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed File Extensions
    |--------------------------------------------------------------------------
    |
    | Define allowed file extensions for models and datasets.
    | These are organized by category for better management.
    |
    */

    'allowed_extensions' => [
        'models' => [
            // 3D Model formats
            'obj', 'mtl', 'fbx', 'dae', 'blend', 'max', '3ds', 'ply', 'stl', 'x3d', 'gltf', 'glb',
            
            // AI/ML Model formats
            'h5', 'hdf5', 'pkl', 'pickle', 'pth', 'pt', 'ckpt', 'pb', 'tflite', 'onnx', 'mlmodel',
            'safetensors', 'bin', 'msgpack', 'npz', 'npy', 'jinja', 'jinja2', 'j2',
            
            // Configuration and metadata
            'json', 'yaml', 'yml', 'toml', 'xml', 'config', 'cfg', 'ini',
            
            // Documentation and text
            'txt', 'md', 'rst', 'readme', 'license', 'changelog',
            
            // Images (for textures, previews)
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'webp', 'tga', 'exr', 'hdr',
            
            // Archives (for nested structures)
            'tar', 'gz', 'bz2', 'xz',
        ],

        'datasets' => [
            // Image formats
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff', 'webp', 'tga', 'svg', 'ico',
            
            // Data formats
            'csv', 'tsv', 'json', 'jsonl', 'ndjson', 'xml', 'parquet', 'feather', 'hdf5', 'h5',
            'npz', 'npy', 'mat', 'pkl', 'pickle',
            
            // Text and document formats
            'txt', 'md', 'rst', 'pdf', 'doc', 'docx', 'rtf',
            
            // Audio formats
            'wav', 'mp3', 'flac', 'ogg', 'aac', 'm4a',
            
            // Video formats (for video datasets)
            'mp4', 'avi', 'mov', 'mkv', 'webm', 'flv',
            
            // Annotation formats
            'xml', 'json', 'yaml', 'yml', 'coco', 'voc', 'yolo',
            
            // Configuration
            'config', 'cfg', 'ini', 'toml',
            
            // Archives
            'tar', 'gz', 'bz2', 'xz',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Processing Limits
    |--------------------------------------------------------------------------
    |
    | Limits for processing operations to prevent resource exhaustion.
    |
    */

    'processing' => [
        /*
        | Maximum time (in seconds) to spend extracting a ZIP file
        | Default: 300 seconds (5 minutes)
        */
        'max_extraction_time' => env('MAX_EXTRACTION_TIME', 300),

        /*
        | Maximum memory usage (in bytes) for ZIP processing
        | Default: 512MB
        */
        'max_memory_usage' => env('MAX_MEMORY_USAGE', 512 * 1024 * 1024),

        /*
        | Enable/disable parallel file uploads to storage
        | Default: true (for faster processing of large datasets)
        */
        'parallel_uploads' => env('ENABLE_PARALLEL_UPLOADS', true),

        /*
        | Number of files to process in parallel
        | Default: 5 concurrent uploads
        */
        'parallel_upload_limit' => env('PARALLEL_UPLOAD_LIMIT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Human Readable Size Conversion
    |--------------------------------------------------------------------------
    |
    | Helper function to convert config values to human readable format
    | for documentation and error messages.
    |
    */

    'size_labels' => [
        'models' => [
            'max_individual_file_size' => env('MODEL_MAX_FILE_SIZE_LABEL', '2GB'),
            'max_total_extracted_size' => env('MODEL_MAX_TOTAL_SIZE_LABEL', '10GB'),
            'max_zip_size' => env('MODEL_MAX_ZIP_SIZE_LABEL', '5GB'),
        ],
        'datasets' => [
            'max_individual_file_size' => env('DATASET_MAX_FILE_SIZE_LABEL', '1GB'),
            'max_total_extracted_size' => env('DATASET_MAX_TOTAL_SIZE_LABEL', '20GB'),
            'max_zip_size' => env('DATASET_MAX_ZIP_SIZE_LABEL', '8GB'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | System File Filtering
    |--------------------------------------------------------------------------
    |
    | Configuration for filtering out unwanted system and hidden files
    | during ZIP extraction. These files are commonly added by operating
    | systems and IDEs but are not useful for end users.
    |
    */

    'system_file_filtering' => [
        /*
        | Enable/disable system file filtering
        | Default: true (recommended)
        */
        'enabled' => env('FILTER_SYSTEM_FILES', true),

        /*
        | Exact filename matches to filter out (case insensitive)
        */
        'exact_matches' => [
            // macOS system files
            '.DS_Store',
            '.DS_Store?',
            '.Spotlight-V100',
            '.Trashes',
            '.AppleDouble',
            '.LSOverride',
            
            // Windows system files
            'Thumbs.db',
            'Thumbs.db:encryptable',
            'ehthumbs.db',
            'ehthumbs_vista.db',
            'Desktop.ini',
            '$RECYCLE.BIN',
            
            // Linux/Unix hidden files (commonly unwanted)
            '.directory',
            
            // Archive artifacts
            '__MACOSX',
        ],

        /*
        | Regex patterns for filtering files
        | These patterns are applied to the full file path
        */
        'regex_patterns' => [
            '/^\.\_.*/',              // macOS resource forks (._filename)
            '/^__MACOSX\/.*/',        // macOS archive artifacts
            '/^\$RECYCLE\.BIN\/.*/',  // Windows recycle bin
            '/\.tmp$/i',              // Temporary files
            '/\.temp$/i',             // Temporary files
            '/\.swp$/i',              // Vim swap files
            '/\.swo$/i',              // Vim swap files
            '/~$/i',                  // Backup files
            '/\.bak$/i',              // Backup files
            '/\#.*\#$/i',             // Emacs backup files
            '/\.orig$/i',             // Original files (merge conflicts)
            '/\.rej$/i',              // Patch reject files
        ],

        /*
        | File extensions to filter out
        */
        'blocked_extensions' => [
            'lnk',    // Windows shortcuts
            'url',    // Internet shortcuts
            'webloc', // macOS web locations
        ],

        /*
        | Allowed dot files (files starting with . that should NOT be filtered)
        | These are commonly useful configuration files
        */
        'allowed_dot_files' => [
            '.env.example', '.env.sample', '.env.template',
            '.gitignore', '.gitattributes', '.gitkeep',
            '.editorconfig', '.eslintrc', '.prettierrc',
            '.dockerignore', '.babelrc', '.htaccess',
            '.nvmrc', '.node-version', '.python-version',
            '.ruby-version', '.terraform-version',
            '.yamllint', '.flake8', '.pylintrc',
            '.pre-commit-config.yaml',
        ],

        /*
        | Directories to completely skip (including all contents)
        | These are typically version control or IDE directories
        */
        'blocked_directories' => [
            '.git', '.svn', '.hg', '.bzr',           // Version control
            '.vscode', '.idea',                      // IDEs
            'node_modules', 'vendor',                // Dependencies
            '.pytest_cache', '__pycache__',          // Python cache
            '.mypy_cache', '.tox',                   // Python tools
            'dist', 'build', 'target',               // Build artifacts
            '.gradle', '.maven',                     // Build tools
        ],
    ],

];
