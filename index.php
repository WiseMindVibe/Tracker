<?php
require_once __DIR__ . "/src/bootstrap.php";
require_once __DIR__ . "/src/TrackerAdminAccess.php";
TrackerAdminAccess::enforceOrExit();

$page = $_GET['page'] ?? 'Dashboard';
$action = $_GET['action'] ?? 'index';

$controllerClass = 'Controller' . ucfirst($page);
$controllerFile = __DIR__ . "/src/controllers/Controller" . "$page.php";

if (!file_exists($controllerFile)) {
    http_response_code(404);
    exit("Page not found - $controllerFile");
}

require_once $controllerFile;

if (!class_exists($controllerClass)) {
    http_response_code(404);
    exit("Controller not found - $controllerClass");
}

$controller = new $controllerClass();

if (!method_exists($controller, $action)) {
    http_response_code(404);
    exit("Action not found - $action");
}

if ($action === 'index') {
    include_once __DIR__ . "/src/assets/includes/header.php";
}

$id = $_GET['id'] ?? null;
$id !== null
    ? $controller->$action($id)
    : $controller->$action();
