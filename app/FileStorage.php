<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Intervention\Image\ImageManager;
use Symfony\Component\HttpFoundation\File\File as SymfonyFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use File;
use Input;
use Settings;
use Sleuth;
use Storage;

/**
 * Model representing files in our storage system.
 *
 * Can represent attachments and is used for content renering on posts.
 *
 * @category   Model
 *
 * @author     Joshua Moon <josh@jaw.sh>
 * @copyright  2016 Infinity Next Development Group
 * @license    http://www.gnu.org/licenses/agpl-3.0.en.html AGPL3
 *
 * @since      0.5.1
 */
class FileStorage extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'files';

    /**
     * The database primary key.
     *
     * @var string
     */
    protected $primaryKey = 'file_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'hash',
        'banned',
        'filesize',
        'file_width',
        'file_height',
        'mime',
        'meta',
        'first_uploaded_at',
        'last_uploaded_at',
        'upload_count',
        'has_thumbnail',
        'thumbnail_width',
        'thumbnail_height',
    ];

    /**
     * Determines if Laravel should set created_at and updated_at timestamps.
     *
     * @var array
     */
    public $timestamps = false;

    /**
     * The \App\FileAttachment relationship.
     * Represents a post -> storage relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function attachments()
    {
        return $this->hasMany('\App\FileAttachment', 'file_id');
    }

    /**
     * The \App\BoardAsset relationship.
     * Used for multiple custom facets of a board.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function assets()
    {
        return $this->hasMany('\App\BoardAsset', 'file_id');
    }

    /**
     * The \App\Posts relationship.
     * Uses the attachments() relationship to find posts where this file is attached..
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function posts()
    {
        return $this->belongsToMany("\App\Post", 'file_attachments', 'file_id', 'post_id')->withPivot('filename', 'position');
    }

    /**
     * Will trigger a file deletion if the storage item is not used anywhere.
     *
     * @return bool
     */
    public function challengeExistence()
    {
        $count = $this->assets->count() + $this->attachments->count();

        if ($count === 0) {
            $this->deleteFile();

            return false;
        }

        return true;
    }

    public static function checkUploadExists(UploadedFile $file, Board $board, Post $thread = null)
    {
        return static::checkHashExists(md5((string) File::get($file)), $board, $thread);
    }

    public static function checkHashExists($hash, Board $board, Post $thread = null)
    {
        $query = Post::where('board_uri', $board->board_uri);

        if (!is_null($thread)) {
            $query = $query->whereInThread($thread);
        }

        return $query->whereHas('attachments', function ($query) use ($hash) {
            $query->whereHash($hash);
        })->first();
    }

    /**
     * Creates a new FileAttachment for a post using a direct upload.
     *
     * @param UploadedFile $file
     * @param Post         $post
     *
     * @return FileAttachment
     */
    public static function createAttachmentFromUpload(UploadedFile $file, Post $post, $autosave = true)
    {
        $storage = static::storeUpload($file);

        $uploadName = urlencode($file->getClientOriginalName());
        $uploadExt = pathinfo($uploadName, PATHINFO_EXTENSION);

        $fileName = basename($uploadName, '.'.$uploadExt);
        $fileExt = $storage->guessExtension();

        $attachment = new FileAttachment();
        $attachment->post_id = $post->post_id;
        $attachment->file_id = $storage->file_id;
        $attachment->filename = urlencode("{$fileName}.{$fileExt}");
        $attachment->is_spoiler = (bool) Input::get('spoilers');

        if ($autosave) {
            $attachment->save();

            ++$storage->upload_count;
            $storage->save();
        }

        return $attachment;
    }

    /**
     * Creates a new FileAttachment for a post using a hash.
     *
     * @param Post   $post
     * @param string $filename
     * @param bool   $spoiler
     *
     * @return FileAttachment
     */
    public function createAttachmentWithThis(Post $post, $filename, $spoiler = false, $autosave = true)
    {
        $fileName = pathinfo($filename, PATHINFO_FILENAME);
        $fileExt = $this->guessExtension();

        $attachment = new FileAttachment();
        $attachment->post_id = $post->post_id;
        $attachment->file_id = $this->file_id;
        $attachment->filename = urlencode("{$fileName}.{$fileExt}");
        $attachment->is_spoiler = (bool) $spoiler;

        if ($autosave) {
            $attachment->save();

            ++$this->upload_count;
            $this->save();
        }

        return $attachment;
    }

    /**
     * Removes the associated file for this storage.
     *
     * @return bool Success. Will return FALSE if the file was already gone.
     */
    public function deleteFile()
    {
        return unlink($this->getFullPath()) && unlink($this->getFullPathThumb());
    }

    /**
     * Returns the storage's file as a filesystem.
     *
     * @return \Illuminate\Filesystem\Filesystem
     */
    public function getAsFile()
    {
        return new File($this->getFullPath());
    }

    /**
     * Returns the storage's thumbnail as a filesystem.
     *
     * @return \Illuminate\Filesystem\Filesystem
     */
    public function getAsFileThumb()
    {
        return new File($this->getFullPathThumb());
    }

    /**
     * Returns the attachment's base filename.
     *
     * @return string
     */
    public function getBaseFileName()
    {
        $pathinfo = pathinfo($this->pivot->filename);

        return $pathinfo['filename'];
    }

    /**
     * Returns the storage directory, minus the file name.
     *
     * @return string
     */
    public function getDirectory()
    {
        $prefix = $this->getHashPrefix($this->hash);

        return "attachments/full/{$prefix}";
    }

    /**
     * Returns the thumbnail's storage directory, minus the file name.
     *
     * @return string
     */
    public function getDirectoryThumb()
    {
        $prefix = $this->getHashPrefix($this->hash);

        return "attachments/thumb/{$prefix}";
    }

    /**
     * Supplies a download name.
     *
     * @return string
     */
    public function getDownloadName()
    {
        return "{$this->getFileName()}.{$this->guessExtension()}";
    }

    /**
     * Supplies a clean URL for downloading an attachment on a board.
     *
     * @param App\Board $board
     *
     * @return string
     */
    public function getDownloadUrl(Board $board)
    {
        $params = [
            'attachment' => $this->pivot->attachment_id,
            'filename' => $this->getDownloadName(),
        ];

        if (!config('app.url_media', false)) {
            $params['board'] = $board;
        }

        return route('static.file.attachment', $params, config('app.url_media', false));
    }

    /**
     * Returns the attachment's extension.
     *
     * @return string
     */
    public function getExtension()
    {
        $pathinfo = pathinfo($this->pivot->filename);

        return $pathinfo['extension'];
    }

    /**
     * Returns the dimensions of the thumbnail, if possible.
     *
     * @return string|null
     */
    public function getFileDimensions()
    {
        if ($this->has_thumbnail) {
            return "{$this->file_width}x{$this->file_height}";
        }

        return;
    }

    /**
     * Determines and returns the "xxx" of "/url/xxx.ext" for URLs.
     *
     * @param string|null $format Optional. The token syntax for the filename. Defaults to site setting.
     *
     * @return string
     */
    public function getFileName($nameFormat = null)
    {
        if (is_null($nameFormat)) {
            // Build a thumbnail using the admin settings.
            $nameFormat = Settings::get('attachmentName');
        }

        $first_uploade_at = new \Carbon\Carbon($this->first_uploaded_at);

        $bits['t'] = $first_uploade_at->timestamp;
        $bits['i'] = 0;
        $bits['n'] = $bits['t'];

        if (isset($this->pivot)) {
            if (isset($this->pivot->position)) {
                $bits['i'] = $this->pivot->position;
            }

            if (isset($this->pivot->filename)) {
                $bits['n'] = $this->getBaseFileName();
            }
        }

        $attachmentName = $nameFormat;

        foreach ($bits as $bitKey => $bitVal) {
            $attachmentName = str_replace("%{$bitKey}", $bitVal, $attachmentName);
        }

        return $attachmentName;
    }

    /**
     * Returns the full internal file path.
     *
     * @return string
     */
    public function getFullPath()
    {
        $storagePath = Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix();

        return "{$storagePath}{$this->getPath()}";
    }

    /**
     * Returns the full internal file path for the thumbnail.
     *
     * @return string
     */
    public function getFullPathThumb()
    {
        $storagePath = Storage::disk('local')->getDriver()->getAdapter()->getPathPrefix();

        return "{$storagePath}{$this->getPathThumb()}";
    }

    /**
     * Fetch an instance of static using the checksum.
     *
     * @param  $hash  Checksum
     *
     * @return static|null
     */
    public static function getHash($hash)
    {
        return static::whereHash($hash)->get()->first();
    }

    /**
     * Returns the skip file directoy prefix.
     *
     * @param  $hash  Checksum
     *
     * @return static Like "a/a/a/a"
     */
    public static function getHashPrefix($hash)
    {
        return implode(str_split(substr($hash, 0, 4)), '/');
    }

    /**
     * Converts the byte size to a human-readable filesize.
     *
     * @author Jeffrey Sambells
     *
     * @param int $decimals
     *
     * @return string
     */
    public function getHumanFilesize($decimals = 2)
    {
        $bytes = $this->filesize;
        $size = array('B', 'kiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB');
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)).' '.@$size[$factor];
    }

    /**
     * Returns a URL part based on available information.
     *
     * @return string|int
     */
    public function getIdentifier()
    {
        if (isset($this->pivot)) {
            return $this->pivot->attachment_id;
        }

        return $this->hash;
    }

    /**
     * Returns the relative internal file path.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->getDirectory().'/'.$this->hash;
    }

    /**
     * Returns the full meta array, if the key is not specified.
     *
     * @param  $key     Defaults to null.
     * @return mixed
     */
    public function getMeta($key = null)
    {
        $meta = json_decode($this->meta, true);

        if (is_null($key))
        {
            return $meta;
        }
        elseif (array_key_exists($key, $meta))
        {
            return $meta[$key];
        }
        else
        {
            return false;
        }
    }

    /**
     * Returns the full internal file path for the thumbnail.
     *
     * @return string
     */
    public function getPathThumb()
    {
        return $this->getDirectoryThumb().'/'.$this->hash;
    }

    /**
     * Returns a removal URL.
     *
     * @param \App\Board $board
     *
     * @return string
     */
    public function getRemoveUrl(Board $board)
    {
        return $board->getUrl('file.delete', [
            'attachment' => $this->pivot->attachment_id,
        ], false);
    }

    /**
     * Truncates the middle of a filename to show extension.
     *
     * @return string Filename.
     */
    public function getShortFilename()
    {
        if (isset($this->pivot) && isset($this->pivot->filename)) {
            $filename = urldecode($this->pivot->filename);

            if (mb_strlen($filename) <= 20) {
                return $filename;
            }

            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $name = mb_substr($name, 0, 15);

            return "{$name}... .{$ext}";
        }

        return $this->getFileName();
    }

    /**
     * Returns a spoiler URL.
     *
     * @param \App\Board $board
     *
     * @return string
     */
    public function getSpoilerUrl(Board $board)
    {
        return $board->getUrl('file.spoiler', [
            'attachment' => $this->pivot->attachment_id,
        ], false);
    }

    /**
     * Returns a string containing class names.
     *
     * @return string
     */
    public function getThumbnailClasses()
    {
        $ext = $this->guessExtension();
        $type = 'other';
        $stock = true;
        $spoil = $this->isSpoiler();

        if ($this->isImageVector()) {
            $stock = false;
            $type = 'img';
        } elseif ($this->isImage()) {
            if ($this->hasThumb()) {
                $stock = false;
                $type = 'img';
            }
        } elseif ($this->isVideo()) {
            if ($this->hasThumb()) {
                $stock = false;
                $type = 'video';
            }
        } elseif ($this->isAudio()) {
            $stock = false;
            $type = 'audio';
        }
        else if ($this->isDocument())
        {
            if ($this->hasThumb())
            {
                $stock = false;
                $type  = "document";
            }
        }

        $classes = [];
        $classes['type'] = "attachment-type-{$type}";
        $classes['ext'] = "attachent-ext-{$ext}";
        $classes['stock'] = $stock ? 'thumbnail-stock' : 'thumbnail-content';
        $classes['spoil'] = $spoil ? 'thumbnail-spoiler' : 'thumbnail-not-spoiler';
        $classHTML = implode(' ', $classes);

        return $classHTML;
    }

    /**
     * Returns an XML valid attachment HTML string that handles missing thumbnail URLs.
     *
     * @param \App\Board $board    The board this thumbnail will belong to.
     * @param int        $maxWidth Optional. Maximum width constraint. Defaults null.
     *
     * @return string as HTML
     */
    public function getThumbnailHtml(Board $board, $maxDimension = null)
    {
        $ext = $this->guessExtension();
        $mime = $this->mime;
        $url = media_url("static/img/filetypes/{$ext}.svg", false);
        $spoil = $this->isSpoiler();
        $deleted = $this->isDeleted();
        $md5     = $deleted ? null : $this->hash;

        if ($deleted) {
            $url = $board->getAssetUrl('file_deleted');
        } elseif ($spoil) {
            $url = $board->getAssetUrl('file_spoiler');
        }
        else if ($this->isImageVector())
        {
            $url = $this->getDownloadURL($board);
        }
        else if ($this->isAudio() || $this->isImage() || $this->isVideo() || $this->isDocument())
        {
            if ($this->hasThumb())
            {
                $url = $this->getThumbnailURL($board);
            }
            else if ($this->isAudio())
            {
                $url = media_url("static/img/assets/audio.gif", false);
            }
        }

        $classHTML = $this->getThumbnailClasses();


        // Measure dimensions.
        $height = 'auto';
        $width = 'auto';
        $maxWidth = 'none';
        $maxHeight = 'none';
        $minWidth = 'none';
        $minHeight = 'none';
        $oHeight = $this->thumbnail_height;
        $oWidth = $this->thumbnail_width;

        if ($this->has_thumbnail && !$this->isSpoiler() && !$this->isDeleted()) {
            $height = $oHeight.'px';
            $width = $this->thumbnail_width.'px';

            if (is_int($maxDimension) && ($oWidth > $maxDimension || $oHeight > $maxDimension)) {
                if ($oWidth > $oHeight) {
                    $height = 'auto';
                    $width = $maxDimension.'px';
                } elseif ($oWidth < $oHeight) {
                    $height = $maxDimension.'px';
                    $width = 'auto';
                } else {
                    $height = $maxDimension;
                    $width = $maxDimension;
                }
            }

            $minWidth = $width;
            $minHeight = $height;
        } else {
            $maxWidth = Settings::get('attachmentThumbnailSize').'px';
            $maxHeight = $maxWidth;
            $width = $maxWidth;
            $height = 'auto';

            if (is_int($maxDimension)) {
                $maxWidth = "{$maxDimension}px";
                $maxHeight = "{$maxDimension}px";
            }

            if ($this->isSpoiler() || $this->isDeleted()) {
                $minHeight = 'none';
                $minWidth = 'none';
                $width = $maxWidth;
            }
        }

        return "<div class=\"attachment-wrapper\" style=\"height: {$height}; width: {$width};\">" .
            "<img class=\"attachment-img {$classHTML}\" src=\"{$url}\" data-mime=\"{$mime}\" data-md5=\"{$md5}\" style=\"height: {$height}; width: {$width};\"/>" .
        "</div>";
    }

    /**
     * Supplies a clean thumbnail URL for embedding an attachment on a board.
     *
     * @param \App\Board $board
     *
     * @return string
     */
    public function getThumbnailUrl(Board $board)
    {
        $ext = $this->guessExtension();

        if ($this->isSpoiler()) {
            return $board->getSpoilerUrl();
        }

        if ($this->isImage() || $this->isDocument())
        {
            $ext = Settings::get('attachmentThumbnailJpeg') ? "jpg" : "png";
        }
        else if ($this->isVideo())
        {
            $ext = "jpg";
        }
        else if ($this->isAudio())
        {
            if (!$this->hasThumb())
            {
                return $board->getAudioArtURL();
            }

            $ext = 'png';
        } elseif ($this->isImageVector()) {
            // With the SVG filetype, we do not generate a thumbnail, so just serve the actual SVG.
            return $this->getDownloadUrl($board);
        }

        $params = [
            'attachment' => $this->getIdentifier(),
            'filename' => "thumb_".$this->getDownloadName().".{$ext}",
        ];

        if (!config('app.url_media', false)) {
            $params['board'] = $board;
        }

        return route('static.thumb.attachment', $params, config('app.url_media', false));
    }

    /**
     * Returns an unspoiler URL.
     *
     * @param \App\Board $board
     *
     * @return string
     */
    public function getUnspoilerUrl(Board $board)
    {
        return $board->getUrl('file.unspoiler', [
            'attachment' => $this->pivot->attachment_id,
        ], false);
    }

    /**
     * A dumb way to guess the file type based on the mime.
     *
     * @return string
     */
    public function guessExtension()
    {
        $mimes = explode('/', $this->mime);

        switch ($this->mime) {
            //#
            // IMAGES
            //#
            case 'image/svg+xml':
                return 'svg';
            case 'image/jpeg':
            case 'image/jpg':
                return 'jpg';
            case 'image/gif':
                return 'gif';
            case 'image/png':
                return 'png';
            case 'image/vnd.adobe.photoshop';
                return 'psd';
            case 'image/x-icon';
                return 'ico';
            //#
            // DOCUMENTS
            //#
            case 'text/plain':
                return 'txt';
            case 'application/epub+zip':
                return 'epub';
            case 'application/pdf':
                return 'pdf';
            //#
            // AUDIO
            //#
            case 'audio/mpeg':
            case 'audio/mp3':
                return 'mp3';
            case 'audio/aac':
                return 'aac';
            case 'audio/mp4':
                return 'mp3';
            case 'audio/ogg':
                return 'ogg';
            case 'audio/wave':
                return 'wav';
            case 'audio/webm':
                return 'wav';
            case 'audio/x-matroska':
                return 'mka';
            //#
            // VIDEO
            //#
            case 'video/3gp':
                return '3gp';
            case 'video/webm':
                return 'webm';
            case 'video/mp4':
                return 'mp4';
            case 'video/ogg':
                return 'ogg';
            case 'video/x-flv':
                return 'flv';
            case 'video/x-matroska':
                return 'mkv';
        }


        if (count($mimes) > 1) {
            return $mimes[1];
        } elseif (count($mimes) === 1) {
            return $mimes[0];
        }

        return 'UNKNOWN';
    }

    /**
     * Returns if the file is present on the disk.
     *
     * @return bool
     */
    public function hasFile()
    {
        return is_readable($this->getFullPath()) && Storage::exists($this->getPath());
    }

    /**
     * Returns if a thumbnail is present on the disk.
     *
     * @return bool
     */
    public function hasThumb()
    {
        return (bool) $this->has_thumbnail;
        //return is_link($this->getPathThumb()) || Storage::exists($this->getPathThumb());
    }

    /**
     * Is this attachment audio?
     *
     * @return bool
     */
    public function isAudio()
    {
        switch ($this->mime) {
            case 'audio/mpeg':
            case 'audio/mp3':
            case 'audio/aac':
            case 'audio/mp4':
            case 'audio/ogg':
            case 'audio/wave':
            case 'audio/webm':
            case 'audio/x-matroska':
                return true;
        }

        return false;
    }

    /**
     * Returns if our pivot is deleted.
     *
     * @return bool
     */
    public function isDeleted()
    {
        return isset($this->pivot)
            && isset($this->pivot->is_deleted)
            && (bool) $this->pivot->is_deleted;
    }

    /**
     * Is this attachment a document?
     *
     * @return boolean
     */
    public function isDocument()
    {
        switch ($this->mime)
        {
            case 'application/epub+zip' :
            case 'application/pdf' :
                return true;
        }

        return false;
    }

    /**
     * Is this attachment an image?
     *
     * @return bool
     */
    public function isImage()
    {
        switch ($this->mime) {
            case 'image/jpeg':
            case 'image/jpg':
            case 'image/gif':
            case 'image/png':
            // These are only thumbnailable with the Imagick backend.
            case 'image/vnd.adobe.photoshop':
            case 'image/x-icon':
                return true;
        }

        return false;
    }

    /**
     * Is this attachment an image vector (SVG)?
     *
     * @reutrn boolean
     */
    public function isImageVector()
    {
        return $this->mime === 'image/svg+xml';
    }

    /**
     * Returns if our pivot is a spoiler.
     *
     * @return bool
     */
    public function isSpoiler()
    {
        return isset($this->pivot) && isset($this->pivot->is_spoiler) && (bool) $this->pivot->is_spoiler;
    }

    /**
     * Is this attachment a video?
     * Primarily used to split files on HTTP range requests.
     *
     * @return bool
     */
    public function isVideo()
    {
        switch ($this->mime) {
            case 'video/3gp':
            case 'video/webm':
            case 'video/mp4':
            case 'video/ogg':
            case 'video/x-flv':
            case 'video/x-matroska':
                return true;
        }

        return false;
    }

    /**
     * Work to be done upon creating an attachment using this storage.
     *
     * @param FileAttachment $attachment Defaults to null.
     *
     * @return FileStorage
     */
    public function processAttachment(FileAttachment $attachment = null)
    {
        $this->last_uploaded_at = $this->freshTimestamp();
        // Not counting uploads unless it ends up on a post.
        // $this->upload_count    += 1;

        $this->processThumb();
        $this->save();

        return $this;
    }

    /**
     * Turns an image into a thumbnail if possible, overwriting previous versions.
     */
    public function processThumb()
    {
        if (!Storage::exists($this->getPathThumb()) || !$this->has_thumbnail) {
            if ($this->isAudio()) {
                $ID3 = new \getID3();
                $meta = $ID3->analyze($this->getFullPath());

                if (isset($meta['comments']['picture']) && count($meta['comments']['picture'])) {
                    foreach ($meta['comments']['picture'] as $albumArt) {
                        try {
                            $image = (new ImageManager())->make($albumArt['data']);

                            $this->file_height = $image->height();
                            $this->file_width = $image->width();

                            $image->resize(Settings::get('attachmentThumbnailSize'), Settings::get('attachmentThumbnailSize'), function ($constraint) {
                                $constraint->aspectRatio();
                                $constraint->upsize();
                            })
                                ->encode(Settings::get('attachmentThumbnailJpeg') ? 'jpg' : 'png', Settings::get('attachmentThumbnailQuality'))
                                ->save($this->getFullPathThumb());

                            $this->has_thumbnail = true;
                            $this->thumbnail_height = $image->height();
                            $this->thumbnail_width = $image->width();

                            return true;
                        } catch (\Exception $error) {
                            app('log')->error("intervention/image encountered an error trying to generate a thumbnail for the audio file {$this->hash}.");
                        }

                        break;
                    }
                }
            } elseif ($this->isVideo()) {
                // Used for debugging.
                $output = "Haven't executed once yet.";

                try {
                    Storage::makeDirectory($this->getDirectoryThumb());

                    $video = $this->getFullPath();
                    $image = $this->getFullPathThumb();
                    $interval = 0;
                    $frames = 1;

                    // get duration
                    $time = exec(env('LIB_FFMPEG', 'ffmpeg')." -i {$video} 2>&1 | grep 'Duration' | cut -d ' ' -f 4 | sed s/,//", $output, $returnvalue);

                    // duration in seconds; half the duration = middle
                    $durationBits = explode(':', $time);
                    $durationSeconds = (float) $durationBits[2] + ((int) $durationBits[1] * 60) + ((int) $durationBits[0] * 3600);
                    $durationMiddle = $durationSeconds / 2;

                    $middleHours = str_pad(floor($durationMiddle / 3600), 2, '0', STR_PAD_LEFT);
                    $middleMinutes = str_pad(floor($durationMiddle / 60 % 3600), 2, '0', STR_PAD_LEFT);
                    $middleSeconds = str_pad(number_format($durationMiddle % 60, 2), 5, '0', STR_PAD_LEFT);
                    $middleTimestamp = "{$middleHours}:{$middleMinutes}:{$middleSeconds}";

                    // $ffmpeg -i $video -deinterlace -an -ss $interval -f mjpeg -t 1 -r 1 -y -s $size $image 2>&1

                    $cmd = env('LIB_FFMPEG', 'ffmpeg').' '.
                            "-i {$video} ".// Input video.
                            //"-filter:v yadif " . // Deinterlace.
                            '-deinterlace '.
                            '-an '.// No audio.
                            "-ss {$middleTimestamp} ".// Timestamp for our thumbnail.
                            '-f mjpeg '.// Output format.
                            '-t 1 '.// Duration in seconds.
                            '-r 1 '.// FPS, 1 for 1 frame.
                            '-y '.// Overwrite file if it already exists.
                            '-threads 1 '.
                            "{$image} 2>&1";


                    exec($cmd, $output, $returnvalue);
                    app('log')->info($output);

                    // Constrain thumbnail to proper dimensions.
                    if (Storage::exists($this->getPathThumb())) {
                        $image = (new ImageManager())->make($this->getFullPathThumb());

                        $this->file_height = $image->height();
                        $this->file_width = $image->width();

                        $image->resize(Settings::get('attachmentThumbnailSize'), Settings::get('attachmentThumbnailSize'), function ($constraint) {
                            $constraint->aspectRatio();
                            $constraint->upsize();
                        })
                            ->encode(Settings::get('attachmentThumbnailJpeg') ? 'jpg' : 'png', Settings::get('attachmentThumbnailQuality'))
                            ->save($this->getFullPathThumb());

                        $this->has_thumbnail = true;
                        $this->thumbnail_height = $image->height();
                        $this->thumbnail_width = $image->width();

                        return true;
                    }
                } catch (\Exception $e) {
                    app('log')->error("ffmpeg encountered an error trying to generate a thumbnail for the video file {$this->hash}.");
                }
            } elseif ($this->isImage()) {
                try {
                    Storage::makeDirectory($this->getDirectoryThumb());

                    $image = (new ImageManager())->make($this->getFullPath());

                    $this->file_height = $image->height();
                    $this->file_width = $image->width();

                    $image->resize(Settings::get('attachmentThumbnailSize'), Settings::get('attachmentThumbnailSize'), function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    })
                        ->encode(Settings::get('attachmentThumbnailJpeg') ? 'jpg' : 'png', Settings::get('attachmentThumbnailQuality'))
                        ->save($this->getFullPathThumb());

                    $this->has_thumbnail = true;
                    $this->thumbnail_height = $image->height();
                    $this->thumbnail_width = $image->width();

                    return true;
                } catch (\Exception $e) {
                    app('log')->error("intervention/image encountered an error trying to generate a thumbnail for the image file {$this->hash}.");
                }
            }
            else if ($this->mime === "application/epub+zip")
            {
                $epub = new \ZipArchive();
                $epub->open($this->getFullPath());

                // Find and parse the rootfile
                $container     = $epub->getFromName("META-INF/container.xml");
                $containerXML  = simplexml_load_string($container);
                $rootFilePath  = $containerXML->rootfiles->rootfile[0]['full-path'];
                $rootFile      = $epub->getFromName($rootFilePath);
                $rootFileXML   = simplexml_load_string($rootFile);

                // Determine base directory
                $rootFileParts = pathinfo($rootFilePath);
                $baseDirectory = ($rootFileParts['dirname'] == "." ? null : $rootFileParts['dirname']);

                // XPath queries with namespaces are shit until XPath 2.0 so we hold its hand
                $rootFileNS    = $rootFileXML->getDocNamespaces();

                if (isset($rootFileNS[""]))
                {
                    $rootFileXML->registerXPathNamespace("default", $rootFileNS[""]);
                    $ns = "default:";
                }
                else
                {
                    $ns = "";
                }

                // Non-standards used with OEB, prior to EPUB
                $oebXPath   = "//{$ns}reference[@type='coverimagestandard' or @type='other.ms-coverimage-standard']";
                // EPUB standards
                $epubXPath = "//{$ns}item[@properties='cover-image' or @id=(//{$ns}meta[@name='cover']/@content)]";

                // Query the rootfile for cover elements
                $coverXPath = $rootFileXML->xpath("{$oebXPath} | {$epubXPath}");

                if ($coverXPath)
                {
                    // Get real cover entry name and read it
                    $coverHref   = $coverXPath[0]['href'];
                    $coverEntry  = (is_null($baseDirectory) ? $coverHref : $baseDirectory . "/" . $coverHref);
                    $coverString = $epub->getFromName($coverEntry);

                    try
                    {
                        $cover = imagecreatefromstring($coverString);
                        $image = (new ImageManager)->make($cover);

                        $this->file_height = $image->height();
                        $this->file_width  = $image->width();

                        $image->resize(Settings::get('attachmentThumbnailSize'), Settings::get('attachmentThumbnailSize'), function($constraint) {
                                    $constraint->aspectRatio();
                                    $constraint->upsize();
                            })
                            ->encode(Settings::get('attachmentThumbnailJpeg') ? "jpg" : "png", Settings::get('attachmentThumbnailQuality'))
                            ->save($this->getFullPathThumb());

                        $this->has_thumbnail    = true;
                        $this->thumbnail_height = $image->height();
                        $this->thumbnail_width  = $image->width();

                        return true;
                    }
                    catch (\Exception $e)
                    {
                        app('log')->error("intervention/image encountered an error trying to generate a thumbnail for the epub file {$this->hash}.");
                    }
                }
            }
        }
        else
        {
            return true;
        }

        return false;
    }

    /**
     * Refines a query to an exact hash match.
     *
     * @param \Illuminate\Database\Query\Builder $query Supplied by the builder.
     * @param string                             $hash  The checksum hash.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeWhereHash($query, $hash)
    {
        return $query->where('hash', $hash);
    }

    /**
     * Refines a query to only storage items which are orphaned (not used anywhere).
     *
     * @param \Illuminate\Database\Query\Builder $query Supplied by the builder.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function scopeWhereOrphan($query)
    {
        return $query->whereDoesntHave('attachments')
            ->whereDoesntHave('assets');
    }

    /**
     * Handles an UploadedFile from form input. Stores, creates a model, and generates a thumbnail.
     *
     * @static
     *
     * @param UploadedFile|File $file
     *
     * @return FileStorage
     */
    public static function storeUpload($file)
    {
        $clientUpload = false;

        if (!($file instanceof SymfonyFile) && !($file instanceof UploadedFile)) {
            throw new \InvalidArgumentException('First argument for FileStorage::storeUpload is not a File or UploadedFile.');

            return false;
        } elseif ($file instanceof UploadedFile) {
            $clientUpload = true;
        }

        $fileContent = File::get($file);
        $fileMD5 = md5((string) File::get($file));
        $storage = static::getHash($fileMD5);

        if (!($storage instanceof static)) {
            $storage = new static();
            $fileTime = $storage->freshTimestamp();

            $storage->hash = $fileMD5;
            $storage->banned = false;
            $storage->filesize = $file->getSize();
            $storage->mime = $clientUpload ? $file->getClientMimeType() : $file->getMimeType();
            $storage->first_uploaded_at = $fileTime;
            $storage->upload_count = 0;

            if (!isset($file->case)) {
                $ext = $file->guessExtension();

                $file->case = Sleuth::check($file->getRealPath(), $ext);

                if (!$file->case) {
                    $file->case = Sleuth::check($file->getRealPath());
                }
            }

            if (is_object($file->case)) {
                $storage->mime = $file->case->getMimeType();

                if ($file->case->getMetaData()) {
                    $storage->meta = json_encode($file->case->getMetaData());
                }
            }
        } else {
            $fileTime = $storage->freshTimestamp();
        }

        if (!Storage::exists($storage->getPath())) {
            Storage::put($storage->getPath(), $fileContent);
            Storage::makeDirectory($storage->getDirectoryThumb());
        }

        $storage->processAttachment();

        return $storage;
    }
}
