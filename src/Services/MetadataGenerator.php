<?php

declare(strict_types=1);

namespace Calevans\StaticForgeSocialMetadata\Services;

class MetadataGenerator
{
    /**
     * Generate social metadata HTML tags
     *
     * @param array<string, mixed> $frontmatter
     * @param array<string, mixed> $siteConfig
     * @param string $baseUrl
     * @return string
     */
    public function generate(array $frontmatter, array $siteConfig, string $baseUrl = ''): string
    {
        // Check for site-wide disable
        if (isset($siteConfig['social']['enabled']) && $siteConfig['social']['enabled'] === false) {
            return '';
        }

        // Check for per-page disable
        // Handle "social: false"
        if (isset($frontmatter['social']) && $frontmatter['social'] === false) {
            return '';
        }

        $tags = [];
        $socialConfig = $siteConfig['social'] ?? [];
        $pageSocial = is_array($frontmatter['social'] ?? null) ? $frontmatter['social'] : [];

        // Handle "social: { enabled: false }"
        if (isset($pageSocial['enabled']) && $pageSocial['enabled'] === false) {
            return '';
        }

        // Helper to resolve URLs
        $resolveUrl = function (string $path) use ($baseUrl): string {
            if (empty($path)) {
                return '';
            }
            // If it's already an absolute URL, return it
            if (preg_match('/^https?:\/\//', $path)) {
                return $path;
            }
            // If we have a base URL, prepend it
            if ($baseUrl) {
                return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
            }
            return $path;
        };

        // Title
        // Priority: social.title -> title -> site.title
        $pageTitle = $frontmatter['title'] ?? $siteConfig['site']['title'] ?? '';
        $socialTitle = $pageSocial['title'] ?? $pageTitle;

        if ($socialTitle) {
            $tags[] = sprintf('<meta property="og:title" content="%s">', htmlspecialchars($socialTitle));
            $tags[] = sprintf('<meta name="twitter:title" content="%s">', htmlspecialchars($socialTitle));
        }

        // Site Name
        $siteName = $siteConfig['site']['name'] ?? '';
        if ($siteName) {
            $tags[] = sprintf('<meta property="og:site_name" content="%s">', htmlspecialchars($siteName));
        }

        // Description
        // Priority: social.description -> description -> site.description
        $pageDesc = $frontmatter['description'] ?? $siteConfig['site']['description'] ?? '';
        $socialDesc = $pageSocial['description'] ?? $pageDesc;

        if ($socialDesc) {
            $tags[] = sprintf('<meta property="og:description" content="%s">', htmlspecialchars($socialDesc));
            $tags[] = sprintf('<meta name="twitter:description" content="%s">', htmlspecialchars($socialDesc));
        }

        // Images
        // 1. Generic Social Image (social.image -> image -> hero -> default)
        $genericImage = $pageSocial['image']
            ?? $frontmatter['image']
            ?? $frontmatter['hero']
            ?? $socialConfig['default_image']
            ?? '';

        // 2. Open Graph Image (social.facebook_image -> generic)
        $ogImage = $pageSocial['facebook_image'] ?? $genericImage;
        if ($ogImage) {
            $tags[] = sprintf('<meta property="og:image" content="%s">', htmlspecialchars($resolveUrl($ogImage)));
        }

        // 3. Twitter Image (social.twitter_image -> social.image -> default)
        $twitterImage = $pageSocial['twitter_image'] ?? $genericImage;
        if ($twitterImage) {
            $tags[] = sprintf('<meta name="twitter:image" content="%s">', htmlspecialchars($resolveUrl($twitterImage)));
        }

        // Image Alt Text
        $imageAlt = $pageSocial['image_alt'] ?? $frontmatter['image_alt'] ?? '';
        if ($imageAlt) {
            $tags[] = sprintf('<meta property="og:image:alt" content="%s">', htmlspecialchars($imageAlt));
            $tags[] = sprintf('<meta name="twitter:image:alt" content="%s">', htmlspecialchars($imageAlt));
        }

        // URL
        $url = $frontmatter['url'] ?? '';
        if ($url) {
             $tags[] = sprintf('<meta property="og:url" content="%s">', htmlspecialchars($resolveUrl($url)));
        }

        // Type
        $type = $frontmatter['type'] ?? 'website';
        $tags[] = sprintf('<meta property="og:type" content="%s">', htmlspecialchars($type));

        // Twitter Card
        $tags[] = '<meta name="twitter:card" content="summary_large_image">';

        // Twitter Site
        $twitterHandle = $socialConfig['twitter_handle'] ?? '';
        if ($twitterHandle) {
            $tags[] = sprintf('<meta name="twitter:site" content="%s">', htmlspecialchars($twitterHandle));
        }

        // Twitter Creator
        $creator = $pageSocial['creator'] ?? $frontmatter['author'] ?? '';
        if ($creator) {
            // Ensure it starts with @ if it looks like a handle
            if (strpos($creator, ' ') === false && strpos($creator, '@') !== 0) {
                 $creator = '@' . $creator;
            }
            $tags[] = sprintf('<meta name="twitter:creator" content="%s">', htmlspecialchars($creator));
        }

        return implode("\n", $tags);
    }
}
