<?php
/**
 * Purpose: Shared PHP helper for summary card import profiles operations used by application workflows.
 */

declare(strict_types=1);

require_once __DIR__ . '/CustomImportFields.php';

final class SummaryCardImportProfiles
{
    private static function ensureDefaultProfiles(PDO $pdo): void
    {
        $pdo->exec("INSERT INTO template_import_profiles (
                template_id, profile_name, destination, sheet_selector, header_aliases,
                required_columns, identity_fields, mapping_rules, defaults_json
            )
            SELECT NULL, 'Unified Summary Cards', 'summary_cards', 'Summary Cards',
                JSON_OBJECT(
                    'import_key', JSON_ARRAY('Global Label'),
                    'card_title', JSON_ARRAY('Title'),
                    'period_key', JSON_ARRAY('Year / Date'),
                    'main_value', JSON_ARRAY('Main Value'),
                    'main_label', JSON_ARRAY('Main Label'),
                    'year_date', JSON_ARRAY('Year / Date'),
                    'secondary_label', JSON_ARRAY('Secondary Label'),
                    'secondary_value', JSON_ARRAY('Secondary Value'),
                    'description', JSON_ARRAY('Description'),
                    'secondary_description', JSON_ARRAY('Italic Supporting Text'),
                    'info_text', JSON_ARRAY('Information'),
                    'category_names', JSON_ARRAY('Categories'),
                    'display_precision', JSON_ARRAY('Display Precision')
                ),
                JSON_ARRAY('import_key', 'period_key', 'main_value', 'main_label', 'card_title'),
                JSON_ARRAY('import_key', 'main_label', 'card_title'),
                JSON_OBJECT(
                    'import_key', 'Global Label',
                    'card_title', 'Title',
                    'period_key', 'Year / Date',
                    'main_value', 'Main Value',
                    'main_label', 'Main Label',
                    'year_date', 'Year / Date',
                    'secondary_label', 'Secondary Label',
                    'secondary_value', 'Secondary Value',
                    'description', 'Description',
                    'secondary_description', 'Italic Supporting Text',
                    'info_text', 'Information',
                    'category_names', 'Categories',
                    'display_precision', 'Display Precision'
                ),
                JSON_OBJECT()
            WHERE NOT EXISTS (
                SELECT 1 FROM template_import_profiles
                WHERE template_id IS NULL AND profile_name = 'Unified Summary Cards' AND destination = 'summary_cards'
            );

            INSERT INTO template_import_profiles (
                template_id, profile_name, destination, sheet_selector, header_aliases,
                required_columns, identity_fields, mapping_rules, defaults_json
            )
            SELECT NULL, 'Unified Ranking History', 'ranking_history', NULL,
                JSON_OBJECT(
                    'organization', JSON_ARRAY('Organization', 'Institution', 'University'),
                    'ranking_type', JSON_ARRAY('Ranking Type', 'Ranking', 'List', 'Ranking List', 'Type'),
                    'year', JSON_ARRAY('Year', 'Edition Year'),
                    'global_rank', JSON_ARRAY('Rank', 'Global Rank', 'Overall Rank', 'World Rank'),
                    'ph_rank', JSON_ARRAY('Philippine Rank', 'PH Rank', 'National Rank'),
                    'info_text', JSON_ARRAY('Information', 'Information Text', 'Info', 'Source', 'Source Information', 'Reference', 'URL')
                ),
                JSON_ARRAY('organization', 'ranking_type', 'year', 'global_rank'),
                JSON_ARRAY('organization', 'ranking_type', 'year'),
                JSON_OBJECT(
                    'organization', 'Organization',
                    'ranking_type', 'Ranking Type',
                    'year', 'Year',
                    'global_rank', 'Rank',
                    'ph_rank', 'Philippine Rank',
                    'info_text', 'Information'
                ),
                JSON_OBJECT()
            WHERE NOT EXISTS (
                SELECT 1 FROM template_import_profiles
                WHERE template_id IS NULL AND profile_name = 'Unified Ranking History' AND destination = 'ranking_history'
            );

            ");
    }

    private static function decode(array $profile): array
    {
        foreach (['header_aliases', 'required_columns', 'identity_fields', 'mapping_rules', 'defaults_json'] as $field) {
            $value = json_decode((string)($profile[$field] ?? ''), true);
            if ($value === null && $field === 'identity_fields') $value = ['import_key'];
            if (!is_array($value)) throw new RuntimeException('The Summary Card import profile contains invalid configuration.');
            $profile[$field] = $value;
        }
        $profile['custom_fields'] = CustomImportFields::definitions(json_decode((string)($profile['custom_fields'] ?? '{}'), true) ?? []);
            if (array_key_exists('workbook_headers', $profile) && is_string($profile['workbook_headers'])) {
                $headers = json_decode($profile['workbook_headers'], true);
                $profile['workbook_headers'] = is_array($headers) ? $headers : [];
            }
        $profile['profile_name'] = (string)($profile['profile_name'] ?: $profile['template_name'] ?: 'Summary Card import profile');
        return $profile;
    }

    public static function get(PDO $pdo, int $profileId, bool $requireActiveTemplate = false, ?string $destination = 'summary_cards'): array
    {
        $sql = 'SELECT profiles.*, profiles.import_profile_id AS id, templates.name AS template_name, templates.original_filename, templates.is_active AS template_is_active
            FROM template_import_profiles profiles
            LEFT JOIN templates ON templates.template_id = profiles.template_id
            WHERE profiles.import_profile_id = ?';
        $parameters = [$profileId];
        if ($destination !== null) {
            $sql .= ' AND profiles.destination = ?';
            $parameters[] = $destination;
        }
        $query = $pdo->prepare($sql . ' LIMIT 1');
        $query->execute($parameters);
        $profile = $query->fetch(PDO::FETCH_ASSOC);
        if (!$profile) throw new RuntimeException('The selected import profile is unavailable.');
        if ($requireActiveTemplate && $profile['template_id'] !== null && (int)$profile['template_is_active'] !== 1) {
            throw new RuntimeException('The selected import template is inactive. Choose an active profile.');
        }
        return self::decode($profile);
    }

    public static function activeId(PDO $pdo, string $destination = 'summary_cards'): ?int
    {
        self::ensureDefaultProfiles($pdo);
        if (!in_array($destination, ['summary_cards', 'ranking_history'], true)) throw new InvalidArgumentException('Choose a valid import destination.');
        $state = json_decode((string)$pdo->query('SELECT state_data FROM app_change_state WHERE id = 1')->fetchColumn(), true);
        $settings = $state['summary_card_import_profiles'][$destination] ?? null;
        if (is_array($settings) && array_key_exists('active_profile_id', $settings)) {
            $value = $settings['active_profile_id'];
            return is_numeric($value) && (int)$value > 0 ? (int)$value : null;
        }
        $profileName = $destination === 'summary_cards' ? 'Unified Summary Cards' : 'Unified Ranking History';
        $query = $pdo->prepare('SELECT import_profile_id FROM template_import_profiles WHERE template_id IS NULL AND profile_name = ? AND destination = ? ORDER BY import_profile_id LIMIT 1');
        $query->execute([$profileName, $destination]);
        $profileId = $query->fetchColumn();
        return $profileId === false ? null : (int)$profileId;
    }

    public static function active(PDO $pdo, string $destination = 'summary_cards'): array
    {
        $profileId = self::activeId($pdo, $destination);
        if ($profileId === null) throw new RuntimeException('No active import profile is configured for this destination. Ask the Super Admin to choose one.');
        return self::get($pdo, $profileId, true, $destination);
    }

    public static function available(PDO $pdo, string $destination = 'summary_cards'): array
    {
        self::ensureDefaultProfiles($pdo);
        $activeId = self::activeId($pdo, $destination);
        $query = $pdo->query('SELECT profiles.import_profile_id AS id, profiles.template_id,
                COALESCE(NULLIF(profiles.profile_name, \'\'), templates.name) AS profile_name,
                COALESCE(profiles.workbook_original_filename, templates.original_filename) AS original_filename,
                profiles.workbook_original_filename,
                profiles.destination
            FROM template_import_profiles profiles
            LEFT JOIN templates ON templates.template_id = profiles.template_id
            WHERE profiles.destination = ' . $pdo->quote($destination) . '
                AND (profiles.template_id IS NULL OR templates.is_active = 1)
            ORDER BY profile_name ASC, profiles.import_profile_id ASC');
        $profiles = $query->fetchAll(PDO::FETCH_ASSOC);
        foreach ($profiles as &$profile) $profile['is_active'] = (int)$profile['id'] === $activeId;
        unset($profile);
        return $profiles;
    }

    public static function activate(PDO $pdo, int $profileId, int $userId, string $destination = 'summary_cards'): array
    {
        $profile = self::get($pdo, $profileId, true, $destination);
        if (!in_array($destination, ['summary_cards', 'ranking_history'], true)) throw new InvalidArgumentException('Choose a valid import destination.');
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) $pdo->beginTransaction();
        try {
            $stateQuery = $pdo->query('SELECT state_data FROM app_change_state WHERE id = 1 FOR UPDATE');
            $encodedState = $stateQuery->fetchColumn();
            $state = $encodedState === false || $encodedState === null || $encodedState === ''
                ? []
                : json_decode((string)$encodedState, true);
            if (!is_array($state)) throw new RuntimeException('Application state is invalid and the active import profile could not be changed.');
            if (!isset($state['summary_card_import_profiles']) || !is_array($state['summary_card_import_profiles'])) {
                $state['summary_card_import_profiles'] = [];
            }
            $state['summary_card_import_profiles'][$destination] = ['active_profile_id' => $profileId];
            $encodedState = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $update = $pdo->prepare('INSERT INTO app_change_state (id, state_data) VALUES (1, ?)
                ON DUPLICATE KEY UPDATE state_data = VALUES(state_data)');
            $update->execute([$encodedState]);
            if ($ownsTransaction) $pdo->commit();
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        return $profile;
    }

    public static function deactivate(PDO $pdo, int $profileId, int $userId, string $destination = 'summary_cards'): array
    {
        if (!in_array($destination, ['summary_cards', 'ranking_history'], true)) throw new InvalidArgumentException('Choose a valid import destination.');
        $profile = self::get($pdo, $profileId, true, $destination);
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) $pdo->beginTransaction();
        try {
            $stateQuery = $pdo->query('SELECT state_data FROM app_change_state WHERE id = 1 FOR UPDATE');
            $encodedState = $stateQuery->fetchColumn();
            $state = $encodedState === false || $encodedState === null || $encodedState === ''
                ? []
                : json_decode((string)$encodedState, true);
            if (!is_array($state)) throw new RuntimeException('Application state is invalid and the active import profile could not be changed.');
            if (self::activeId($pdo, $destination) !== $profileId) {
                throw new RuntimeException('The selected import profile is no longer active. Refresh and try again.');
            }
            if (!isset($state['summary_card_import_profiles']) || !is_array($state['summary_card_import_profiles'])) {
                $state['summary_card_import_profiles'] = [];
            }
            $state['summary_card_import_profiles'][$destination] = ['active_profile_id' => null];
            $encodedState = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $update = $pdo->prepare('INSERT INTO app_change_state (id, state_data) VALUES (1, ?)
                ON DUPLICATE KEY UPDATE state_data = VALUES(state_data)');
            $update->execute([$encodedState]);
            if ($ownsTransaction) $pdo->commit();
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        return $profile;
    }
}