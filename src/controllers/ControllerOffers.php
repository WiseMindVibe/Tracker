<?php

require_once __DIR__ . "/ControllerBase.php";
require_once __DIR__ . "/../models/ModelOffers.php";
require_once __DIR__ . "/../models/ModelCountries.php";
require_once __DIR__ . "/../CrudSharedLabels.php";

class ControllerOffers extends ControllerBase
{
    protected string $modelClass = ModelOffers::class;
    protected string $formView = __DIR__ . "/../views/Forms/FormOffers.php";
    protected array $extraData = [];

    protected array $config = [
        'id' => [
            'th_title' => 'ID',
            'value' => 'id',
            'column' => 't.id',
            'filter' => 'filter_id',
            'type' => 'hidden',
        ],
        'name' => [
            'th_title' => CrudSharedLabels::NAME,
            'value' => 'name',
            'column' => 't.name',
            'filter' => 'filter_name',
            'type' => 'text'
        ],
        'affiliate_program' => [
            'th_title' => 'Affiliate',
            'value' => 'affiliate_program',
            'column' => 'aa.affiliate_program',
            'filter' => 'filter_affiliate_program',
            'type' => 'datalist',
            'datalistId' => 'affiliate-list',
            'source' => 'affiliates',
            'valueKey' => 'affiliate_program',
            'labelKey' => 'affiliate_program'
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
            'useValue' => true
        ],
        'affiliate_link' => [
            'th_title' => 'Affiliate Link',
            'value' => 'affiliate_link',
            'column' => 't.affiliate_link',
            'filter' => 'filter_affiliate_link',
            'type' => 'url'
        ],
        'website' => [
            'th_title' => 'Website',
            'value' => 'domain',
            'column' => 'w.domain',
            'filter' => 'filter_website',
            'type' => 'datalist',
            'datalistId' => 'website-list',
            'source' => 'websites',
            'valueKey' => 'domain',
            'labelKey' => 'domain'
        ],
        'articles' => [
            'th_title' => 'Article',
            'value' => 'url_count',
            'column' => 'COALESCE(oa.url_count,0)',
            'filter' => 'filter_article',
            'type' => 'repeatable',
            'fieldType' => 'url'
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
        ]
    ];

    public function index()
    {
        $this->extraData = [
            'countries' => ModelCountries::GetCountries(),
            'affiliates' => ModelOffers::GetActiveAffiliates(),
            'websites' => ModelOffers::GetWebsites(),
        ];

        parent::index();
    }

    public function viewJSON()
    {
        header('Content-Type: application/json');

        echo json_encode($this->extraData = [
            'affiliates' => ModelOffers::GetActiveAffiliates(),
            'websites' => ModelOffers::GetWebsites(),
        ], JSON_PRETTY_PRINT);

    }


    /////////////

    public function create()
    {
        ModelOffers::Create(
            $_POST['name'],
            $_POST['affiliate_id'],
            strtoupper($_POST['country']),
            $_POST['affiliate_link'],
            $_POST['website_id'],
            $_POST['articles'] ?? []
        );

        header("Location: index.php?page=offers");
        exit;

    }

    public function view($id)
    {
        header('Content-Type: application/json');

        $offer = ModelOffers::view($id);

        echo json_encode([
            'data' => $offer,
            'extraData' => [
                'affiliates' => ModelOffers::GetActiveAffiliates(),
                'websites' => ModelOffers::GetWebsites(),
                'countries' => ModelCountries::GetCountries(),
            ],
            'config' => $this->config
        ], JSON_PRETTY_PRINT);

        exit;
    }

    public function save()
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        try {
            ModelOffers::save($data);

            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }

}