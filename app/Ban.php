<?php

namespace App;

use App\Support\IP as IP;
use App\Contracts\PermissionUser as PermissionUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use File;
use Request;

class Ban extends Model
{
    use \App\Traits\EloquentBinary;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'bans';

    /**
     * The primary key that is used by ::get().
     *
     * @var string
     */
    protected $primaryKey = 'ban_id';

    /**
     * Attributes which are automatically sent through a Carbon instance on load.
     *
     * @var array
     */
    protected $dates = ['created_at', 'updated_at', 'expires_at'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['ban_ip_start', 'ban_ip_end', 'board_uri', 'seen', 'created_at', 'updated_at', 'expires_at', 'mod_id', 'post_id', 'ban_reason_id', 'justification', 'is_robot'];

    public function appeals()
    {
        return $this->hasMany('\App\BanAppeal', 'ban_id');
    }

    public function board()
    {
        return $this->belongsTo('\App\Board', 'board_uri');
    }

    public function mod()
    {
        return $this->belongsTo('\App\User', 'mod_id', 'user_id');
    }

    public function post()
    {
        return $this->belongsTo('\App\Post', 'post_id');
    }

    /**
     * Automatically adds a R9K ban to the specified board.
     *
     * @static
     *
     * @param \App\Board         $board The board the ban is to be added in.
     * @param binary|string|null $ip    Optional. The IP to ban. Defaults to client IP.
     *
     * @return static
     */
    public static function addRobotBan(Board $board, $ip = null)
    {
        $ip = new IP($ip);

        // Default time is 2 seconds.
        $time = 2;

        // Pull the ban that expires the latest.
        $ban = $board->bans()
            ->whereIpInBan($ip)
            ->whereRobot()
            ->orderBy('expires_at', 'desc')
            ->first();

        if ($ban instanceof static) {
            if (!$ban->isExpired()) {
                return $ban;
            }

            $time = $ban->created_at->diffInSeconds($ban->expires_at) * 2;
        }

        return static::create([
            'ban_ip_start' => $ip,
            'ban_ip_end' => $ip,
            'expires_at' => Carbon::now()->addSeconds($time),
            'board_uri' => $board->board_uri,
            'is_robot' => true,
            'justification' => trans('validation.custom.unoriginal_content'),
        ]);
    }

    /**
     * Determines if this ban can be appealed.
     *
     * @return bool
     */
    public function canAppeal()
    {
        return is_null($this->getAppeal());
    }

    /**
     * Determines if a user can view this ban (as moderator or client).
     *
     * @param PermissionUser $user
     *
     * @return bool
     */
    public function canView(PermissionUser $user)
    {
        return $this->isBanForIP() || $user->canViewBan($this);
    }

    /**
     * Mutator that creates a single IP instance with the start and end ban IPs.
     *
     * @return IP
     */
    public function getBanIpAttribute()
    {
        return new IP($this->ban_ip_start, $this->ban_ip_end);
    }

    /**
     * Gets our binary value and unwraps it from any stream wrappers.
     *
     * @param mixed $value
     *
     * @return IP
     */
    public function getBanIpStartAttribute($value)
    {
        return new IP($value);
    }

    /**
     * Gets our binary value and unwraps it from any stream wrappers.
     *
     * @param mixed $value
     *
     * @return IP
     */
    public function getBanIpEndAttribute($value)
    {
        return new IP($value);
    }

    /**
     * Sets our binary value and encodes it if required.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function setBanIpStartAttribute($value)
    {
        $this->attributes['ban_ip_start'] = (new IP($value))->toSQL();
    }

    /**
     * Sets our binary value and encodes it if required.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function setBanIpEndAttribute($value)
    {
        $this->attributes['ban_ip_end'] = (new IP($value))->toSQL();
    }

    /**
     * Fetches the last appeal for this IP on this ban.
     *
     * @param string $ip Optional. Human-readable IP. Defaults to the request.
     *
     * @return \App\BanAppeal
     */
    public function getAppeal($ip = null)
    {
        return $this->appeals()
            ->where('appeal_ip', (new IP($ip))->toSQL())
            ->orderBy('ban_appeal_id', 'desc')
            ->first();
    }

    /**
     * Fetches the latest applicable ban.
     *
     * @param string $ip Human-readable IP.
     * @param  string|null|false  (Board|Global Only|Both)
     *
     * @return \App\Ban
     */
    public static function getBan($ip, $board_uri = null)
    {
        return self::ipString($ip)
            ->board($board_uri)
            ->whereActive()
            ->orderBy('board_uri', 'desc') // Prioritizes local over global bans.
            ->take(1)
            ->get()
            ->last();
    }

    /**
     * Fetches all applicable bans.
     *
     * @param string            $ip        Human-readable IP.
     * @param string|null|false $board_uri Board|Global Only|Both
     *
     * @return Ban
     */
    public static function getBans($ip, $board_uri = null, $fetch = true)
    {
        $query = self::ipString($ip)
            ->board($board_uri)
            ->whereActive()
            ->orderBy('board_uri', 'desc') // Prioritizes local over global bans.
            ->with('mod');

        return $fetch ? $query->get() : $query;
    }

    /**
     * Returns a fully qualified URL for a route on this ban.
     *
     * @param  bool  $absolute  Options indicator if the URL is to be absolute.
     *
     * @return string
     */
    public function getUrl($absolute = false)
    {
        if ($this->isGlobal()) {
            $route = 'panel.global.ban';
            $parameters = ['ban' => $this->ban_id];
        } else {
            $route = 'panel.board.ban';
            $parameters = ['board' => $this->board_uri, 'ban' => $this->ban_id];
        }

        return route($route, $parameters, $absolute);
    }

    /**
     * Returns a random image from the Robot directory.
     *
     * @static
     *
     * @return string
     */
    public static function getRobotImage()
    {
        $images = File::allFiles(base_path().'/public/static/img/errors/robot');
        $filename = $images[array_rand($images)]->getFilename();

        return asset("static/img/errors/robot/{$filename}");
    }

    public function getAppealUrl(array $params = [], $abs = true)
    {
        return $this->getUrl("appeal", $params, $abs);
    }

    public static function isBanned($ip, $board = null)
    {
        $board_uri = null;

        if ($board instanceof Board) {
            $board_uri = $board->board_uri;
        } elseif ($board != '') {
            $board_uri = $board;
        }

        return static::getBan($ip, $board_uri) ? true : false;
    }

    /**
     * Determines if this ban applies to the requesting client.
     *
     * @param  string  Optional. IP to be checked. Defaults to request IP.
     *
     * @return bool If the IP is within he range of this ban.
     */
    public function isBanForIP($ip = null)
    {
        return IP::cidr_intersect(new IP($ip), $this->ban_ip);
    }

    public function isExpired()
    {
        return !is_null($this->expires_at) && $this->expires_at->isPast();
    }

    /**
     * Returns if this ban applies to all boards.
     *
     * @return bool
     */
    public function isGlobal()
    {
        return is_null($this->board_uri);
    }

    public function scopeBoard($query, $board_uri = null)
    {
        if ($board_uri === false) {
            return $query;
        } elseif (is_null($board_uri)) {
            return $query->whereNull('board_uri');
        }

        return $query
            ->where(function ($query) use ($board_uri) {
                $query
                    ->where('board_uri', '=', $board_uri)
                    ->orWhereNull('board_uri');
            });
    }

    public function scopeWhereIPInBan($query, $ip)
    {
        $ip = new IP($ip);

        return $query->where(function ($query) use ($ip) {
            $query->where('ban_ip_start', '<=', $ip->toSQL());
            $query->where('ban_ip_end', '>=', $ip->toSQL());
        });
    }

    public function scopeIpString($query, $ip)
    {
        return $query->whereIPInBan($ip);
    }

    public function scopeIpBinary($query, $ip)
    {
        return $query->whereIPInBan($ip);
    }

    public function scopeWhereActive($query)
    {
        return $query
            ->where(function ($query) {
                $query->whereCurrent();
                $query->orWhere('seen', false);
            })
            ->whereUnappealed();
    }

    public function scopeWhereAppealed($query)
    {
        return $query->whereHas('appeals', function ($query) {
            $query->where('approved', true);
        });
    }

    public function scopeWhereCurrent($query)
    {
        return $query->where('expires_at', '>', $this->freshTimestamp());
    }

    public function scopeWhereUnappealed($query)
    {
        return $query->whereDoesntHave('appeals', function ($query) {
            $query->where('approved', true);
        });
    }

    public function scopeWhereRobot($query)
    {
        return $query->where('is_robot', true);
    }

    public function willExpire()
    {
        return !is_null($this->expires_at);
    }
}
