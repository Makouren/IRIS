<?php
/**
 * Purpose: Define the common PHP upload limit and translate upload errors.
 * Included by: Upload pages and API endpoints before handling uploaded files.
 * Inputs/outputs: Reads PHP request and upload settings; provides constants and helper results.
 * Dependencies: PHP runtime upload constants and configuration.
 * Load order: Include before upload validation or parser invocation.
 */
const IRIS_MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

/**
 * Format the shared upload byte limit for user-facing validation messages.
 *
 * @return string Human-readable upload limit.
 */
function iris_upload_limit_label(): string
{
    return rtrim(rtrim(number_format(IRIS_MAX_UPLOAD_BYTES / 1048576, 2, '.', ''), '0'), '.') . ' MB';
}

/**
 * Convert a PHP ini size value (including K/M/G suffixes) to bytes.
 *
 * @param string $value Value returned by an ini setting.
 * @return int Parsed byte count, or zero for an empty setting.
 */
function iris_ini_size_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') return 0;
    $unit = strtolower(substr($value, -1));
    $number = (float)$value;
    return (int)($number * match ($unit) {
        'g' => 1024 ** 3,
        'm' => 1024 ** 2,
        'k' => 1024,
        default => 1
    });
}

/**
 * Detect multipart requests discarded by PHP because post_max_size was exceeded.
 *
 * @return bool True when PHP dropped the POST body due to its configured size limit.
 */
function iris_upload_request_exceeded_post_limit(): bool
{
    $contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    $contentLength = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    $postLimit = iris_ini_size_bytes((string)ini_get('post_max_size'));
    return str_starts_with($contentType, 'multipart/form-data')
        && $contentLength > 0
        && $postLimit > 0
        && $contentLength > $postLimit
        && empty($_POST)
        && empty($_FILES);
}

/**
 * Map a PHP upload error code to the established user-facing message.
 *
 * @param int $code PHP upload error code.
 * @return string User-facing explanation.
 */
function iris_upload_error_message(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file exceeds the ' . iris_upload_limit_label() . ' upload limit. Check the PHP upload_max_filesize and post_max_size settings.',
        UPLOAD_ERR_PARTIAL => 'The upload was interrupted before the file finished transferring. Please try again.',
        UPLOAD_ERR_NO_FILE => 'Choose a file to upload.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server upload temporary directory is unavailable. Contact the administrator.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not save the uploaded file. Contact the administrator.',
        UPLOAD_ERR_EXTENSION => 'A server extension stopped the upload. Contact the administrator.',
        default => 'The file could not be uploaded. Please try again.'
    };
}
