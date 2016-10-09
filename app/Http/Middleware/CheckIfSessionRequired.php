<?php namespace App\Http\Middleware;
use Closure;
class CheckIfSessionRequired {
    /**
     * Implement Etag support
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Get response
        $response = $next($request);
        if (user()->user_id === null) { // User is not logged in, don't set header
            $response->header('X-No-Session', 'yes');
        }
        // Send response
        return $response;
    }
}
