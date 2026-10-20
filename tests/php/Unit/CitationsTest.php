<?php

declare(strict_types=1);

namespace Sibol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sibol\Services\Ai\Citations;

/** Citations must point only at passages that were actually retrieved and sent. */
final class CitationsTest extends TestCase
{
    private function source(int $n, string $content, ?int $page = null): array
    {
        return [
            'n' => $n, 'material_id' => 10 + $n, 'version_id' => 20 + $n, 'chunk_id' => 30 + $n, 'title' => 'Notes ' . $n,
            'page_from' => $page, 'page_to' => $page, 'section_title' => null, 'extension' => 'pdf', 'content' => $content,
        ];
    }

    public function testKeepsSuppliedSourcesAndRemovesInventedOnes(): void
    {
        $sources = [$this->source(1, 'The alveoli have walls only one cell thick. They are wrapped in capillaries.', 3)];
        $result = Citations::resolve('Gas exchange is fast because alveoli are thin [1]. Mitochondria are the powerhouse [4].', $sources);

        self::assertSame('Gas exchange is fast because alveoli are thin [1]. Mitochondria are the powerhouse.', $result['text']);
        self::assertCount(1, $result['citations']);
        self::assertSame(1, $result['citations'][0]['ref_no']);
        self::assertSame(3, $result['citations'][0]['page_from']);
        self::assertStringContainsString('one cell thick', $result['citations'][0]['excerpt']);
    }

    public function testMixedMarkerKeepsOnlyValidNumbers(): void
    {
        $result = Citations::resolve('Both systems work together [1, 9].', [$this->source(1, 'The respiratory and circulatory systems work together to deliver oxygen.')]);
        self::assertSame('Both systems work together [1].', $result['text']);
    }

    public function testNoSourcesMeansNoCitations(): void
    {
        $result = Citations::resolve('A claim with a fake source [1].', []);
        self::assertSame([], $result['citations']);
        self::assertSame('A claim with a fake source.', $result['text']);
    }

    public function testExcerptPicksTheBestMatchingSentence(): void
    {
        $passage = 'Arteries carry blood away from the heart. Veins carry blood back to the heart and have valves. Capillaries are the smallest vessels.';
        self::assertSame('Veins carry blood back to the heart and have valves.', Citations::excerpt($passage, 'Which vessels have valves to stop backflow?'));
    }

    /** A PDF page often starts with its headings; they match a question well but say nothing on their own. */
    public function testHeadingLinesAreNeverQuoted(): void
    {
        $page = "Heredity: inheritance and variation\nPatterns beyond simple dominance\nNot every trait follows simple dominance. In incomplete dominance, the heterozygous offspring shows a phenotype in between.";
        self::assertSame('Not every trait follows simple dominance.', Citations::excerpt($page, 'What are the patterns beyond simple dominance?'));
        self::assertSame(
            'Not every trait follows simple dominance. In incomplete dominance, the heterozygous offspring shows a phenotype in between.',
            Citations::withoutHeadings($page)
        );
        self::assertSame('Arteries carry blood away from the heart.', Citations::withoutHeadings("# Blood vessels\nArteries carry blood away from the heart."), 'a markdown heading line goes too');
    }

    public function testListItemsAndWrappedSentencesStay(): void
    {
        $list = "- Gene, allele, chromosome\n- Dominant and recessive\n1. Trace the path of oxygen\n2. Explain the thin walls";
        self::assertSame($list, Citations::withoutHeadings($list));
        // A sentence broken across lines goes on in lower case: it is not a heading.
        $wrapped = "Traits are characteristics that are passed from\nparents to their offspring, such as the colour of a flower.\nGenes carry the instructions.";
        self::assertSame($wrapped, Citations::withoutHeadings($wrapped));
        // A short line ending like a sentence or a question is kept.
        self::assertSame("Inference or prediction?\nA prediction is a kind of inference.", Citations::withoutHeadings("Inference or prediction?\nA prediction is a kind of inference."));
    }
}
