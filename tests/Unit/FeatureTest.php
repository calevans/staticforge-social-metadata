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

    public function testHandlePostRenderPreservesUppercaseHeadTagCasing(): void
    {
        $this->setContainerVariable('site_config', ['site' => ['name' => 'Test Site']]);
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');

        $event = $this->makeRenderEvent(
            '<html><HEAD><title>Test</title></HEAD><body></body></html>',
            ['title' => 'Test Page', 'description' => 'A test page']
        );

        $this->feature->handlePostRender($event);

        $this->assertNotNull($event->renderedContent);
        $this->assertStringContainsString('</HEAD>', $event->renderedContent);
        $this->assertStringNotContainsString('</head>', $event->renderedContent);
    }

    public function testHandlePostRenderSkipsHeadCloseInsideInlineScriptString(): void
    {
        $this->setContainerVariable('site_config', ['site' => ['name' => 'Test Site']]);
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');

        $scriptSource = 'var s = "</head>"; console.log(s);';
        $html = '<html><head><title>Test</title>'
            . '<script>' . $scriptSource . '</script>'
            . '</head><body></body></html>';

        $event = $this->makeRenderEvent($html, ['title' => 'Test Page', 'description' => 'A test page']);

        $this->feature->handlePostRender($event);

        $this->assertNotNull($event->renderedContent);
        $rendered = $event->renderedContent;

        // The inline script's own source must be byte-for-byte intact.
        $this->assertStringContainsString('<script>' . $scriptSource . '</script>', $rendered);

        // Metadata must land before the REAL </head>, not inside the script string.
        $metadataPos = stripos($rendered, 'og:title');
        $scriptPos = stripos($rendered, '<script>');
        $realHeadClosePos = strripos($rendered, '</head>');

        $this->assertNotFalse($metadataPos);
        $this->assertNotFalse($scriptPos);
        $this->assertNotFalse($realHeadClosePos);
        $this->assertGreaterThan($scriptPos, $metadataPos);
        $this->assertLessThan($realHeadClosePos, $metadataPos);
    }

    public function testHandlePostRenderSkipsHeadCloseInsideHtmlComment(): void
    {
        $this->setContainerVariable('site_config', ['site' => ['name' => 'Test Site']]);
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');

        $html = '<html><head><title>Test</title>'
            . '<!-- legacy: </head> -->'
            . '</head><body></body></html>';

        $event = $this->makeRenderEvent($html, ['title' => 'Test Page', 'description' => 'A test page']);

        $this->feature->handlePostRender($event);

        $this->assertNotNull($event->renderedContent);
        $rendered = $event->renderedContent;

        $this->assertStringContainsString('<!-- legacy: </head> -->', $rendered);

        $metadataPos = stripos($rendered, 'og:title');
        $commentPos = stripos($rendered, '<!--');
        $realHeadClosePos = strripos($rendered, '</head>');

        $this->assertNotFalse($metadataPos);
        $this->assertNotFalse($commentPos);
        $this->assertNotFalse($realHeadClosePos);
        $this->assertGreaterThan($commentPos, $metadataPos);
        $this->assertLessThan($realHeadClosePos, $metadataPos);
    }

    public function testHandlePostRenderSkipsHeadCloseInsideStyleBlock(): void
    {
        $this->setContainerVariable('site_config', ['site' => ['name' => 'Test Site']]);
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');

        $styleSource = "body::after { content: '</head>'; }";
        $html = '<html><head><title>Test</title>'
            . '<style>' . $styleSource . '</style>'
            . '</head><body></body></html>';

        $event = $this->makeRenderEvent($html, ['title' => 'Test Page', 'description' => 'A test page']);

        $this->feature->handlePostRender($event);

        $this->assertNotNull($event->renderedContent);
        $rendered = $event->renderedContent;

        $this->assertStringContainsString('<style>' . $styleSource . '</style>', $rendered);

        $metadataPos = stripos($rendered, 'og:title');
        $stylePos = stripos($rendered, '<style>');
        $realHeadClosePos = strripos($rendered, '</head>');

        $this->assertNotFalse($metadataPos);
        $this->assertNotFalse($stylePos);
        $this->assertNotFalse($realHeadClosePos);
        $this->assertGreaterThan($stylePos, $metadataPos);
        $this->assertLessThan($realHeadClosePos, $metadataPos);
    }

    public function testHandlePostRenderSkipsWhenOnlyHeadCloseIsInsideScript(): void
    {
        $this->setContainerVariable('site_config', ['site' => ['name' => 'Test Site']]);
        $this->setContainerVariable('SITE_BASE_URL', 'https://example.com');

        $html = '<html><head><title>Test</title>'
            . '<script>var s = "</head>";</script>'
            . '<body></body></html>';

        $event = $this->makeRenderEvent($html, ['title' => 'Test Page', 'description' => 'A test page']);

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
