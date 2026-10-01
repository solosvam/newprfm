<?php
// Offline SQL/contract check. Does not connect to the legacy database.
abstract class Controller { public $request; public $response; public $db; }
class CreditExportTestResponse
{
    public $headers = array(); public $body;
    public function addHeader($header) { $this->headers[] = $header; }
    public function setOutput($body) { $this->body = $body; }
}
class CreditExportTestDatabase
{
    public $pdo; public $queries = array();
    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->sqliteCreateFunction('CHAR_LENGTH', 'strlen', 1);
        $this->pdo->sqliteCreateFunction('regexp', function ($pattern, $value) { return preg_match('/'.$pattern.'/', (string) $value); }, 2);
        $this->pdo->exec('CREATE TABLE oc_credits2 (id INTEGER PRIMARY KEY, name TEXT, surname TEXT, fathername TEXT, gender TEXT,
            card_id TEXT, card_fin TEXT, mobile TEXT, job_type TEXT, job_salary TEXT, relation_number1 TEXT, relation_number2 TEXT,
            relation_number1_who TEXT, relation_number2_who TEXT, id_front TEXT, id_back TEXT)');
    }
    public function query($sql)
    {
        if (strpos($sql, 'SELECT ') !== 0) throw new RuntimeException('Export attempted a write.');
        $this->queries[] = $sql;
        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        return (object) array('rows' => $rows, 'row' => $rows ? $rows[0] : array());
    }
    public function escape($value) { return str_replace("'", "''", $value); }
    public function insert($id, $phone)
    {
        $this->pdo->prepare('INSERT INTO oc_credits2 (id, mobile, card_fin, card_id, id_front, id_back) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute(array($id, $phone, 'ABC1234', 'AZE 09217804', 'front.png', 'back.png'));
    }
}
define('DB_PREFIX', 'oc_');
require dirname(__DIR__).'/catalog/controller/api/credit_profile_export.php';
$token = str_repeat('t', 32);
putenv('CUSTOMER_EXPORT_API_TOKEN='.$token);
$db = new CreditExportTestDatabase();
$db->insert(1, '994103227575'); $db->insert(10, '994103227575');
$db->insert(2, '994503227575'); $db->insert(11, '994503227575');
$db->insert(99, '+994103227575'); $db->insert(98, '0503227575'); $db->insert(97, "994103227575\n");
$checks = 0;
function verifyCredit($condition, $message) {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function callCredit($parameters = array(), $header = null, $method = 'GET') {
    global $db, $token;
    $c = new ControllerApiCreditProfileExport();
    $c->db = $db;
    $c->request = (object) array('get' => $parameters, 'server' => array('REQUEST_METHOD' => $method,
        'HTTP_X_CUSTOMER_EXPORT_TOKEN' => $header === null ? $token : $header));
    $c->response = new CreditExportTestResponse();
    $c->index();
    return array(json_decode($c->response->body, true), $c->response->headers);
}
list($data, $headers) = callCredit(array(), 'wrong');
verifyCredit(in_array('HTTP/1.1 401 Unauthorized', $headers), 'Invalid token accepted.');
verifyCredit(count($db->queries) === 0, 'Unauthenticated call accessed the database.');
list($data, $headers) = callCredit(array(), null, 'POST');
verifyCredit(in_array('HTTP/1.1 405 Method Not Allowed', $headers), 'POST accepted.');
foreach (array(array('after_mobile' => '+994103227575'), array('after_mobile' => "x' OR 1=1"), array('after_mobile' => array('1')),
    array('limit' => '201'), array('limit' => '0'), array('snapshot_max_id' => '-1')) as $params) {
    list($data, $headers) = callCredit($params);
    verifyCredit(in_array('HTTP/1.1 422 Unprocessable Entity', $headers), 'Invalid parameters accepted.');
}
list($first, $headers) = callCredit(array('limit' => '1'));
verifyCredit(in_array('HTTP/1.1 200 OK', $headers), 'First page failed.');
verifyCredit(count($first['groups']) === 1, 'Wrong group page size.');
verifyCredit(array_column($first['groups'][0]['records'], 'id') === array(10, 1), 'Duplicates were split, skipped or ordered incorrectly.');
verifyCredit($first['pagination']['snapshot_max_id'] === 99, 'Snapshot incorrect.');
verifyCredit($first['pagination']['next_after_mobile'] === '994103227575', 'Phone cursor incorrect.');
verifyCredit($first['groups'][0]['records'][0]['id_back'] === 'back.png', 'Back document missing.');
$db->insert(100, '994103227575');
list($second) = callCredit(array('limit' => '1', 'after_mobile' => '994103227575', 'snapshot_max_id' => '99'));
verifyCredit(array_column($second['groups'][0]['records'], 'id') === array(11, 2), 'Second group incorrect.');
verifyCredit($second['pagination']['has_more'] === false && $second['pagination']['next_after_mobile'] === null, 'End of export incorrect.');
list($snapshot) = callCredit(array('limit' => '1', 'snapshot_max_id' => '99'));
verifyCredit(array_column($snapshot['groups'][0]['records'], 'id') === array(10, 1), 'New record leaked into snapshot.');
putenv('CUSTOMER_EXPORT_API_TOKEN=');
list($data, $headers) = callCredit();
verifyCredit(in_array('HTTP/1.1 503 Service Unavailable', $headers), 'Missing token did not disable export.');
putenv('CUSTOMER_EXPORT_API_TOKEN');
echo $checks." export checks passed. No network or legacy database accessed.\n";
