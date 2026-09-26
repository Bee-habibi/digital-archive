<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Filesystem\FilesystemAdapter;
use App\Models\Archive;
use App\Policies\ArchivePolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDriveAdapter;
use Google\Client;
use Google\Service\Drive;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Disk "google" untuk Google Drive (Flysystem adapter + Google API client).
        // Dipakai saat FILESYSTEM_ARCHIVE_DISK=google, lihat config/filesystems.php
        // dan panduan kredensial di GOOGLE-DRIVE-SETUP.md.
        // Catatan: FilesystemManager::callCustomCreator memanggil callback dengan
        // ($app, $config) — argumen pertama adalah Application, bukan config.
        Storage::extend('google', function ($app, array $config) {
            $client = new Client();
            $client->setClientId($config['clientId']);
            $client->setClientSecret($config['clientSecret']);
            $client->refreshToken($config['refreshToken']);
            $client->setApplicationName(config('app.name', 'Arsip Digital'));

            $options = [];
            if (! empty($config['folderId'])) {
                // Pakai folder tertentu via ID (paling andal, nama folder boleh berubah)
                $options['sharedFolderId'] = $config['folderId'];
            }

            $adapter = new GoogleDriveAdapter(
                new Drive($client),
                $config['folderId'] ? null : ($config['folder'] ?: 'Arsip Digital'),
                $options
            );

            return new FilesystemAdapter(
                new Filesystem($adapter),
                $adapter,
                $config
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Archive::class, ArchivePolicy::class);

        // Render pagination with Bootstrap 5 markup (the app loads Bootstrap,
        // not Tailwind - the default paginator's SVG arrows render huge here).
        Paginator::useBootstrapFive();
    }
}
