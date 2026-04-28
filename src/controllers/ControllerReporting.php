<?php

class ControllerReporting
{
    public function index(): void
    {
        $tz = new DateTimeZone(date_default_timezone_get());
        $today = new DateTimeImmutable('today', $tz);

        $viewData = [
            'defaultDatePreset' => 'today',
            'defaultDateFrom' => $today->format('Y-m-d'),
            'defaultDateTo' => $today->format('Y-m-d'),
            'defaultGroupBy' => ['offer', 'campaign'],
            'availableGroups' => [
                'offer' => 'Offer',
                'campaign' => 'Campaign',
                'external_campaign_id' => 'External Campaign ID',
                'device' => 'Device',
                'os' => 'OS',
                'os_version' => 'OS-Version',
                'browser' => 'Browser',
                'browser_version' => 'Browser-Version',
                'country' => 'Country',
                'region' => 'Region',
                'language' => 'Language',
                'connection_type' => 'Connection Type',
                'carrier' => 'Carrier',
                'isp' => 'ISP',
                'zoneid' => 'Zone ID',
                'subzone_id' => 'Subzone ID',
            ],
            'apiEndpoint' => './api/report/v2/index.php',
        ];

        extract($viewData, EXTR_SKIP);
        require_once __DIR__ . '/../views/ViewReporting.php';
    }

    public function slice(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(410);
        echo json_encode([
            'error' => 'Legacy reporting slice endpoint is retired. Use /api/report/v2/index.php.',
        ], JSON_UNESCAPED_UNICODE);
    }
}
