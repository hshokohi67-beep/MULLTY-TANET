<?php

namespace App\Modules\Notifications\Console;

use App\Support\Push\WebPushCrypto;
use Illuminate\Console\Command;

/** Prints a new VAPID key pair for .env. Run once per platform; changing keys drops every subscription. */
final class VapidCommand extends Command
{
    protected $signature = 'push:vapid';

    protected $description = 'Generate a VAPID key pair for browser push notifications';

    public function handle(): int
    {
        ['private' => $key, 'public' => $public] = WebPushCrypto::newKeyPair();

        $this->line('Add these to the API .env (keep the private key secret):');
        $this->newLine();
        $this->line('WEBPUSH_PUBLIC_KEY='.WebPushCrypto::b64($public));
        $this->line('WEBPUSH_PRIVATE_KEY='.base64_encode(WebPushCrypto::exportPrivate($key)));
        $this->line('WEBPUSH_SUBJECT=mailto:support@your-domain');

        return self::SUCCESS;
    }
}
