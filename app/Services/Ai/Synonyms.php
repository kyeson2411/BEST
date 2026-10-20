<?php

declare(strict_types=1);

namespace Sibol\Services\Ai;

/**
 * A bilingual list of school words, so a question in Filipino or Taglish finds class materials written in English
 * (and the other way round): "Ilan ang silid ng puso?" also looks for "heart" and "chambers". Each group holds
 * words that mean the same; a question's word brings in the rest of its group. Retrieval uses it to widen the
 * full-text search and to count which of the question's terms a passage covers. Kept to subject words students
 * use in class; grammar words are left to Retrieval::keywords(), which drops them.
 */
final class Synonyms
{
    private const GROUPS = [
        // The body: breathing and circulation.
        ['heart', 'hearts', 'puso'],
        ['blood', 'dugo'],
        ['lungs', 'lung', 'baga'],
        ['breathing', 'breathe', 'breath', 'paghinga', 'hinga', 'huminga', 'humihinga', 'hininga'],
        ['inhale', 'inhaling', 'lumanghap', 'paglanghap', 'langhap'],
        ['exhale', 'exhaling', 'bumuga', 'pagbuga', 'ibuga'],
        ['air', 'hangin'],
        ['body', 'katawan'],
        ['cell', 'cells', 'selula', 'selyula'],
        ['veins', 'vein', 'ugat'],
        ['arteries', 'artery', 'arterya'],
        ['vessels', 'vessel', 'daluyan'],
        ['chambers', 'chamber', 'silid', 'kamara'],
        ['oxygen', 'oksiheno', 'oksihino'],
        ['carbon', 'karbon'],
        ['dioxide', 'dioksido'],
        ['muscle', 'muscles', 'kalamnan', 'masel'],
        ['smoking', 'smoke', 'paninigarilyo', 'sigarilyo', 'yosi', 'pagyoyosi'],
        ['exercise', 'ehersisyo'],
        ['healthy', 'health', 'malusog', 'kalusugan'],
        ['energy', 'enerhiya'],
        ['food', 'pagkain'],
        ['nose', 'ilong'],
        ['mouth', 'bibig'],
        ['chest', 'dibdib'],
        ['ribs', 'rib', 'tadyang'],
        ['thin', 'manipis'],
        ['thick', 'makapal'],
        // Heredity.
        ['traits', 'trait', 'characteristics', 'katangian'],
        ['parents', 'parent', 'magulang'],
        ['offspring', 'anak', 'supling'],
        ['inheritance', 'inherited', 'inherit', 'heredity', 'mana', 'namamana', 'pagmamana', 'minana', 'manahin'],
        ['genes', 'gene'],
        ['dominant', 'dominante', 'nangingibabaw'],
        ['recessive', 'resesibo'],
        ['flower', 'flowers', 'bulaklak'],
        ['plants', 'plant', 'halaman'],
        ['probability', 'chance', 'posibilidad', 'tsansa'],
        // Mathematics.
        ['equation', 'equations', 'ekwasyon', 'ekwasyong'],
        ['solve', 'solving', 'lutasin', 'malutas', 'paglutas'],
        ['solution', 'solutions', 'solusyon'],
        ['formula', 'pormula'],
        ['roots', 'root', 'ugat'],
        ['factor', 'factoring', 'salik'],
        ['quadratic', 'kuwadratiko', 'kwadratiko'],
        ['sum', 'kabuuan'],
        ['product', 'produkto'],
        ['fraction', 'fractions', 'praksiyon', 'praksyon'],
        ['graph', 'grap'],
        // Reading.
        ['inference', 'inferences', 'infer', 'hinuha', 'hinuhang', 'paghihinuha'],
        ['reading', 'read', 'pagbasa', 'pagbabasa', 'basahin'],
        ['story', 'kuwento', 'kwento'],
        ['character', 'characters', 'tauhan'],
        ['clues', 'clue', 'palatandaan', 'pahiwatig'],
        ['prediction', 'predict', 'hula', 'panghuhula', 'prediksyon'],
        ['text', 'teksto'],
        ['mistakes', 'mistake', 'errors', 'mali', 'pagkakamali'],
        ['conclusion', 'konklusyon', 'kongklusyon'],
        ['idea', 'ideas', 'ideya'],
    ];

    /**
     * Each keyword with the words it stands for, itself first.
     *
     * @param list<string> $keywords lower case
     * @return array<string,list<string>>
     */
    public static function expand(array $keywords): array
    {
        static $index = null;
        if ($index === null) {
            $index = [];
            foreach (self::GROUPS as $group) {
                foreach ($group as $word) {
                    $index[$word] = array_merge($index[$word] ?? [], $group);
                }
            }
        }
        $out = [];
        foreach ($keywords as $keyword) {
            $out[$keyword] = array_values(array_unique(array_merge([$keyword], $index[$keyword] ?? [])));
        }
        return $out;
    }
}
