<?php

$EM_CONF[$_EXTKEY] = array(
    'title' => 'Textmedia Migration',
    'description' => 'Allows for gradual migration of content elements to fluid_styled_content',
    'category' => 'misc',
    'author' => 'Cedric Ziel',
    'author_email' => 'cedric@cedric-ziel.com',
    'state' => 'stable',
    'internal' => '',
    'uploadfolder' => '0',
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'version' => '1.0.1',
    'constraints' => array(
        'depends' => array(
            'typo3' => '7.6.0-7.99.99',
            'fluid_styled_content' => '7.6.0-7.99.99',
        ),
        'conflicts' => array(),
        'suggests' => array(),
    ),
    'autoload' => array(
        'psr-4' => array(
            'CedricZiel\\TextmediaMigration\\' => 'Classes',
        ),
    ),
);
