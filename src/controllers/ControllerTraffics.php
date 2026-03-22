<?php

require_once __DIR__ . "/ControllerBase.php";
require_once __DIR__ . "/../models/ModelTraffics.php";
require_once __DIR__ . "/../CrudSharedLabels.php";

class ControllerTraffics extends ControllerBase
{

    protected string $modelClass = ModelTraffics::class;
    protected string $formView = __DIR__ . "/../views/Forms/FormTraffics.php";
    /**
     * Table configuration for generic CRUD view
     */
    protected array $config = [

        'id' => [
            'th_title' => 'ID',
            'value' => 'id',
            'column' => 't.id',
            'filter' => 'filter_id',
            'filter_type' => 'exact',
            'type' => 'hidden',
        ],
        'traffic_source' => [
            'th_title' => CrudSharedLabels::NAME,
            'value' => 'name',
            'column' => 't.name',
            'filter' => 'filter_name',
            'filter_type' => 'like',
            'type' => 'text'
        ],
        'API' => [
            'th_title' => 'API',
            'value' => 'api_key',
            'column' => 't.api_key',
            'filter' => 'filter_api_key',
            'filter_type' => 'exact',
            'type' => 'datalist',
            'datalistId' => 'affiliate-list'
        ],
    ];

    /**
     * Override index only if you need extra data
     */
    public function index()
    {
        parent::index();
    }

    public function create()
    {
        ModelTraffics::Create(
            $_POST['name'],
            $_POST['api_key']
        );

        header("Location: index.php?page=traffics");
        exit;

    }

    public function view($id)
    {
        header('Content-Type: application/json');

        $traffic = ModelTraffics::view($id);

        echo json_encode([
            'data' => $traffic,
            'extraData' => [],
            'config' => $this->config
        ], JSON_PRETTY_PRINT);

        exit;
    }
}