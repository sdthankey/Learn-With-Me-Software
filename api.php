<?php
// ════════════════════════════════════
//   api.php — Learn With Me
//   shyamthanki.com/lwm/api.php
// ════════════════════════════════════

// ── CORS & Headers ──
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

// ── Config ──
define('DB_HOST', 'sdb-j.hosting.stackcp.net');
define('DB_NAME', 'learnwithme_db-3139306365');
define('DB_USER', 'ShyamThanki');
define('DB_PASS', 'Extruder@7001');
define('ADMIN_PASS', 'Extruder@7001');
define('UPLOAD_DIR', __DIR__ . '/uploads/pdfs/');
define('UPLOAD_URL', '/lwm/uploads/pdfs/');

// ══════════════════════════════════════════════
//  php://input SIRF EK BAAR readable hota hai.
//  Isliye yahan TOP PE ek baar padhte hain.
// ══════════════════════════════════════════════
$BODY = [];
$raw  = file_get_contents('php://input');
if ($raw !== false && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $BODY = $decoded;
    }
}

// action — GET param > POST param > JSON body
$action = isset($_GET['action'])  ? trim($_GET['action'])  :
         (isset($_POST['action']) ? trim($_POST['action']) :
         (isset($BODY['action'])  ? trim($BODY['action'])  : ''));

// ── DB ──
function getDB() {
    static $pdo = null;
    if ($pdo) return $pdo;
    try {
        $pdo = new PDO(
            "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
            DB_USER, DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    } catch (PDOException $e) {
        respond(['error' => 'DB error: '.$e->getMessage()], 500);
    }
    return $pdo;
}

function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function uid() {
    return substr(base_convert(microtime(true)*1000, 10, 36), 0, 8)
         . substr(base_convert(rand(0, PHP_INT_MAX), 10, 36), 0, 5);
}

// ── Admin check — $BODY global use karta hai ──
function isAdmin() {
    global $BODY;
    // JSON body se (apiPost), ya FormData se (save_unit)
    $pass = isset($BODY['adminPass'])  ? $BODY['adminPass']  :
           (isset($_POST['adminPass']) ? $_POST['adminPass'] :
           (isset($_GET['adminPass'])  ? $_GET['adminPass']  : ''));
    return trim($pass) === ADMIN_PASS;
}

// ══════════════════════════════════════════════
//   ROUTER
// ══════════════════════════════════════════════
switch ($action) {

    // ── Admin Login ──
    case 'check_admin':
        global $BODY;
        // Frontend dono 'password' aur 'adminPass' bhejta hai — dono check karo
        $pass = '';
        if (isset($BODY['password']))  $pass = $BODY['password'];
        if ($pass === '' && isset($BODY['adminPass'])) $pass = $BODY['adminPass'];
        if (trim($pass) === ADMIN_PASS) {
            respond(['success' => true]);
        } else {
            respond(['error' => 'Wrong password'], 401);
        }

    // ── Stats ──
    case 'get_stats':
        $db = getDB();
        respond([
            'topics'   => (int)$db->query("SELECT COUNT(*) FROM topics")->fetchColumn(),
            'units'    => (int)$db->query("SELECT COUNT(*) FROM units")->fetchColumn(),
            'comments' => (int)$db->query("SELECT COUNT(*) FROM comments")->fetchColumn(),
        ]);

    // ── Topics ──
    case 'get_topics':
        $db = getDB();
        $topics = $db->query("SELECT * FROM topics ORDER BY created_at ASC")->fetchAll();
        foreach ($topics as &$t) {
            $s = $db->prepare("SELECT COUNT(*) FROM units WHERE topic_id=?");
            $s->execute([$t['id']]);
            $t['unit_count'] = (int)$s->fetchColumn();
        }
        respond($topics);

    case 'save_topic':
        if (!isAdmin()) respond(['error' => 'Unauthorized'], 403);
        global $BODY;
        $id   = trim($BODY['id']   ?? '');
        $name = trim($BODY['name'] ?? '');
        $desc = trim($BODY['desc'] ?? '');
        $icon = trim($BODY['icon'] ?? '📖');
        if (!$name) respond(['error' => 'Name required'], 400);
        $db = getDB();
        if ($id) {
            $db->prepare("UPDATE topics SET name=?,description=?,icon=? WHERE id=?")->execute([$name,$desc,$icon,$id]);
            respond(['success'=>true,'id'=>$id]);
        } else {
            $newId = uid();
            $db->prepare("INSERT INTO topics (id,name,description,icon) VALUES (?,?,?,?)")->execute([$newId,$name,$desc,$icon]);
            respond(['success'=>true,'id'=>$newId]);
        }

    case 'delete_topic':
        if (!isAdmin()) respond(['error' => 'Unauthorized'], 403);
        global $BODY;
        $id = trim($BODY['id'] ?? '');
        if (!$id) respond(['error'=>'ID required'], 400);
        $db = getDB();
        $us = $db->prepare("SELECT pdf_filename FROM units WHERE topic_id=?");
        $us->execute([$id]);
        foreach ($us->fetchAll() as $u) {
            if ($u['pdf_filename']) @unlink(UPLOAD_DIR.$u['pdf_filename']);
        }
        $db->prepare("DELETE FROM topics WHERE id=?")->execute([$id]);
        respond(['success'=>true]);

    // ── Units ──
    case 'get_units':
        $topicId = trim($_GET['topic_id'] ?? '');
        if (!$topicId) respond(['error'=>'topic_id required'], 400);
        $db = getDB();
        $s = $db->prepare("SELECT * FROM units WHERE topic_id=? ORDER BY num ASC");
        $s->execute([$topicId]);
        $units = $s->fetchAll();
        foreach ($units as &$u) {
            $u['pdf_url'] = $u['pdf_filename'] ? UPLOAD_URL.rawurlencode($u['pdf_filename']) : null;
        }
        respond($units);

    case 'save_unit':
        // FormData (multipart) — $_POST use karo, $BODY nahi
        if (!isAdmin()) respond(['error' => 'Unauthorized'], 403);
        $id      = trim($_POST['id']       ?? '');
        $topicId = trim($_POST['topic_id'] ?? '');
        $title   = trim($_POST['title']    ?? '');
        $dateVal = trim($_POST['date_val'] ?? '') ?: null;
        $timeVal = trim($_POST['time_val'] ?? '') ?: null;
        $photo   = trim($_POST['photo']    ?? '') ?: null;
        $summary = trim($_POST['summary']  ?? '') ?: null;
        if (!$title || !$topicId) respond(['error'=>'title and topic_id required'], 400);
        $db = getDB();

        $pdfFilename = null;
        if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] === UPLOAD_ERR_OK) {
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            $origName = basename($_FILES['pdf']['name']);
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            if ($ext !== 'pdf') respond(['error'=>'Only PDF allowed'], 400);
            $pdfFilename = uid().'_'.preg_replace('/[^a-zA-Z0-9._-]/','_',$origName);
            move_uploaded_file($_FILES['pdf']['tmp_name'], UPLOAD_DIR.$pdfFilename);
        }

        if ($id) {
            if ($pdfFilename) {
                $old = $db->prepare("SELECT pdf_filename FROM units WHERE id=?");
                $old->execute([$id]);
                $oldF = $old->fetchColumn();
                if ($oldF) @unlink(UPLOAD_DIR.$oldF);
                $db->prepare("UPDATE units SET title=?,date_val=?,time_val=?,photo=?,summary=?,pdf_filename=? WHERE id=?")
                   ->execute([$title,$dateVal,$timeVal,$photo,$summary,$pdfFilename,$id]);
            } else {
                $db->prepare("UPDATE units SET title=?,date_val=?,time_val=?,photo=?,summary=? WHERE id=?")
                   ->execute([$title,$dateVal,$timeVal,$photo,$summary,$id]);
            }
            respond(['success'=>true,'id'=>$id]);
        } else {
            $cs = $db->prepare("SELECT COUNT(*) FROM units WHERE topic_id=?");
            $cs->execute([$topicId]);
            $num = (int)$cs->fetchColumn() + 1;
            $newId = uid();
            $db->prepare("INSERT INTO units (id,topic_id,num,title,date_val,time_val,photo,summary,pdf_filename) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute([$newId,$topicId,$num,$title,$dateVal,$timeVal,$photo,$summary,$pdfFilename]);
            respond(['success'=>true,'id'=>$newId]);
        }

    case 'delete_unit':
        if (!isAdmin()) respond(['error' => 'Unauthorized'], 403);
        global $BODY;
        $id      = trim($BODY['id']       ?? '');
        $topicId = trim($BODY['topic_id'] ?? '');
        if (!$id) respond(['error'=>'ID required'], 400);
        $db = getDB();
        $fs = $db->prepare("SELECT pdf_filename FROM units WHERE id=?");
        $fs->execute([$id]);
        $file = $fs->fetchColumn();
        if ($file) @unlink(UPLOAD_DIR.$file);
        $db->prepare("DELETE FROM units WHERE id=?")->execute([$id]);
        if ($topicId) {
            $rem = $db->prepare("SELECT id FROM units WHERE topic_id=? ORDER BY num ASC");
            $rem->execute([$topicId]);
            $i = 1;
            foreach ($rem->fetchAll() as $r) {
                $db->prepare("UPDATE units SET num=? WHERE id=?")->execute([$i++,$r['id']]);
            }
        }
        respond(['success'=>true]);

    // ── Comments ──
    case 'get_comments':
        $unitId = trim($_GET['unit_id'] ?? '');
        if (!$unitId) respond(['error'=>'unit_id required'], 400);
        $db = getDB();
        $s = $db->prepare("SELECT * FROM comments WHERE unit_id=? ORDER BY created_at ASC");
        $s->execute([$unitId]);
        respond($s->fetchAll());

    case 'post_comment':
        global $BODY;
        $unitId = trim($BODY['unit_id'] ?? '');
        $name   = trim($BODY['name']   ?? '');
        $text   = trim($BODY['text']   ?? '');
        if (!$unitId || !$name || !$text) respond(['error'=>'unit_id, name, text required'], 400);
        $db = getDB();
        $newId = uid();
        $db->prepare("INSERT INTO comments (id,unit_id,name,text) VALUES (?,?,?,?)")->execute([$newId,$unitId,$name,$text]);
        respond(['success'=>true,'id'=>$newId]);

    case 'delete_comment':
        if (!isAdmin()) respond(['error' => 'Unauthorized'], 403);
        global $BODY;
        $id = trim($BODY['id'] ?? '');
        if (!$id) respond(['error'=>'ID required'], 400);
        $db = getDB();
        $db->prepare("DELETE FROM comments WHERE id=?")->execute([$id]);
        respond(['success'=>true]);

    default:
        respond(['error' => 'Unknown action: '.$action], 400);
}
?>
