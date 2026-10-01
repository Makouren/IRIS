<?php
declare(strict_types=1);

final class SummaryCardImportProfiles
{
    private static function decode(array $profile): array
    {
        foreach (['header_aliases', 'required_columns', 'identity_fields', 'mapping_rules', 'defaults_json'] as $field) {
            $value = json_decode((string)($profile[$field] ?? ''), true);
            if ($value === null && $field === 'identity_fields') $value = ['import_key'];
            if (!is_array($value)) throw new RuntimeException('The Summary Card import profile contains invalid configuration.');
            $profile[$field] = $value;
        }
        $profile['profile_name'] = (string)($profile['profile_name'] ?: $profile['template_name'] ?: 'Summary Card import profile');
        return $profile;
    }

    public static function get(PDO $pdo, int $profileId, bool $requireActiveTemplate = false): array
    {
        $query = $pdo->prepare('SELECT profiles.*, templates.name AS template_name, templates.original_filename, templates.is_active AS template_is_active
            FROM template_import_profiles profiles
            LEFT JOIN templates ON templates.id = profiles.template_id
            WHERE profiles.id = ? AND profiles.destination = \'summary_cards\' LIMIT 1');
        $query->execute([$profileId]);
        $profile = $query->fetch(PDO::FETCH_ASSOC);
        if (!$profile) throw new RuntimeException('The selected Summary Card import profile is unavailable.');
        if ($requireActiveTemplate && $profile['template_id'] !== null && (int)$profile['template_is_active'] !== 1) {
            throw new RuntimeException('The selected Summary Card template is inactive. Choose an active profile.');
        }
        return self::decode($profile);
    }

    public static function activeId(PDO $pdo): ?int
    {
        $value = $pdo->query('SELECT active_profile_id FROM summary_card_import_settings WHERE id = 1')->fetchColumn();
        return $value === false || $value === null ? null : (int)$value;
    }

    public static function active(PDO $pdo): array
    {
        $profileId = self::activeId($pdo);
        if ($profileId === null) throw new RuntimeException('No active Summary Card import profile is configured. Ask the Super Admin to choose one.');
        return self::get($pdo, $profileId, true);
    }

    public static function available(PDO $pdo): array
    {
        $activeId = self::activeId($pdo);
        $query = $pdo->query('SELECT profiles.id, profiles.template_id,
                COALESCE(NULLIF(profiles.profile_name, \'\'), templates.name) AS profile_name,
                templates.original_filename,
                profiles.destination
            FROM template_import_profiles profiles
            LEFT JOIN templates ON templates.id = profiles.template_id
            WHERE profiles.destination = \'summary_cards\'
                AND (profiles.template_id IS NULL OR templates.is_active = 1)
            ORDER BY profile_name ASC, profiles.id ASC');
        $profiles = $query->fetchAll(PDO::FETCH_ASSOC);
        foreach ($profiles as &$profile) $profile['is_active'] = (int)$profile['id'] === $activeId;
        unset($profile);
        return $profiles;
    }

    public static function activate(PDO $pdo, int $profileId, int $userId): array
    {
        $profile = self::get($pdo, $profileId, true);
        $update = $pdo->prepare('UPDATE summary_card_import_settings SET active_profile_id = ?, updated_by = ? WHERE id = 1');
        $update->execute([$profileId, $userId]);
        return $profile;
    }
}