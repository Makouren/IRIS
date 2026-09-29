<?php
require_once __DIR__ . '/../includes/functions.php';
require_auth();
header('Content-Type: application/json; charset=utf-8');

$pdo = db();

function ensure_admin_for_mutation(): void {
    if (($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden']);
        exit;
    }
}
$resource = $_GET['resource'] ?? '';
$action = $_GET['action'] ?? '';
$id = $_GET['id'] ?? null;
$recordId = $_GET['record_id'] ?? null;

function json_input(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}
function json_col($v, $fallback = null) {
    if ($v === null || $v === '') return $fallback;
    if (is_string($v)) {
        $decoded = json_decode($v, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $fallback;
    }
    return $v;
}
function output_record(array $r): array {
    $r['extractedData'] = json_col($r['extractedData'], []);
    $r['graphDrafts'] = json_col($r['graphDrafts'], []);
    $r['metadata'] = json_col($r['metadata'], []);
    return $r;
}
function output_graph(array $g): array {
    $g['labels'] = json_col($g['labels'], []);
    $g['values_data'] = json_col($g['values_data'], []);
    $g['chart_data'] = json_col($g['chart_data'] ?? null, json_col($g['chartData'] ?? null, null));
    $g['is_published'] = isset($g['is_published']) ? (bool)$g['is_published'] : false;
    return $g;
}
function bad(string $message, int $status=400): never {
    http_response_code($status); echo json_encode(['error'=>$message]); exit;
}

try {
    if ($resource === 'summary_cards') {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $rows = $pdo->query('SELECT * FROM summary_cards ORDER BY display_order ASC, created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(array_map(static function (array $card): array {
                $card['display_precision'] = (int)($card['display_precision'] ?? 2);
                $card['display_order'] = (int)($card['display_order'] ?? 0);
                $card['is_published'] = (bool)($card['is_published'] ?? false);
                return $card;
            }, $rows));
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            ensure_admin_for_mutation();
            $data = json_input();
            $id = $data['id'] ?? ('summary_card_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)));
            $title = trim((string)($data['title'] ?? ''));
            if ($title === '') bad('title is required');

            $displayPrecision = isset($data['display_precision']) ? (int)$data['display_precision'] : 2;
            $displayPrecision = max(0, min(2, $displayPrecision));
            $published = !empty($data['is_published']) || (!array_key_exists('is_published', $data) && !empty($data['published']));

            $stmt = $pdo->prepare('INSERT INTO summary_cards (id, title, main_value, main_label, year_date, secondary_label, secondary_value, description, display_order, display_precision, is_published) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $id,
                $title,
                (string)($data['main_value'] ?? ''),
                (string)($data['main_label'] ?? ''),
                (string)($data['year_date'] ?? ''),
                (string)($data['secondary_label'] ?? ''),
                (string)($data['secondary_value'] ?? ''),
                (string)($data['description'] ?? ''),
                isset($data['display_order']) ? (int)$data['display_order'] : 0,
                $displayPrecision,
                $published ? 1 : 0
            ]);

            $q = $pdo->prepare('SELECT * FROM summary_cards WHERE id=?');
            $q->execute([$id]);
            echo json_encode($q->fetch(PDO::FETCH_ASSOC));
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'PATCH') {
            ensure_admin_for_mutation();
            if ($id === null) bad('Summary card id is required');
            $data = json_input();
            $allowed = ['title','main_value','main_label','year_date','secondary_label','secondary_value','description','display_order','display_precision','is_published'];
            $sets = [];
            $values = [];
            foreach ($allowed as $field) {
                if (!array_key_exists($field, $data)) continue;
                if ($field === 'display_order') {
                    $sets[] = 'display_order = ?';
                    $values[] = (int)$data[$field];
                    continue;
                }
                if ($field === 'display_precision') {
                    $precision = max(0, min(2, (int)$data[$field]));
                    $sets[] = 'display_precision = ?';
                    $values[] = $precision;
                    continue;
                }
                if ($field === 'is_published') {
                    $sets[] = 'is_published = ?';
                    $values[] = !empty($data[$field]) ? 1 : 0;
                    continue;
                }
                $sets[] = $field . ' = ?';
                $values[] = (string)$data[$field];
            }
            if (!$sets) bad('No fields to update');
            $values[] = $id;
            $pdo->prepare('UPDATE summary_cards SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($values);
            $q = $pdo->prepare('SELECT * FROM summary_cards WHERE id=?');
            $q->execute([$id]);
            echo json_encode($q->fetch(PDO::FETCH_ASSOC));
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            ensure_admin_for_mutation();
            if ($id === null) bad('Summary card id is required');
            $pdo->prepare('DELETE FROM summary_cards WHERE id=?')->execute([$id]);
            echo json_encode(['success' => true, 'deleted_id' => $id]);
            exit;
        }
    }

    if ($resource === 'records') {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            if ($id !== null) {
                $q = $pdo->prepare('SELECT * FROM records WHERE id=?'); $q->execute([$id]);
                $r = $q->fetch(PDO::FETCH_ASSOC); if (!$r) bad('Record not found',404);
                echo json_encode(output_record($r)); exit;
            }
            $rows = $pdo->query('SELECT * FROM records ORDER BY scannedAt DESC, updatedAt DESC')->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(array_map('output_record',$rows)); exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk-approve') {
            ensure_admin_for_mutation();
            $data=json_input();
            $ids=array_values(array_unique(array_filter($data['ids']??[], fn($x)=>is_scalar($x)&&$x!=='')));
            if (!$ids) bad('ids must be a non-empty array');
            $ph=implode(',',array_fill(0,count($ids),'?'));
            $q=$pdo->prepare("SELECT id FROM records WHERE id IN ($ph)"); $q->execute($ids);
            $existing=$q->fetchAll(PDO::FETCH_COLUMN);
            if ($existing) {
                $ph2=implode(',',array_fill(0,count($existing),'?'));
                $pdo->prepare("UPDATE records SET status='Approved', updatedAt=NOW() WHERE id IN ($ph2)")->execute($existing);
            }
            $set=array_fill_keys($existing,true); $results=[];
            foreach($ids as $x) $results[]=['id'=>$x,'success'=>isset($set[$x]),'error'=>isset($set[$x])?null:'Record not found'];
            echo json_encode(['results'=>$results,'successCount'=>count($existing),'failureCount'=>count($ids)-count($existing)]); exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['bulk-publish', 'bulk-unpublish'], true)) {
            ensure_admin_for_mutation();
            $data = json_input();
            $ids = array_values(array_unique(array_filter($data['ids'] ?? [], fn($x) => is_scalar($x) && $x !== '')));
            if (!$ids) bad('ids must be a non-empty array');
            $published = $action === 'bulk-publish';
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->beginTransaction();
            try {
                $query = $pdo->prepare("SELECT id FROM records WHERE id IN ($placeholders) FOR UPDATE");
                $query->execute($ids);
                $existing = $query->fetchAll(PDO::FETCH_COLUMN);
                $graphCount = 0;
                if ($existing) {
                    $recordPlaceholders = implode(',', array_fill(0, count($existing), '?'));
                    $pdo->prepare('UPDATE records SET status=?, updatedAt=NOW() WHERE id IN ('.$recordPlaceholders.')')->execute(array_merge([$published ? 'Approved' : 'Pending Review'], $existing));
                    $graphs = $pdo->prepare('UPDATE saved_graphs SET is_published=? WHERE record_id IN ('.$recordPlaceholders.') AND is_published<>?');
                    $graphs->execute(array_merge([$published ? 1 : 0], $existing, [$published ? 1 : 0]));
                    $graphCount = $graphs->rowCount();
                }
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            $existingSet = array_fill_keys($existing, true);
            $results = [];
            foreach ($ids as $recordId) $results[] = ['id'=>$recordId,'success'=>isset($existingSet[$recordId]),'error'=>isset($existingSet[$recordId]) ? null : 'Record not found'];
            echo json_encode(['results'=>$results,'successCount'=>count($existing),'failureCount'=>count($ids)-count($existing),'published_graph_count'=>$graphCount]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'unpublish') {
            ensure_admin_for_mutation();
            if ($id === null) bad('Record id is required');
            $pdo->beginTransaction();
            try {
                $q = $pdo->prepare('SELECT id FROM records WHERE id=? FOR UPDATE');
                $q->execute([$id]);
                if (!$q->fetch()) {
                    $pdo->rollBack();
                    bad('Record not found', 404);
                }
                $pdo->prepare("UPDATE records SET status='Pending Review', updatedAt=NOW() WHERE id=?")->execute([$id]);
                $graphs = $pdo->prepare('UPDATE saved_graphs SET is_published=0 WHERE record_id=? AND is_published=1');
                $graphs->execute([$id]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
            $q = $pdo->prepare('SELECT * FROM records WHERE id=?');
            $q->execute([$id]);
            echo json_encode(['success'=>true,'record'=>output_record($q->fetch(PDO::FETCH_ASSOC)),'unpublished_graph_count'=>$graphs->rowCount()]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'bulk-delete') {
            ensure_admin_for_mutation();
            $data=json_input(); $ids=array_values(array_unique(array_filter($data['ids']??[], fn($x)=>is_scalar($x)&&$x!=='')));
            if (!$ids) bad('ids must be a non-empty array');
            $ph=implode(',',array_fill(0,count($ids),'?')); $q=$pdo->prepare("SELECT id FROM records WHERE id IN ($ph)");$q->execute($ids);$existing=$q->fetchAll(PDO::FETCH_COLUMN);
            if ($existing) { $ph2=implode(',',array_fill(0,count($existing),'?')); $pdo->prepare("DELETE FROM saved_graphs WHERE record_id IN ($ph2)")->execute($existing); $q=$pdo->prepare("DELETE FROM records WHERE id IN ($ph2)");$q->execute($existing); }
            $set=array_fill_keys($existing,true); $results=[]; foreach($ids as $x)$results[]=['id'=>$x,'success'=>isset($set[$x]),'error'=>isset($set[$x])?null:'Record not found'];
            echo json_encode(['results'=>$results,'successCount'=>count($existing),'failureCount'=>count($ids)-count($existing)]); exit;
        }
        $data=json_input();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            ensure_admin_for_mutation();
            $id2=$data['id']??('rec_'.date('YmdHis').'_'.bin2hex(random_bytes(3)));
            $stmt=$pdo->prepare('INSERT INTO records (id,fileName,fileType,fileSize,scannedAt,status,docType,rawText,extractedData,graphDrafts,adminNotes,metadata,updatedAt) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$id2,$data['fileName']??$data['name']??'Untitled',$data['fileType']??$data['type']??'unknown',(int)($data['fileSize']??$data['size']??0),date('Y-m-d H:i:s',strtotime($data['scannedAt']??'now')),$data['status']??'Pending Review',$data['docType']??'General Institutional Data',$data['rawText']??'',json_encode($data['extractedData']??$data['sheetsData']??[]),json_encode($data['graphDrafts']??[]),$data['adminNotes']??'',json_encode($data['metadata']??[]),null]);
            $q=$pdo->prepare('SELECT * FROM records WHERE id=?');$q->execute([$id2]);echo json_encode(output_record($q->fetch(PDO::FETCH_ASSOC)));exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            ensure_admin_for_mutation();
            if ($id===null) bad('Record id is required'); $data=json_input(); $allowed=['fileName','fileType','fileSize','status','docType','rawText','adminNotes'];$sets=[];$vals=[];
            if (array_key_exists('status',$data) && !in_array($data['status'], ['Pending Review','Approved','Needs Revision'], true)) bad('Invalid record status');
            foreach($allowed as $f) if(array_key_exists($f,$data)){ $sets[]="$f=?";$vals[]=$data[$f]; }
            foreach(['extractedData','graphDrafts','metadata'] as $f) if(array_key_exists($f,$data)){ $col=$f;$sets[]="$col=?";$vals[]=json_encode($data[$f]); }
            if(array_key_exists('scannedAt',$data)){ $sets[]='scannedAt=?';$vals[]=date('Y-m-d H:i:s',strtotime($data['scannedAt'])); }
            if(!$sets) bad('No fields to update'); $sets[]='updatedAt=NOW()';$vals[]=$id;$stmt=$pdo->prepare('UPDATE records SET '.implode(',',$sets).' WHERE id=?');$stmt->execute($vals);if(!$stmt->rowCount()){$q=$pdo->prepare('SELECT id FROM records WHERE id=?');$q->execute([$id]);if(!$q->fetch())bad('Record not found',404);}
            $q=$pdo->prepare('SELECT * FROM records WHERE id=?');$q->execute([$id]);echo json_encode(output_record($q->fetch(PDO::FETCH_ASSOC)));exit;
        }
        if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
            ensure_admin_for_mutation();
            if($id===null)bad('Record id is required');$q=$pdo->prepare('SELECT * FROM records WHERE id=?');$q->execute([$id]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)bad('Record not found',404);$pdo->prepare('DELETE FROM saved_graphs WHERE record_id=?')->execute([$id]);$q=$pdo->prepare('DELETE FROM records WHERE id=?');$q->execute([$id]);echo json_encode(['message'=>'Record and published graphs deleted','record'=>$r]);exit; }
    }

    if ($resource === 'graphs') {
        if ($_SERVER['REQUEST_METHOD']==='GET') {
            if($id!==null){$q=$pdo->prepare('SELECT saved_graphs.* FROM saved_graphs WHERE saved_graphs.id=?');$q->execute([$id]);$g=$q->fetch(PDO::FETCH_ASSOC);if(!$g)bad('Graph not found',404);echo json_encode(output_graph($g));exit;}
            if($recordId!==null){$q=$pdo->prepare('SELECT saved_graphs.*, records.id AS source_file_id, records.fileName AS source_file_name, records.fileType AS source_file_type FROM saved_graphs LEFT JOIN records ON records.id=saved_graphs.record_id WHERE saved_graphs.record_id=? ORDER BY saved_graphs.created_at DESC');$q->execute([$recordId]);echo json_encode(array_map('output_graph',$q->fetchAll(PDO::FETCH_ASSOC)));exit;}
            $rows=$pdo->query('SELECT saved_graphs.*, records.id AS source_file_id, records.fileName AS source_file_name, records.fileType AS source_file_type FROM saved_graphs LEFT JOIN records ON records.id=saved_graphs.record_id ORDER BY saved_graphs.created_at DESC')->fetchAll(PDO::FETCH_ASSOC);echo json_encode(array_map('output_graph',$rows));exit;
        }
        if ($_SERVER['REQUEST_METHOD']==='POST' && $action==='bulk-delete'){
            ensure_admin_for_mutation();
            $d=json_input();$ids=array_values(array_unique(array_filter($d['ids']??[])));if(!$ids)bad('ids must be a non-empty array');$ph=implode(',',array_fill(0,count($ids),'?'));$q=$pdo->prepare("SELECT id FROM saved_graphs WHERE id IN ($ph)");$q->execute($ids);$existing=$q->fetchAll(PDO::FETCH_COLUMN);if($existing){$ph2=implode(',',array_fill(0,count($existing),'?'));$pdo->prepare("DELETE FROM saved_graphs WHERE id IN ($ph2)")->execute($existing);} $set=array_fill_keys($existing,true);$results=[];foreach($ids as $x)$results[]=['id'=>$x,'success'=>isset($set[$x]),'error'=>isset($set[$x])?null:'Graph not found'];echo json_encode(['results'=>$results,'successCount'=>count($existing),'failureCount'=>count($ids)-count($existing)]);exit;}
        if ($_SERVER['REQUEST_METHOD']==='POST' && ($action==='publish' || $action==='unpublish' || $action==='toggle-publish')) {
            ensure_admin_for_mutation();
            $d=json_input();
            $published = isset($d['published']) ? (bool)$d['published'] : ($action === 'publish');
            if($id===null)bad('Graph id is required');
            $q=$pdo->prepare('SELECT id FROM saved_graphs WHERE id=?');$q->execute([$id]);if(!$q->fetch())bad('Graph not found',404);
            $pdo->prepare('UPDATE saved_graphs SET is_published = ? WHERE id = ?')->execute([$published ? 1 : 0,$id]);
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
            $query = $pdo->prepare("SELECT saved_graphs.*, records.fileName AS source_file_name FROM saved_graphs LEFT JOIN records ON records.id=saved_graphs.record_id WHERE saved_graphs.id IN ($placeholders) ORDER BY saved_graphs.created_at DESC");
            $query->execute($ids);
            $graphs = $query->fetchAll(PDO::FETCH_ASSOC);
            if (!$graphs) bad('No saved graphs found', 404);
            if ($mode === 'script') {
                $out = '';
                foreach ($graphs as $graph) {
                    $labels = json_col($graph['labels'], []);
                    $values = json_col($graph['values_data'], []);
                    $out .= 'Title: '.($graph['title'] ?? 'Saved Chart')."\nChart Type: ".strtoupper($graph['chart_type'] ?? 'bar')."\nSource Record ID: ".$graph['record_id']."\n\nCategory: Value\n";
                    foreach ($labels as $index => $label) {
                        $out .= ($label ?: 'Item '.($index + 1)).': '.($values[$index] ?? '')."\n";
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
            foreach ($graphs as $graph) {
                $newId = 'export_'.date('YmdHis').'_'.bin2hex(random_bytes(4));
                $stmt = $pdo->prepare('INSERT INTO saved_graphs (id,record_id,title,chart_type,orientation,value_axis_reversed,value_axis_min,value_axis_max,rank_semantic,rank_value_min,rank_value_max,labels,values_data) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$newId,$graph['record_id'],$graph['title'],$graph['chart_type'],$graph['orientation'],(int)$graph['value_axis_reversed'],$graph['value_axis_min'],$graph['value_axis_max'],(int)$graph['rank_semantic'],$graph['rank_value_min'],$graph['rank_value_max'],$graph['labels'],$graph['values_data']]);
                $new[] = $newId;
            }
            echo json_encode(['mode' => 'database', 'count' => count($new), 'exported_ids' => $new]);
            exit;
        }
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            ensure_admin_for_mutation();
            $d=json_input();
            $gid=$d['id']??('graph_'.date('YmdHis').'_'.bin2hex(random_bytes(4)));
            $rid=$d['record_id']??$d['recordId']??null;
            if(!$rid)bad('record_id is required');
            $q=$pdo->prepare('SELECT id FROM records WHERE id=?');
            $q->execute([$rid]);
            if(!$q->fetch())bad('Record not found',404);
            $published = isset($d['is_published']) ? (int)(bool)$d['is_published'] : 0;
            $chartData = $d['chart_data'] ?? $d['chartData'] ?? null;
            $stmt=$pdo->prepare('INSERT INTO saved_graphs (id,record_id,title,chart_type,orientation,value_axis_reversed,value_axis_min,value_axis_max,rank_semantic,rank_value_min,rank_value_max,labels,values_data,chart_data,is_published) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([$gid,$rid,$d['title']??'Saved Chart',$d['chart_type']??$d['chartType']??'bar',$d['orientation']??'vertical',!empty($d['valueAxisReversed'])?1:0,is_numeric($d['valueAxisMin']??null)?$d['valueAxisMin']:null,is_numeric($d['valueAxisMax']??null)?$d['valueAxisMax']:null,!empty($d['rankSemantic'])?1:0,is_numeric($d['rankValueMin']??null)?$d['rankValueMin']:null,is_numeric($d['rankValueMax']??null)?$d['rankValueMax']:null,json_encode($d['labels']??[]),json_encode($d['values_data']??$d['valuesData']??$d['data']??[]),json_encode($chartData ?? []),$published]);
            $q=$pdo->prepare('SELECT * FROM saved_graphs WHERE id=?');
            $q->execute([$gid]);
            echo json_encode(output_graph($q->fetch(PDO::FETCH_ASSOC)));
            exit;
        }
        if ($_SERVER['REQUEST_METHOD']==='DELETE'){ ensure_admin_for_mutation(); if($id===null)bad('Graph id is required');$q=$pdo->prepare('SELECT * FROM saved_graphs WHERE id=?');$q->execute([$id]);$g=$q->fetch(PDO::FETCH_ASSOC);if(!$g)bad('Graph not found',404);$pdo->prepare('DELETE FROM saved_graphs WHERE id=?')->execute([$id]);echo json_encode(['message'=>'Graph deleted','graph'=>$g]);exit;}
    }
    bad('Unknown API resource',404);
} catch(Throwable $e) { error_log($e->getMessage()); bad('Server error: '.$e->getMessage(),500); }
