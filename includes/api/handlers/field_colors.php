<?php

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $rows = $pdo->query('SELECT field_name AS field_key, field_name AS label, color, updated_at FROM field_colors ORDER BY field_name ASC')->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$row) {
                $row['field_key'] = normalize_field_key($row['field_key']);
                $row['color'] = valid_field_color($row['color']) ? strtoupper($row['color']) : null;
            }
            unset($row);
            echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            ensure_admin_for_mutation();
            $data = json_input();
            $label = trim((string)($data['label'] ?? ''));
            $fieldKey = normalize_field_key($data['field_key'] ?? $label);
            $color = $data['color'] ?? null;
            if ($fieldKey === '' || $label === '') bad('A field label is required.');
            if (!valid_field_color($color)) bad('color must be a six-digit HEX value.');
            $stmt = $pdo->prepare('INSERT INTO field_colors (field_name, color) VALUES (?, ?) ON DUPLICATE KEY UPDATE color = VALUES(color), updated_at = CURRENT_TIMESTAMP');
            $stmt->execute([$fieldKey, strtoupper($color)]);
            $q = $pdo->prepare('SELECT field_name AS field_key, field_name AS label, color, updated_at FROM field_colors WHERE field_name = ?');
            $q->execute([$fieldKey]);
            echo json_encode($q->fetch(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            ensure_admin_for_mutation();
            $fieldKey = normalize_field_key($_GET['field_key'] ?? '');
            if ($fieldKey === '') bad('field_key is required.');
            $stmt = $pdo->prepare('DELETE FROM field_colors WHERE field_name = ?');
            $stmt->execute([$fieldKey]);
            echo json_encode(['success' => true, 'deleted' => $stmt->rowCount() > 0]);
            exit;
        }
