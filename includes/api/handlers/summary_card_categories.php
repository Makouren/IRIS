<?php
/**
 * Purpose: API handler for summary card categories operations; dispatched through the shared API router.
 */


        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if ($action === 'public-default') {
                $state = json_col($pdo->query('SELECT state_data FROM app_change_state WHERE id = 1')->fetchColumn(), []);
                echo json_encode(['default_category_slug' => is_array($state) ? ($state['summary_cards_default_category'] ?? null) : null], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                exit;
            }
            $rows = ($_SESSION['role'] ?? '') === 'super_admin'
                ? $pdo->query('SELECT category_id AS id, name, slug, sort_order FROM summary_card_categories ORDER BY sort_order ASC, name ASC')->fetchAll(PDO::FETCH_ASSOC)
                : $pdo->query("SELECT DISTINCT categories.category_id AS id, categories.name, categories.slug, categories.sort_order
                    FROM summary_card_categories categories
                    INNER JOIN summary_card_category_map mapping ON mapping.category_id = categories.category_id
                    INNER JOIN summary_cards cards ON cards.card_id = mapping.card_id
                    WHERE cards.is_published = 1 ORDER BY categories.sort_order ASC, categories.name ASC")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        ensure_admin_for_mutation();
        ensure_json_csrf();
        $data = json_input();
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save-public-default') {
            if (($_SESSION['role'] ?? '') !== 'super_admin') bad('Only a Super Admin can change the public summary-card default.', 403);
            $slug = trim((string)($data['default_category_slug'] ?? ''));
            if ($slug !== '') {
                $check = $pdo->prepare('SELECT 1 FROM summary_card_categories categories
                    WHERE categories.slug = ? AND EXISTS (
                        SELECT 1 FROM summary_card_category_map mapping
                        INNER JOIN summary_cards cards ON cards.card_id = mapping.card_id
                        WHERE mapping.category_id = categories.category_id AND cards.is_published = 1
                    ) LIMIT 1');
                $check->execute([$slug]);
                if (!$check->fetchColumn()) bad('Choose a category with at least one published summary card.', 404);
            }
            $value = $slug !== '' ? $slug : null;
            $save = $pdo->prepare("UPDATE app_change_state SET state_data = JSON_SET(COALESCE(state_data, JSON_OBJECT()),
                '$.summary_cards_default_category', ?) WHERE id = 1");
            $save->execute([$value]);
            echo json_encode(['success' => true, 'default_category_slug' => $value], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim((string)($data['name'] ?? ''));
            $slug = summary_category_slug($name);
            $sortOrder = 0;
            if (array_key_exists('sort_order', $data)) {
                $sortOrder = filter_var($data['sort_order'], FILTER_VALIDATE_INT);
                if ($sortOrder === false || $sortOrder < 0) bad('Category sort order must be a non-negative whole number.');
            }
            $existing = $pdo->prepare('SELECT category_id AS id, name, slug, sort_order FROM summary_card_categories WHERE slug = ? LIMIT 1');
            $existing->execute([$slug]);
            $category = $existing->fetch(PDO::FETCH_ASSOC);
            if (!$category) {
                $insert = $pdo->prepare('INSERT INTO summary_card_categories (name, slug, sort_order) VALUES (?, ?, ?)');
                $insert->execute([$name, $slug, $sortOrder]);
                $id = (int)$pdo->lastInsertId();
                $existing->execute([$slug]);
                $category = $existing->fetch(PDO::FETCH_ASSOC);
            }
            echo json_encode($category, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
            if ($id === null) bad('Category id is required.');
            $sets = [];
            $values = [];
            if (array_key_exists('name', $data)) {
                $name = trim((string)$data['name']);
                $slug = summary_category_slug($name);
                $sets[] = 'name = ?';
                $values[] = $name;
                $sets[] = 'slug = ?';
                $values[] = $slug;
            }
            if (array_key_exists('sort_order', $data)) {
                $sortOrder = filter_var($data['sort_order'], FILTER_VALIDATE_INT);
                if ($sortOrder === false || $sortOrder < 0) bad('Category sort order must be a non-negative whole number.');
                $sets[] = 'sort_order = ?';
                $values[] = $sortOrder;
            }
            if (!$sets) bad('No category fields to update.');
            $values[] = (int)$id;
            try {
                $pdo->prepare('UPDATE summary_card_categories SET ' . implode(', ', $sets) . ' WHERE category_id = ?')->execute($values);
            } catch (PDOException $exception) {
                bad('A category with that name or slug already exists.', 409);
            }
            $query = $pdo->prepare('SELECT category_id AS id, name, slug, sort_order FROM summary_card_categories WHERE category_id = ?');
            $query->execute([(int)$id]);
            $category = $query->fetch(PDO::FETCH_ASSOC);
            if (!$category) bad('Category not found.', 404);
            echo json_encode($category, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            if ($id === null) bad('Category id is required.');
            $delete = $pdo->prepare('DELETE FROM summary_card_categories WHERE category_id = ?');
            $delete->execute([(int)$id]);
            echo json_encode(['success' => true, 'deleted' => $delete->rowCount() > 0]);
            exit;
        }
