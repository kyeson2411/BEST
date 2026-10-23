<?php

declare(strict_types=1);

namespace Sibol\Services\Ai;

use Sibol\Core\Dates;
use Sibol\Core\Translator;

/**
 * Demo-mode answers: honest, deterministic, and built only from retrieved passages
 * (real text with real citations) or from the student's own upcoming work. It never
 * pretends to be a language model.
 */
final class DemoResponder
{
    private const STOP = ['because', 'through', 'between', 'another', 'different', 'important', 'example', 'usually', 'called',
        'should', 'without', 'during', 'within', 'around', 'therefore', 'however', 'students', 'teacher', 'something', 'whether',
        'sometimes', 'example', 'before', 'things', 'having', 'others', 'itself', 'person', 'people', 'second', 'answer', 'question',
        'questions', 'section', 'notes', 'always', 'enough', 'almost', 'rather', 'number', 'remember', 'explain', 'describe',
        'following', 'learning', 'reading', 'understand', 'probably', 'together', 'clearly', 'directly'];

    public static function respond(array $user, array $conversation, string $question, array $sources, array $upcoming = []): string
    {
        // The reply follows the session language and the message, as a connected model is told to.
        $locale = Translator::locale();
        Translator::setLocale(Language::answerLanguage((string) ($conversation['language'] ?? 'en'), $question));
        try {
            return self::compose($user, $conversation, $question, $sources, $upcoming);
        } finally {
            Translator::setLocale($locale);
        }
    }

    private static function compose(array $user, array $conversation, string $question, array $sources, array $upcoming): string
    {
        $mode = (string) $conversation['mode'];
        $label = '**' . t('Demo mode') . ':** ' . t('no AI model is connected, so this reply is assembled from your class materials, not written by an AI.');

        if ($mode === 'study_plan') {
            return $label . "\n\n" . self::studyPlan($upcoming);
        }
        // A greeting or "1+1" asks about nothing in the materials, so it is not told that they don't cover it.
        if ($mode !== 'summarize' && Retrieval::namesNoTopic($question)) {
            return $label . "\n\n" . ($user['role'] === 'teacher'
                ? t('What are you preparing? Name a topic, a lesson or practice items, and I will start from your class materials.')
                : t('Ask me about a topic from your class materials, and I will show you the passages that match. What would you like to look at?'));
        }
        if (!$sources) {
            return $label . "\n\n" . t('None of the released class materials matched “:q”. Try different key words, choose a class or a specific material when you start a session, or ask your teacher.', ['q' => \Sibol\Core\Markdown::plain($question, 80)]);
        }
        // Passages that share only some of the question's key words are offered as the closest
        // match, never as an answer the materials give.
        $partial = $mode !== 'summarize' && Retrieval::partialMatch($question, $sources);
        if ($partial && in_array($mode, ['practice', 'flashcards', 'socratic'], true)) {
            $label .= "\n\n" . t('Your class materials may not cover this directly. What follows is built from the closest passages.');
        }

        switch ($mode) {
            case 'practice':
                $items = self::clozeItems($sources, 3);
                if (!$items) {
                    break;
                }
                $lines = [$label, '', t('Practice from your materials. Fill in each blank, then check the answers below.'), ''];
                foreach ($items as $i => $item) {
                    $lines[] = ($i + 1) . '. ' . $item['question'] . ' [' . $item['source'] . ']';
                }
                $lines[] = '';
                $lines[] = '---';
                $lines[] = '**' . t('Answers') . ':** ' . implode('; ', array_map(static fn ($it, $i) => ($i + 1) . '. ' . $it['answer'], $items, array_keys($items)));
                return implode("\n", $lines);

            case 'flashcards':
                $cards = self::flashcards($sources, 5);
                $lines = [$label, '', t('Flashcards from your materials:'), ''];
                foreach ($cards as $card) {
                    $lines[] = '- **' . t('Front') . ':** ' . $card['front'] . ' / **' . t('Back') . ':** ' . $card['back'] . ' [' . $card['source'] . ']';
                }
                return implode("\n", $lines);

            case 'summarize':
                $lines = [$label, '', t('Main points, in the order they appear in the material:'), ''];
                foreach (array_slice($sources, 0, 8) as $s) {
                    $first = self::sentences((string) $s['content'])[0] ?? '';
                    $where = Retrieval::location($s);
                    $lines[] = '- ' . ($where !== '' ? '**' . $where . ':** ' : '') . $first . ' [' . $s['n'] . ']';
                }
                $lines[] = '';
                $lines[] = t('Try this: cover the list, then write the main idea of each part from memory.');
                return implode("\n", $lines);

            case 'socratic':
                $best = $sources[0];
                $quote = Citations::excerpt((string) $best['content'], Retrieval::expandedQuery($question));
                return $label . "\n\n" . t('Start with this passage:') . "\n\n> " . $quote . ' [' . $best['n'] . "]\n\n"
                    . t('Question for you: in your own words, what does this passage tell you about your question? Write your answer, then compare it with the passage.');
        }

        // explain, vocabulary, ask_materials, teacher modes: the best matching passages.
        $lines = [$label, '', $partial ? t('Your class materials may not cover this directly. These are the closest passages:') : t('The passages that best match your question:'), ''];
        // A question in Filipino or Taglish chooses its quotation by the English words it stands for too.
        $citing = Retrieval::expandedQuery($question);
        foreach (array_slice($sources, 0, 3) as $s) {
            $where = Retrieval::location($s);
            $lines[] = '- “' . Citations::excerpt((string) $s['content'], $citing) . '” *' . $s['title'] . ($where !== '' ? ', ' . $where : '') . '* [' . $s['n'] . ']';
        }
        $lines[] = '';
        $lines[] = $user['role'] === 'teacher'
            ? t('With an AI provider connected, B.E.S.T. Learning and Interactive Study System would draft from these passages for you to review.')
            : t('Next step: read the passages, then explain the idea in your own words. Ask your teacher if something is still unclear.');
        return implode("\n", $lines);
    }

    /**
     * Fill-in-the-blank items from real sentences, with distractors from other key terms.
     * @return list<array{question:string,answer:string,options:list<string>,source:int,sentence:string}>
     */
    public static function clozeItems(array $sources, int $count): array
    {
        $candidates = [];
        foreach ($sources as $s) {
            foreach (self::sentences((string) $s['content']) as $sentence) {
                $len = mb_strlen($sentence);
                if ($len < 45 || $len > 230) {
                    continue;
                }
                $term = self::keyTerm($sentence);
                if ($term !== null) {
                    $candidates[] = ['sentence' => $sentence, 'term' => $term, 'source' => (int) $s['n']];
                }
            }
        }
        $terms = array_values(array_unique(array_map(static fn ($c) => mb_strtolower($c['term']), $candidates)));
        $items = [];
        $usedTerms = [];
        foreach ($candidates as $c) {
            $lower = mb_strtolower($c['term']);
            if (isset($usedTerms[$lower])) {
                continue;
            }
            $usedTerms[$lower] = true;
            $blanked = (string) preg_replace('/\b' . preg_quote($c['term'], '/') . '\b/u', '_____', $c['sentence'], 1);
            $distractors = array_values(array_filter($terms, static fn ($t) => $t !== $lower));
            $options = [];
            if (count($distractors) >= 3) {
                $pick = array_slice($distractors, (count($items) * 3) % max(1, count($distractors) - 2), 3);
                $options = array_merge([$lower], $pick);
                sort($options);
            }
            $items[] = ['question' => $blanked, 'answer' => $lower, 'options' => $options, 'source' => $c['source'], 'sentence' => $c['sentence']];
            if (count($items) >= $count) {
                break;
            }
        }
        return $items;
    }

    /** @return list<array{front:string,back:string,source:int}> */
    public static function flashcards(array $sources, int $count): array
    {
        $cards = [];
        foreach ($sources as $s) {
            foreach (self::sentences((string) $s['content']) as $sentence) {
                if (preg_match('/^(?:A |An |The )?([\p{L} -]{3,40}?) (?:is|are|means|refers to) (.{15,200})$/u', $sentence, $m)) {
                    $cards[] = ['front' => t('What is :term?', ['term' => trim($m[1])]), 'back' => $sentence, 'source' => (int) $s['n']];
                } elseif (($term = self::keyTerm($sentence)) !== null && mb_strlen($sentence) <= 200) {
                    $cards[] = ['front' => (string) preg_replace('/\b' . preg_quote($term, '/') . '\b/u', '_____', $sentence, 1), 'back' => $term, 'source' => (int) $s['n']];
                }
                if (count($cards) >= $count) {
                    return $cards;
                }
            }
        }
        return $cards;
    }

    /** Two short sessions before each upcoming deadline, in the student's timezone. */
    private static function studyPlan(array $upcoming): string
    {
        if (!$upcoming) {
            return t('You have no upcoming deadlines in the next two weeks, so there is nothing to plan around yet. You can add your own study sessions on the Calendar page.');
        }
        $lines = [t('A simple plan built from your upcoming work: two 30-minute sessions before each deadline.'), ''];
        $plan = [];
        $now = Dates::now();
        foreach (array_slice($upcoming, 0, 4) as $item) {
            $due = Dates::local((string) $item['due_at']);
            if ($due === null || $due <= $now) {
                continue;
            }
            foreach ([2, 1] as $daysBefore) {
                $day = $due->modify('-' . $daysBefore . ' day')->setTime(19, 0);
                if ($day <= $now) {
                    $day = $now->modify('+1 hour')->setTime((int) $now->modify('+1 hour')->format('G'), 0);
                }
                $task = $daysBefore === 2 ? t('Review the materials for :title', ['title' => $item['title']]) : t('Work on :title and check it against the instructions', ['title' => $item['title']]);
                $lines[] = '- ' . Dates::format($day->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'datetime_short') . ' (' . t(':n min', ['n' => 30]) . '): ' . $task;
                $plan[] = $day->format('Y-m-d H:i') . ' | 30 | ' . $task;
            }
        }
        if (!$plan) {
            return t('Your upcoming deadlines are too close to plan sessions before them. Focus on the next due item now.');
        }
        return implode("\n", $lines) . "\n\n```plan\n" . implode("\n", array_unique($plan)) . "\n```";
    }

    /** @return list<string> sentences, without heading lines (Citations::withoutHeadings) */
    public static function sentences(string $text): array
    {
        $text = (string) preg_replace('/\s+/u', ' ', Citations::withoutHeadings($text));
        $parts = preg_split('/(?<=[.!?])\s+(?=[\p{Lu}\d"“(])/u', trim($text)) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn ($s) => $s !== '' && !str_starts_with($s, '#')));
    }

    private static function keyTerm(string $sentence): ?string
    {
        preg_match_all('/\b\p{L}{6,}\b/u', $sentence, $m, PREG_OFFSET_CAPTURE);
        $best = null;
        foreach ($m[0] as [$word, $offset]) {
            if ($offset === 0 || in_array(mb_strtolower($word), self::STOP, true)) {
                continue;
            }
            if ($best === null || mb_strlen($word) > mb_strlen($best)) {
                $best = $word;
            }
        }
        return $best;
    }
}
