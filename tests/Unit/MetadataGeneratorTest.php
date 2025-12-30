<?php

declare(strict_types=1);

namespace Calevans\StaticForgeSocialMetadata\Tests\Unit;

use Calevans\StaticForgeSocialMetadata\Services\MetadataGenerator;
use PHPUnit\Framework\TestCase;

class MetadataGeneratorTest extends TestCase
{
    private MetadataGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new MetadataGenerator();
    }

    public function testGenerateWithFullFrontmatter(): void
    {
        $frontmatter = [
            'title' => 'Test Title',
            'description' => 'Test Description',
            'social' => [
                'image' => '/images/test.jpg'
            ],
            'url' => '/test-page',
            'type' => 'article'
        ];

        $siteConfig = [
            'social' => [
                'twitter_handle' => '@testuser'
            ]
        ];

        $html = $this->generator->generate($frontmatter, $siteConfig);

        $this->assertStringContainsString('<meta property="og:title" content="Test Title">', $html);
        $this->assertStringContainsString('<meta name="twitter:title" content="Test Title">', $html);
        $this->assertStringContainsString('<meta property="og:description" content="Test Description">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="/images/test.jpg">', $html);
        $this->assertStringContainsString('<meta property="og:url" content="/test-page">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);
        $this->assertStringContainsString('<meta name="twitter:site" content="@testuser">', $html);
    }

    public function testGenerateWithDefaults(): void
    {
        $frontmatter = [];
        $siteConfig = [
            'site' => [
                'title' => 'Default Site Title',
                'description' => 'Default Site Description'
            ],
            'social' => [
                'default_image' => '/images/default.jpg'
            ]
        ];

        $html = $this->generator->generate($frontmatter, $siteConfig);

        $this->assertStringContainsString('<meta property="og:title" content="Default Site Title">', $html);
        $this->assertStringContainsString('<meta property="og:description" content="Default Site Description">', $html);
        $this->assertStringContainsString('<meta property="og:image" content="/images/default.jpg">', $html);
        $this->assertStringContainsString('<meta property="og:type" content="website">', $html);
    }

    public function testGenerateWithSocialBlock(): void
    {
        $frontmatter = [
            'title' => 'Page Title',
            'social' => [
                'title' => 'Social Title',
                'image' => '/images/social.jpg',
                'twitter_image' => '/images/twitter.jpg'
            ]
        ];
        $siteConfig = [];

        $html = $this->generator->generate($frontmatter, $siteConfig);

        // Should use social title override
        $this->assertStringContainsString('<meta property="og:title" content="Social Title">', $html);

        // Should use generic social image for OG (since facebook_image not set)
        $this->assertStringContainsString('<meta property="og:image" content="/images/social.jpg">', $html);

        // Should use specific twitter image
        $this->assertStringContainsString('<meta name="twitter:image" content="/images/twitter.jpg">', $html);
    }

    public function testGenerateWithMissingData(): void
    {
        $frontmatter = [];
        $siteConfig = [];

        $html = $this->generator->generate($frontmatter, $siteConfig);

        // Should contain type and card at minimum
        $this->assertStringContainsString('<meta property="og:type" content="website">', $html);
        $this->assertStringContainsString('<meta name="twitter:card" content="summary_large_image">', $html);

        // Should NOT contain empty tags
        $this->assertStringNotContainsString('og:title', $html);
        $this->assertStringNotContainsString('og:description', $html);
    }

    public function testGenerateWithExtendedFields(): void
    {
        $frontmatter = [
            'title' => 'Test Page',
            'author' => 'Original Author',
            'social' => [
                'image_alt' => 'A beautiful sunset',
                'creator' => 'social_creator'
            ]
        ];

        $siteConfig = [
            'site' => [
                'name' => 'My Awesome Site'
            ]
        ];

        $html = $this->generator->generate($frontmatter, $siteConfig);

        $this->assertStringContainsString('<meta property="og:site_name" content="My Awesome Site">', $html);
        $this->assertStringContainsString('<meta property="og:image:alt" content="A beautiful sunset">', $html);
        $this->assertStringContainsString('<meta name="twitter:image:alt" content="A beautiful sunset">', $html);
        $this->assertStringContainsString('<meta name="twitter:creator" content="@social_creator">', $html);
    }

    public function testGenerateWithRootImageFallbacks(): void
    {
        // Test 'image' fallback
        $frontmatterImage = ['image' => '/root/image.jpg'];
        $html = $this->generator->generate($frontmatterImage, []);
        $this->assertStringContainsString('<meta property="og:image" content="/root/image.jpg">', $html);

        // Test 'hero' fallback
        $frontmatterHero = ['hero' => '/root/hero.jpg'];
        $html = $this->generator->generate($frontmatterHero, []);
        $this->assertStringContainsString('<meta property="og:image" content="/root/hero.jpg">', $html);

        // Test priority: social.image > image > hero
        $frontmatterPriority = [
            'social' => ['image' => '/social.jpg'],
            'image' => '/root.jpg',
            'hero' => '/hero.jpg'
        ];
        $html = $this->generator->generate($frontmatterPriority, []);
        $this->assertStringContainsString('<meta property="og:image" content="/social.jpg">', $html);

        $frontmatterPriority2 = [
            'image' => '/root.jpg',
            'hero' => '/hero.jpg'
        ];
        $html = $this->generator->generate($frontmatterPriority2, []);
        $this->assertStringContainsString('<meta property="og:image" content="/root.jpg">', $html);
    }

    public function testGenerateDisabledSiteWide(): void
    {
        $frontmatter = ['title' => 'Test'];
        $siteConfig = [
            'social' => [
                'enabled' => false
            ]
        ];

        $html = $this->generator->generate($frontmatter, $siteConfig);
        $this->assertEmpty($html);
    }

    public function testGenerateDisabledPerPage(): void
    {
        // Case 1: social: false
        $frontmatter = [
            'title' => 'Test',
            'social' => false
        ];
        $siteConfig = [];
        $html = $this->generator->generate($frontmatter, $siteConfig);
        $this->assertEmpty($html);

        // Case 2: social: { enabled: false }
        $frontmatter2 = [
            'title' => 'Test',
            'social' => ['enabled' => false]
        ];
        $html = $this->generator->generate($frontmatter2, $siteConfig);
        $this->assertEmpty($html);
    }

    public function testGenerateWithBaseUrl(): void
    {
        $frontmatter = [
            'title' => 'Test',
            'social' => [
                'image' => '/assets/image.jpg'
            ]
        ];
        $siteConfig = [];
        $baseUrl = 'https://example.com/site/';

        $html = $this->generator->generate($frontmatter, $siteConfig, $baseUrl);

        // Should prepend base URL to relative path
        $this->assertStringContainsString('<meta property="og:image" content="https://example.com/site/assets/image.jpg">', $html);

        // Should NOT prepend if already absolute
        $frontmatterAbsolute = [
            'title' => 'Test',
            'social' => [
                'image' => 'https://other.com/image.jpg'
            ]
        ];
        $htmlAbsolute = $this->generator->generate($frontmatterAbsolute, $siteConfig, $baseUrl);
        $this->assertStringContainsString('<meta property="og:image" content="https://other.com/image.jpg">', $htmlAbsolute);
    }
}
