<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */
    'api_prefix' => env('PDF_DESIGNER_API_PREFIX', 'api/pdf-designer'),
    'api_middleware' => ['api'],  // 'auth:sanctum' commented out as opt-in

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    */
    'storage' => [
        'disk' => env('PDF_DESIGNER_STORAGE_DISK', 'local'),
        'path' => env('PDF_DESIGNER_STORAGE_PATH', 'pdf-documents'),
    ],

    /*
    |--------------------------------------------------------------------------
    | DomPDF Configuration
    |--------------------------------------------------------------------------
    */
    'dompdf' => [
        'default_paper_size' => env('PDF_DESIGNER_PAPER_SIZE', 'a4'),
        'default_orientation' => env('PDF_DESIGNER_ORIENTATION', 'portrait'),
        'default_margins' => [
            'top' => 10,
            'right' => 10,
            'bottom' => 10,
            'left' => 10,
        ],
        'options' => [
            'enable_remote' => env('PDF_DESIGNER_DOMPDF_ENABLE_REMOTE', true),
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
            'isFontSubsettingEnabled' => true,
            'defaultMediaType' => 'print',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Layout Engine Configuration
    |--------------------------------------------------------------------------
    */
    'layout' => [
        'default_unit' => 'mm',
        'dpi' => 96,
        'mm_per_point' => 0.352778,
        'point_per_mm' => 2.83465,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pdf-Engine Configuration
    |--------------------------------------------------------------------------
    | Settings for the composite designer PDF engine (tc-lib-pdf based).
    | Currently a placeholder for future configuration options.
    */
    'pdf-engine' => [
        'enabled' => env('PDF_DESIGNER_PDF_ENGINE_ENABLED', true),
        'default_font' => env('PDF_DESIGNER_PDF_ENGINE_FONT', 'dejavusans'),
        'fileOptions' => [
            'allowedHosts' => array_filter(array_map('trim', explode(',', env('PDF_DESIGNER_ALLOWED_HOSTS', '*')))),
        ],
    ],
];
