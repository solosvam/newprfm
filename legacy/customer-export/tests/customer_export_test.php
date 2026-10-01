<?php
// Offline contract checks: execute the export SQL against an in-memory SQLite database.
// No legacy database, HTTP calls, SMS or customer data are used.
abstract class Controller
{
    public $request;
    public $response;
    public $db;
}
class ExportTestResponse
{
    public $headers = array();
    public $output;
    public function addHeader($header) { $this->headers[] = $header; }
    public function setOutput($output) { $this->output = $output; }
}
class ExportTestDatabase
{
    public $pdo;
    public $queries = array();
    public function __construct()
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->sqliteCreateFunction('CHAR_LENGTH', 'strlen', 1);
        $this->pdo->sqliteCreateFunction('regexp', function ($pattern, $value) {
            return preg_match('/' . $pattern . '/', (string) $value);
        }, 2);
        $this->pdo->exec('CREATE TABLE oc_customer (customer_id INTEGER PRIMARY KEY, firstname TEXT, lastname TEXT, email TEXT, telephone TEXT, sex INTEGER, bonus DECIMAL)');
    }
    public function query($sql)
    {
        if (strpos($sql, 'SELECT ') !== 0) throw new RuntimeException('Export attempted a write.');
        $this->queries[] = $sql;
        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        return (object) array('row' => $rows ? $rows[0] : array(), 'rows' => $rows);
    }
    public function insert($id, $phone, $bonus, $sex = 1)
    {
        $statement = $this->pdo->prepare('INSERT INTO oc_customer VALUES (?, ?, ?, ?, ?, ?, ?)');
        $statement->execute(array($id, 'Test', 'Customer', 'test@example.com', $phone, $sex, $bonus));
    }
}
define('DB_PREFIX', 'oc_');
require dirname(__DIR__) . '/catalog/controller/api/customer_export.php';
$token = str_repeat('a', 64);
putenv('CUSTOMER_EXPORT_API_TOKEN=' . $token);
$database = new ExportTestDatabase();
$database->insert(1, '994103227575', '-5');
$database->insert(2, '+994103227575', '50');
$database->insert(3, '994503227575', '0.99', 2);
$database->insert(4, '0503227575', '50');
$database->insert(5, '994703227575', '1');
$database->insert(6, '994 10 3227575', '50');
$database->insert(7, '994773227575', '12.50');
$database->insert(8, '9941032275750', '50');
$database->insert(9, '99410322757', '50');
$database->insert(10, "994103227575\n", '50');
$database->insert(11, '994993227575', null, null);
$checks = 0;
function verify($condition, $message)
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function callExport($parameters = array(), $header = null, $method = 'GET')
{
    global $database, $token;
    $controller = new ControllerApiCustomerExport();
    $controller->request = (object) array('get' => $parameters, 'server' => array(
        'REQUEST_METHOD' => $method, 'HTTP_X_CUSTOMER_EXPORT_TOKEN' => $header === null ? $token : $header,
    ));
    $controller->response = new ExportTestResponse();
    $controller->db = $database;
    $controller->index();
    return array(json_decode($controller->response->output, true), $controller->response->headers);
}
list($unauthorized, $headers) = callExport(array(), 'wrong');
verify(in_array('HTTP/1.1 401 Unauthorized', $headers), 'Wrong token was accepted.');
verify(count($database->queries) === 0, 'Unauthenticated call queried the database.');
list($method, $headers) = callExport(array(), null, 'POST');
verify(in_array('HTTP/1.1 405 Method Not Allowed', $headers), 'POST was accepted.');
foreach (array(array('limit' => '1001'), array('limit' => '0'), array('after_id' => '-1'), array('after_id' => '1 OR 1=1'), array('after_id' => array('1')), array('snapshot_max_id' => 'x')) as $invalid) {
    list($data, $headers) = callExport($invalid);
    verify(in_array('HTTP/1.1 422 Unprocessable Entity', $headers), 'Invalid cursor was accepted.');
}
list($first, $headers) = callExport(array('limit' => '2'));
verify(in_array('HTTP/1.1 200 OK', $headers), 'First page failed.');
verify(in_array('Cache-Control: no-store, private', $headers), 'Missing cache protection.');
verify(array_column($first['customers'], 'customer_id') === array(1, 3), 'Exact phone filtering failed.');
verify($first['customers'][0]['bonus'] === '0' && $first['customers'][1]['bonus'] === '0', 'Bonus under 1 was not zeroed.');
verify($first['customers'][1]['sex'] === 2, 'Legacy sex mapping changed.');
verify($first['pagination']['next_after_id'] === 3 && $first['pagination']['snapshot_max_id'] === 11, 'Cursor metadata is wrong.');
verify(array_keys($first['customers'][0]) === array('customer_id', 'firstname', 'lastname', 'email', 'telephone', 'sex', 'bonus'), 'Unexpected customer fields were exported.');
$database->insert(12, '994553227575', '100');
list($second) = callExport(array('after_id' => '3', 'snapshot_max_id' => '11', 'limit' => '2'));
verify(array_column($second['customers'], 'customer_id') === array(5, 7), 'Second page skipped or repeated customers.');
verify($second['customers'][0]['bonus'] === '1' && (string) $second['customers'][1]['bonus'] === '12.5', 'Positive bonus changed.');
list($third) = callExport(array('after_id' => '7', 'snapshot_max_id' => '11', 'limit' => '2'));
verify(array_column($third['customers'], 'customer_id') === array(11), 'Snapshot included a newly created customer.');
verify($third['customers'][0]['bonus'] === '0' && $third['customers'][0]['sex'] === null, 'NULL fields handled incorrectly.');
verify($third['pagination']['has_more'] === false && $third['pagination']['next_after_id'] === null, 'Final page not marked complete.');
list($empty) = callExport(array('after_id' => '11', 'snapshot_max_id' => '11'));
verify($empty['customers'] === array() && $empty['pagination']['has_more'] === false, 'Empty page failed.');
list($invalid, $headers) = callExport(array('after_id' => '12', 'snapshot_max_id' => '11'));
verify(in_array('HTTP/1.1 422 Unprocessable Entity', $headers), 'Cursor beyond snapshot was accepted.');
putenv('CUSTOMER_EXPORT_API_TOKEN=');
list($disabled, $headers) = callExport();
verify(in_array('HTTP/1.1 503 Service Unavailable', $headers), 'Unconfigured API was not disabled.');
putenv('CUSTOMER_EXPORT_API_TOKEN');
echo $checks . " checks passed. No network or legacy database was accessed.\n";
