<?php

declare(strict_types=1);

namespace Calevans\StaticForgeSocialMetadata\Tests\Unit;

use Calevans\StaticForgeSocialMetadata\Feature;
use Calevans\StaticForgeSocialMetadata\Tests\TestCase;
use EICC\StaticForge\Core\Events\RenderEvent;
use EICC\StaticForge\Core\Events\SeoAuditPageEvent;
use EICC\StaticForge\Core\EventManager;
use EICC\StaticForge\Core\FeatureFactory;
use Symfony\Component\DomCrawler\Crawler;

class FeatureTest extends TestCase
{
    private Feature $feature;
    private EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventManager = new EventManager();

        $feature = (new FeatureFactory($this->container))->make(Feature::class);
        $this->assertInstanceOf(Feature::class, $feature);
        $this->feature = $feature;
        $this->feature->register($this->eventManager);
    }

    public function testRegisterRegistersBothListeners(): void
    {
        $postRenderListeners = $this->eventManager->getListeners('POST_RENDER');
        $this->assertNotEmpty($postRenderListeners);

        $auditListeners = $this->eventManager->getListeners('SEO_AUDIT_PAGE');
        $this->assertNotEmpty($auditListeners);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function makeRenderEvent(string $renderedContent, array $metadata = []): RenderEvent
    {
        return new RenderEvent(
            name: 'POST_RENDER',
            filePath: 'content/page.md',
            fileUrl: '',
            metadata: $metadata,
            renderedContent: $renderedContent,
        );
    }

    public function testHandlePostRenderDoesNothingWithoutRenderedContent(): void
    {
        $event = new RenderEvent(
            name: 'POST_RENDER',
            filePath: 'content/page.md',
            fileUrl: '',
            metadata: ['title' => 'Test'],
        );

        $this->feature->handlePostRender($event);

        $this->assertNull($event->renderedContent);
    }

    public function testHandlePostRenderInjectsMetadataBeforeHeadClose(): void
    {
        $this->setContainerVariable('site_config', ['site' => ['name' => 'Test Site']]);
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');

        $event = $this->makeRenderEvent(
            '<html><head><title>Test</title></head><body></body></html>',
            ['title' => 'Test Page', 'description' => 'A test page']
        );

        $this->feature->handlePostRender($event);

        $this->assertNotNull($event->renderedContent);
        $this->assertStringContainsString('og:title', $event->renderedContent);
        $this->assertStringContainsString('</head>', $event->renderedContent);
    }

    public function testHandlePostRenderSkipsWhenNoHeadTag(): void
    {
        $this->setContainerVariable('site_config', ['site' => ['name' => 'Test Site']]);

        $html = '<html><body>No head here</body></html>';
        $event = $this->makeRenderEvent($html, ['title' => 'Test Page']);

        $this->feature->handlePostRender($event);

        $this->assertEquals($html, $event->renderedContent);
    }

    public function testAuditPageFlagsMissingTags(): void
    {
        $crawler = new Crawler('<html><head></head><body></body></html>');
        $event = new SeoAuditPageEvent('SEO_AUDIT_PAGE', $crawler, 'page.html', []);

        $this->feature->auditPage($event);

        $this->assertNotEmpty($event->issues);
        $messages = array_column($event->issues, 'message');
        $this->assertContains('Missing <meta property="og:title"> tag', $messages);
        $this->assertContains('Missing <meta name="twitter:card"> tag', $messages);
    }

    public function testAuditPageFindsNoIssuesWhenAllTagsPresent(): void
    {
        $html = '<html><head>'
            . '<meta property="og:title" content="Test">'
            . '<meta property="og:description" content="Test">'
            . '<meta property="og:image" content="test.jpg">'
            . '<meta property="og:url" content="https://example.com">'
            . '<meta name="twitter:card" content="summary">'
            . '<meta name="twitter:title" content="Test">'
            . '<meta name="twitter:description" content="Test">'
            . '<meta name="twitter:image" content="test.jpg">'
            . '</head><body></body></html>';
        $crawler = new Crawler($html);
        $event = new SeoAuditPageEvent('SEO_AUDIT_PAGE', $crawler, 'page.html', []);

        $this->feature->auditPage($event);

        $this->assertEmpty($event->issues);
    }
}
