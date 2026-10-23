<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Controllers\FileController;
use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Request;

/** Text materials (.md, .txt) open as a formatted page inside Sibol instead of the bare file. */
final class MaterialReadingTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testAStudentReadsAMarkdownMaterialAsAPage(): void
    {
        Auth::actingAs($this->person('andrea.cruz'));
        $id = $this->materialId('Making inferences while reading', 'English 9 – Narra');
        $html = $this->read($id)->body;

        self::assertStringContainsString('<h1 class="page-title">Making inferences while reading</h1>', $html);
        self::assertStringContainsString('<h2 class="md-h">What an inference is</h2>', $html, '"##" sections become the page\'s headings');
        self::assertStringNotContainsString('# Making inferences', $html, 'no Markdown marks are shown');
        self::assertSame(1, substr_count($html, 'Making inferences while reading</h'), 'the title is not repeated as a heading');
        self::assertStringContainsString('<li>Ignoring clues that contradict your idea.', $html);
        self::assertStringContainsString('href="' . e(url('/materials/' . $id . '/file', ['download' => 1])) . '"', $html, 'the file can still be downloaded');
        self::assertStringContainsString('href="' . e(url('/student/classes/' . $this->classId('English 9 – Narra') . '?tab=materials')) . '"', $html, 'the way back goes to the class materials');
    }

    public function testOtherFilesAndLinksOpenAsBefore(): void
    {
        Auth::actingAs($this->person('andrea.cruz'));
        $pdf = $this->materialId('Heredity notes', 'Science 9 – Narra');
        $response = $this->read($pdf);
        self::assertSame(303, $response->status);
        self::assertSame(url('/materials/' . $pdf . '/file'), $response->headers['Location']);

        $link = $this->materialId('Graph quadratic functions with Desmos', 'Mathematics 9 – Narra');
        self::assertSame(url('/materials/' . $link . '/file'), $this->read($link)->headers['Location']);
    }

    public function testOnlyPeopleWhoCanOpenTheFileCanReadIt(): void
    {
        $id = $this->materialId('Making inferences while reading', 'English 9 – Narra');
        $outsider = Db::value(
            "SELECT u.username FROM users u WHERE u.role = 'student' AND u.id NOT IN
             (SELECT user_id FROM class_memberships WHERE class_id = :c) LIMIT 1",
            ['c' => $this->classId('English 9 – Narra')]
        );
        foreach ([(string) $outsider, 'ramon.dizon', 'teresa.villanueva'] as $username) {
            Auth::actingAs($this->person($username));
            try {
                $this->read($id);
                self::fail($username . ' should not reach the material');
            } catch (HttpException $e) {
                self::assertSame(404, $e->status, $username);
            }
        }

        Auth::actingAs($this->person('joy.santiago'));
        self::assertStringContainsString('<h2 class="md-h">What an inference is</h2>', $this->read($id)->body, 'the class teacher can read it');
    }

    private function read(int $id)
    {
        return (new FileController(new Request('GET', '/materials/' . $id . '/read')))->read($id);
    }
}
