<?php

declare(strict_types=1);

namespace Calevans\StaticForgeSocialMetadata;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\ConfigurableFeatureInterface;
use EICC\StaticForge\Core\EventManager;
use Calevans\StaticForgeSocialMetadata\Services\MetadataGenerator;
use EICC\Utils\Container;
use EICC\Utils\Log;

/**
 * Social Metadata Feature - generates Open Graph and Twitter Card metadata
 * Listens to POST_RENDER to inject metadata into the head of the HTML
 */
class Feature extends BaseFeature implements FeatureInterface, ConfigurableFeatureInterface
{
    protected string $name = 'SocialMetadata';
    protected Log $logger;
    private MetadataGenerator $generator;

    /**
     * @var array<string, array{method: string, priority: int}>
     */
    protected array $eventListeners = [
        'POST_RENDER' => ['method' => 'handlePostRender', 'priority' => 50] // Run before RssFeed (110) but priority is lower number = higher priority?
        // EventManager sorts by priority: $a <=> $b. So lower number is FIRST.
        // RssFeed runs at 110.
        // We want to modify HTML.
        // If we run at 50, we run BEFORE RssFeed.
        // That's fine.
    ];

    public function getRequiredConfig(): array
    {
        return []; // No strictly required config, but 'social' key is used if present
    }

    public function getRequiredEnv(): array
    {
        return [];
    }

    public function register(EventManager $eventManager, Container $container): void
    {
        parent::register($eventManager, $container);
        $eventManager->registerListener('SEO_AUDIT_PAGE', [$this, 'auditPage']);

        $this->logger = $container->get('logger');
        $this->generator = new MetadataGenerator();

        $this->logger->log('INFO', 'SocialMetadata Feature registered');
    }

    /**
     * Inject social metadata into HTML during POST_RENDER
     *
     * @param Container $container
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function handlePostRender(Container $container, array $parameters): array
    {
        if (!isset($parameters['rendered_content']) || !isset($parameters['metadata'])) {
            return $parameters;
        }

        $html = $parameters['rendered_content'];
        $frontmatter = $parameters['metadata'];
        $filePath = $parameters['file_path'] ?? 'unknown';
        $siteConfig = $container->getVariable('site_config') ?? [];
        $baseUrl = $container->getVariable('SITE_BASE_URL') ?? '';

        // Generate metadata tags
        $metadata = $this->generator->generate($frontmatter, $siteConfig, $baseUrl);

        if (empty($metadata)) {
            return $parameters;
        }

        // Inject into <head>
        $pos = stripos($html, '</head>');
        if ($pos !== false) {
            $html = substr_replace($html, "\n" . $metadata . "\n</head>", $pos, 7);
            $parameters['rendered_content'] = $html;
            $this->logger->log('DEBUG', "Injected social metadata for " . basename($filePath));
        } else {
            $this->logger->log('WARNING', "Could not find </head> tag in " . basename($filePath) . ", skipping metadata injection");
        }

        return $parameters;
    }

    /**
     * Audit page for missing social metadata
     *
     * @param Container $container
     * @param array $params
     * @return array
     */
    public function auditPage(Container $container, array $params): array
    {
        $crawler = $params['crawler'];
        $filename = $params['filename'];
        $issues = $params['issues'];

        $checks = [
            'og:title' => 'property',
            'og:description' => 'property',
            'og:image' => 'property',
            'og:url' => 'property',
            'twitter:card' => 'name',
            'twitter:title' => 'name',
            'twitter:description' => 'name',
            'twitter:image' => 'name',
        ];

        foreach ($checks as $tag => $attribute) {
            $count = $crawler->filter("meta[$attribute='$tag']")->count();
            if ($count === 0) {
                $issues[] = [
                    'file' => $filename,
                    'type' => 'warning',
                    'message' => "Missing <meta $attribute=\"$tag\"> tag",
                ];
            }
        }

        $params['issues'] = $issues;
        return $params;
    }
}
