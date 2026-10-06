<?php

return [
    'default_skd_target' => 'cpns',

    // Draw exam questions from a shared cached list of question IDs instead of
    // ORDER BY RAND() per participant (App\Support\QuestionPool). Set
    // EXAM_QUESTION_POOL=false to go back to the database query.
    'question_pool' => (bool) env('EXAM_QUESTION_POOL', true),

    // Requests slower than this are logged as "Slow request" warnings (shown
    // on the admin Kesehatan Sistem page). 0 turns the logging off.
    'slow_request_ms' => (int) env('EXAM_SLOW_REQUEST_MS', 1000),

    'passing_grades' => [
        'cpns' => [
            'twk' => 65,
            'tiu' => 80,
            'tkp' => 166,
            'total' => 311,
        ],
        'sekolah_kedinasan' => [
            'twk' => 65,
            'tiu' => 80,
            'tkp' => 156,
            'total' => 301,
        ],
    ],

    'score_max' => [
        'twk' => 150,
        'tiu' => 175,
        'tkp' => 225,
        'total' => 550,
    ],
];
