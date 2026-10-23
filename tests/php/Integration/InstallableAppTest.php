<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Controllers\PageController;
use Sibol\Controllers\Student\ClassController;
use Sibol\Core\Auth;
use Sibol\Core\Config;
use Sibol\Core\Db;
use Sibol\Core\Request;
use Sibol\Core\Translator;

/** Item 14: Sibol as an installable app, with an offline page and class files kept on a device for offline reading. */
final class InstallableAppTest extends IntegrationTestCase
{
    private ?string $schoolName = null;

    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        Translator::setLocale('en');
        if ($this->schoolName !== null) {
            Config::set('school.name', $this->schoolName);
        }
        parent::tearDown();
    }

    public function testTheManifestNamesTheSchoolAndUsesIconsMadeFromItsSeal(): void
    {
        $this->schoolName = (string) Config::get('school.name', '');
        Config::set('school.name', 'Mataas na Paaralan ng Halimbawa');
        $response = (new PageController(new Request('GET', '/manifest.webmanifest')))->manifest();
        self::assertSame('application/manifest+json; charset=utf-8', $response->headers['Content-Type']);
        self::assertSame('private, max-age=86400', $response->headers['Cache-Control'], 'kept by the browser only: it follows the language');

        $manifest = json_decode($response->body, true);
        $base = base_path_url();
        self::assertSame(Config::get('app.name') . ' – Mataas na Paaralan ng Halimbawa', $manifest['name']);
        self::assertSame(Config::get('app.short_name'), $manifest['short_name'], 'the short name sits under the icon');
        self::assertSame([$base . '/', $base . '/', $base . '/'], [$manifest['id'], $manifest['start_url'], $manifest['scope']]);
        self::assertSame(['standalone', '#F6F5EE', '#1E3D31'], [$manifest['display'], $manifest['background_color'], $manifest['theme_color']]);
        self::assertSame(['any', 'any', 'maskable', 'maskable'], array_column($manifest['icons'], 'purpose'));
        foreach ($manifest['icons'] as $icon) {
            $file = BASE_PATH . '/public' . substr((string) parse_url($icon['src'], PHP_URL_PATH), strlen($base));
            self::assertFileExists($file);
            $size = getimagesize($file);
            self::assertSame($icon['sizes'], $size[0] . 'x' . $size[1], $icon['src']);
            self::assertSame('image/png', $size['mime']);
        }
    }

    public function testTheManifestFollowsThePersonsLanguage(): void
    {
        Translator::setLocale('fil');
        $manifest = json_decode((new PageController(new Request('GET', '/manifest.webmanifest')))->manifest()->body, true);
        self::assertSame('fil', $manifest['lang']);
        self::assertStringStartsWith('Mag-sign in sa B.E.S.T. Learning and Interactive Study System', $manifest['description']);
    }

    public function testTheOfflinePageCarriesNothingAboutThePersonWhoLoadedIt(): void
    {
        $andrea = $this->person('andrea.cruz');
        Auth::actingAs($andrea);
        $html = (new PageController(new Request('GET', '/offline')))->offline()->body;

        self::assertStringContainsString('<h1 class="page-title">You are offline</h1>', $html);
        self::assertStringContainsString('data-offline-list hidden', $html, 'offline.js lists the files kept on the device');
        self::assertStringContainsString('/assets/js/offline.js', $html);
        self::assertStringContainsString('rel="manifest"', $html);
        self::assertStringNotContainsString('srcset', $html, 'one file per image: the service worker stores what the page shows');
        // The page is stored on the device and shown to whoever uses it later: no form token, no name.
        self::assertStringNotContainsString('csrf-token', $html);
        foreach ([$andrea['given_name'], $andrea['family_name'], $andrea['username']] as $personal) {
            self::assertStringNotContainsString((string) $personal, $html);
        }
    }

    public function testStudentsCanKeepFilesButNotLinksOffline(): void
    {
        $andrea = $this->person('andrea.cruz');
        Auth::actingAs($andrea);
        $classId = $this->classId('Science 9 – Narra');
        $html = (new ClassController(new Request('GET', '/student/classes/' . $classId, ['tab' => 'materials'])))->show($classId)->body;

        preg_match_all('/data-offline-save="([^"]+)"/', $html, $kept);
        $files = Db::all(
            "SELECT id FROM learning_materials m WHERE class_id = :c AND kind = 'file' AND " . \Sibol\Policies\Access::releasedMaterialSql('m'),
            ['c' => $classId]
        );
        self::assertNotEmpty($files);
        $expected = array_map(static fn ($m) => url('/materials/' . (int) $m['id'] . '/file'), $files);
        sort($expected);
        $found = $kept[1];
        sort($found);
        self::assertSame($expected, $found, 'one toggle per released file, none for links');
        self::assertStringContainsString('data-offline-class="Science 9 – Narra"', $html);
        // Hidden until offline.js finds a browser that can keep files.
        self::assertSame(count($files), preg_match_all('/aria-pressed="false" hidden/', $html));
        self::assertStringContainsString('/assets/js/offline.js', $html);

        $overview = (new ClassController(new Request('GET', '/student/classes/' . $classId)))->show($classId)->body;
        self::assertStringNotContainsString('offline.js', $overview, 'only the Materials tab loads the script');
    }
}
