<?php
declare(strict_types=1);

final class CustomImportFields
{
    public static function columnExists(PDO $pdo, string $table, string $column = 'custom_fields'): bool
    {
        if (!in_array($table, ['template_import_profiles', 'rankings', 'summary_card_snapshots', 'summary_card_periods'], true) || $column !== 'custom_fields') {
            throw new InvalidArgumentException('Unsupported custom-field schema lookup.');
        }
        $query = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $query->execute([$table, $column]);
        return (int)$query->fetchColumn() > 0;
    }

    public static function storageReady(PDO $pdo): bool
    {
        $query = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'custom_fields'
                AND TABLE_NAME IN ('template_import_profiles', 'rankings', 'summary_card_snapshots', 'summary_card_periods')");
        return (int)$query->fetchColumn() === 4;
    }

    public static function definitions(mixed $definitions): array
    {
        if (!is_array($definitions) || array_is_list($definitions)) {
            if ($definitions === []) return [];
            throw new InvalidArgumentException('Custom fields must be an object of field keys and display labels.');
        }
        if (count($definitions) > 50) throw new InvalidArgumentException('A profile may define at most 50 custom fields.');
        $validated = [];
        foreach ($definitions as $key => $label) {
            if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]{0,47}$/', $key)) {
                throw new InvalidArgumentException('Custom field keys must use lowercase letters, numbers, and underscores.');
            }
            if (!is_string($label)) throw new InvalidArgumentException('Each custom field needs a display label.');
            $label = trim($label);
            $length = function_exists('mb_strlen') ? mb_strlen($label, 'UTF-8') : strlen($label);
            if ($length < 1 || $length > 80) throw new InvalidArgumentException('Custom field labels must be between 1 and 80 characters.');
            $validated[$key] = $label;
        }
        return $validated;
    }

    public static function decode(mixed $value): array
    {
        if (is_string($value)) {
            if (trim($value) === '') return [];
            $value = json_decode($value, true);
        }
        if ($value === null || $value === []) return [];
        if (!is_array($value) || array_is_list($value)) return [];
        $fields = [];
        foreach ($value as $key => $field) {
            if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]{0,47}$/', $key) || !is_array($field)) continue;
            $label = trim((string)($field['label'] ?? ''));
            $text = (string)($field['value'] ?? '');
            if ($label === '' || $text === '') continue;
            $fields[$key] = ['label' => $label, 'value' => $text];
        }
        ksort($fields, SORT_STRING);
        return $fields;
    }

    public static function merge(mixed $base, mixed $incoming): array
    {
        $fields = self::decode($base);
        if (!is_array($incoming)) return $fields;
        foreach ($incoming as $key => $field) {
            if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]{0,47}$/', $key) || !is_array($field)) continue;
            $value = trim((string)($field['value'] ?? ''));
            if ($value === '') continue;
            if ($value === '__CLEAR__') {
                unset($fields[$key]);
                continue;
            }
            if (strlen($value) > 16777215) throw new RuntimeException("Custom field '{$key}' exceeds the 16 MB storage limit.");
            $label = trim((string)($field['label'] ?? $fields[$key]['label'] ?? $key));
            $fields[$key] = ['label' => $label, 'value' => $value];
        }
        return $fields;
    }

    public static function encode(mixed $value): ?string
    {
        $fields = self::decode($value);
        return $fields ? json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) : null;
    }
}
