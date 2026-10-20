<?php

declare(strict_types=1);

namespace Sibol\Services\Ai\Evaluation;

use Sibol\Core\Db;
use Sibol\Services\Ai\Retrieval;

/**
 * A fixed set of questions in English, Filipino and Taglish about the demo school's materials, each with the
 * passage that should come back, to measure retrieval (bin/sibol.php ai:retrieval). It sends nothing to an AI
 * provider: it only runs Retrieval::search() as Andrea, a demo student in the Narra classes.
 */
final class RetrievalSet
{
    public const STUDENT = 'andrea.cruz';
    public const LANGUAGES = ['en' => 'English', 'fil' => 'Filipino', 'taglish' => 'Taglish', 'none' => 'Not in the materials'];

    /**
     * Each case: id, language, class name, question, and the passage that should be found (a material title and the
     * passage's heading or its first words), or null when the materials do not cover the question.
     *
     * @return list<array{id:string,lang:string,class:string,question:string,expect:?array{0:string,1:string}}>
     */
    public static function cases(): array
    {
        $sci = 'Science 9 – Narra';
        $math = 'Mathematics 9 – Narra';
        $eng = 'English 9 – Narra';
        $notes = 'Breathing and circulation notes';
        $heredity = 'Heredity notes';
        $quadratic = 'Quadratic equations: four methods';
        $reading = 'Making inferences while reading';
        $rows = [
            ['E01', 'en', $sci, 'How does oxygen get from the air into the blood?', [$notes, 'Gas exchange in the alveoli']],
            ['E02', 'en', $sci, 'What does the diaphragm do when we inhale?', [$notes, 'How breathing works']],
            ['E03', 'en', $sci, 'How many chambers does the heart have?', [$notes, 'The heart and its four chambers']],
            ['E04', 'en', $sci, 'What is the difference between arteries and veins?', [$notes, 'Blood vessels']],
            ['E05', 'en', $sci, 'How do you use a Punnett square?', [$heredity, 'Using a Punnett square']],
            ['E06', 'en', $math, 'When should I use the quadratic formula?', [$quadratic, 'Choosing a method']],
            ['E07', 'en', $eng, 'What is an inference?', [$reading, 'What an inference is']],
            ['E08', 'en', $eng, 'What are the common mistakes?', [$reading, 'Common mistakes']],
            ['F01', 'fil', $sci, 'Paano pumapasok ang oxygen sa dugo mula sa baga?', [$notes, 'Gas exchange in the alveoli']],
            ['F02', 'fil', $sci, 'Ano ang ginagawa ng diaphragm kapag humihinga tayo?', [$notes, 'How breathing works']],
            ['F03', 'fil', $sci, 'Ilan ang silid ng puso?', [$notes, 'The heart and its four chambers']],
            ['F04', 'fil', $sci, 'Ano ang pagkakaiba ng ugat at arterya?', [$notes, 'Blood vessels']],
            ['F05', 'fil', $sci, 'Paano namamana ng anak ang mga katangian ng magulang?', [$heredity, 'Genes, chromosomes and DNA']],
            ['F06', 'fil', $sci, 'Bakit masama sa baga at puso ang paninigarilyo?', [$notes, 'Keeping both systems healthy']],
            ['F07', 'fil', $math, 'Paano lutasin ang quadratic equation gamit ang pormula?', [$quadratic, 'Method 4: The quadratic formula']],
            ['F08', 'fil', $eng, 'Ano ang hinuha sa pagbasa?', [$reading, 'What an inference is']],
            ['T01', 'taglish', $sci, 'Bakit manipis ang walls ng alveoli?', [$notes, 'Gas exchange in the alveoli']],
            ['T02', 'taglish', $sci, 'Ano yung trabaho ng left ventricle?', [$notes, 'The heart and its four chambers']],
            ['T03', 'taglish', $sci, 'Paano nagwo-work ang breathing natin?', [$notes, 'How breathing works']],
            ['T04', 'taglish', $sci, 'Ano ang difference ng incomplete dominance at codominance?', [$heredity, 'Patterns beyond simple dominance']],
            ['T05', 'taglish', $sci, 'Saan napupunta ang dugo galing sa right ventricle?', [$notes, 'Two circuits working together']],
            ['T06', 'taglish', $math, 'Paano mag-factor ng quadratic equation?', [$quadratic, 'Method 2: Factoring']],
            ['T07', 'taglish', $math, 'Ano ang sinasabi ng discriminant tungkol sa roots?', [$quadratic, 'The discriminant']],
            ['T08', 'taglish', $eng, 'Ano yung mga common mistakes sa pag-infer?', [$reading, 'Common mistakes']],
            ['N01', 'none', $sci, 'Sino ang unang presidente ng Pilipinas?', null],
            ['N02', 'none', $sci, 'Paano magluto ng adobo?', null],
            ['N03', 'none', $math, 'What is the capital of Japan?', null],
        ];
        return array_map(static fn (array $r): array => ['id' => $r[0], 'lang' => $r[1], 'class' => $r[2], 'question' => $r[3], 'expect' => $r[4]], $rows);
    }

    /**
     * Runs every case. For a covered question, rank is the position (1 to Retrieval::MAX_SOURCES) of the expected
     * passage among those returned, or null when it was not returned; for the others, passed means nothing came back.
     *
     * @return list<array{id:string,lang:string,question:string,rank:?int,returned:int,passed:bool}>
     */
    public static function run(): array
    {
        $user = Db::one(
            'SELECT u.*, o.name AS org_name FROM users u JOIN organizations o ON o.id = u.organization_id WHERE u.username = :u',
            ['u' => self::STUDENT]
        );
        if (!$user) {
            throw new \RuntimeException('The demo school is not loaded (no ' . self::STUDENT . '). Run "php bin/sibol.php db:reset" first.');
        }
        $out = [];
        foreach (self::cases() as $case) {
            $classId = (int) Db::value('SELECT id FROM classes WHERE name = :n', ['n' => $case['class']]);
            $sources = Retrieval::search($user, $case['question'], $classId);
            $rank = null;
            if ($case['expect'] !== null) {
                [$title, $marker] = $case['expect'];
                foreach ($sources as $i => $s) {
                    $text = (string) $s['section_title'] . "\n" . (string) $s['content'];
                    if ($s['title'] === $title && mb_stripos($text, $marker) !== false) {
                        $rank = $i + 1;
                        break;
                    }
                }
            }
            $out[] = [
                'id' => $case['id'],
                'lang' => $case['lang'],
                'question' => $case['question'],
                'rank' => $rank,
                'returned' => count($sources),
                'passed' => $case['expect'] === null ? $sources === [] : $rank !== null,
            ];
        }
        return $out;
    }

    /**
     * Per language: cases, found first, found anywhere in the returned passages (or, for questions the materials do
     * not cover, nothing returned).
     *
     * @param list<array<string,mixed>> $results
     * @return array<string,array{cases:int,first:int,found:int}>
     */
    public static function summary(array $results): array
    {
        $out = [];
        foreach (self::LANGUAGES as $lang => $label) {
            $rows = array_values(array_filter($results, static fn ($r) => $r['lang'] === $lang));
            $out[$lang] = [
                'cases' => count($rows),
                'first' => count(array_filter($rows, static fn ($r) => $r['rank'] === 1 || ($lang === 'none' && $r['passed']))),
                'found' => count(array_filter($rows, static fn ($r) => $r['passed'])),
            ];
        }
        return $out;
    }
}
