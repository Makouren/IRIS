<?php
require_once __DIR__.'/functions.php';
require_once __DIR__.'/helpers/RankBoundsParser.php';

function insert_reference_name(PDO $pdo, string $table, string $idColumn, string $name, array $extra = []): ?int
{
	$allowed = [
		'ranking_types' => ['ranking_type_id', 'ranking_body_id'],
		'ranking_categories' => ['category_id', 'ranking_body_id'],
		'accrediting_bodies' => ['accrediting_body_id'],
		'accreditation_criteria' => ['criterion_id']
	];
	if (!isset($allowed[$table]) || $allowed[$table][0] !== $idColumn || $name === '') return null;
	$where = [];
	$values = [];
	foreach ($extra as $column => $value) {
		if (!in_array($column, $allowed[$table], true)) return null;
		$where[] = '`' . $column . '` = ?';
		$values[] = $value;
	}
	$query = $pdo->prepare('SELECT `' . $idColumn . '` FROM `' . $table . '` WHERE ' . implode(' AND ', [...$where, 'LOWER(name) = LOWER(?)']) . ' LIMIT 1');
	$query->execute([...$values, $name]);
	$id = $query->fetchColumn();
	if ($id) return (int)$id;
	$columns = [...array_keys($extra), 'name'];
	$insert = $pdo->prepare('INSERT INTO `' . $table . '` (`' . implode('`, `', $columns) . '`) VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')');
	try {
		$insert->execute([...array_values($extra), $name]);
		return (int)$pdo->lastInsertId();
	} catch (PDOException $exception) {
		$query->execute([...$values, $name]);
		$id = $query->fetchColumn();
		if ($id) return (int)$id;
		throw $exception;
	}
}

function insert_ranking(array $r): bool
{
	$pdo = db();
	$bodyQuery = $pdo->prepare('SELECT ranking_body_id FROM ranking_bodies WHERE short_name = ? LIMIT 1');
	$bodyQuery->execute([trim((string)($r['ranking_body_short_name'] ?? ''))]);
	$bodyId = $bodyQuery->fetchColumn();
	$year = filter_var($r['year'] ?? null, FILTER_VALIDATE_INT);
	$rank = trim((string)($r['global_rank'] ?? ''));
	if (!$bodyId || $year === false || $year < 1 || $rank === '') return false;
	[$rankLow, $rankHigh, $rankValue] = RankBoundsParser::parse($rank);
	$typeName = trim((string)($r['ranking_type'] ?? ''));
	$typeId = $typeName !== '' ? insert_reference_name($pdo, 'ranking_types', 'ranking_type_id', $typeName, ['ranking_body_id' => (int)$bodyId]) : null;
	$categoryName = trim((string)($r['category'] ?? ''));
	$categoryId = $categoryName !== '' ? insert_reference_name($pdo, 'ranking_categories', 'category_id', $categoryName, ['ranking_body_id' => (int)$bodyId]) : null;
	$phRank = trim((string)($r['ph_rank'] ?? ''));
	$values = [(int)$bodyId, $typeId, $categoryId, (int)$year, $rankLow, $rank, $rankLow, $rankHigh, $rankValue,
		$phRank !== '' ? parse_rank_to_value($phRank) : null, $phRank !== '' ? $phRank : null,
		$phRank !== '' ? parse_rank_to_value($phRank) : null,
		trim((string)($r['note'] ?? '')) ?: null];
	$find = $pdo->prepare('SELECT ranking_id FROM rankings WHERE ranking_body_id = ? AND ranking_type_id <=> ? AND category_id <=> ? AND year = ? ORDER BY ranking_id LIMIT 1');
	$find->execute(array_slice($values, 0, 4));
	$rankingId = $find->fetchColumn();
	if ($rankingId) {
		$save = $pdo->prepare('UPDATE rankings SET global_rank = ?, global_rank_display = ?, rank_low = ?, rank_high = ?, rank_value = ?, ph_rank = ?, ph_rank_display = ?, ph_rank_value = ?, note = ? WHERE ranking_id = ?');
		return $save->execute([$values[4], $values[5], $values[6], $values[7], $values[8], $values[9], $values[10], $values[11], $values[12], (int)$rankingId]);
	}
	$save = $pdo->prepare('INSERT INTO rankings (ranking_body_id, ranking_type_id, category_id, year, global_rank, global_rank_display, rank_low, rank_high, rank_value, ph_rank, ph_rank_display, ph_rank_value, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
	return $save->execute($values);
}

function insert_breakdown(array $r): bool
{
	$pdo = db();
	$bodyQuery = $pdo->prepare('SELECT ranking_body_id FROM ranking_bodies WHERE short_name = ? LIMIT 1');
	$bodyQuery->execute([trim((string)($r['ranking_body_short_name'] ?? ''))]);
	$bodyId = $bodyQuery->fetchColumn();
	$item = trim((string)($r['item_label'] ?? ''));
	$year = filter_var($r['year'] ?? null, FILTER_VALIDATE_INT);
	if (!$bodyId || $item === '' || $year === false) return false;
	$sql = 'SELECT rankings.ranking_id FROM rankings LEFT JOIN ranking_types ON ranking_types.ranking_type_id = rankings.ranking_type_id WHERE rankings.ranking_body_id = ? AND rankings.year = ?';
	$params = [(int)$bodyId, (int)$year];
	$type = trim((string)($r['ranking_type'] ?? ''));
	if ($type !== '') { $sql .= ' AND LOWER(ranking_types.name) = LOWER(?)'; $params[] = $type; }
	$category = trim((string)($r['category'] ?? ''));
	if ($category !== '') {
		$sql .= ' AND rankings.category_id = (SELECT category_id FROM ranking_categories WHERE ranking_body_id = ? AND LOWER(name) = LOWER(?) LIMIT 1)';
		$params[] = (int)$bodyId;
		$params[] = $category;
	}
	$sql .= ' ORDER BY rankings.ranking_id LIMIT 2';
	$find = $pdo->prepare($sql);
	$find->execute($params);
	$matches = $find->fetchAll(PDO::FETCH_COLUMN);
	if (count($matches) !== 1) return false;
	$rankingId = (int)$matches[0];
	$group = trim((string)($r['group_label'] ?? '')) ?: null;
	$rankDisplay = trim((string)($r['rank_display'] ?? '')) ?: null;
	$note = trim((string)($r['note'] ?? '')) ?: null;
	$existing = $pdo->prepare('SELECT breakdown_id FROM ranking_breakdowns WHERE ranking_id = ? AND group_label <=> ? AND item_label = ? ORDER BY breakdown_id LIMIT 1');
	$existing->execute([$rankingId, $group, $item]);
	$breakdownId = $existing->fetchColumn();
	if ($breakdownId) {
		$save = $pdo->prepare('UPDATE ranking_breakdowns SET rank_display = ?, rank_value = ?, note = ? WHERE breakdown_id = ?');
		return $save->execute([$rankDisplay, $rankDisplay !== null ? parse_rank_to_value($rankDisplay) : null, $note, (int)$breakdownId]);
	}
	$save = $pdo->prepare('INSERT INTO ranking_breakdowns (ranking_id, group_label, item_label, rank_display, rank_value, note) VALUES (?, ?, ?, ?, ?, ?)');
	return $save->execute([$rankingId, $group, $item, $rankDisplay, $rankDisplay !== null ? parse_rank_to_value($rankDisplay) : null, $note]);
}

function insert_college(array $r): bool
{
	$pdo = db();
	$name = trim((string)($r['name'] ?? ''));
	$shortCode = trim((string)($r['short_code'] ?? ''));
	if ($name === '') return false;
	$shortCodeValue = $shortCode !== '' ? $shortCode : null;
	$find = $shortCodeValue === null
		? $pdo->prepare('SELECT college_id FROM colleges WHERE name = ? LIMIT 1')
		: $pdo->prepare('SELECT college_id FROM colleges WHERE short_code = ? OR name = ? ORDER BY (short_code = ?) DESC LIMIT 1');
	$find->execute($shortCodeValue === null ? [$name] : [$shortCodeValue, $name, $shortCodeValue]);
	$collegeId = $find->fetchColumn();
	if ($collegeId) {
		$pdo->prepare('UPDATE colleges SET name = ?, short_code = ? WHERE college_id = ?')->execute([$name, $shortCodeValue, (int)$collegeId]);
	} else {
		$insert = $pdo->prepare('INSERT INTO colleges (name, short_code) VALUES (?, ?)');
		$insert->execute([$name, $shortCodeValue]);
		$collegeId = (int)$pdo->lastInsertId();
	}
	$year = filter_var($r['year'] ?? null, FILTER_VALIDATE_INT);
	if ($year !== false && $year !== null && $year > 0) {
		$contribution = is_numeric($r['contribution_percent'] ?? null) ? (float)$r['contribution_percent'] : null;
		$save = $pdo->prepare('INSERT INTO college_years (college_id, year, contribution_percent) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE contribution_percent = VALUES(contribution_percent)');
		$save->execute([(int)$collegeId, (int)$year, $contribution]);
	}
	return true;
}

function insert_program(array $r): bool
{
	$pdo = db();
	$name = trim((string)($r['name'] ?? ''));
	$shortCode = trim((string)($r['college_short_code'] ?? ''));
	$year = filter_var($r['year'] ?? null, FILTER_VALIDATE_INT);
	if ($name === '' || $shortCode === '' || $year === false || $year < 1) return false;
	$college = $pdo->prepare('SELECT college_id FROM colleges WHERE short_code = ? LIMIT 1');
	$college->execute([$shortCode]);
	$collegeId = $college->fetchColumn();
	if (!$collegeId) return false;
	$find = $pdo->prepare('SELECT program_id FROM programs WHERE college_id = ? AND name = ? LIMIT 1');
	$find->execute([(int)$collegeId, $name]);
	$programId = $find->fetchColumn();
	if (!$programId) {
		$insert = $pdo->prepare('INSERT INTO programs (college_id, name) VALUES (?, ?)');
		$insert->execute([(int)$collegeId, $name]);
		$programId = (int)$pdo->lastInsertId();
	}
	$rank = is_numeric($r['national_rank'] ?? null) ? (int)$r['national_rank'] : null;
	$score = is_numeric($r['score'] ?? null) ? (float)$r['score'] : null;
	$movement = trim((string)($r['movement'] ?? '')) ?: null;
	$save = $pdo->prepare('INSERT INTO program_rankings (program_id, year, national_rank, score, movement) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE national_rank = VALUES(national_rank), score = VALUES(score), movement = VALUES(movement)');
	return $save->execute([(int)$programId, (int)$year, $rank, $score, $movement]);
}

function insert_accreditation(array $r): bool
{
	$pdo = db();
	$programName = trim((string)($r['program_name'] ?? ''));
	$criterionName = trim((string)($r['criterion'] ?? ''));
	$bodyName = trim((string)($r['accrediting_body'] ?? 'AUN-QA')) ?: 'AUN-QA';
	if ($programName === '' || $criterionName === '') return false;
	$programSql = 'SELECT programs.program_id FROM programs';
	$programParams = [];
	$collegeCode = trim((string)($r['college_short_code'] ?? ''));
	if ($collegeCode !== '') {
		$programSql .= ' INNER JOIN colleges ON colleges.college_id = programs.college_id WHERE colleges.short_code = ? AND LOWER(programs.name) = LOWER(?)';
		$programParams = [$collegeCode, $programName];
	} else {
		$programSql .= ' WHERE LOWER(programs.name) = LOWER(?)';
		$programParams = [$programName];
	}
	$programSql .= ' ORDER BY programs.program_id LIMIT 2';
	$programQuery = $pdo->prepare($programSql);
	$programQuery->execute($programParams);
	$programIds = $programQuery->fetchAll(PDO::FETCH_COLUMN);
	if (count($programIds) !== 1) return false;
	$bodyId = insert_reference_name($pdo, 'accrediting_bodies', 'accrediting_body_id', $bodyName);
	$criterionId = insert_reference_name($pdo, 'accreditation_criteria', 'criterion_id', $criterionName);
	if (!$bodyId || !$criterionId) return false;
	$year = filter_var($r['year'] ?? null, FILTER_VALIDATE_INT);
	$year = $year !== false && $year > 0 ? (int)$year : null;
	$assessmentDate = trim((string)($r['assessment_date'] ?? '')) ?: null;
	$score = trim((string)($r['score'] ?? ''));
	$numericScore = is_numeric($score) ? (float)$score : null;
	$find = $pdo->prepare('SELECT accreditation_id FROM accreditations WHERE program_id = ? AND accrediting_body_id = ? AND criterion_id = ? AND year <=> ? AND assessment_date <=> ? ORDER BY accreditation_id LIMIT 1');
	$find->execute([(int)$programIds[0], $bodyId, $criterionId, $year, $assessmentDate]);
	$accreditationId = $find->fetchColumn();
	if ($accreditationId) {
		$save = $pdo->prepare('UPDATE accreditations SET score = ?, numeric_score = ? WHERE accreditation_id = ?');
		return $save->execute([$score !== '' ? $score : null, $numericScore, (int)$accreditationId]);
	}
	$save = $pdo->prepare('INSERT INTO accreditations (program_id, accrediting_body_id, criterion_id, year, assessment_date, score, numeric_score) VALUES (?, ?, ?, ?, ?, ?, ?)');
	return $save->execute([(int)$programIds[0], $bodyId, $criterionId, $year, $assessmentDate, $score !== '' ? $score : null, $numericScore]);
}