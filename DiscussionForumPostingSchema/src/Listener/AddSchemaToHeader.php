<?php

namespace Seodevse\DiscussionForumPostingSchema\Listener;

use Flarum\Frontend\Document;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\User\User;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Flarum\Discussion\DiscussionRepository;
use Flarum\Foundation\SiteInterface;
use Flarum\Frontend\FrontendHandler;
use Flarum\Http\UrlGenerator;
use Flarum\Foundation\Config;

class AddSchemaToHeader
{
    protected $settings;
    protected $discussions;
    protected $url;
    protected $config;

    public function __construct(
        SettingsRepositoryInterface $settings,
        DiscussionRepository $discussions,
        UrlGenerator $url,
        Config $config
    ) {
        $this->settings = $settings;
        $this->discussions = $discussions;
        $this->url = $url;
        $this->config = $config;
    }

    public function __invoke(Document $document)
    {
        $siteName = $this->settings->get('forum_title', '');
        $siteUrl = $this->config['url'];
        $logoUrl = $this->settings->get('logo_path', $siteUrl.'/logo/logo-schema.png');

        if(empty(($logoUrl)))
        {
            $logoUrl = $siteUrl.'/logo/logo-schema.png';
        }

        $schema = [
            "@context" => "https://schema.org",
            "@type" => "WebSite",
            "name" => $siteName,
            "url" => $siteUrl,
            "publisher" => [
                "@type" => "Organization",
                "name" => $siteName,
                "logo" => !empty($logoUrl) ? [
                    "@type" => "ImageObject",
                    "url" => $logoUrl
                ] : null
            ],
            "potentialAction" => [
                "@type" => "SearchAction",
                "target" => $siteUrl . "/?q={search_term_string}",
                "query-input" => "required name=search_term_string"
            ]
        ];

        try {
            if (strpos($_SERVER['REQUEST_URI'], '/d/') !== false) {
                preg_match('/\/d\/(\d+)/', $_SERVER['REQUEST_URI'], $matches);
                $discussionId = $matches[1] ?? null;

                $discussion = $this->discussions->findOrFail($discussionId);
                $firstPost = $discussion->firstPost;
                if (!$firstPost) {
                    throw new \Exception('First post not found');
                }
                $author = $firstPost->user;
                if (!$author) {
                    throw new \Exception('Author not found');
                }
                $title = $discussion->title;
                $slug = $discussion->slug;
                $dURL = $siteUrl."/d/$discussionId-$slug";
                $createdAt = $discussion->created_at ? $discussion->created_at->toIso8601String() : '';
                $updatedAt = $discussion->last_posted_at ? $discussion->last_posted_at->toIso8601String() : '';
                $commentCount = max(($discussion->comment_count - 1), 0);
                $likeCount = method_exists($discussion, 'likes') ? $discussion->likes()->count() : 0;
                $content = $firstPost->content ?? ''; 

                $comments = [];
                $isFirst = true;
                $postCount = 0;
                foreach ($discussion->posts as $post) {
                    if ($isFirst) {
                        $isFirst = false;
                        continue;
                    }
                    if ($postCount >= 100) {
                        break;
                    }
                    $commentContent = $this->stripHtml($post->content);
                    if(empty($commentContent))
                    {
                        continue;
                    }

                    $postCount++;
                    $commentAuthor = $post->user;
                //    $commentUrl = $siteUrl . '/p/' . $post->id;
                    $comments[] = [
                        "@type" => "Comment",
//                        "@id" => $commentUrl,
//                        "url" => $commentUrl,
                        "text" => $commentContent,
                        "dateCreated" => $post->created_at->toIso8601String(),
                        "author" => [
                            "@type" => "Person",
                            "name" => $commentAuthor ? $commentAuthor->username : 'Anonymous',
                            "url" => $this->url->to('forum')->route('user', ['username' => $commentAuthor->username])
                        ]
                    ];

                    $commentContent = null;
                }

                $schema['mainEntity'] = [
                    "@type" => "DiscussionForumPosting",
                    "headline" => $title,
                    "articleBody" => $this->stripHtml($content),
                    "datePublished" => $createdAt,
                    "dateModified" => $updatedAt,
                    "url" => $dURL,
                    "author" => [
                        "@type" => "Person",
                        "name" => $author->username,
                        "url" => $this->url->to('forum')->route('user', ['username' => $author->username])
                    ],
                    "publisher" => [
                        "@type" => "Organization",
                        "name" => $siteName,
                        "logo" => [
                            "@type" => "ImageObject",
                            "url" => $logoUrl
                        ]
                    ],
                    "interactionStatistic" => [
                        [
                            "@type" => "InteractionCounter",
                            "interactionType" => "https://schema.org/CommentAction",
                            "userInteractionCount" => $commentCount
                        ],
                        [
                            "@type" => "InteractionCounter",
                            "interactionType" => "https://schema.org/LikeAction",
                            "userInteractionCount" => $likeCount
                        ] 
                    ],
                    "comment" => $comments
                ];
            }

      } catch (\Exception $e) {
         error_log('Schema Error: ' . $e->getMessage());
          return; // Avoid crashing the page
      }
      


        // Convert the schema array to JSON-LD
        $jsonLd = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

       // error_log(json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

       


        // Inject the JSON-LD script into the head
        $document->head[] = '<script type="application/ld+json">' . $jsonLd . '</script>';
    }

    /**
     * Helper function to strip HTML tags from content.
     *
     * @param string $html
     * @return string
     */
    
        protected function stripHtml($html)
        {
            return strip_tags(is_array($html) ? '' : $html);
        }
    }
