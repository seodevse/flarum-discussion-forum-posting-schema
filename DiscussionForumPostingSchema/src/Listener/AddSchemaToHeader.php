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
    /**
     * @var SettingsRepositoryInterface
     */
    protected $settings;

    /**
     * @var DiscussionRepository
     */
    protected $discussions; // <-- Declare the property here

    protected $url;
    protected $config;

    /**
     * Constructor.
     *
     * @param SettingsRepositoryInterface $settings
     * @param DiscussionRepository $discussions
     */
    public function __construct(
        SettingsRepositoryInterface $settings,
        DiscussionRepository $discussions,
        UrlGenerator $url, // Correctly inject the URL Generator
        Config $config // Inject Config
    ) {
        $this->settings = $settings;
        $this->discussions = $discussions;
        $this->url = $url; // Assign it
        $this->config = $config; // Assign config
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
        $siteUrl = $this->config['url']; // Fetch URL from config.php
        $logoUrl = $this->settings->get('logo_path', $siteUrl.'/logo/logo-schema.png'); 

        if(empty(($logoUrl)))
        {
            $logoUrl = $siteUrl.'/logo/logo-schema.png';
        }

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
      try {
  
      
          if (strpos($_SERVER['REQUEST_URI'], '/d/') !== false) {

                preg_match('/\/d\/(\d+)/', $_SERVER['REQUEST_URI'], $matches);
                $discussionId = $matches[1] ?? null;
      
                 // Fetch the discussion from the repository
                 $discussion = $this->discussions->findOrFail($discussionId);
      
              
                // Ensure the first post exists
                $firstPost = $discussion->firstPost;
                if (!$firstPost) {
                    throw new \Exception('First post not found'); // Log and exit if no first post
                }
            
                // Ensure the author exists
                $author = $firstPost->user;
                if (!$author) {
                    throw new \Exception('Author not found'); // Log and exit if no author
                }
            
                // Extract attributes
                $title = $discussion->title;
                $slug = $discussion->slug;

                // fuklfix 
                $dURL = $siteUrl."/d/$discussionId-$slug";

                $createdAt = $discussion->created_at ? $discussion->created_at->toIso8601String() : '';
                $updatedAt = $discussion->last_posted_at ? $discussion->last_posted_at->toIso8601String() : '';
                
            
                // Interaction statistics
              //  $viewCount = $discussion->attributes['view_count'] ?? 0;
                $commentCount = $discussion->comment_count ?? 0;
                $likeCount = method_exists($discussion, 'likes') ? $discussion->likes()->count() : 0; // Use relationship if defined

            
                // Handle tags safely
                // $tags = $discussion->tags ?? collect();
                // $tagNames = $tags->pluck('name')->toArray();

                // Safely fetch article body content
                $content = $firstPost->content ?? '';             
            
                $schema['mainEntity'] = [
                    "@type" => "DiscussionForumPosting",
                    "headline" => $title,
                    "datePublished" => $createdAt,
                    "dateModified" => $updatedAt,
                    "url" => $dURL,

                    // "articleSection" => $tagNames,
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
                    "articleBody" => $this->stripHtml($content)
                ];
            
 /*               // Breadcrumbs
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
                if ($tags) {
                    foreach ($tags as $tag) {
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
                }
            
                // Add the discussion title as the last breadcrumb
                $breadcrumbs[] = [
                    "@type" => "ListItem",
                    "position" => $position,
                    "item" => [
                        "@type" => "Thing",
                        "name" => $title,
                        "url" => url("/d/{$discussion->id}-" . Str::slug($title))
                    ]
                ];
            
                $schema['breadcrumb'] = [
                    "@type" => "BreadcrumbList",
                    "itemListElement" => $breadcrumbs
                ];
                */
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
            return strip_tags($html ?? '');
        }
    }