<?php

// Owner-approved catalog knowledge belongs to installation configuration, not generic Core.
return [
    'catalog_rules' => [
        'Manna Vom Himmel' => [
            [
                'id' => 'fifteen_second_posts',
                'kinds' => ['video', 'short'],
                'duration_seconds_min' => 14,
                'duration_seconds_max' => 16,
                'target_profile' => 'posts',
                'guidance' => 'The owner reports that approximately 15-second clips in this catalog are Beiträge (posts), not regular videos or Shorts. Prefer posts for these clips. Do not classify all short videos as posts. If other evidence contradicts this pattern, use confidence below 0.85 for human review. Missing duration does not establish a match.',
            ],
        ],
    ],
];
