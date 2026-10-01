<?php
// Place beside the legacy config.php. Does not execute the storefront index.php.
ini_set('display_errors', '0');
ob_start();
$creditProfileExportFinished = false;
$creditProfileExportFail = function () use (&$creditProfileExportFinished) {
    $creditProfileExportFinished = true;
    while (ob_get_level() > 0) ob_end_clean();
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo '{"error":"Credit profile export failed. Check the server database configuration and PHP error log."}';
};
register_shutdown_function(function () use (&$creditProfileExportFinished, $creditProfileExportFail) {
    // Legacy DB drivers may exit() on SQL errors. Never expose their HTML/errors.
    if (!$creditProfileExportFinished) $creditProfileExportFail();
});

class CreditProfileExportDatabase
{
    private $connection;
    public function query($sql)
    {
        // Connect only after the controller has checked the token and parameters.
        if (!$this->connection) {
            $this->connection = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE);
        }
        return $this->connection->query($sql);
    }
}

try {
    require_once __DIR__ . '/config.php';
    if (!defined('DIR_DATABASE')) define('DIR_DATABASE', DIR_SYSTEM . 'database/');
    require_once DIR_SYSTEM . 'engine/registry.php';
    require_once DIR_SYSTEM . 'engine/controller.php';
    require_once DIR_SYSTEM . 'library/db.php';
    require_once DIR_SYSTEM . 'library/response.php';
    require_once __DIR__ . '/catalog/controller/api/credit_profile_export.php';
    $registry = new Registry();
    $response = new Response();
    $registry->set('response', $response);
    $registry->set('request', (object) array('get' => $_GET, 'server' => $_SERVER));
    $registry->set('db', new CreditProfileExportDatabase());
    $controller = new ControllerApiCreditProfileExport($registry);
    $controller->index();
    while (ob_get_level() > 0) ob_end_clean();
    $response->output();
    $creditProfileExportFinished = true;
} catch (Exception $e) {
    $creditProfileExportFail();
} catch (Throwable $e) {
    $creditProfileExportFail();
}
