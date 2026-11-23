<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Safety copies before a deploy
    |--------------------------------------------------------------------------
    |
    | When a new version is about to run a migration, the SQLite database is
    | copied here first (see the backup:before-deploy command), and only the
    | newest copies are kept. On the server the folder sits on the database
    | volume, so the copies outlive the containers.
    |
    */

    'backups' => [
        'path' => env('DEPLOY_BACKUP_PATH', storage_path('database/before-deploy')),
        'keep' => (int) env('DEPLOY_BACKUPS_TO_KEEP', 3),
    ],

];
