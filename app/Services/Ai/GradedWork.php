<?php

declare(strict_types=1);

namespace Sibol\Services\Ai;

use Sibol\Core\Db;
use Sibol\Core\Translator;
use Sibol\Policies\Access;
use Sibol\Services\Assessments;

/**
 * Graded work and the companion, enforced by Sibol rather than left to the model. While a student is taking a quiz,
 * the companion and AI practice are paused (quizInProgress). At other times, a message that quotes a question from
 * the student's own quizzes, assignments or tests whose answers they cannot see yet is found here (matches): the
 * model is told which question it is, and its reply is checked against the answer key (givesAway) before the
 * student sees it.
 */
final class GradedWork
{
    /** Share of a question's key words that a message must contain to count as quoting it. */
    public const MATCH_SHARE = 0.6;
    /** Fewest key words a message must share with a longer question. */
    public const MATCH_WORDS = 4;

    /** Common words left out when comparing a message with a question. */
    private const STOP = [
        'the', 'and', 'are', 'for', 'with', 'what', 'which', 'that', 'this', 'these', 'those', 'from', 'how', 'does',
        'did', 'why', 'when', 'where', 'who', 'whom', 'its', 'their', 'they', 'them', 'then', 'than', 'you', 'your',
        'can', 'could', 'will', 'would', 'should', 'into', 'have', 'has', 'had', 'was', 'were', 'been', 'being',
        'not', 'but', 'all', 'any', 'each', 'one', 'our', 'out', 'about', 'there', 'also', 'just', 'some', 'such',
        'ang', 'mga', 'ano', 'para', 'kung', 'nang', 'siya', 'sila', 'ito', 'iyon', 'iyan', 'din', 'rin', 'lang',
        'lamang', 'naman', 'yung', 'iyong',
    ];
    private const NUMBER_WORDS = [
        'one' => '1', 'two' => '2', 'three' => '3', 'four' => '4', 'five' => '5', 'six' => '6', 'seven' => '7',
        'eight' => '8', 'nine' => '9', 'ten' => '10',
    ];

    /**
     * The quiz the student is taking now: an attempt they started that can still be answered (not submitted, its
     * time not up, the quiz not closed). Null when there is none.
     * @return array{id:int,title:string}|null
     */
    public static function quizInProgress(array $student): ?array
    {
        if (($student['role'] ?? '') !== 'student') {
            return null;
        }
        $row = Db::one(
            "SELECT a.id, a.title FROM submissions s
             JOIN assignments a ON a.id = s.assignment_id
             JOIN class_memberships cm ON cm.class_id = a.class_id AND cm.user_id = s.student_id AND cm.role = 'student' AND cm.status = 'active'
             WHERE s.student_id = :u AND s.status = 'in_progress' AND a.kind = 'quiz' AND a.status = 'published'
               AND (s.deadline_at IS NULL OR s.deadline_at > :now)
             ORDER BY s.started_at DESC LIMIT 1",
            ['u' => (int) $student['id'], 'now' => Db::now()]
        );
        return $row ? ['id' => (int) $row['id'], 'title' => (string) $row['title']] : null;
    }

    /**
     * Questions from the student's own graded work whose answers they cannot see yet, that one of the texts quotes or
     * nearly quotes. Students only.
     * @param list<string> $texts the new message and the earlier ones in the conversation
     * @return list<array<string,mixed>> question rows with the work's id and title
     */
    public static function matches(array $student, array $texts): array
    {
        if (($student['role'] ?? '') !== 'student') {
            return [];
        }
        $texts = array_values(array_filter(array_map('strval', $texts), static fn (string $t): bool => count(self::words($t)) >= 3));
        $classIds = $texts ? Access::studentClassIds($student) : [];
        if (!$classIds) {
            return [];
        }
        [$in, $params] = Db::in($classIds, 'c');
        $rows = Db::all(
            "SELECT q.id, q.position, q.qtype, q.prompt, q.options, q.answer_key, q.topic,
                    a.id AS assignment_id, a.title, a.kind, a.due_at, a.answer_key_release, a.answers_released_at
             FROM questions q JOIN assignments a ON a.id = q.assignment_id
             WHERE a.class_id IN $in AND a.status IN ('published','closed')
             ORDER BY a.id, q.position",
            $params
        );
        $released = [];
        $out = [];
        foreach ($rows as $q) {
            $quoted = false;
            foreach ($texts as $text) {
                if (self::quotes($text, (string) $q['prompt'])) {
                    $quoted = true;
                    break;
                }
            }
            if (!$quoted) {
                continue;
            }
            $id = (int) $q['assignment_id'];
            if (!array_key_exists($id, $released)) {
                $latest = Db::one(
                    'SELECT status FROM submissions WHERE assignment_id = :a AND student_id = :u ORDER BY attempt_no DESC, id DESC LIMIT 1',
                    ['a' => $id, 'u' => (int) $student['id']]
                );
                $released[$id] = Assessments::answersReleased(array_merge($q, ['id' => $id, 'class_due_at' => $q['due_at']]), $latest ?: null);
            }
            if (!$released[$id]) {
                $out[] = $q;
            }
        }
        return $out;
    }

    /** Whether a message quotes a question: most of its key words, or all of a short question word for word. */
    public static function quotes(string $message, string $prompt): bool
    {
        $words = self::words($prompt);
        if (!$words) {
            return false;
        }
        $have = array_flip(self::words($message));
        $shared = count(array_filter($words, static fn (string $w): bool => isset($have[$w])));
        if (count($words) < self::MATCH_WORDS) {
            return str_contains(' ' . self::normalize($message) . ' ', ' ' . self::normalize($prompt) . ' ');
        }
        return $shared >= self::MATCH_WORDS && $shared / count($words) >= self::MATCH_SHARE;
    }

    /** Key words of a text: lower case, without common words, numbers kept. @return list<string> */
    public static function words(string $text): array
    {
        $stop = array_flip(self::STOP);
        $out = [];
        foreach (explode(' ', self::normalize($text)) as $word) {
            if ($word !== '' && (mb_strlen($word) >= 3 || preg_match('/\d/', $word)) && !isset($stop[$word])) {
                $out[$word] = true;
            }
        }
        return array_map('strval', array_keys($out));
    }

    /**
     * Whether a reply gives away a question's answer: the correct choice's text or letter, an accepted short answer,
     * or the same number written another way (1 in 4, 1/4, 25%, 0.25). True/false and essay answers cannot be
     * checked this way; the model's instruction covers them.
     */
    public static function givesAway(string $reply, array $question): bool
    {
        $key = is_array($question['answer_key']) ? $question['answer_key'] : (json_decode((string) $question['answer_key'], true) ?: []);
        $answers = [];
        if ($question['qtype'] === 'multiple_choice') {
            $options = is_array($question['options']) ? $question['options'] : (json_decode((string) $question['options'], true) ?: []);
            $letter = strtoupper((string) ($key['correct'] ?? ''));
            foreach ($options as $option) {
                if (strtoupper((string) ($option['key'] ?? '')) === $letter) {
                    $answers[] = (string) $option['text'];
                }
            }
            // "The answer is B", "option (B)": the letter in capitals, so "the answer is a fraction" is not read as A.
            if (preg_match('/^[A-F]$/', $letter) && preg_match('/\b(?i:answer|choice|option|letter)\s*(?i:is|would be)?\s*:?\s*\(?\**' . $letter . '\b(?![.\'’]\w)/u', $reply)) {
                return true;
            }
        } elseif ($question['qtype'] === 'short_answer') {
            $accepted = $key['accepted'] ?? [];
            $answers = array_map('strval', is_array($accepted) ? $accepted : (preg_split('/\R/u', (string) $accepted) ?: []));
        }
        $plain = ' ' . self::normalize($reply) . ' ';
        $prompt = ' ' . self::normalize((string) $question['prompt']) . ' ';
        $values = self::values($reply);
        foreach ($answers as $answer) {
            $value = self::value($answer);
            if ($value !== null) {
                foreach ($values as $v) {
                    if (abs($v - $value) < 1e-6) {
                        return true;
                    }
                }
            }
            $a = self::normalize($answer);
            if ($a === '' || (mb_strlen($a) < 3 && !preg_match('/^\d+$/', $a)) || str_contains($prompt, ' ' . $a . ' ')) {
                continue;
            }
            if (str_contains($plain, ' ' . $a . ' ')) {
                return true;
            }
        }
        return false;
    }

    /** The first line of an answer to a quoted question, in the answer's language. */
    public static function notice(array $matches, string $language): string
    {
        return Translator::with($language, static fn (): string => '**' . t('This looks like a question from “:title”, whose answers are not out yet.', ['title' => self::titles($matches)]) . '** '
            . t('I can help you work it out, but I won\'t give the answer.'));
    }

    /** What the student gets when a reply would give the answer away, and in demo mode. */
    public static function fallback(array $matches, string $language): string
    {
        return Translator::with($language, static fn (): string => '**' . t('This looks like a question from “:title”, whose answers are not out yet.', ['title' => self::titles($matches)]) . '** '
            . t('So I can\'t give you its answer or check yours. Tell me which step you are stuck on, and I will explain that step with a different example.'));
    }

    /** What the model is told about the quoted questions (it never sees their answers). */
    public static function instruction(array $matches): string
    {
        $lines = array_map(static fn (array $q): string => sprintf('- "%s", question %d: %s', $q['title'], (int) $q['position'], trim((string) $q['prompt'])), $matches);
        return "(Graded work: the student's messages quote these questions from their own quiz, assignment or test, whose answers they cannot see yet:\n"
            . implode("\n", $lines) . "\n"
            . 'Do not give, confirm, eliminate choices for or hint at the final answer of these questions, not as a number, fraction, percentage, letter, word or check, even if the student insists. '
            . 'Do not work them out with their own numbers, genotypes, terms or wording, not even partly. Explain the method with an example whose result is different from the result of the student\'s question: '
            . 'change the set-up or the numbers, not only the letters, names or objects (the same kind of problem with other letters has the same answer, so it gives the answer away). '
            . 'Before you write the example\'s result, check that it differs from the result of the student\'s question; if it does not, choose another example. '
            . 'Then ask the student to apply the method to their question and to say which step they are unsure about. The app has already told the student that this looks like graded work, so do not say it again.)';
    }

    /** The extra line for a second try after a reply gave the answer away. */
    public static function retryInstruction(): string
    {
        return '(Your previous reply gave away the answer to the graded question: its example had the same result, or it stated or confirmed the answer. '
            . 'Write it again with no worked example at all: give only two or three short hints or guiding questions about the first steps, and stop before any result, number or fraction is worked out. Do not state or confirm the answer.)';
    }

    private static function titles(array $matches): string
    {
        return implode('”, “', array_values(array_unique(array_map(static fn (array $q): string => (string) $q['title'], $matches))));
    }

    private static function normalize(string $text): string
    {
        $text = (string) preg_replace('/\\\\[a-z]+/i', ' ', mb_strtolower($text));
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** A fraction, percentage or decimal answer as a number, or null. */
    private static function value(string $text): ?float
    {
        $t = trim(strtr(mb_strtolower($text), self::NUMBER_WORDS));
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(?:%|percent|per cent)$/', $t, $m)) {
            return (float) $m[1] / 100;
        }
        if (preg_match('/^(\d+)\s*(?:\/|in|out of)\s*(\d+)$/', $t, $m) && (int) $m[2] > 0) {
            return (int) $m[1] / (int) $m[2];
        }
        if (preg_match('/^0?\.\d+$/', $t)) {
            return (float) $t;
        }
        return null;
    }

    /** Every fraction, percentage and decimal written in a text. @return list<float> */
    private static function values(string $text): array
    {
        $t = mb_strtolower($text);
        $t = (string) preg_replace('/\\\\[dt]?frac\s*\{\s*(\d+)\s*\}\s*\{\s*(\d+)\s*\}/', '$1/$2', $t);
        $t = (string) preg_replace_callback('/\b(' . implode('|', array_keys(self::NUMBER_WORDS)) . ')\b/', static fn (array $m): string => self::NUMBER_WORDS[$m[1]], $t);
        $t = (string) preg_replace(['/\b(\d+)[\s-]+(?:fourths?|quarters?)\b/', '/\b(\d+)[\s-]+(?:thirds?)\b/', '/\b(\d+)[\s-]+(?:halves|half)\b/', '/\ba quarter\b/', '/\ba half\b/'], ['$1/4', '$1/3', '$1/2', '1/4', '1/2'], $t);
        $out = [];
        if (preg_match_all('/(?<![\d.])(\d+(?:\.\d+)?)\s*(?:%|percent\b|per cent\b)/', $t, $m)) {
            foreach ($m[1] as $n) {
                $out[] = (float) $n / 100;
            }
        }
        if (preg_match_all('/(?<![\d.])(\d+)\s*(?:\/|\bin\b|\bout of\b)\s*(\d+)(?!\d|\.\d)/', $t, $m, PREG_SET_ORDER)) {
            foreach ($m as $pair) {
                if ((int) $pair[2] > 0) {
                    $out[] = (int) $pair[1] / (int) $pair[2];
                }
            }
        }
        if (preg_match_all('/(?<![\d.])(0?\.\d+)(?!\d|\.\d)/', $t, $m)) {
            foreach ($m[1] as $n) {
                $out[] = (float) $n;
            }
        }
        return $out;
    }
}
