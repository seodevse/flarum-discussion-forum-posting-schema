<?php

namespace Seodev\DiscussionForumPostingSchema\Listener;

use Flarum\Frontend\Document;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\User\User;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;

class AddSchemaToHeader
{
    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    /**
     * Constructor.
     *
     * @param SettingsRepositoryInterface $settings
     */
    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Handle the rendering of the document.
     *
     * @param Document $document
     */
    public function __invoke(Document $document)
    {
        // Retrieve dynamic settings
        $siteName = $this->settings->get('forum_title', ''); // empty not set
        $siteUrl = $this->settings->get('url', ''); // empty not set
        $logoUrl = $this->settings->get('logo_path', ''); // empty not set

        // Base schema for the website
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

        // If viewing a discussion thread
        if ($document->payload->page instanceof Discussion) {
            /** @var Discussion $discussion */
            $discussion = $document->payload->page;

            // Fetch the first post as the main entity
            $firstPost = $discussion->firstPost;

            // Fetch the author of the first post
            /** @var User $author */
            $author = $firstPost->user;

            // Interaction statistics (ensure these attributes exist or adjust accordingly)
            $viewCount = $discussion->view_count ?? 0;
            $commentCount = $discussion->comment_count ?? 0;
            $likeCount = $discussion->likes ?? 0;

            $schema['mainEntity'] = [
                "@type" => "DiscussionForumPosting",
                "headline" => $discussion->title,
                "datePublished" => $discussion->created_at->toIso8601String(),
                "dateModified" => $discussion->updated_at->toIso8601String(),
                "url" => $discussion->url(),
                "articleSection" => $discussion->tags->pluck('name')->toArray(),
                "author" => [
                    "@type" => "Person",
                    "name" => $author->username,
                    "url" => $author->url()
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
                        "interactionType" => "https://schema.org/ViewAction",
                        "userInteractionCount" => $viewCount
                    ],
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
                "articleBody" => $this->stripHtml($firstPost->content)
            ];

            // Optionally, add breadcrumbs
            $breadcrumbs = [];
            $position = 1;

            // Add home breadcrumb
            $breadcrumbs[] = [
                "@type" => "ListItem",
                "position" => $position,
                "item" => [
                    "@type" => "Thing",
                    "name" => "Home",
                    "url" => $siteUrl
                ]
            ];
            $position++;

            // Add tags as breadcrumbs
            foreach ($discussion->tags as $tag) {
                $breadcrumbs[] = [
                    "@type" => "ListItem",
                    "position" => $position,
                    "item" => [
                        "@type" => "Thing",
                        "name" => $tag->name,
                        "url" => $tag->url()
                    ]
                ];
                $position++;
            }

            // Add the discussion title as the last breadcrumb
            $breadcrumbs[] = [
                "@type" => "ListItem",
                "position" => $position,
                "item" => [
                    "@type" => "Thing",
                    "name" => $discussion->title,
                    "url" => $discussion->url()
                ]
            ];

            $schema['breadcrumb'] = [
                "@type" => "BreadcrumbList",
                "itemListElement" => $breadcrumbs
            ];
        }

        // Convert the schema array to JSON-LD
        $jsonLd = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

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
        return strip_tags($html);
    }
}
