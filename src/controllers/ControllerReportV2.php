<?php

require_once __DIR__ . '/../services/ReportingService.php';

final class ControllerReportV2
{
    private ReportingService $service;

    public function __construct(?ReportingService $service = null)
    {
        $this->service = $service ?? new ReportingService();
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function handle(array $request): array
    {
        return $this->service->buildReport($request);
    }
}
