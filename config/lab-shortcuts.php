<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Laboratory Information System Keyboard Shortcuts Configuration
    |--------------------------------------------------------------------------
    | Centrally remappable keyboard shortcuts for the compact LIS module.
    */

    'global' => [
        'search' => '/',
        'help' => '?',
        'nav_billing' => 'Alt+1',
        'nav_specimen' => 'Alt+2',
        'nav_results' => 'Alt+3',
        'nav_verify' => 'Alt+4',
        'nav_reports' => 'Alt+5',
        'nav_masters' => 'Alt+6',
        'escape' => 'Escape',
    ],

    'billing' => [
        'new_bill' => 'Alt+n',
        'focus_patient' => 'Alt+p',
        'focus_test' => 'Alt+t',
        'discount_panel' => 'Alt+d',
        'payment_panel' => 'Alt+m',
        'save_and_print' => 'Ctrl+Enter',
        'remove_row' => 'Delete',
    ],

    'worklists' => [
        'next_row' => 'j',
        'prev_row' => 'k',
        'open_row' => 'Enter',
        'select_row' => 'Space',
        'switch_tab_prev' => 'Alt+ArrowLeft',
        'switch_tab_next' => 'Alt+ArrowRight',
    ],

    'results' => [
        'save_results' => 'Ctrl+s',
        'validate_verify' => 'Ctrl+Enter',
        'verify_and_next' => 'Ctrl+Shift+Enter',
    ],

    'reports' => [
        'print_pdf' => 'Alt+r',
        'whatsapp' => 'Alt+w',
    ],
];
