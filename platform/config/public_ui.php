<?php
// These are sections of one approved website, not alternative design concepts.
return [
    'screens'=>[
        'start'=>['reference'=>'01-start.png','path'=>'/'],
        'videos'=>['reference'=>'02-videos.png','path'=>'/videos'],
        'video'=>['reference'=>'03-video-detail.png','path'=>'/videos/{slug}'],
        'beitraege'=>['reference'=>'04-beitraege.png','path'=>'/beitraege'],
        'beitrag'=>['reference'=>'05-beitrag-detail.png','path'=>'/beitraege/{slug}'],
        'buecher'=>['reference'=>'06-buecher.png','path'=>'/buecher'],
        'buch'=>['reference'=>'07-buch-detail.png','path'=>'/buecher/{slug}'],
        'live'=>['reference'=>'08-live.png','path'=>'/live'],
        'podcast'=>['reference'=>'09-podcast.png','path'=>'/podcast'],
        'community'=>['reference'=>'10-community.png','path'=>'/community'],
    ],
    'navigation'=>[
        'start'=>['label'=>'Start','path'=>'/'], 'videos'=>['label'=>'Videos','path'=>'/videos'],
        'beitraege'=>['label'=>'Beiträge','path'=>'/beitraege'], 'buecher'=>['label'=>'Bücher','path'=>'/buecher'],
        'live'=>['label'=>'Livestreams','path'=>'/live'], 'podcast'=>['label'=>'Podcast','path'=>'/podcast'],
        'community'=>['label'=>'Community','path'=>'/community'], 'about'=>['label'=>'Über uns','path'=>'/ueber-uns'],
    ],
];
