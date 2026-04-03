<?php

return [
    /*
     * DataTables search options.
     */
    'search' => [
        'smart' => true,
        'multi_term' => true,
        'case_insensitive' => true,
        'use_wildcards' => false,
        'starts_with' => false,
    ],

    'index_column' => 'DT_RowIndex',

    'engines' => [
        'eloquent' => Yajra\DataTables\EloquentDataTable::class,
        'query' => Yajra\DataTables\QueryDataTable::class,
        'collection' => Yajra\DataTables\CollectionDataTable::class,
        'resource' => Yajra\DataTables\ApiResourceDataTable::class,
    ],

    'builders' => [],

    'nulls_last_sql' => ':column :direction NULLS LAST',

    'error' => env('DATATABLES_ERROR', null),

    'columns' => [
        'excess' => ['rn', 'row_num'],
        'escape' => '*',
        'raw' => ['action', 'checkbox', 'name', 'nse_modified_at'],
        'blacklist' => ['password', 'remember_token'],
        'whitelist' => '*',
    ],

    'json' => [
        'header' => [],
        'options' => 0,
    ],

    'callback' => ['$', '$.', 'function'],
];
