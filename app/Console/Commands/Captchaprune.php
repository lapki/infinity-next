<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Settings;
use InfinityNext\LaravelCaptcha\Captcha;

class Captchaprune extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'captchaprune';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically prune captchas based on time elapsed';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $carbonNow = Carbon::now();

        // Delete all expired captchas
        $this->comment('    Pruning expired captchas...');
        $this->handleExpired($carbonNow);

        // Delete all validated captchas that are older than their lifespan.
        $this->comment('    Pruning old validated captchas...');
        $this->handleOld($carbonNow);
    }

    /**
     * Handles expired captchas that were never solved.
     */
    protected function handleExpired($now)
    {
        Captcha::pruneExpired($now);
    }

    /**
     * Handles captchas that were once valid, but are now too old.
     */
    protected function handleOld($now)
    {
        $captchaLife = (int) Settings::get('captchaLifespanTime', 0);

        Captcha::pruneOld($now, $captchaLife);
    }
}
