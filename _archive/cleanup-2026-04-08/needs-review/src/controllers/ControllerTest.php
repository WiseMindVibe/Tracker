<?php
require_once __DIR__ . "/../models/ModelTest.php";

class ControllerTest
{
    public function index(): void
    {
        $rangeParam = isset($_GET['range']) ? (string) $_GET['range'] : 'last6';
        $customStart = isset($_GET['start']) ? (string) $_GET['start'] : null;
        $customEnd = isset($_GET['end']) ? (string) $_GET['end'] : null;

        $resolved = ModelTest::resolveDateRange($rangeParam, $customStart, $customEnd);
        $startDate = $resolved['start'];
        $endDate = $resolved['end'];
        //$rangePreset = $resolved['preset'];

        $stats = ModelTest::fetchAggregateStats($startDate, $endDate);
        //$affiliates = ModelDashboard::fetchAffiliateBreakdown($startDate, $endDate);
        //$topOffers = ModelDashboard::fetchTopOffersLast7Days(5);

        $tz = new DateTimeZone(date_default_timezone_get());
        $todayTop = new DateTime('today', $tz);
        $startTop = (clone $todayTop)->modify('-6 days');
        //$topOffersRangeLabel = $startTop->format('M j') . ' – ' . $todayTop->format('M j, Y');

        require_once __DIR__ . "/../views/ViewTest.php";
    }
}

