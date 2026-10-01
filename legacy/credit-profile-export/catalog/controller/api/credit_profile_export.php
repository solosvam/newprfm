<?php

class ControllerApiCreditProfileExport extends Controller
{
    public function index()
    {
        $this->response->addHeader('Cache-Control: no-store, private');
        $this->response->addHeader('X-Content-Type-Options: nosniff');
        $token = defined('CUSTOMER_EXPORT_API_TOKEN') ? CUSTOMER_EXPORT_API_TOKEN : getenv('CUSTOMER_EXPORT_API_TOKEN');
        if (!is_string($token) || strlen($token) < 32 || $token === 'CHANGE_ME_TO_A_RANDOM_TOKEN_OF_AT_LEAST_32_CHARACTERS') {
            $this->json(503, array('error' => 'Export token is not configured.'));
            return;
        }
        $provided = isset($this->request->server['HTTP_X_CUSTOMER_EXPORT_TOKEN']) ? $this->request->server['HTTP_X_CUSTOMER_EXPORT_TOKEN'] : '';
        if (!is_string($provided) || !hash_equals($token, $provided)) {
            $this->json(401, array('error' => 'Unauthorized.'));
            return;
        }
        if ((isset($this->request->server['REQUEST_METHOD']) ? $this->request->server['REQUEST_METHOD'] : '') !== 'GET') {
            $this->response->addHeader('Allow: GET');
            $this->json(405, array('error' => 'Only GET is allowed.'));
            return;
        }
        $after = isset($this->request->get['after_mobile']) ? $this->request->get['after_mobile'] : '';
        $limit = $this->integer('limit', 50, 1, 200);
        $snapshot = $this->integer('snapshot_max_id', null, 0, PHP_INT_MAX);
        if (!is_string($after) || ($after !== '' && !preg_match('/^994[0-9]{9}$/D', $after)) || $limit === false || $snapshot === false) {
            $this->json(422, array('error' => 'Invalid cursor, snapshot or limit (1-200).'));
            return;
        }
        $table = DB_PREFIX . 'credits2';
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
            $this->json(503, array('error' => 'Invalid database configuration.'));
            return;
        }
        if ($snapshot === null) {
            $maximum = $this->db->query('SELECT COALESCE(MAX(id), 0) AS max_id FROM `' . $table . '`');
            $snapshot = (int) $maximum->row['max_id'];
        }
        // Page by phone, not row ID: every duplicate for a phone must arrive together.
        $phones = $this->db->query('SELECT DISTINCT mobile FROM `' . $table . '` WHERE id <= ' . $snapshot
            . " AND CHAR_LENGTH(mobile) = 12 AND mobile REGEXP '^994[0-9]{9}$'"
            . " AND mobile > '" . $this->db->escape($after) . "' ORDER BY mobile ASC LIMIT " . ($limit + 1));
        $more = count($phones->rows) > $limit;
        $selected = array_slice($phones->rows, 0, $limit);
        $groups = array();
        foreach ($selected as $phone) {
            $mobile = (string) $phone['mobile'];
            $query = $this->db->query('SELECT id, name, surname, fathername, gender, card_id, card_fin, mobile, job_type, job_salary, '
                . 'relation_number1, relation_number2, relation_number1_who, relation_number2_who, id_front, id_back '
                . 'FROM `' . $table . "` WHERE mobile = '" . $this->db->escape($mobile) . "' AND id <= " . $snapshot . ' ORDER BY id DESC');
            $records = array();
            foreach ($query->rows as $row) {
                $row['id'] = (int) $row['id'];
                $row['mobile'] = (string) $row['mobile'];
                $records[] = $row;
            }
            $groups[] = array('mobile' => $mobile, 'records' => $records);
        }
        $last = $groups ? $groups[count($groups) - 1]['mobile'] : $after;
        $this->json(200, array('version' => 1, 'groups' => $groups, 'pagination' => array(
            'after_mobile' => $after, 'limit' => $limit, 'count' => count($groups), 'snapshot_max_id' => $snapshot,
            'last_mobile' => $last, 'has_more' => $more, 'next_after_mobile' => $more ? $last : null,
        )));
    }

    private function integer($key, $default, $min, $max)
    {
        if (!isset($this->request->get[$key])) return $default;
        $raw = $this->request->get[$key];
        if (!is_string($raw) || !preg_match('/^(0|[1-9][0-9]*)$/D', $raw)) return false;
        return filter_var($raw, FILTER_VALIDATE_INT, array('options' => array('min_range' => $min, 'max_range' => $max)));
    }

    private function json($status, array $data)
    {
        $body = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) { $status = 500; $body = '{"error":"Invalid database UTF-8."}'; }
        $labels = array(200 => 'OK', 401 => 'Unauthorized', 405 => 'Method Not Allowed', 422 => 'Unprocessable Entity', 500 => 'Internal Server Error', 503 => 'Service Unavailable');
        $this->response->addHeader('HTTP/1.1 ' . $status . ' ' . $labels[$status]);
        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->setOutput($body);
    }
}
