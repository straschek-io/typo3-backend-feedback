<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Backend Feedback',
    'description' => 'Backend toolbar button for editors to send feedback such as a missing permission, with the current view attached, by mail',
    'category' => 'be',
    'author' => 'Michael Straschek',
    'author_email' => 'hallo@straschek.io',
    'state' => 'stable',
    'version' => '1.0.1',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
        ],
    ],
];
