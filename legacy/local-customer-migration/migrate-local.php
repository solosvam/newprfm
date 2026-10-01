<?php
// PHP 5.6+. Upload beside config.php. Tables must already exist.
ini_set('display_errors', '0');
@set_time_limit(120);
session_start();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
require __DIR__ . '/config.php';
function out($value) { echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "\n"; }
function legacySalary($raw) {
    $text = trim((string)$raw);
    if ($text === '') return null;
    // Use the first amount; never join numbers from ranges or gross/net text.
    if (!preg_match('/[0-9]+(?:[ \x{00A0}][0-9]{3})*(?:[.,][0-9]+)?/u', $text, $match)) return null;
    $amount = preg_replace('/[ \x{00A0}]/u', '', $match[0]);
    $amount = str_replace(',', '.', $amount);
    $parts = explode('.', $amount);
    $parts[0] = ltrim($parts[0], '0');
    if ($parts[0] === '') $parts[0] = '0';
    if (strlen($parts[0]) > 8 || (isset($parts[1]) && strlen($parts[1]) > 2)) return null;
    return $parts[0] . '.' . str_pad(isset($parts[1]) ? $parts[1] : '', 2, '0');
}
$token = defined('CUSTOMER_EXPORT_API_TOKEN') ? CUSTOMER_EXPORT_API_TOKEN : getenv('CUSTOMER_EXPORT_API_TOKEN');
if (!$token || strlen($token) < 32) exit('CUSTOMER_EXPORT_API_TOKEN config.php daxilində təyin edilməlidir.');
if (isset($_POST['token']) && hash_equals($token, (string) $_POST['token'])) {
    session_regenerate_id(true);
    $_SESSION['local_migration_authorized'] = hash('sha256', $token);
}
if (!isset($_SESSION['local_migration_authorized']) || !hash_equals(hash('sha256', $token), $_SESSION['local_migration_authorized'])) {
    echo '<form method="post">Token: <input type="password" name="token" autocomplete="off"><button>Daxil ol</button></form>';
    exit;
}
$limit = 200;
$source = DB_PREFIX . 'customer';
$credits = DB_PREFIX . 'credits2';
if (!preg_match('/^[a-zA-Z0-9_]+$/', $source) || !preg_match('/^[a-zA-Z0-9_]+$/', $credits)) exit('Yanlış cədvəl adı.');
$pdo = null;
$locked = false;
echo '<pre>';
try {
    $pdo = new PDO('mysql:host=' . DB_HOSTNAME . ';dbname=' . DB_DATABASE . ';charset=utf8mb4', DB_USERNAME, DB_PASSWORD, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false));
    $locked = (bool) $pdo->query("SELECT GET_LOCK('legacy_local_customer_migration', 0)")->fetchColumn();
    if (!$locked) throw new Exception('Başqa köçürmə işləyir.');
    foreach (array($source, $credits) as $table) {
        if (!$pdo->query("SHOW COLUMNS FROM `$table` LIKE 'checked'")->fetch()) $pdo->exec("ALTER TABLE `$table` ADD checked TINYINT(1) NOT NULL DEFAULT 0");
    }
    $after = isset($_SESSION['local_customer_after']) ? (int) $_SESSION['local_customer_after'] : 0;
    $rows = $pdo->query("SELECT customer_id, firstname, lastname, email, telephone, sex, date_added,
        CASE WHEN bonus IS NULL OR bonus < 1 THEN 0 ELSE bonus END AS bonus
        FROM `$source` WHERE customer_id > $after AND (checked = 0 OR checked IS NULL)
        AND CHAR_LENGTH(telephone) = 12 AND telephone REGEXP '^994[0-9]{9}$'
        ORDER BY customer_id LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC);
    $exists = $pdo->prepare('SELECT id FROM customers WHERE old_customer_id = ?');
    $conflict = $pdo->prepare('SELECT id FROM customers WHERE mobile = ? OR (? IS NOT NULL AND email = ?) LIMIT 1');
    $insert = $pdo->prepare('INSERT INTO customers (old_customer_id,name,surname,email,mobile,gender,bonus_balance,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
    $bonusInsert = $pdo->prepare("INSERT INTO customer_bonus_transactions (customer_id,type,amount,note,created_at,updated_at) VALUES (?,'adjustment',?,'Köhnə sistemdən köçürülən bonus',NOW(),NOW())");
    $mark = $pdo->prepare("UPDATE `$source` SET checked = 1 WHERE customer_id = ?");
    $created = 0;
    foreach ($rows as $row) {
        $id = (int) $row['customer_id'];
        $pdo->beginTransaction();
        $exists->execute(array($id));
        $newId = $exists->fetchColumn();
        if (!$newId) {
            $name = trim($row['firstname']); $surname = trim($row['lastname']);
            $email = trim($row['email']); $email = $email === '' ? null : $email;
            $date = DateTime::createFromFormat('!Y-m-d H:i:s', $row['date_added']);
            if ($name === '' || $surname === '' || mb_strlen($name,'UTF-8') > 30 || mb_strlen($surname,'UTF-8') > 30
                || ($email !== null && (strlen($email) > 50 || !filter_var($email,FILTER_VALIDATE_EMAIL)))
                || !in_array((string)$row['sex'],array('1','2'),true)
                || !$date || $date->format('Y-m-d H:i:s') !== $row['date_added']) {
                $pdo->rollBack(); out('Uyğunsuz müştəri ID: '.$id); $_SESSION['local_customer_after'] = $id; continue;
            }
            $conflict->execute(array($row['telephone'],$email,$email));
            if ($conflict->fetchColumn()) {
                $pdo->rollBack(); out('Nömrə/email konflikti, köhnə ID: '.$id); $_SESSION['local_customer_after'] = $id; continue;
            }
            $insert->execute(array($id,$name,$surname,$email,$row['telephone'],(string)$row['sex']==='1'?1:0,$row['bonus'],$row['date_added']));
            $newId = $pdo->lastInsertId();
            if ((float)$row['bonus'] >= 1) $bonusInsert->execute(array($newId,$row['bonus']));
            $created++;
        }
        $mark->execute(array($id)); $pdo->commit();
        $_SESSION['local_customer_after'] = $id;
    }
    out('Baxılan müştəri: '.count($rows).'; yaradılan: '.$created);
    if (!$rows) { $_SESSION['local_customer_after'] = 0; out('Müştəri turu tamamlandı. Növbəti refreshdə qalan uyğunsuz/konfliktli qeydlər yenidən yoxlanacaq.'); }

    $afterCredit = isset($_SESSION['local_credit_after']) ? (int)$_SESSION['local_credit_after'] : 0;
    $groups = $pdo->query("SELECT cr.customer_id AS old_id, c.id AS new_id FROM `$credits` cr
        INNER JOIN `$source` oc ON oc.customer_id = cr.customer_id AND oc.checked = 1
        INNER JOIN customers c ON c.old_customer_id = cr.customer_id
        WHERE cr.customer_id > $afterCredit AND cr.customer_id <> 0 AND (cr.checked = 0 OR cr.checked IS NULL)
        GROUP BY cr.customer_id, c.id ORDER BY cr.customer_id LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC);
    $getRecords = $pdo->prepare("SELECT * FROM `$credits` WHERE customer_id = ? ORDER BY id ASC");
    $hasProfile = $pdo->prepare('SELECT id FROM customer_credit_profiles WHERE customer_id = ? LIMIT 1');
    $profileInsert = $pdo->prepare('INSERT INTO customer_credit_profiles (customer_id,father_name,fin,id_card_series,id_card_number,relative_1_name,relative_1_phone,relative_2_name,relative_2_phone,id_card_front,id_card_back,workplace_name,salary,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())');
    $creditMark = $pdo->prepare("UPDATE `$credits` SET checked = 1 WHERE customer_id = ?");
    $genderUpdate = $pdo->prepare('UPDATE customers SET gender = ? WHERE id = ?');
    $scoredFields = array('fathername','gender','card_id','card_fin','relation_number1','relation_number2','relation_number1_who','relation_number2_who','job_type','job_salary','id_front','id_back');
    $profiles = 0;
    foreach ($groups as $group) {
        $files = array();
        try {
            $pdo->beginTransaction();
            $hasProfile->execute(array($group['new_id']));
            if (!$hasProfile->fetchColumn()) {
                $getRecords->execute(array($group['old_id']));
                $best = null; $bestScore = -1;
                while ($record = $getRecords->fetch(PDO::FETCH_ASSOC)) {
                    $score = 0;
                    foreach ($scoredFields as $field) if (isset($record[$field]) && trim((string)$record[$field]) !== '') $score++;
                    if ($score > $bestScore) { $best = $record; $bestScore = $score; }
                }
                if (!$best) throw new Exception('Kredit qeydi tapılmadı.');
                $value = function($field,$max) use ($best) {
                    $v = isset($best[$field]) ? trim((string)$best[$field]) : '';
                    if ($v === '') return null;
                    if (mb_strlen($v,'UTF-8') > $max) throw new Exception('Sahə çox uzundur: '.$field);
                    return $v;
                };
                $card = mb_strtoupper((string)$best['card_id'],'UTF-8');
                $card = preg_replace('/[\s№#\-]+/u','',$card);
                $series = null; $number = null;
                if ($card !== '') {
                    if (preg_match('/^[0-9]{1,8}$/', $card)) {
                        $number = $card;
                    } elseif (preg_match('/^(AZE|AA|AB|MYİ|DYİ|DY)([0-9]{1,8})$/u',$card,$parts)) {
                        $series = $parts[1]; $number = $parts[2];
                    } else {
                        throw new Exception('Vəsiqə nömrəsi tanınmadı.');
                    }
                }
                $fin = preg_replace('/\s+/u','',mb_strtoupper((string)$best['card_fin'],'UTF-8'));
                if ($fin === '') $fin = null;
                if ($fin !== null && !preg_match('/^[A-Z0-9]{7}$/',$fin)) throw new Exception('FIN formatı uyğun deyil.');
                $salary = legacySalary(isset($best['job_salary']) ? $best['job_salary'] : null);
                $images = array();
                foreach (array('id_front','id_back') as $field) {
                    $filename = $value($field,255);
                    if ($filename === null) { $images[] = null; continue; }
                    if (basename($filename) !== $filename || strpos($filename,'\\') !== false) throw new Exception('Şəkil fayl adı uyğun deyil.');
                    $from = __DIR__.'/credit_images/'.$filename;
                    if (!is_file($from)) { $images[] = null; continue; }
                    $directory = __DIR__.'/frontend/uploads/customers';
                    if (!is_dir($directory) && !mkdir($directory,0755,true)) throw new Exception('Şəkil qovluğu yaradıla bilmədi.');
                    $extension = strtolower(pathinfo($filename,PATHINFO_EXTENSION));
                    if (!in_array($extension,array('jpg','jpeg','png','webp','pdf'),true)) throw new Exception('Vəsiqə fayl tipi uyğun deyil.');
                    $target = 'legacy-'.$group['new_id'].'-'.$best['id'].'-'.$field.'-'.bin2hex(openssl_random_pseudo_bytes(8)).'.'.$extension;
                    if (!copy($from,$directory.'/'.$target)) throw new Exception('Vəsiqə faylı köçürülmədi.');
                    $files[] = $directory.'/'.$target; $images[] = $target;
                }
                $profileInsert->execute(array($group['new_id'],$value('fathername',100),$fin,$series,$number,
                    $value('relation_number1_who',100),$value('relation_number1',20),$value('relation_number2_who',100),$value('relation_number2',20),
                    $images[0],$images[1],$value('job_type',255),$salary));
                if (in_array($best['gender'],array('male','female'),true)) $genderUpdate->execute(array($best['gender']==='male'?1:0,$group['new_id']));
                $profiles++;
            }
            $creditMark->execute(array($group['old_id'])); $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            foreach ($files as $file) @unlink($file);
            out('Kredit köçürülmədi, köhnə customer_id '.$group['old_id'].': '.$e->getMessage());
        }
        $_SESSION['local_credit_after'] = (int)$group['old_id'];
    }
    out('Baxılan kredit müştərisi: '.count($groups).'; yaradılan profil: '.$profiles);
    if (!$groups) { $_SESSION['local_credit_after'] = 0; out('Kredit turu tamamlandı.'); }
    out('Növbəti paketi işləmək üçün səhifəni refresh et.');
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    out('Əməliyyat dayandı. Server PHP logunu yoxla.');
    error_log('Local customer migration: '.$e->getMessage());
}
if ($pdo && $locked) $pdo->query("SELECT RELEASE_LOCK('legacy_local_customer_migration')");
echo '</pre>';
