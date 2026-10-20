<?php

declare(strict_types=1);

namespace Sibol\Tests\Integration;

use RuntimeException;
use Sibol\Controllers\Teacher\LessonController;
use Sibol\Core\Translator;
use Sibol\Services\LessonPlans;

/** Item 13: a lesson laid out as a DepEd Daily Lesson Log. */
final class LessonPlansTest extends IntegrationTestCase
{
    public function testEachLessonSectionLandsInOneDllRowAndTheRestStayBlank(): void
    {
        $content = [];
        foreach (LessonController::SECTIONS as $key) {
            $content[$key] = 'Text for ' . $key;
        }
        $content['sources'] = [['n' => 1, 'title' => 'Breathing notes', 'where' => 'p. 2']];
        $dll = LessonPlans::dll(['title' => 'Gas exchange', 'language' => 'en'], $content);

        self::assertSame(['I. Objectives', 'II. Content', 'III. Learning resources', 'IV. Procedures', 'V. Remarks', 'VI. Reflection'], array_column($dll['parts'], 'heading'));
        $byKey = [];
        foreach ($dll['parts'] as $part) {
            foreach ($part['rows'] as $row) {
                if ($row['key'] !== null) {
                    self::assertArrayNotHasKey($row['key'], $byKey, $row['key'] . ' is shown once');
                    $byKey[$row['key']] = $row;
                }
            }
        }
        foreach (LessonController::SECTIONS as $key) {
            self::assertArrayHasKey($key, $byKey, 'no lesson section is left out');
            self::assertSame('Text for ' . $key, $byKey[$key]['text']);
        }
        self::assertSame('C. Learning competencies or objectives (write the LC code for each)', $byKey['objectives']['label']);
        self::assertSame('A. Reviewing the previous lesson or presenting the new lesson', $byKey['prior_knowledge']['label']);
        self::assertSame('I. Evaluating learning', $byKey['exit_ticket']['label']);
        self::assertSame('Gas exchange', $byKey['title']['text']);
        self::assertSame('A. References', $byKey['sources']['label']);

        $procedures = $dll['parts'][3]['rows'];
        self::assertCount(10, $procedures, 'A to J');
        self::assertSame(['B', 'G', 'H', 'J'], array_values(array_map(static fn (array $r): string => $r['label'][0], array_filter($procedures, static fn (array $r): bool => $r['key'] === null))),
            'rows Sibol has nothing for are left to write in');
        self::assertCount(7, $dll['parts'][5]['rows'], 'reflection A to G, all blank');
    }

    public function testTheLogFollowsTheLessonsLanguageAndPutsThePageLanguageBack(): void
    {
        $before = Translator::locale();
        $dll = LessonPlans::dll(['title' => 'Palitan ng gas', 'language' => 'fil'], ['objectives' => 'Mailarawan ang alveoli.']);
        self::assertSame('I. Layunin', $dll['parts'][0]['heading']);
        self::assertSame('Paaralan', $dll['header']['school']);
        self::assertSame($before, Translator::locale());

        self::assertSame('I. Objectives', LessonPlans::dll(['title' => 'X', 'language' => 'xx'], [])['parts'][0]['heading'], 'an unknown language keeps the page\'s own');
        try {
            Translator::with('fil', static function (): void {
                throw new RuntimeException('stop');
            });
        } catch (RuntimeException $e) {
            self::assertSame($before, Translator::locale(), 'even when something fails inside');
        }
    }
}
