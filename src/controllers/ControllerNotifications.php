<?php
require_once __DIR__ . "/../models/ModelNotifications.php";

class ControllerNotifications
{
    public function index(): void
    {
        $perPage = isset($_GET['per']) ? (int) $_GET['per'] : 25;
        $perPage = ModelNotifications::normalizePerPage($perPage);

        $page = isset($_GET['p']) ? max(1, (int) $_GET['p']) : 1;
        $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
        if (strlen($q) > 120) {
            $q = substr($q, 0, 120);
        }

        ModelNotifications::markAllRead();

        $total = ModelNotifications::countFiltered($q);
        $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $notifications = ModelNotifications::fetchPage($page, $perPage, $q);

        $allowedPerPage = ModelNotifications::allowedPerPage();

        require __DIR__ . "/../views/ViewNotifications.php";
    }
}
