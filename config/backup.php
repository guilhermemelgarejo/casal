<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Senha do Arquivo ZIP (AES-256)
    |--------------------------------------------------------------------------
    | Senha utilizada para criptografar o dump do banco de dados.
    | Guarde esta senha em local seguro para poder restaurar o arquivo.
    */
    'zip_password' => env('BACKUP_ZIP_PASSWORD', ''),

    /*
    |--------------------------------------------------------------------------
    | Integração com Google Drive
    |--------------------------------------------------------------------------
    | Caminho para o JSON da Conta de Serviço do Google Cloud (Service Account)
    | e ID da pasta de destino no Google Drive onde os backups serão salvos.
    */
    'google_drive' => [
        'credentials_path' => env('GOOGLE_DRIVE_CREDENTIALS_PATH', storage_path('app/google-credentials.json')),
        'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notificação por E-mail
    |--------------------------------------------------------------------------
    | Destinatário que receberá o link do Google Drive após a conclusão.
    */
    'notification_email' => env('BACKUP_NOTIFICATION_EMAIL', ''),
];

