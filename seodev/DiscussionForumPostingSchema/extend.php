<?php

use Flarum\Extend;
use Seodev\DiscussionForumPostingSchema\Listener\AddSchemaToHeader;

return [
    (new Extend\Frontend('forum'))
        ->content(AddSchemaToHeader::class)
];
