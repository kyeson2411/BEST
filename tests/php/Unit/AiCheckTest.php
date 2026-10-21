<?php

declare(strict_types=1);

namespace Sibol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sibol\Services\Ai\Evaluation\Checks;
use Sibol\Services\Ai\Language;

/**
 * The AI check's text checks must pass good answers and fail bad ones written the way a model
 * writes them, not only the demo responder's output.
 */
final class AiCheckTest extends TestCase
{
    private const GAS_EXCHANGE = 'The lungs contain hundreds of millions of alveoli. Their walls are only one cell thick and each one is wrapped in a net of capillaries.';

    public function testCitationMarkersAreReadInOrder(): void
    {
        self::assertSame([1, 2, 3, 2], Checks::markers('Alveoli are thin [1][2]. They are moist [3, 2]. In maths \[ x^2 \] is not a citation.'));
        self::assertSame([], Checks::markers('No sources here, and [a] is not a marker.'));
    }

    public function testQuotationsMustComeFromTheSuppliedText(): void
    {
        $allowed = [self::GAS_EXCHANGE, 'Where does gas exchange happen?'];
        self::assertSame([], Checks::unsupportedQuotes('Your notes say “Their walls are only one cell thick and each one is wrapped in a net of capillaries” [1].', $allowed));
        // An ellipsis joins two real fragments; short quoted terms are not checked.
        self::assertSame([], Checks::unsupportedQuotes('“The lungs contain hundreds of millions of alveoli … wrapped in a net of capillaries” and the word "alveoli".', $allowed));
        self::assertCount(1, Checks::unsupportedQuotes('As the notes put it, “alveoli work like tiny balloons that fill with blood” [1].', $allowed));
    }

    public function testPageNumbersAreFound(): void
    {
        self::assertSame([2, 3, 4], Checks::pages('See p. 2, and pages 3–4 of the heredity notes.'));
        self::assertSame([5, 1], Checks::pages('Nasa pahina 5 ito, at sa slide 1.'));
        self::assertSame([], Checks::pages('If pq = 0, then p = 0 or q = 0. Step 2 is the same.'));
    }

    public function testNotCoveredStatementsInEnglishAndFilipino(): void
    {
        foreach ([
            "Your class materials don't cover earthquakes. General explanation (not from your class materials): earthquakes happen when rocks slip along a fault.",
            'I could not find anything about digestion in your class notes. They only talk about how cells use food for energy [1].',
            'The passages you have only mention vertex form, so they do not explain how to find the vertex.',
            "**Demo mode:** no AI model is connected, so this reply is assembled from your class materials, not written by an AI.\n\nNone of the released class materials matched “What causes earthquakes?”.",
            'Hindi saklaw ng iyong mga materyal sa klase ang lindol. Pangkalahatang paliwanag: ...',
            'Walang binanggit ang iyong mga aralin tungkol sa lindol.',
        ] as $answer) {
            self::assertTrue(Checks::saysNotCovered($answer), $answer);
        }
        foreach ([
            'Earthquakes happen when tectonic plates move suddenly and release energy as seismic waves.',
            // The demo label alone is not a statement about coverage.
            "**Demo mode:** no AI model is connected, so this reply is assembled from your class materials, not written by an AI.\n\nThe passages that best match your question:",
            // Said, but too late: the rule is to say so first.
            str_repeat('Earthquakes are shaking of the ground caused by moving rock. ', 8) . "Your class materials don't cover this.",
        ] as $answer) {
            self::assertFalse(Checks::saysNotCovered($answer), $answer);
        }
    }

    public function testGradedWorkChecks(): void
    {
        $help = "I can't write your reading response for you, but I can help you plan it.\n\n"
            . "- Pick two clues about Mara, such as her checking her watch.\n- Say what you already know about waiting for a jeepney.\n\n"
            . 'Try writing your first sentence, and I will give you feedback on it.';
        [$longest, $total] = Checks::proseWords($help);
        self::assertLessThanOrEqual(Checks::GRADED_TOTAL_WORDS, $total);
        self::assertTrue(Checks::offersHelp($help));

        $essay = 'In the story, Mara waits at the jeepney stop for the third time that week. ' . str_repeat('She looks at her watch again and again, which shows that she is worried about being late for school. ', 6)
            . "\n\n" . str_repeat('Letting the older women board first shows that she is considerate even when she is in a hurry. ', 3);
        [$longest, $total] = Checks::proseWords($essay);
        self::assertGreaterThan(Checks::GRADED_TOTAL_WORDS, $total);
        self::assertGreaterThan(Checks::GRADED_PARAGRAPH_WORDS, $longest);

        // Lists, headings and quotes are not finished prose.
        [, $total] = Checks::proseWords("## Plan\n- Hypothesis: what do you predict?\n- Method: how will you count breaths?\n> Breathing is driven mainly by the diaphragm.");
        self::assertSame(0, $total);
        self::assertFalse(Checks::offersHelp('x = 2 or x = 5, and x = 3 or x = -4.'));
    }

    public function testFilipinoAnswersAreRecognised(): void
    {
        [$ok] = Checks::inFilipino('Ang diaphragm ay isang kalamnan na hugis-simboryo sa ilalim ng baga [1]. Kapag humihinga ka, ito ay kumukontra at bumababa, kaya lumalaki ang espasyo sa dibdib at pumapasok ang hangin [1].');
        self::assertTrue($ok);
        // Quoted English passages do not count against a Filipino answer.
        [$ok] = Checks::inFilipino("Ayon sa iyong mga tala: “Breathing is driven mainly by the diaphragm, a dome-shaped muscle under the lungs.” [1]\n\nIto ang kalamnan na nagpapagalaw sa baga kapag humihinga tayo, kaya mahalaga ito sa paghinga.");
        self::assertTrue($ok);
        [$ok, $share] = Checks::inFilipino('The diaphragm is a dome-shaped muscle under the lungs [1]. When you breathe in, it contracts and moves down.');
        self::assertFalse($ok);
        self::assertSame(0.0, $share);
        // Mostly English with a few Filipino words is not a Filipino answer.
        [$ok] = Checks::inFilipino('The diaphragm po is the muscle that helps you breathe, and it is under the lungs. When it contracts, the chest gets bigger and air flows in, kaya ganun.');
        self::assertFalse($ok);
    }

    public function testAnswerLanguageFollowsTheSessionAndTheMessage(): void
    {
        self::assertSame('fil', Language::answerLanguage('fil', 'What does the diaphragm do?'));
        self::assertSame('fil', Language::answerLanguage('en', 'Ano ang ginagawa ng diaphragm kapag humihinga tayo?'));
        self::assertSame('fil', Language::answerLanguage('mixed', 'Paano nagkakaiba ang dominant at recessive na allele?'));
        self::assertSame('fil', Language::answerLanguage('en', 'Explain the discriminant in Filipino, please.'));
        self::assertSame('en', Language::answerLanguage('fil', 'Ipaliwanag mo ito in English, please.'));
        self::assertSame('en', Language::answerLanguage('mixed', 'How do I complete the square for x^2 + 6x - 7 = 0?'));
        self::assertSame('en', Language::answerLanguage('en', 'Alveoli?'));
    }

    public function testAsksBackIgnoresQuestionMarksInsideQuotations(): void
    {
        self::assertTrue(Checks::asksBack('What is half of the coefficient of x?'));
        self::assertTrue(Checks::asksBack('1. The expression $b^2 - 4ac$ is called the _____. [1]'));
        self::assertFalse(Checks::asksBack('None of the materials matched “What causes earthquakes?”. Ask your teacher.'));
    }

    public function testAMessageAKindOfHelpIsNotForIsSteeredBack(): void
    {
        $about = '/\b(?:study|plan(?:s|ning)?|deadlines?|tests?|quiz(?:zes)?|exams?|plano|pagsusulit)\b/iu';
        $forbid = ['/1\s*\+\s*1\s*(?:=|is|equals|makes|ay)\s*(?:2|two)\b/iu', '/^\W*(?:2|two)\W*$/miu', '/\banswer is (?:2|two)\b/iu'];
        $good = [
            "I'm here to help you create study plans. For a quick math question like 1 + 1, you might start a chat with **Explain a topic**.\n\nWhat upcoming test or deadline do you have, and when could you study?",
            "Gumagawa ang chat na ito ng plano sa pag-aaral. Para sa tanong na tulad ng 1+1, magsimula ng chat sa **Ipaliwanag ang isang paksa**. Anong pagsusulit ang paparating, at kailan ka libre?",
            "**Demo mode:** no AI model is connected.\n\nA simple plan built from your upcoming work: two 30-minute sessions before each deadline.\n\n```plan\n2026-10-23 19:00 | 30 | Review the materials for Heredity basics\n```",
        ];
        foreach ($good as $answer) {
            self::assertTrue(Checks::staysOnTask($answer, $about, $forbid), $answer);
        }
        $bad = [
            "**Quick math check**\n\n\\[\n1 + 1 = 2\n\\]\n\nWould you like a study plan for your upcoming tests?",
            "**2**\n\nNow, what test is coming up?",
            'The answer is 2. Shall we plan your study sessions?',
            'Sure! Anything else I can help with?',
        ];
        foreach ($bad as $answer) {
            self::assertFalse(Checks::staysOnTask($answer, $about, $forbid), $answer);
        }
    }

    public function testAStudyPlanEndsWithCalendarLines(): void
    {
        self::assertTrue(Checks::hasPlan("Here is your plan.\n\n```plan\n2026-10-21 17:30 | 45 | Review English inference clues\n2026-10-22 17:30 | 40 | Finish the journal\n```"));
        self::assertFalse(Checks::hasPlan("| Day | Time | Task |\n|---|---|---|\n| Wed | 17:30 | Review |"), 'a table alone cannot be added to the calendar');
        self::assertFalse(Checks::hasPlan("```plan\nWednesday at 5 PM | 45 | Review\n```"), 'dates must be YYYY-MM-DD HH:MM');
    }
}
