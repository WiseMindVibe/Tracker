<?php

require_once __DIR__ . "/ControllerBase.php";
require_once __DIR__ . "/../models/ModelCampaigns.php";
require_once __DIR__ . "/../models/ModelCountries.php";
require_once __DIR__ . "/../CrudSharedLabels.php";

class ControllerCampaigns extends ControllerBase
{
    protected string $modelClass = ModelCampaigns::class;
    protected string $formView = __DIR__ . "/../views/Forms/FormCampaigns.php";
    protected array $extraData = [];

    protected array $config = [
        'id' => [
            'th_title' => 'ID',
            'value' => 'id',
            'column' => 't.id',
            'filter' => 'filter_id',
            'type' => 'hidden',
        ],
        'tester' => [
            'th_title' => 'Tester',
            'value' => 'tester',
            'column' => 't.tester',
            'filter' => 'filter_tester',
            'type' => 'checkbox',
        ],
        'campaign' => [
            'th_title' => CrudSharedLabels::NAME,
            'value' => 'campaign',
            'column' => 't.name',
            'filter' => 'filter_name',
            'type' => 'text',
        ],
        'campaign_offers' => [
            'th_title' => 'Offers',
            'value' => 'campaign_offers',
            'column' => 't.id',
            'filter' => '',
            'type' => 'campaign_offers',
            'source' => 'offers_list',
            'showInList' => false,
        ],
        'views' => [
            'th_title' => 'Views',
            'value' => 'views',
            'column' => 'percentage',
            'filter' => '',
        ],
        'external_campaign_id' => [
            'th_title' => 'External Campaign IDs',
            'value' => 'external_campaign_id',
            'column' => 'cei.external_campaign_id',
            'filter' => 'filter_external_campaign_id',
            'type' => 'repeatable',
            'fieldType' => 'text',
        ],
        'country' => [
            'th_title' => CrudSharedLabels::COUNTRY,
            'value' => 'country',
            'column' => 't.country',
            'filter' => 'filter_country',
            'type' => 'datalist',
            'datalistId' => 'country-list',
            'source' => 'countries',
            'valueKey' => 'code',
            'labelKey' => 'name',
            'useValue' => true,
        ],
        'tracking_url' => [
            'th_title' => 'Tracking URL',
            'value' => 'tracking_url',
            'column' => 'uuid',
            'filter' => '',
            'type' => 'url-locked',
        ],
        'traffic_source' => [
            'th_title' => 'Traffic source',
            'value' => 'traffic',
            'column' => 'ts.name',
            'filter' => 'filter_traffic',
            'type' => 'datalist',
            'datalistId' => 'traffic-list',
            'source' => 'traffics',
            'valueKey' => 'name',
            'labelKey' => 'name',
        ],
        'uuid' => [
            'th_title' => 'UUID',
            'value' => 'uuid',
            'column' => 't.uuid',
            'filter' => 'filter_uuid',
            'type' => 'hidden',
        ],
        'created_time' => [
            'th_title' => 'Created At',
            'value' => 'created_at',
            'column' => 't.created_at',
            'filter' => 'filter_created_at',
        ],
        'updated_time' => [
            'th_title' => 'Updated At',
            'value' => 'updated_at',
            'column' => 't.updated_at',
            'filter' => 'filter_updated_at',
        ],
    ];

    public function index()
    {
        $this->extraData = [
            'countries' => ModelCountries::GetCountries(),
            'traffics' => ModelCampaigns::GetTrafficsSources(),
            'offers_list' => ModelCampaigns::listOffersForSelect(),
        ];

        parent::index();
    }

    protected function transformRows(array $rows): array
    {
        foreach ($rows as &$row) {
            $row['tracking_url'] = $this->GenerateTrackingURL($row);
        }

        return $rows;
    }

    public function create()
    {
        $ids = $_POST['campaign_offer_id'] ?? [];
        $caps = $_POST['campaign_offer_cap'] ?? [];
        $assignments = [];
        if (is_array($ids)) {
            foreach ($ids as $i => $oid) {
                $oid = (int) $oid;
                if ($oid < 1) {
                    continue;
                }
                $assignments[] = [
                    'offer_id' => $oid,
                    'cap' => (int) ($caps[$i] ?? 0),
                ];
            }
        }

        try {
            ModelCampaigns::create(
                $_POST['name'],
                $_POST['tester'] ?? 0,
                $_POST['traffic_source_id'],
                $_POST['country'],
                $_POST['uuid'],
                $_POST['external_campaign_id'] ?? [],
                $assignments
            );
        } catch (Throwable $e) {
            http_response_code(500);
            exit('Could not create campaign: ' . htmlspecialchars($e->getMessage()));
        }

        header("Location: index.php?page=campaigns");
        exit;
    }

    public function GenerateTrackingURL(array $campaign): string
    {
        $base_url = getenv('BASE_URL') ?: 'MISSING ENV';
        $tracking_url = 'https://' . rtrim($base_url, '/') . "/public/redirect.php?uuid=" . rawurlencode($campaign['uuid'] ?? '');
        $traffic = strtolower(trim((string) ($campaign['traffic'] ?? '')));

        switch ($traffic) {
            case 'propellerads':
                $tracking_url .= '&SUB_ID=${SUBID}'
                . '&campaign_id={campaign_id}'
                . '&country={country}'
                . '&region={region}'
                . '&language={language}'
                . '&device={device}'
                . '&os={os}'
                . '&os_version={osversion}'
                . '&browser={browser}'
                . '&browser_version={browser_version}'
                . '&connection_type={connection_type}'
                . '&carrier={carrier}'
                . '&isp={isp}'
                . '&zoneid={zoneid}'
                . '&subzone_id={subzone_id}'
                . '&cost={cost}'
                . '&useragent={useragent}'
                . '&user_activity={user_activity}'
                ;
            break;
            case 'hilltop':
                $tracking_url .= '&geo={{geo}}&zoneid={{zoneid}}&adid={{adid}}&campaignid={{campaignid}}&category={{category}}&cpmbid={{cpmbid}}&price={{price}}&browsername={{browsername}}&appname={{appname}}';
                break;
            default:
                if ($traffic !== '') {
                    $tracking_url .= '&source=' . rawurlencode($traffic);
                }
                break;
        }

        return $tracking_url;
    }

    public function view($id)
    {
        header('Content-Type: application/json');

        $campaign = ModelCampaigns::view((int) $id);
        if ($campaign === []) {
            echo json_encode(['error' => 'Not found']);
            exit;
        }

        $campaign['tracking_url'] = $this->GenerateTrackingURL($campaign);

        echo json_encode([
            'data' => $campaign,
            'extraData' => [
                'traffics' => ModelCampaigns::GetTrafficsSources(),
                'countries' => ModelCountries::GetCountries(),
                'offers_list' => ModelCampaigns::listOffersForSelect(),
            ],
            'config' => $this->config,
        ], JSON_PRETTY_PRINT);

        exit;
    }

    public function save()
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        try {
            ModelCampaigns::save($data ?? []);

            echo json_encode([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }

        exit;
    }
}
