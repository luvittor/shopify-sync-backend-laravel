<?php

namespace App\Console\Commands;

use App\Services\ProductService;
use Illuminate\Console\Command;

class SyncShopifyProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync products from Shopify to local database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Starting Shopify products sync...');
        
        try {
            $productService = app(ProductService::class);
            
            $this->line('📡 Fetching products from Shopify...');
            
            $result = $productService->syncFromShopify();
            
            $this->newLine();
            $this->info('✅ Sync completed successfully!');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total products', $result['total']],
                    ['Synced', $result['synced']],
                    ['Skipped', $result['skipped']],
                ]
            );
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ Sync failed: ' . $e->getMessage());
            
            if ($this->getOutput()->isVerbose()) {
                $this->line('');
                $this->line('Stack trace:');
                $this->line($e->getTraceAsString());
            } else {
                $this->line('Use -v flag for more details.');
            }
            
            return Command::FAILURE;
        }
    }
}
