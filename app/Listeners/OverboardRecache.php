<?php

namespace App\Listeners;

use Acetone;
use Cache;
use DB;

class OverboardRecache extends Listener
{
    /**
     * Handle the event.
     *
     * @param Event $event
     */
    public function handle($event)
    {
        switch (config('cache.default')) {
            case 'file':
                for ($i = 1; $i <= 1; ++$i) {
                    Cache::forget("site.overboard.page.{$i}");
                }
                break;

            case 'database':
                DB::table('cache')
                    ->where('key', 'like', '%site.overboard.page.%')
                    ->delete();
                break;

            default:
                Cache::tags('site.overboard.pages')->flush();
                break;
        }

        if (env('APP_VARNISH')) {
            Acetone::purge('/overboard.html');
        }
    }
}
