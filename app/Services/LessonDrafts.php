<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Controllers\Teacher\LessonController;
use Sibol\Core\Audit;
use Sibol\Core\Db;
use Sibol\Policies\Access;
use Sibol\Services\Ai\Companion;
use Sibol\Services\Ai\Retrieval;

/**
 * A lesson outline from the teacher companion ("Plan a lesson"), saved as a lesson draft. The outline's headings
 * are matched to the lesson's sections; students see nothing until the teacher reviews and publishes it.
 */
final class LessonDrafts
{
    /** Heading words (English and Filipino) for each section, checked in this order. */
    private const HEADINGS = [
        'exit_ticket' => ['exit', 'ticket'],
        'worked_example' => ['example', 'halimbawa'],
        'prior_knowledge' => ['prior', 'already know', 'review', 'warm-up', 'balik-aral', 'dating kaalaman'],
        'checks' => ['check', 'understanding', 'assessment', 'pagtataya', 'pag-unawa'],
        'activities' => ['activit', 'practice', 'gawain', 'pagsasanay'],
        'objectives' => ['objective', 'goal', 'layunin'],
        'explanation' => ['explanation', 'explain', 'discussion', 'lesson proper', 'paliwanag', 'talakayan', 'pagtalakay'],
    ];

    /**
     * The outline split into lesson sections. Lines under a heading the sections do not know stay in the section
     * above them; with no known heading at all, the whole outline goes to the explanation.
     * @return array<string,string>
     */
    public static function sections(string $outline): array
    {
        $parts = array_fill_keys(LessonController::SECTIONS, []);
        $current = null;
        $preamble = [];
        foreach (preg_split('/\R/u', trim($outline)) ?: [] as $line) {
            $heading = self::heading($line);
            $section = $heading !== null ? self::sectionFor($heading[0]) : null;
            if ($section !== null) {
                $current = $section;
                // A plain heading ("Activities") is the section's own name; one with details is kept.
                if (str_word_count($heading[0]) > 4 || preg_match('/[\d(]/', $heading[0])) {
                    $parts[$current][] = '**' . $heading[0] . '**';
                }
                if ($heading[1] !== '') {
                    $parts[$current][] = $heading[1];
                }
                continue;
            }
            if ($current === null) {
                $preamble[] = $line;
            } else {
                $parts[$current][] = $line;
            }
        }
        if ($current === null) {
            $parts['explanation'] = $preamble;
        }
        return array_map(static fn (array $lines): string => trim(implode("\n", $lines)), $parts);
    }

    /**
     * Saves one of the teacher's own "Plan a lesson" answers as a draft in the conversation's class.
     * @param array $message an assistant message in $conversation, which belongs to $user
     * @return int the new lesson's id
     */
    public static function fromCompanion(array $user, array $conversation, array $message): int
    {
        $class = Access::teacherClass($user, (int) $conversation['class_id']);
        [$outline] = Companion::extractPlan((string) $message['content']);
        $content = self::sections($outline);
        $sources = [];
        foreach (Db::all('SELECT ac.*, mv.extension FROM ai_citations ac LEFT JOIN material_versions mv ON mv.id = ac.material_version_id WHERE ac.message_id = :m ORDER BY ac.ref_no', ['m' => (int) $message['id']]) as $c) {
            $sources[] = ['n' => (int) $c['ref_no'], 'material_id' => $c['material_id'] !== null ? (int) $c['material_id'] : null, 'title' => (string) $c['title'], 'where' => Retrieval::location($c)];
        }
        $content['sources'] = $sources;
        $aiAssisted = !(int) $message['is_demo'];
        $id = Db::insert('lessons', [
            'organization_id' => (int) $user['organization_id'],
            'class_id' => (int) $class['id'],
            'title' => mb_substr((string) $conversation['title'], 0, 200),
            'status' => 'draft',
            'grade_level' => $class['grade_level'],
            'language' => $conversation['language'] === 'fil' ? 'fil' : 'en',
            'objectives' => $content['objectives'],
            'draft_content' => json_encode($content, JSON_UNESCAPED_UNICODE),
            'source_material_ids' => json_encode(array_values(array_unique(array_filter(array_column($sources, 'material_id'))))),
            'ai_assisted' => $aiAssisted ? 1 : 0,
            'created_by' => (int) $user['id'],
            'created_at' => Db::now(),
            'updated_at' => Db::now(),
        ]);
        Audit::log('lesson.created', 'lesson', $id, ['ai' => $aiAssisted, 'from' => 'companion']);
        return $id;
    }

    /** @return array{0:string,1:string}|null the heading's text and anything after it on the same line */
    private static function heading(string $line): ?array
    {
        if (preg_match('/^\s{0,3}#{1,6}\s+(.+?)\s*#*\s*$/u', $line, $m)) {
            return [trim($m[1], " *:\t"), ''];
        }
        // A bold label that starts the line: "**Objectives**", "1. **Activities (20 min):** Pairs …"
        if (preg_match('/^\s*(?:\d+[.)]\s*)?\*\*([^*]+?)\*\*\s*:?\s*(.*)$/u', $line, $m)) {
            return [trim($m[1], " :\t"), trim($m[2])];
        }
        return null;
    }

    private static function sectionFor(string $heading): ?string
    {
        $text = mb_strtolower($heading);
        foreach (self::HEADINGS as $section => $words) {
            foreach ($words as $word) {
                if (str_contains($text, $word)) {
                    return $section;
                }
            }
        }
        return null;
    }
}
