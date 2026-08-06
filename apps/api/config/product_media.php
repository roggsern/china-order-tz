<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Product media upload diagnostics
    |--------------------------------------------------------------------------
    |
    | When true, catalog media uploads log safe metadata only (name, MIME,
    | sniffed type, size, upload error, isValid). Default false.
    |
    */

    'upload_diagnostics' => (bool) env('PRODUCT_MEDIA_UPLOAD_DIAGNOSTICS', false),

];
