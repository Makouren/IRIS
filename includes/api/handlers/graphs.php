<?php

        if ($_SERVER['REQUEST_METHOD']==='GET') {
            if($id!==null){$g=load_saved_graph($pdo,(int)$id);if(!$g)bad('Graph not found',404);echo json_encode($g);exit;}
            $query = $recordId !== null
                ? $pdo->prepare('SELECT graph_id FROM saved_graphs WHERE record_id = ? ORDER BY created_at DESC')
                : $pdo->query('SELECT graph_id FROM saved_graphs ORDER BY created_at DESC');
            if ($recordId !== null) $query->execute([(int)$recordId]);
            $rows = [];
            foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $graphId) {
                $graph = load_saved_graph($pdo, (int)$graphId);
                if ($graph) $rows[] = $graph;
            }
            echo json_encode($rows);exit;
        }
        if ($_SERVER['REQUEST_METHOD']==='POST' && $action==='bulk-delete'){
            ensure_admin_for_mutation();
            $d=json_input();$ids=array_values(array_unique(array_filter(array_map('intval', $d['ids']??[]), static fn(int $value): bool => $value > 0)));if(!$ids)bad('ids must be a non-empty array');$ph=implode(',',array_fill(0,count($ids),'?'));$q=$pdo->prepare("SELECT graph_id FROM saved_graphs WHERE graph_id IN ($ph)");$q->execute($ids);$existing=$q->fetchAll(PDO::FETCH_COLUMN);if($existing){$ph2=implode(',',array_fill(0,count($existing),'?'));$pdo->prepare("DELETE FROM saved_graphs WHERE graph_id IN ($ph2)")->execute($existing);} $set=array_fill_keys(array_map('intval', $existing),true);$results=[];foreach($ids as $x)$results[]=['id'=>$x,'success'=>isset($set[$x]),'error'=>isset($set[$x])?null:'Graph not found'];echo json_encode(['results'=>$results,'successCount'=>count($existing),'failureCount'=>count($ids)-count($existing)]);exit;}
        if ($_SERVER['REQUEST_METHOD']==='POST' && ($action==='publish' || $action==='unpublish' || $action==='toggle-publish')) {
            ensure_admin_for_mutation();
            $d=json_input();
            $published = isset($d['published']) ? (bool)$d['published'] : ($action === 'publish');
            if($id===null)bad('Graph id is required');
            $q=$pdo->prepare('SELECT graph_id FROM saved_graphs WHERE graph_id=?');$q->execute([(int)$id]);if(!$q->fetch())bad('Graph not found',404);
            $pdo->prepare('UPDATE saved_graphs SET is_published = ? WHERE graph_id = ?')->execute([$published ? 1 : 0,(int)$id]);
            echo json_encode(['success'=>true,'id'=>$id,'is_published'=>$published,'message'=>$published ? 'Graph published to the Observatory.' : 'Graph removed from the Observatory.']);exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'export') {
            ensure_admin_for_mutation();
            $d = json_input();
            $ids = array_values(array_unique(array_filter($d['snapshot_ids'] ?? [])));
            $mode = $d['mode'] ?? '';
            if (!$ids) bad('snapshot_ids must contain at least one graph id');
            if (!in_array($mode, ['database', 'script'], true)) bad('mode must be database or script');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $query = $pdo->prepare("SELECT graph_id FROM saved_graphs WHERE graph_id IN ($placeholders) ORDER BY created_at DESC");
            $query->execute(array_map('intval', $ids));
            $graphs = [];
            foreach ($query->fetchAll(PDO::FETCH_COLUMN) as $graphId) {
                $graph = load_saved_graph($pdo, (int)$graphId);
                if ($graph) $graphs[] = $graph;
            }
            if (!$graphs) bad('No saved graphs found', 404);
            if ($mode === 'script') {
                $out = '';
                foreach ($graphs as $graph) {
                    $labels = json_col($graph['labels'], []);
                    $values = json_col($graph['values_data'], []);
                    $chartData = json_col($graph['chart_data'] ?? null, []);
                    $nestedGroups = $chartData['irisConfig']['nestedGroups'] ?? [];
                    $nested = strtolower((string)($graph['chart_type'] ?? '')) === 'nestedpie' && is_array($nestedGroups) && count($nestedGroups) > 0;
                    $out .= 'Title: '.($graph['title'] ?? 'Saved Chart')."\nChart Type: ".strtoupper($graph['chart_type'] ?? 'bar')."\nSource Record ID: ".$graph['record_id']."\n\n".($nested ? 'Group: Category: Value' : 'Category: Value')."\n";
                    if ($nested) {
                        foreach ($nestedGroups as $group) {
                            foreach (($group['children'] ?? []) as $child) {
                                $out .= json_encode((string)($group['label'] ?? ''), JSON_UNESCAPED_UNICODE).': '.json_encode((string)($child['label'] ?? ''), JSON_UNESCAPED_UNICODE).': '.($child['rawValue'] ?? $child['value'] ?? '')."\n";
                            }
                        }
                    } else {
                        foreach ($labels as $index => $label) {
                            $out .= json_encode((string)($label ?: 'Item '.($index + 1)), JSON_UNESCAPED_UNICODE).': '.($values[$index] ?? '')."\n";
                        }
                    }
                    $out .= "\n\n";
                }
                header('Content-Type:text/plain; charset=utf-8');
                header('Content-Disposition: attachment; filename="iris_saved_graphs_'.time().'.txt"');
                header('X-Export-Count: '.count($graphs));
                echo $out;
                exit;
            }
            $new = [];
            // Keep graph rows and their related series/points atomic as one export operation.
            $pdo->beginTransaction();
            try {
                foreach ($graphs as $graph) {
                    $stmt = $pdo->prepare('INSERT INTO saved_graphs (record_id, title, chart_type, orientation, value_axis_reversed, value_axis_min, value_axis_max, rank_semantic, rank_value_min, rank_value_max, is_published, chart_options) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)');
                    $stmt->execute([(int)$graph['record_id'], $graph['title'], $graph['chart_type'], $graph['orientation'], (int)$graph['value_axis_reversed'], $graph['value_axis_min'], $graph['value_axis_max'], !empty($graph['rank_semantic']) ? 'rank' : null, $graph['rank_value_min'], $graph['rank_value_max'], json_encode($graph['chart_data'] ?? [])]);
                    $newId = (int)$pdo->lastInsertId();
                    save_graph_relations($pdo, $newId, $graph);
                    $new[] = $newId;
                }
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }
            echo json_encode(['mode' => 'database', 'count' => count($new), 'exported_ids' => $new]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
            ensure_admin_for_mutation();
            if ($id === null || $id === '') bad('Graph id is required.');
            $data = json_input();
            if (!array_key_exists('scope', $data) || !is_string($data['scope'])) bad('Scope must be text.');
            $scope = trim($data['scope']);
            $scopeLength = function_exists('mb_strlen') ? mb_strlen($scope, 'UTF-8') : strlen($scope);
            if ($scopeLength > 150) bad('Scope must not exceed 150 characters.');
            $pdo->prepare('UPDATE saved_graphs SET scope = ?, updated_at = NOW() WHERE graph_id = ?')->execute([$scope !== '' ? $scope : null, (int)$id]);
            $saved = load_saved_graph($pdo, (int)$id);
            if (!$saved) bad('Graph not found', 404);
            echo json_encode($saved);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            ensure_admin_for_mutation();
            if (($_SESSION['role'] ?? '') !== 'super_admin') bad('Forbidden', 403);
            if ($id === null || $id === '') bad('Graph id is required.');
            $data = json_input();
            $existingQuery = $pdo->prepare('SELECT graph_id AS id, record_id FROM saved_graphs WHERE graph_id = ?');
            $existingQuery->execute([(int)$id]);
            $existing = $existingQuery->fetch(PDO::FETCH_ASSOC);
            if (!$existing) bad('Graph not found', 404);
            $recordId = $data['record_id'] ?? $data['recordId'] ?? null;
            if ($recordId !== null && (string)$recordId !== (string)$existing['record_id']) bad('A saved graph cannot be moved to another record.', 409);

            $colors = require_graph_colors($data);
            $chartData = $data['chart_data'] ?? $data['chartData'] ?? null;
            $sets = [
                'title = ?',
                'chart_type = ?',
                'orientation = ?',
                'value_axis_reversed = ?',
                'value_axis_min = ?',
                'value_axis_max = ?',
                'rank_semantic = ?',
                'rank_value_min = ?',
                'rank_value_max = ?',
                'chart_options = ?'
            ];
            $values = [
                $data['title'] ?? 'Saved Chart',
                normalize_graph_type($data['chart_type'] ?? $data['chartType'] ?? 'bar'),
                $data['orientation'] ?? 'vertical',
                !empty($data['valueAxisReversed']) ? 1 : 0,
                is_numeric($data['valueAxisMin'] ?? null) ? $data['valueAxisMin'] : null,
                is_numeric($data['valueAxisMax'] ?? null) ? $data['valueAxisMax'] : null,
                !empty($data['rankSemantic']) ? 'rank' : null,
                is_numeric($data['rankValueMin'] ?? null) ? $data['rankValueMin'] : null,
                is_numeric($data['rankValueMax'] ?? null) ? $data['rankValueMax'] : null,
                json_encode($chartData ?? [])
            ];
            if (array_key_exists('is_published', $data)) {
                $sets[] = 'is_published = ?';
                $values[] = (int)(bool)$data['is_published'];
            }
            $sets[] = 'updated_at = NOW()';
            $values[] = (int)$id;
            $pdo->beginTransaction();
            try {
                $update = $pdo->prepare('UPDATE saved_graphs SET ' . implode(', ', $sets) . ' WHERE graph_id = ?');
                $update->execute($values);
                save_graph_relations($pdo, (int)$id, $data);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }
            $saved = load_saved_graph($pdo, (int)$id);
            if (!$saved) bad('Graph not found', 404);
            echo json_encode(output_graph($saved));
            exit;
        }
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            ensure_admin_for_mutation();
            $d=json_input();
            $colors = require_graph_colors($d);
            $rid=$d['record_id']??$d['recordId']??null;
            if(!$rid)bad('record_id is required');
            $rid = filter_var($rid, FILTER_VALIDATE_INT);
            if (!$rid || $rid < 1) bad('record_id must be a valid record id.');
            $q=$pdo->prepare('SELECT record_id FROM records WHERE record_id=?');
            $q->execute([(int)$rid]);
            if(!$q->fetch())bad('Record not found',404);
            $published = isset($d['is_published']) ? (int)(bool)$d['is_published'] : 0;
            $chartData = $d['chart_data'] ?? $d['chartData'] ?? null;
            $pdo->beginTransaction();
            try {
                $stmt=$pdo->prepare('INSERT INTO saved_graphs (record_id, title, chart_type, orientation, value_axis_reversed, value_axis_min, value_axis_max, rank_semantic, rank_value_min, rank_value_max, chart_options, is_published) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([(int)$rid,$d['title']??'Saved Chart',normalize_graph_type($d['chart_type']??$d['chartType']??'bar'),$d['orientation']??'vertical',!empty($d['valueAxisReversed'])?1:0,is_numeric($d['valueAxisMin']??null)?$d['valueAxisMin']:null,is_numeric($d['valueAxisMax']??null)?$d['valueAxisMax']:null,!empty($d['rankSemantic'])?'rank':null,is_numeric($d['rankValueMin']??null)?$d['rankValueMin']:null,is_numeric($d['rankValueMax']??null)?$d['rankValueMax']:null,json_encode($chartData ?? []),$published]);
                $gid=(int)$pdo->lastInsertId();
                save_graph_relations($pdo, $gid, $d);
                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }
            echo json_encode(load_saved_graph($pdo, $gid));
            exit;
        }
        if ($_SERVER['REQUEST_METHOD']==='DELETE'){ ensure_admin_for_mutation(); if($id===null)bad('Graph id is required');$g=load_saved_graph($pdo,(int)$id);if(!$g)bad('Graph not found',404);$pdo->prepare('DELETE FROM saved_graphs WHERE graph_id=?')->execute([(int)$id]);echo json_encode(['message'=>'Graph deleted','graph'=>$g]);exit;}
