<?php

return [
    'number_prefix' => env('ORDER_NUMBER_PREFIX', 'COTZ'),
    'number_sequence_padding' => (int) env('ORDER_NUMBER_SEQUENCE_PADDING', 6),
];
