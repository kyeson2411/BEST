<?php

declare(strict_types=1);

namespace Sibol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sibol\Services\Ai\GradedWork;

/**
 * How Sibol recognises a quiz question pasted into the companion, and how it tells that a reply gives the answer
 * away, so the hints-only rule does not depend on the model alone.
 */
final class GradedWorkTest extends TestCase
{
    private const PUNNETT = 'Two heterozygous tall pea plants (Tt) are crossed. What is the probability that an offspring is short?';

    private static function choice(string $prompt, array $options, string $correct): array
    {
        $letters = ['A', 'B', 'C', 'D'];
        return ['qtype' => 'multiple_choice', 'prompt' => $prompt, 'answer_key' => ['correct' => $correct],
            'options' => array_map(static fn (int $i, string $text): array => ['key' => $letters[$i], 'text' => $text], array_keys($options), $options)];
    }

    public function testAPastedQuizQuestionIsRecognised(): void
    {
        // As a student tried it on 2026-10-25, when the model worked the answer out in full.
        self::assertTrue(GradedWork::quotes('In the Heredity basics quiz, two Tt pea plants are crossed. What is the probability that an offspring is short? Just tell me the answer.', self::PUNNETT));
        self::assertTrue(GradedWork::quotes('two heterozygous tall pea plants are crossed, probability of a short offspring??', self::PUNNETT), 'case and punctuation do not matter');
    }

    public function testStudyingTheSameTopicIsNotTreatedAsTheQuizQuestion(): void
    {
        self::assertFalse(GradedWork::quotes('How do I use a Punnett square to find the probability of short offspring?', self::PUNNETT));
        self::assertFalse(GradedWork::quotes('I am stuck on the Punnett square question in my Heredity basics quiz. Can you help me?', self::PUNNETT));
    }

    public function testAShortQuestionCountsOnlyWhenQuotedWhole(): void
    {
        self::assertTrue(GradedWork::quotes('What is the term for a trait? I need it now', 'What is the term for a trait?'));
        self::assertFalse(GradedWork::quotes('Can you explain what a trait is?', 'What is the term for a trait?'));
    }

    public function testTheCorrectChoiceWrittenAnyWayGivesTheAnswerAway(): void
    {
        $q = self::choice(self::PUNNETT, ['0', '1 in 4', '1 in 2', '3 in 4'], 'B');
        foreach ([
            'Probability (short) = \frac{1}{4} = 0.25 = 25\%',
            'Only one box is tt, so it is 1/4.',
            'That makes it one in four.',
            'So the chance is 0.25.',
            'The answer is B.',
            'It is option (B), since only tt is short.',
            '1 out of 4 offspring would be short.',
        ] as $reply) {
            self::assertTrue(GradedWork::givesAway($reply, $q), $reply);
        }
    }

    public function testHintsWithADifferentExampleAreKept(): void
    {
        $q = self::choice(self::PUNNETT, ['0', '1 in 4', '1 in 2', '3 in 4'], 'B');
        foreach ([
            'Try a different cross first: Aa x aa. Half of the boxes, 1/2, are aa. Now draw the square for your own plants. What do you get?',
            'Write the gametes of each parent along the sides of the square, then count the boxes that show the recessive trait.',
            'The genotypic ratio for that kind of cross is written like 1 TT : 2 Tt : 1 tt, but work out the chance yourself.',
        ] as $reply) {
            self::assertFalse(GradedWork::givesAway($reply, $q), $reply);
        }
    }

    public function testTheLetterCountsOnlyInCapitals(): void
    {
        $q = self::choice('Which gas do the alveoli pass into the blood during breathing?', ['Oxygen', 'Nitrogen', 'Argon', 'Helium'], 'A');
        self::assertFalse(GradedWork::givesAway('The answer is a gas that your cells use for energy. Which one is it?', $q));
        self::assertTrue(GradedWork::givesAway('The answer is A, oxygen.', $q));
        self::assertTrue(GradedWork::givesAway('Your cells need oxygen, so that is what passes in.', $q), 'the choice text itself');
    }

    public function testAcceptedShortAnswersGiveItAwayButWordsAlreadyInTheQuestionDoNot(): void
    {
        $q = ['qtype' => 'short_answer', 'prompt' => 'What is the term for different versions of the same gene?', 'answer_key' => ['accepted' => ['alleles', 'allele']]];
        self::assertTrue(GradedWork::givesAway('These different versions are called alleles.', $q));
        self::assertFalse(GradedWork::givesAway('Think about the word your notes use for the different forms of one gene.', $q));

        $inPrompt = ['qtype' => 'short_answer', 'prompt' => 'Name the gene version that is hidden: the recessive or the dominant?', 'answer_key' => ['accepted' => ['recessive']]];
        self::assertFalse(GradedWork::givesAway('Compare what recessive and dominant mean in your notes.', $inPrompt), 'a word the question itself shows is no give-away');
    }

    public function testTrueFalseAndEssayAnswersAreLeftToTheInstruction(): void
    {
        self::assertFalse(GradedWork::givesAway('That statement is true.', ['qtype' => 'true_false', 'prompt' => 'Codominance shows both alleles.', 'answer_key' => ['correct' => true]]));
        self::assertFalse(GradedWork::givesAway('Here is a full paragraph.', ['qtype' => 'essay', 'prompt' => 'Explain codominance.', 'answer_key' => null]));
    }
}
