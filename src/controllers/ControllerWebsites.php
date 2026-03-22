<?php

require_once __DIR__ . "/ControllerBase.php";
require_once __DIR__ . "/../models/ModelWebsites.php";
require_once __DIR__ . "/../models/ModelCountries.php";
require_once __DIR__ . "/../CrudSharedLabels.php";

class ControllerWebsites extends ControllerBase
{

    protected string $modelClass = ModelWebsites::class;

    protected string $formView = __DIR__ . "/../views/Forms/FormWebsites.php";
    protected array $extraData = [];

    protected array $config = [

        'id' => [
            'th_title' => 'ID',
            'value' => 'id',
            'column' => 't.id',
            'filter' => 'filter_id',
            'filter_type' => 'exact',
            'type' => 'hidden',
        ],
        'website' => [
            'th_title' => CrudSharedLabels::NAME,
            'value' => 'domain',
            'column' => 't.domain',
            'filter' => 'filter_domain',
            'filter_type' => 'like',
            'type' => 'text'
        ],
        'country' => [
            'th_title' => CrudSharedLabels::COUNTRY,
            'value' => 'country',
            'column' => 't.country',
            'filter' => 'filter_country',
            'filter_type' => 'like',
            'type' => 'datalist',
            'datalistId' => 'affiliate-list'
        ],
    ];

    /**
     * Override index only if you need extra data
     */
    public function index()
    {
        $this->extraData = [
            'countries' => ModelCountries::GetCountries()
        ];

        parent::index();
    }

    public function create()
    {
        ModelWebsites::Create(
            $_POST['domain'],
            $_POST['country']
        );

        header("Location: index.php?page=websites");
        exit;

    }
    public function view($id)
    {
        header('Content-Type: application/json');

        $website = ModelWebsites::view($id);

        echo json_encode([
            'data' => $website,
            'extraData' => [
                'countries' => ModelCountries::GetCountries(),
            ],
            'config' => $this->config
        ], JSON_PRETTY_PRINT);

        exit;
    }
}