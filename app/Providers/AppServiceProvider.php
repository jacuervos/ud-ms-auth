<?php

namespace App\Providers;

use AzureOss\FlysystemAzureBlobStorage\AzureBlobStorageAdapter;
use AzureOss\Storage\Blob\BlobServiceClient;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Storage::extend('azure', function ($app, $config) {
            $client = BlobServiceClient::fromConnectionString(
                "DefaultEndpointsProtocol=https;AccountName={$config['account-name']};AccountKey={$config['account-key']};EndpointSuffix=core.windows.net"
            );

            $containerClient = $client->getContainerClient($config['container']);
            $adapter = new AzureBlobStorageAdapter($containerClient);
            $filesystem = new Filesystem($adapter);

            return new FilesystemAdapter($filesystem, $adapter, $config);
        });
    }
}
