<?php

declare(strict_types=1);

namespace Calevans\StaticForgeSocialMetadata;

use EICC\StaticForge\Core\BaseFeature;
use EICC\StaticForge\Core\FeatureInterface;
use EICC\StaticForge\Core\ConfigurableFeatureInterface;
use EICC\StaticForge\Core\Events\EventListener;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\Events\SeoAuditPageEvent;
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

    public function __construct(Container $container, Log $logger, MetadataGenerator $generator)
    {
        $this->container = $container;
        $this->logger = $logger;
        $this->generator = $generator;
    }

    public function getRequiredConfig(): array
    {
        return []; // No strictly required config, but 'social' key is used if present
    }

    public function getRequiredEnv(): array
    {
        return [];
    }

    public function register(EventManager $eventManager): void
    {
        parent::register($eventManager);
        $this->logger->log('INFO', 'SocialMetadata Feature registered');
    }

    /**
     * Inject social metadata into HTML during POST_RENDER
     *
     * Priority 50 runs before RssFeed's POST_RENDER listener (110) — lower
     * priority numbers run first.
     */
    #[EventListener('POST_RENDER', priority: 50)]
    public function handlePostRender(RenderEvent $event): void
    {
        if ($event->renderedContent === null) {
            return;
        }

        $html = $event->renderedContent;
        $frontmatter = $event->metadata;
        $filePath = $event->filePath !== '' ? $event->filePath : 'unknown';
        $siteConfig = $this->container->getVariable('site_config') ?? [];
        $baseUrl = $this->container->getVariable('SITE_BASE_URL') ?? '';

        // Generate metadata tags
        $metadata = $this->generator->generate($frontmatter, $siteConfig, $baseUrl);

        if (empty($metadata)) {
            return;
        }

        // Inject into <head>
        $pos = $this->findInsertionOffset($html, '</head>');
        if ($pos !== false) {
            $event->renderedContent = substr_replace($html, "\n" . $metadata . "\n", $pos, 0);
            $this->logger->log('DEBUG', "Injected social metadata for " . basename($filePath));
        } else {
            // Unlike GoogleAnalytics (which falls back to </body> or appends
            // at the end), we skip outright: an og:/twitter: meta tag is only
            // valid inside <head>, so there is no safe fallback location.
            $this->logger->log(
                'WARNING',
                "Could not find </head> tag in " . basename($filePath) . ", skipping metadata injection"
            );
        }
    }

    /**
     * Locates the first occurrence of $tag that is not inside a
     * <script>...</script> region, a <style>...</style> region, or an
     * <!-- ... --> comment, so injection never lands inside a string
     * literal, style rule, or dead comment. A single forward scan over
     * the tokens that can open/close those regions (plus the target tag
     * itself), tracking which region (if any) is currently open.
     */
    private function findInsertionOffset(string $content, string $tag): int|false
    {
        $pattern = '/<!--|-->|<script\b[^>]*>|<\/script\s*>|<style\b[^>]*>|<\/style\s*>|'
            . preg_quote($tag, '/') . '/i';

        if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE) === false) {
            return false;
        }

        /** @var list<array{0: string, 1: int}> $tokens */
        $tokens = $matches[0];

        $inScript = false;
        $inStyle = false;
        $inComment = false;

        foreach ($tokens as [$token, $offset]) {
            $lower = strtolower($token);

            if ($inComment) {
                if ($lower === '-->') {
                    $inComment = false;
                }
                continue;
            }

            if ($inScript) {
                if (str_starts_with($lower, '</script')) {
                    $inScript = false;
                }
                continue;
            }

            if ($inStyle) {
                if (str_starts_with($lower, '</style')) {
                    $inStyle = false;
                }
                continue;
            }

            if ($lower === '<!--') {
                $inComment = true;
                continue;
            }

            if (str_starts_with($lower, '<script')) {
                $inScript = true;
                continue;
            }

            if (str_starts_with($lower, '<style')) {
                $inStyle = true;
                continue;
            }

            // Only the target tag can reach here: it's a live match.
            return $offset;
        }

        return false;
    }

    /**
     * Audit page for missing social metadata
     */
    #[EventListener('SEO_AUDIT_PAGE')]
    public function auditPage(SeoAuditPageEvent $event): void
    {
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
            $count = $event->crawler->filter("meta[$attribute='$tag']")->count();
            if ($count === 0) {
                $event->issues[] = [
                    'file' => $event->filename,
                    'type' => 'warning',
                    'message' => "Missing <meta $attribute=\"$tag\"> tag",
                ];
            }
        }
    }
}
