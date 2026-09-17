<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Update endpoint
    |--------------------------------------------------------------------------
    |
    | URI that receives Ripple action / bind payloads and returns delta patches.
    |
    */
    'update_uri' => '/ripple/update',

    /*
    |--------------------------------------------------------------------------
    | Component namespace
    |--------------------------------------------------------------------------
    |
    | Default namespace prefix used when resolving short component names.
    |
    */
    'component_namespace' => 'App\\Ripple\\',
];
