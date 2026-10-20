<?php

declare(strict_types=1);

namespace Sibol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sibol\Services\Ai\Retrieval;
use Sibol\Services\Ai\Synonyms;

/** Item 18: Filipino and Taglish questions about English notes, through a bilingual list of school words. */
final class SynonymsTest extends TestCase
{
    public function testAWordBringsInTheRestOfItsGroupBothWays(): void
    {
        self::assertSame(['puso', 'heart', 'hearts'], Synonyms::expand(['puso'])['puso']);
        self::assertContains('puso', Synonyms::expand(['heart'])['heart']);
        self::assertSame(['photosynthesis'], Synonyms::expand(['photosynthesis'])['photosynthesis'], 'a word not on the list stands for itself');
        // "ugat" is a vein in Science and a root in Mathematics: both are looked for.
        self::assertEqualsCanonicalizing(['ugat', 'veins', 'vein', 'roots', 'root'], Synonyms::expand(['ugat'])['ugat']);
    }

    public function testTheSearchLooksForTheEnglishWordsAQuestionStandsFor(): void
    {
        $query = Retrieval::expandedQuery('Ilan ang silid ng puso?');
        self::assertStringStartsWith('Ilan ang silid ng puso?', $query);
        foreach (['chambers', 'chamber', 'heart'] as $word) {
            self::assertStringContainsString(' ' . $word, $query);
        }
        self::assertSame('Where is the heart?', Retrieval::expandedQuery('Where is the heart?', []), 'no keywords, nothing added');
    }

    public function testAPassageCoversAKeywordThroughItsSynonyms(): void
    {
        $passage = 'The heart is a muscular pump. It has four chambers: two atria and two ventricles.';
        self::assertSame(2, Retrieval::coverage($passage, Retrieval::keywords('Ilan ang silid ng puso?')));
        self::assertSame(0, Retrieval::coverage($passage, ['adobo']));
        // Each keyword counts once, however many of its words appear.
        self::assertSame(1, Retrieval::coverage('The heart, the hearts, puso.', ['puso']));
    }
}
