<?php

/** Read-only customer export for the new ParfumShop. Compatible with PHP 5.6+. */
class ControllerApiCustomerExport extends Controller
{
    public function index()
    {
        $this->response->addHeader('Cache-Control: no-store, private');
        $this->response->addHeader('X-Content-Type-Options: nosniff');

        $expected = defined('CUSTOMER_EXPORT_API_TOKEN') ? CUSTOMER_EXPORT_API_TOKEN : getenv('CUSTOMER_EXPORT_API_TOKEN');
        if (!is_string($expected) || strlen($expected) < 32 || $expected === 'CHANGE_ME_TO_A_RANDOM_TOKEN_OF_AT_LEAST_32_CHARACTERS') {
            $this->json(503, array('error' => 'Export token is not configured.'));
            return;
        }

        // Custom header works even when Apache/PHP does not forward Authorization.
        $provided = isset($this->request->server['HTTP_X_CUSTOMER_EXPORT_TOKEN'])
            ? $this->request->server['HTTP_X_CUSTOMER_EXPORT_TOKEN'] : '';
        if (!is_string($provided) || !hash_equals($expected, $provided)) {
            $this->json(401, array('error' => 'Unauthorized.'));
            return;
        }

        if (!isset($this->request->server['REQUEST_METHOD']) || $this->request->server['REQUEST_METHOD'] !== 'GET') {
            $this->response->addHeader('Allow: GET');
            $this->json(405, array('error' => 'Only GET is allowed.'));
            return;
        }

        $after = $this->integerParameter('after_id', 0, 0, PHP_INT_MAX);
        $limit = $this->integerParameter('limit', 200, 1, 1000);
        $snapshot = $this->integerParameter('snapshot_max_id', null, 0, PHP_INT_MAX);
        if ($after === false || $limit === false || $snapshot === false) {
            $this->json(422, array('error' => 'Invalid pagination. after_id and snapshot_max_id must be non-negative integers; limit must be 1-1000.'));
            return;
        }

        $table = DB_PREFIX . 'customer';
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $table)) {
            $this->json(503, array('error' => 'Invalid database table configuration.'));
            return;
        }

        if ($snapshot === null) {
            $maximum = $this->db->query('SELECT COALESCE(MAX(customer_id), 0) AS max_id FROM `' . $table . '`');
            $snapshot = (int) $maximum->row['max_id'];
        }
        if ($after > $snapshot) {
            $this->json(422, array('error' => 'after_id cannot exceed snapshot_max_id.'));
            return;
        }

        // No trimming, prefix insertion or stripping of punctuation: only exact stored numbers qualify.
        // CHAR_LENGTH also rejects a trailing newline that some regexp engines allow with $.
        $query = $this->db->query(
            'SELECT customer_id, firstname, lastname, email, telephone, sex, date_added, '
            . 'CASE WHEN bonus IS NULL OR bonus < 1 THEN 0 ELSE bonus END AS bonus '
            . 'FROM `' . $table . '` '
            . 'WHERE customer_id > ' . $after . ' AND customer_id <= ' . $snapshot . ' '
            . "AND CHAR_LENGTH(telephone) = 12 AND telephone REGEXP '^994[0-9]{9}$' "
            . 'ORDER BY customer_id ASC LIMIT ' . ($limit + 1)
        );

        $hasMore = count($query->rows) > $limit;
        $rows = array_slice($query->rows, 0, $limit);
        $customers = array();
        foreach ($rows as $row) {
            $customers[] = array(
                'customer_id' => (int) $row['customer_id'],
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'email' => $row['email'],
                'telephone' => (string) $row['telephone'],
                'sex' => $row['sex'] === null ? null : (int) $row['sex'],
                'date_added' => $row['date_added'],
                // Keep monetary values as decimal strings; do not convert them to floats.
                'bonus' => (string) $row['bonus'],
            );
        }

        $lastId = $customers ? $customers[count($customers) - 1]['customer_id'] : $after;
        $this->json(200, array(
            'version' => 1,
            'customers' => $customers,
            'pagination' => array(
                'after_id' => $after,
                'limit' => $limit,
                'count' => count($customers),
                'snapshot_max_id' => $snapshot,
                'last_customer_id' => $lastId,
                'has_more' => $hasMore,
                'next_after_id' => $hasMore ? $lastId : null,
            ),
        ));
    }

    private function integerParameter($name, $default, $minimum, $maximum)
    {
        if (!isset($this->request->get[$name])) {
            return $default;
        }
        $raw = $this->request->get[$name];
        if (!is_string($raw) || !preg_match('/^(0|[1-9][0-9]*)$/D', $raw)) {
            return false;
        }
        return filter_var($raw, FILTER_VALIDATE_INT, array('options' => array('min_range' => $minimum, 'max_range' => $maximum)));
    }

    private function json($status, array $data)
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            $status = 500;
            $json = '{"error":"Export contains invalid UTF-8. Check database encoding."}';
        }
        $statuses = array(200 => 'OK', 401 => 'Unauthorized', 405 => 'Method Not Allowed', 422 => 'Unprocessable Entity', 500 => 'Internal Server Error', 503 => 'Service Unavailable');
        $this->response->addHeader('HTTP/1.1 ' . $status . ' ' . $statuses[$status]);
        $this->response->addHeader('Content-Type: application/json; charset=utf-8');
        $this->response->setOutput($json);
    }
}
