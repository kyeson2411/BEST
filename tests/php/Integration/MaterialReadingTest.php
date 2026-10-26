<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use Sibol\Controllers\FileController;
use Sibol\Core\Auth;
use Sibol\Core\Db;
use Sibol\Core\HttpException;
use Sibol\Core\Request;

/**
 * File materials open as a page inside Sibol instead of the bare file: .md and .txt as they are, PDF, Word and
 * PowerPoint files as the text read from them, with the file itself a click away.
 */
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

    public function testAPdfOpensAsItsTextWithTheFileOneClickAway(): void
    {
        Auth::actingAs($this->person('andrea.cruz'));
        $pdf = $this->materialId('Heredity notes', 'Science 9 – Narra');
        $response = $this->read($pdf);
        self::assertSame(200, $response->status, 'the PDF is shown inside Sibol, not downloaded');
        $html = $response->body;

        self::assertStringContainsString('<h2 class="md-h">Page 1</h2>', $html);
        self::assertStringContainsString('<h2 class="md-h">Page 2</h2>', $html);
        self::assertStringContainsString('The instructions for traits are carried by genes.', $html);
        self::assertSame(1, substr_count($html, 'The instructions for traits are carried by genes.'), 'no sentence is shown twice');
        self::assertStringContainsString('without its pictures or layout', $html);
        self::assertStringContainsString('href="' . e(url('/materials/' . $pdf . '/file')) . '" target="_blank"', $html, 'the PDF itself opens in a new tab');
        self::assertStringContainsString('href="' . e(url('/materials/' . $pdf . '/file', ['download' => 1])) . '"', $html, 'and can be downloaded');
    }

    public function testAWordFileOpensAsItsTextBySection(): void
    {
        $class = $this->classId('Science 9 – Narra');
        $org = (int) Db::value('SELECT organization_id FROM classes WHERE id = :c', ['c' => $class]);
        $material = Db::insert('learning_materials', [
            'organization_id' => $org, 'class_id' => $class, 'title' => 'Short C lesson', 'kind' => 'file',
            'visibility' => 'published', 'ai_enabled' => 1, 'published_at' => Db::now(), 'created_at' => Db::now(), 'updated_at' => Db::now(),
        ]);
        try {
            $version = Db::insert('material_versions', [
                'material_id' => $material, 'version_no' => 1, 'original_filename' => 'Short_C_lesson.docx', 'stored_path' => 'materials/none.docx',
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'extension' => 'docx',
                'size_bytes' => 1000, 'sha256' => str_repeat('0', 64), 'processing_status' => 'ready', 'created_at' => Db::now(),
            ]);
            Db::update('learning_materials', ['current_version_id' => $version], ['id' => $material]);
            foreach ([['Variables', "A variable holds a value.\n\n- int holds whole numbers"], ['Output', 'printf shows text on the screen.']] as $i => [$section, $text]) {
                Db::insert('material_chunks', [
                    'organization_id' => $org, 'class_id' => $class, 'material_id' => $material, 'material_version_id' => $version,
                    'chunk_index' => $i, 'section_title' => $section, 'content' => $text, 'char_count' => mb_strlen($text), 'created_at' => Db::now(),
                ]);
            }

            Auth::actingAs($this->person('andrea.cruz'));
            $html = $this->read($material)->body;
            self::assertStringContainsString('<h2 class="md-h">Variables</h2>', $html);
            self::assertStringContainsString('<li>int holds whole numbers', $html, 'Word lists stay lists');
            self::assertStringContainsString('printf shows text on the screen.', $html);
            self::assertStringNotContainsString('Open the PDF', $html, 'a Word file cannot open in the browser, so only Download is offered');

            Db::update('material_versions', ['processing_status' => 'extracting'], ['id' => $version]);
            self::assertStringContainsString('Sibol is still reading this file.', $this->read($material)->body);
        } finally {
            Db::delete('learning_materials', ['id' => $material]);
        }
    }

    public function testLinksOpenAsBefore(): void
    {
        Auth::actingAs($this->person('andrea.cruz'));
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
