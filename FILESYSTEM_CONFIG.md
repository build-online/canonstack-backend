# Filesystem Configuration for AI Models and Datasets

This document explains how to configure file size limits for your AI model and dataset uploads.

## Environment Variables

Add these variables to your `.env` file to customize file size limits:

### Model File Limits

```env
# Maximum size for individual files within a model ZIP (in bytes)
# Common AI models: PyTorch (.pth), TensorFlow (.pb), ONNX (.onnx), etc.
# Default: 2GB
MODEL_MAX_FILE_SIZE=2147483648
MODEL_MAX_FILE_SIZE_LABEL="2GB"

# Maximum total size for all extracted files in a model ZIP (in bytes)
# Large models with multiple files (weights, config, tokenizer, etc.)
# Default: 10GB
MODEL_MAX_TOTAL_SIZE=10737418240
MODEL_MAX_TOTAL_SIZE_LABEL="10GB"

# Maximum size for the ZIP file itself (in bytes)
# Default: 5GB
MODEL_MAX_ZIP_SIZE=5368709120
MODEL_MAX_ZIP_SIZE_LABEL="5GB"

# Maximum number of files allowed in a single model ZIP
# Default: 10000 files
MODEL_MAX_FILE_COUNT=10000
```

### Dataset File Limits

```env
# Maximum size for individual files within a dataset ZIP (in bytes)
# Default: 1GB
DATASET_MAX_FILE_SIZE=1073741824
DATASET_MAX_FILE_SIZE_LABEL="1GB"

# Maximum total size for all extracted files in a dataset ZIP (in bytes)
# Default: 20GB
DATASET_MAX_TOTAL_SIZE=21474836480
DATASET_MAX_TOTAL_SIZE_LABEL="20GB"

# Maximum size for the dataset ZIP file itself (in bytes)
# Default: 8GB
DATASET_MAX_ZIP_SIZE=8589934592
DATASET_MAX_ZIP_SIZE_LABEL="8GB"

# Maximum number of files allowed in a single dataset ZIP
# Default: 50000 files
DATASET_MAX_FILE_COUNT=50000
```

### Processing Limits

```env
# Maximum time (in seconds) to spend extracting a ZIP file
# Default: 300 seconds (5 minutes)
MAX_EXTRACTION_TIME=300

# Maximum memory usage (in bytes) for ZIP processing
# Default: 512MB
MAX_MEMORY_USAGE=536870912

# Enable/disable parallel file uploads to storage
# Default: true
ENABLE_PARALLEL_UPLOADS=true

# Number of files to process in parallel
# Default: 5 concurrent uploads
PARALLEL_UPLOAD_LIMIT=5
```

## Configuration Examples

### Small/Medium Projects (Limited Resources)
```env
MODEL_MAX_FILE_SIZE=536870912          # 512MB
MODEL_MAX_TOTAL_SIZE=2147483648        # 2GB
MODEL_MAX_ZIP_SIZE=1073741824          # 1GB
DATASET_MAX_TOTAL_SIZE=5368709120      # 5GB
```

### Large Enterprise Projects (High-end Infrastructure)
```env
MODEL_MAX_FILE_SIZE=10737418240        # 10GB
MODEL_MAX_TOTAL_SIZE=53687091200       # 50GB
MODEL_MAX_ZIP_SIZE=21474836480         # 20GB
DATASET_MAX_TOTAL_SIZE=107374182400    # 100GB
```

### Research Institutions (Very Large Datasets)
```env
MODEL_MAX_TOTAL_SIZE=107374182400      # 100GB
DATASET_MAX_TOTAL_SIZE=1099511627776   # 1TB
DATASET_MAX_ZIP_SIZE=107374182400      # 100GB
DATASET_MAX_FILE_COUNT=1000000         # 1 million files
```

## Supported File Types

### Models
- **AI/ML Formats**: `.h5`, `.hdf5`, `.pkl`, `.pth`, `.pt`, `.ckpt`, `.pb`, `.tflite`, `.onnx`, `.safetensors`, `.bin`
- **3D Models**: `.obj`, `.mtl`, `.fbx`, `.dae`, `.blend`, `.gltf`, `.glb`
- **Configuration**: `.json`, `.yaml`, `.yml`, `.toml`, `.xml`, `.config`
- **Documentation**: `.txt`, `.md`, `.rst`, `.readme`
- **Images**: `.jpg`, `.jpeg`, `.png`, `.gif`, `.bmp`, `.tiff`, `.webp`

### Datasets
- **Images**: `.jpg`, `.jpeg`, `.png`, `.gif`, `.bmp`, `.tiff`, `.webp`, `.svg`
- **Data**: `.csv`, `.tsv`, `.json`, `.jsonl`, `.xml`, `.parquet`, `.hdf5`, `.npz`, `.npy`
- **Audio**: `.wav`, `.mp3`, `.flac`, `.ogg`, `.aac`
- **Video**: `.mp4`, `.avi`, `.mov`, `.mkv`, `.webm`
- **Annotations**: `.xml`, `.json`, `.yaml`, `.coco`, `.voc`, `.yolo`

## Size Conversion Reference

| Bytes | KB | MB | GB | TB |
|-------|----|----|----|----|
| 1,048,576 | 1,024 | 1 | 0.001 | 0.000001 |
| 1,073,741,824 | 1,048,576 | 1,024 | 1 | 0.001 |
| 1,099,511,627,776 | 1,073,741,824 | 1,048,576 | 1,024 | 1 |

## Security Features

- **ZIP Slip Protection**: Prevents directory traversal attacks
- **File Type Validation**: Only allows whitelisted file extensions
- **Size Limits**: Prevents resource exhaustion
- **File Count Limits**: Prevents ZIP bomb attacks
- **Path Validation**: Blocks malicious file paths

## Performance Considerations

- **Parallel Uploads**: Enable for faster processing with sufficient bandwidth
- **Memory Limits**: Adjust based on your server's available RAM
- **Extraction Time**: Balance between user experience and server resources
- **Storage**: Ensure adequate Wasabi/S3 storage for both ZIPs and extracted files
