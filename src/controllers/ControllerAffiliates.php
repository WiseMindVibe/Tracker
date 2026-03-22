<?php

require_once __DIR__ . "/ControllerBase.php";
require_once __DIR__ . "/../models/ModelAffiliates.php";

class ControllerAffiliates extends ControllerBase
{

    protected string $modelClass = ModelAffiliates::class;
    protected string $formView = __DIR__ . "/../views/Forms/FormAffiliates.php";
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
        'affiliate_program' => [
            'th_title' => 'Affiliate Program',
            'value' => 'affiliate_program',
            'column' => 't.affiliate_program',
            'filter' => 'filter_name',
            'filter_type' => 'like',
            'type' => 'text'
        ],
        'active' => [
            'th_title' => 'Active',
            'value' => 'active',
            'column' => 'aa.affiliate_program',
            'filter' => 'filter_affiliate_program',
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
        parent::index();
    }

    public function create()
    {
        ModelAffiliates::Create(
            $_POST['name'],
            $_POST['api_key']
        );

        header("Location: index.php?page=affiliates");
        exit;

    }
}