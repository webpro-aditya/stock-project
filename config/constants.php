<?php

return [

    /**
     * Base URL Website
     */
    'app_url' => env('APP_URL', '127.0.0.1'),

    /**
     * SMTP Configuration
     */
    'is_smtp_active' => env('IS_SMTP_ACTIVE', false),
    'mail_from_name' => env('MAIL_FROM_NAME', 'Stock Project'),
    'mail_from_email' => env('MAIL_FROM_EMAIL', 'test@ens.enterprises'),

    /**
     * Pagination records per page
     */
    'pagination_records_per_page' => env('PAGINATION_RECORDS_PER_PAGE', 10),

    /**
     * Pagination links on each side of current page
     */
    'pagination_links_each_side' => env('PAGINATION_LINKS_EACH_SIDE', 1),
];