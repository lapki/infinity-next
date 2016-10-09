<?php

namespace App\Http\Controllers\ESI;

use App\Post;
use App\Board;

use Response;
use Illuminate\Http\Request;
use View;

class SiteController extends InternalController
{
    const VIEW_GLOBAL_NAV = 'nav.gnav';
    const VIEW_RECENT_IMAGES = 'content.index.sections.recent_images';
    const VIEW_RECENT_POSTS = 'content.index.sections.recent_posts';
    const VIEW_POST_FORM = 'content.board.post.form';

    public function getGlobalNavigation()
    {
        $partial = View::make(static::VIEW_GLOBAL_NAV);
        $partial .= '<!-- ESI '.date('r').'-->';

        return Response::make($partial)
            ->setTtl(60);
    }

    public function getRecentImages(Request $req)
    {
        $partial = View::make(static::VIEW_RECENT_IMAGES, [
            "nsfw" => ($req->input('nsfw', false) !== false),
        ]);
        $partial .= '<!-- ESI '.date('r').'-->';

        return Response::make($partial)
            ->setTtl(60);
    }

    public function getRecentPosts()
    {
        $partial = View::make(static::VIEW_RECENT_POSTS);
        $partial .= '<!-- ESI '.date('r').'-->';

        return Response::make($partial)
            ->setTtl(60);
    }

    public function getPostForm(Request $req) {
        $reply_to = Post::find($req->input('reply_to', false));
        $partial = View::make(static::VIEW_POST_FORM, [
            "reply_to" => $reply_to,
            "board" => Board::getBoardWithEverything($req->input('board_uri', null)),
            "user" => user(),
            "actions" => [$reply_to ? "reply" : "thread"],
        ]);
        $partial .= '<!-- ESI '.date('r').'-->';
        return Response::make($partial)
            ->setTtl(2);
    }
}
