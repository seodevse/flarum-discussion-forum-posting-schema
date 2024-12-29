<?php

use Flarum\Extend;
use Seodevse\DiscussionForumPostingSchema\Listener\AddSchemaToHeader;

return [
    (new Extend\Frontend('forum'))
        ->content(AddSchemaToHeader::class)
];
