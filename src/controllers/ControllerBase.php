<?php

abstract class ControllerBase
{
    protected function getPageName()
    {
        return strtolower(str_replace('Controller', '', static::class));
    }

    protected string $modelClass; // Globl model

    protected array $config; //Globl config
    protected string $formView;

    protected array $extraData = [];




    public function index()
    {

        $page = $this->getPageName();
        $model = $this->modelClass;

        $limit = (int) ($_GET['limit'] ?? 500); //Modify Later with Page Nav
        $p = max(1, (int) ($_GET['p'] ?? 1));
        $offset = ($p - 1) * $limit ?? 0;

        $sortKey = $_GET['sort'] ?? 'id';
        $sortColumn = $this->config[$sortKey]['column'] ?? 't.id'; //Sort auto by table id
        $direction = strtolower($_GET['direction'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';


        $filters = [];
        //Apply to each key's filter ??? IDK honstely
        foreach ($this->config as $key => $meta) {
            $raw = $_GET[$meta['filter']] ?? null;
            if ($raw !== null && $raw !== '') {
                $filters[$key] = $raw;
            }
        }

        [$total, $rows] = $model::getAll($filters, $sortColumn, $direction, $limit, $offset);

        $rows = $this->transformRows($rows);

        // Build sorting URLs
        foreach ($this->config as $key => &$meta) {
            // Determine new sort direction
            $newDirection = ($sortKey === $key && $direction === 'DESC') ? 'asc' : 'desc';

            // Preserve all current query parameters and override sort info
            $query = $_GET;
            $query['page'] = $page;           // current page
            $query['sort'] = $key;            // column being sorted
            $query['direction'] = $newDirection; // new direction

            // Build the full URL safely
            $meta['sortURL'] = '?' . http_build_query($query);

            // Add arrow indicator if currently sorting by this column
            if ($sortKey === $key) {
                $meta['arrow'] = $direction === 'ASC' ? '↑' : '↓';
            }
        }
        //unset($meta); // REMOVE THE //

        $config = $this->config; //Linking to the child

        $formView = $this->formView;
        extract($this->extraData);

        require __DIR__ . "/../views/ViewBase.php";
    }

    protected function transformRows(array $rows): array
    {
        return $rows;
    }

    public function viewJson()
    {
        header('Content-Type: application/json');

        //echo json_encode($this->config, JSON_PRETTY_PRINT);
        //echo json_encode(ModelOffers::getAll('', 't.id', 'ASC', 10, 0), JSON_PRETTY_PRINT);

    }

    // public function create()
    // {
    //     $model = $this->modelClass;

    //     $model::create();
    // }

    // public function view($id)
    // {
    //     header('Content-Type: application/json');

    //     $model = $this->modelClass;

    //     if (!method_exists($model, 'view')) {
    //         echo json_encode(['error' => 'View not implemented']);
    //         exit;
    //     }

    //     $data = $model::view((int) $id);

    //     if (!$data) {
    //         echo json_encode(['error' => 'Not found']);
    //         exit;
    //     }

    //     echo json_encode($data);
    //     exit;
    // }

}