<?php

namespace App\Http\Controllers;

use App\Modules\ModuleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use phpDocumentor\Reflection\Types\Nullable;

class ModuleController extends Controller
{
    public function index(string $module, Request $request, ModuleManager $manager): Response
    {
        $config = $manager->get($module)->config();

        $titles = $config->titles();

        $table = $config->table();
        ///SORT
        $sortColumn = $request->input('sort', $table->defaultSort['column'] ?? null);
        $sortDirection = $request->input('direction', $table->defaultSort['direction'] ?? 'asc');
        $sortDirection = $sortDirection === 'desc' ? 'desc' : 'asc';

        $activeColumn = collect($table->columns)
            ->first(fn($column) => ($column->sortable ?? false) && $column->field === $sortColumn);

        if (! $activeColumn && $table->defaultSort) {
            $activeColumn = collect($table->columns)
                ->first(fn($column) => $column->field === $table->defaultSort['column']);
            $sortColumn = $table->defaultSort['column'] ?? null;
            $sortDirection = $table->defaultSort['direction'] ?? 'asc';
        }
        ///SORT
        $query = $table->applyToQuery($config->model()::query());
        if ($activeColumn) {
            $activeColumn->applySort($query, $sortDirection);
        }

        $rows = $query->paginate(25)->withQueryString();

        return Inertia::render('Modules/Index', [
            'module' => $module,
            'titles' => $titles,
            'columns' => $table->columns,
            'actions' => $table->actions,
            'rows' => $rows,
            'sort' => $sortColumn ? ['column' => $sortColumn, 'direction' => $sortDirection] : null,
        ]);
    }

    public function create(string $module, ModuleManager $manager): Response
    {
        $config = $manager->get($module)->config();

        $data = [
            'module' => $module,
            'titles' => $config->titles(),
            'fields' => $config->fields(),
            'repeaters' => $config->repeaters(),
            'defaults' => [
                'uuid' => (string) Str::uuid(),
            ],
        ];

        $data = array_merge($data, $this->trafficCredentialDefinitionsData($config->repeaters()));
        $data = array_merge($data, $this->affiliateCredentialDefinitionsData($config->repeaters()));
        $data = array_merge($data, $this->trackingLinkData($config->fields()));
        $data = array_merge($data, $this->merchantIdLabelData($config->fields()));

        if ($unique = $config->uniqueConstraint()) {
            $data['uniqueConstraint'] = $unique;
            $data['existingCombos'] = $this->existingCombos($config, $unique);
        }



        return Inertia::render('Modules/Create', $data);
    }

    private function existingCombos($config, array $unique, ?int $ignoreId = null): array
    {
        [$a, $b] = $unique;
        return $config->model()::query()
            ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
            ->get([$a, $b])
            ->map(fn($row) => [(string) $row->{$a}, (string) $row->{$b}])
            ->all();
    }

    public function store(string $module, Request $request, ModuleManager $manager): RedirectResponse
    {
        $config = $manager->get($module)->config();
        $repeaters = $config->repeaters();

        $rules = $this->rulesFor($config->fields());
        $rules = array_merge($rules, $this->repeaterRulesFor($repeaters));
        $rules = $this->applyUniqueConstraint($rules, $config->uniqueConstraint(), $config->model(), $request);

        $validated = $request->validate($rules);


        $model = $config->model()::create(
            collect($validated)->only(collect($config->fields())->pluck('field')->all())->all()
        );

        foreach ($repeaters as $repeater) {
            $rows = $validated[$repeater->relation] ?? [];
            foreach ($rows as $row) {
                $model->{$repeater->relation}()->create($row);
            }
        }

        return redirect('/m/' . $module)
            ->with('success', $config->titles()['header_s'] . ' created.');
    }

    public function edit(string $module, int $id, ModuleManager $manager): Response
    {
        return Inertia::render('Modules/Edit', array_merge(
            $this->buildEditData($module, $id, $manager),
            ['readOnly' => false]
        ));
    }

    private function applyUniqueConstraint(array $rules, ?array $unique, string $modelClass, Request $request, ?int $ignoreId = null): array
    {
        if (!$unique) {
            return $rules;
        }

        [$a, $b] = $unique;
        $table = (new $modelClass)->getTable();

        $rule = Rule::unique($table)->where(fn($q) => $q->where($a, $request->input($a)));
        if ($ignoreId) {
            $rule = $rule->ignore($ignoreId);
        }

        $rules[$b][] = $rule;
        return $rules;
    }

    public function view(string $module, int $id, ModuleManager $manager): Response
    {
        return Inertia::render('Modules/Edit', array_merge(
            $this->buildEditData($module, $id, $manager),
            ['readOnly' => true]
        ));
    }

    private function buildEditData(string $module, int $id, ModuleManager $manager): array
    {
        $config = $manager->get($module)->config();
        $repeaters = $config->repeaters();

        $with = collect($repeaters)->pluck('relation')->all();
        $record = $config->model()::with($with)->findOrFail($id);

        $repeaterData = [];
        foreach ($repeaters as $repeater) {
            $repeaterData[$repeater->relation] = $record->{$repeater->relation}
                ->map(fn($row) => array_merge(
                    ['id' => $row->id],
                    collect($repeater->fields)->mapWithKeys(
                        fn($f) => [$f->field => (string) $row->{$f->field}]
                    )->all()
                ))
                ->values()
                ->all();
        }

        $data = [
            'module' => $module,
            'id' => $id,
            'titles' => $config->titles(),
            'fields' => $config->fields(),
            'repeaters' => $repeaters,
            'record' => $record->only(collect($config->fields())->pluck('field')->all()),
            'repeaterData' => $repeaterData,
        ];

        $data = array_merge($data, $this->affiliateCredentialDefinitionsData($config->repeaters()));
        $data = array_merge($data, $this->trafficCredentialDefinitionsData($config->repeaters()));
        $data = array_merge($data, $this->trackingLinkData($config->fields()));
        $data = array_merge($data, $this->merchantIdLabelData($config->fields()));
        if (collect($config->fields())->contains(fn($f) => $f->type === 'tracking_link')) {
            $data['uuid'] = $record->uuid;
        }

        if ($unique = $config->uniqueConstraint()) {
            $data['uniqueConstraint'] = $unique;
            $data['existingCombos'] = $this->existingCombos($config, $unique, $id);
        }

        return $data;
    }

    public function update(string $module, int $id, Request $request, ModuleManager $manager): RedirectResponse
    {
        $config = $manager->get($module)->config();
        $repeaters = $config->repeaters();
        $record = $config->model()::findOrFail($id);

        $rules = $this->rulesFor($config->fields());
        $rules = array_merge($rules, $this->repeaterRulesFor($repeaters));
        $rules = $this->applyUniqueConstraint($rules, $config->uniqueConstraint(), $config->model(), $request, $id);

        $validated = $request->validate($rules);

        $record->update(
            collect($validated)->only(collect($config->fields())->pluck('field')->all())->all()
        );

        foreach ($repeaters as $repeater) {
            $rows = $validated[$repeater->relation] ?? [];
            $submittedIds = collect($rows)->pluck('id')->filter()->all();

            // delete rows removed on the frontend
            $record->{$repeater->relation}()->whereNotIn('id', $submittedIds)->delete();

            foreach ($rows as $row) {
                $attrs = collect($row)->except('id')->all();

                if (!empty($row['id'])) {
                    $record->{$repeater->relation}()->where('id', $row['id'])->update($attrs);
                } else {
                    $record->{$repeater->relation}()->create($attrs);
                }
            }
        }

        return redirect('/m/' . $module)
            ->with('success', $config->titles()['header_s'] . ' updated.');
    }


    private function rulesFor(array $fields): array
    {
        $rules = [];
        foreach ($fields as $field) {
            $fieldRules = [$field->required ? 'required' : 'nullable'];
            $fieldRules[] = match ($field->type) {
                'url' => 'url',
                'select' => 'in:' . implode(',', array_keys($field->options ?? [])),
                'country' => 'in:' . implode(',', array_column($field->options ?? [], 'value')),
                default => 'string',
            };
            $rules[$field->field] = $fieldRules;
        }
        return $rules;
    }

    private function repeaterRulesFor(array $repeaters): array
    {
        $rules = [];
        foreach ($repeaters as $repeater) {
            $rules[$repeater->relation] = [
                $repeater->min > 0 ? 'required' : 'nullable',
                'array',
                'min:' . $repeater->min
            ];
            $rules["{$repeater->relation}.*.id"] = ['nullable', 'integer'];

            foreach ($repeater->fields as $field) {
                $fieldRules = ['required'];
                $fieldRules[] = match ($field->type) {
                    'url' => 'url',
                    default => 'string',
                };
                $rules["{$repeater->relation}.*.{$field->field}"] = $fieldRules;
            }
        }
        return $rules;
    }

    private function affiliateCredentialDefinitionsData(array $repeaters): array
    {
        $hasCredentials = collect($repeaters)->contains(fn($r) => $r->relation === 'affiliateCredentials');

        if (!$hasCredentials) {
            return [];
        }

        return [
            'affiliateFieldDefinitions' => \App\Models\AffiliateFieldDefinition::orderBy('id')
                ->get()
                ->groupBy('affiliate_catalog_id')
                ->map(fn($group) => $group->map(fn($d) => [
                    'label' => $d->label,
                    'key' => $d->field_key,
                ])->values())
                ->all(),
        ];
    }

    private function trafficCredentialDefinitionsData(array $repeaters): array
    {
        $hasCredentials = collect($repeaters)->contains(fn($r) => $r->relation === 'trafficCredentials');

        if (!$hasCredentials) {
            return [];
        }

        return [
            'trafficFieldDefinitions' => \App\Models\TrafficFieldDefinition::orderBy('id')
                ->get()
                ->groupBy('traffic_catalog_id')
                ->map(fn($group) => $group->map(fn($d) => [
                    'label' => $d->label,
                    'key' => $d->field_key,
                ])->values())
                ->all(),
        ];
    }

    private function trackingLinkData(array $fields): array
    {
        $hasTrackingLink = collect($fields)->contains(fn($f) => $f->type === 'tracking_link');

        if (!$hasTrackingLink) {
            return [];
        }

        return [
            'trafficSourceTemplates' => config('traffic_sources'),
            'trackerBaseUrl' => config('tracker.base_url'),
            'trackerRedirectPath' => config('tracker.redirect_path'),
            'trafficAccountSlugs' => \App\Models\TrafficAccount::with('trafficCatalog')
                ->get()
                ->mapWithKeys(fn($a) => [(string) $a->id => $a->trafficCatalog?->slug])
                ->filter()
                ->all(),
        ];
    }

    private function merchantIdLabelData(array $fields): array
    {
        $hasMerchantField = collect($fields)->contains(fn($f) => $f->field === 'merchant_id');

        if (!$hasMerchantField) {
            return [];
        }

        return [
            'merchantIdLabels' => \App\Models\AffiliateAccount::with('affiliateCatalog')
                ->get()
                ->mapWithKeys(fn($account) => [
                    (string) $account->id => $account->affiliateCatalog?->merchant_id_label ?? 'Merchant ID',
                ])
                ->all(),
        ];
    }

    public function destroy(string $module, int $id, ModuleManager $manager): RedirectResponse
    {
        $config = $manager->get($module)->config();
        $record = $config->model()::findOrFail($id);

        try {
            $record->delete();
        } catch (QueryException $e) {
            if ($this->isForeignKeyViolation($e)) {
                return redirect('/m/' . $module . '/' . $id . '/edit')
                    ->with('error', $this->foreignKeyErrorMessage($e, $config->titles()['header_s']));
            }

            throw $e;
        }

        return redirect('/m/' . $module)
            ->with('success', $config->titles()['header_s'] . ' deleted.');
    }

    private function isForeignKeyViolation(QueryException $e): bool
    {
        return in_array($e->getCode(), ['23000', '23503'], true)
            || str_contains($e->getMessage(), 'foreign key constraint');
    }

    private function foreignKeyErrorMessage(QueryException $e, string $recordLabel): string
    {
        // Extract the referencing table from the error, e.g. "offers" from
        // "CONSTRAINT `offers_blog_id_foreign` FOREIGN KEY ... REFERENCES `blogs`"
        if (preg_match('/`(\w+)`\.`(\w+)`, CONSTRAINT/', $e->getMessage(), $matches)) {
            $referencingTable = $matches[2];
            $friendlyName = \Illuminate\Support\Str::of($referencingTable)
                ->replace('_', ' ')
                ->title();

            return "Cannot delete this {$recordLabel} because it still has related {$friendlyName} records. Remove or reassign them first.";
        }

        return "Cannot delete this {$recordLabel} because other records still depend on it.";
    }
}
