<?php
/**
 * Purpose: Shared PHP helper for summary card category storage operations used by application workflows.
 */

declare(strict_types=1);

final class SummaryCardCategoryStorage
{
    public static function names(PDO $pdo, string $cardId): array
    {
        $query = $pdo->prepare('SELECT categories.name
            FROM summary_card_category_map mapping
            INNER JOIN summary_card_categories categories ON categories.category_id = mapping.category_id
            WHERE mapping.card_id = ?
            ORDER BY categories.sort_order ASC, categories.name ASC');
        $query->execute([$cardId]);
        return array_map('strval', $query->fetchAll(PDO::FETCH_COLUMN));
    }

    private static function categoryId(PDO $pdo, string $name): int
    {
        $name = trim($name);
        $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);
        if ($length < 1 || $length > 40) throw new InvalidArgumentException('Category names must contain between 1 and 40 characters.');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $normalized = strtolower($ascii === false ? $name : $ascii);
        $slug = trim(preg_replace('/[^a-z0-9]+/', '-', $normalized) ?? '', '-');
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) throw new InvalidArgumentException('Category names must contain letters or numbers.');

        $query = $pdo->prepare('SELECT category_id FROM summary_card_categories WHERE slug = ? LIMIT 1');
        $query->execute([$slug]);
        $id = $query->fetchColumn();
        if ($id !== false) return (int)$id;

        $sortOrder = (int)$pdo->query('SELECT COALESCE(MAX(sort_order), -1) + 1 FROM summary_card_categories')->fetchColumn();
        try {
            $pdo->prepare('INSERT INTO summary_card_categories (name, slug, sort_order) VALUES (?, ?, ?)')->execute([$name, $slug, $sortOrder]);
            return (int)$pdo->lastInsertId();
        } catch (PDOException $exception) {
            $query->execute([$slug]);
            $id = $query->fetchColumn();
            if ($id === false) throw $exception;
            return (int)$id;
        }
    }

    public static function replace(PDO $pdo, string $cardId, array $names): void
    {
        $ids = [];
        foreach ($names as $name) {
            if (!is_scalar($name)) throw new InvalidArgumentException('Category names must be text values.');
            $name = trim((string)$name);
            if ($name !== '') $ids[] = self::categoryId($pdo, $name);
        }
        $ids = array_values(array_unique($ids));
        $pdo->prepare('DELETE FROM summary_card_category_map WHERE card_id = ?')->execute([$cardId]);
        $insert = $pdo->prepare('INSERT INTO summary_card_category_map (card_id, category_id) VALUES (?, ?)');
        foreach ($ids as $categoryId) $insert->execute([$cardId, $categoryId]);
    }
}