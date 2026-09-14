<?php

// Owner-approved catalog knowledge belongs to installation configuration, not generic Core.
return [
    // Initial values only; editable import settings take precedence, including disabling the rule.
    'duration_rule_defaults' => [
        'Manna Vom Himmel' => ['enabled'=>true, 'min_seconds'=>14, 'max_seconds'=>16, 'target_profile'=>'posts'],
    ],
];
