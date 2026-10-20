<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Translator;

/**
 * A lesson laid out as a DepEd Daily Lesson Log (the rows of the DLL in DepEd Order No. 42, s. 2016), to print. Each
 * of the lesson's sections goes in one row; rows Sibol has nothing for stay blank for the teacher to write in. The
 * labels follow the lesson's own language. The school's own template file can replace this layout later.
 */
final class LessonPlans
{
    /** Which DLL procedure row each lesson section fills. */
    public const PROCEDURES = [
        'A' => 'prior_knowledge',
        'B' => null,
        'C' => 'worked_example',
        'D' => 'explanation',
        'E' => 'activities',
        'F' => 'checks',
        'G' => null,
        'H' => null,
        'I' => 'exit_ticket',
        'J' => null,
    ];

    /**
     * @param array<string,mixed> $lesson the lesson row (title, language) @param array<string,mixed> $content its sections
     * @return array{title:string,header:array<string,string>,parts:list<array{heading:string,rows:list<array{label:string,key:?string,text:string}>}>}
     *         header labels by key (school, grade, teacher, area, dates, quarter); in each row, key is the lesson section
     *         shown there ('sources' for the references), or null for a row left blank
     */
    public static function dll(array $lesson, array $content): array
    {
        $content['title'] = (string) ($lesson['title'] ?? '');
        return Translator::with((string) ($lesson['language'] ?? 'en'), static function () use ($content): array {
            $row = static fn (string $label, ?string $key): array => [
                'label' => $label,
                'key' => $key,
                'text' => $key !== null && $key !== 'sources' ? trim((string) ($content[$key] ?? '')) : '',
            ];
            $procedures = [
                'A' => t('Reviewing the previous lesson or presenting the new lesson'),
                'B' => t('Establishing a purpose for the lesson'),
                'C' => t('Presenting examples or instances of the new lesson'),
                'D' => t('Discussing new concepts and practicing new skills #1'),
                'E' => t('Discussing new concepts and practicing new skills #2'),
                'F' => t('Developing mastery (leads to formative assessment)'),
                'G' => t('Finding practical applications of concepts and skills in daily living'),
                'H' => t('Making generalizations and abstractions about the lesson'),
                'I' => t('Evaluating learning'),
                'J' => t('Additional activities for application or remediation'),
            ];
            $reflection = [
                'A' => t('No. of learners who earned 80% in the evaluation'),
                'B' => t('No. of learners who require additional activities for remediation'),
                'C' => t('Did the remedial lessons work? No. of learners who have caught up with the lesson'),
                'D' => t('No. of learners who continue to require remediation'),
                'E' => t('Which of my teaching strategies worked well? Why did these work?'),
                'F' => t('What difficulties did I encounter which my principal or supervisor can help me solve?'),
                'G' => t('What innovation or localized materials did I use or discover which I wish to share with other teachers?'),
            ];
            $parts = [
                ['heading' => t('I. Objectives'), 'rows' => [
                    $row(t('A. Content standards'), null),
                    $row(t('B. Performance standards'), null),
                    $row(t('C. Learning competencies or objectives (write the LC code for each)'), 'objectives'),
                ]],
                ['heading' => t('II. Content'), 'rows' => [$row(t('Subject matter'), 'title')]],
                ['heading' => t('III. Learning resources'), 'rows' => [
                    $row(t('A. References'), 'sources'),
                    $row(t('B. Other learning resources'), null),
                ]],
                ['heading' => t('IV. Procedures'), 'rows' => []],
                ['heading' => t('V. Remarks'), 'rows' => [$row('', null)]],
                ['heading' => t('VI. Reflection'), 'rows' => []],
            ];
            foreach ($procedures as $letter => $label) {
                $parts[3]['rows'][] = $row($letter . '. ' . $label, self::PROCEDURES[$letter]);
            }
            foreach ($reflection as $letter => $label) {
                $parts[5]['rows'][] = $row($letter . '. ' . $label, null);
            }
            return [
                'title' => t('Daily Lesson Log'),
                'header' => [
                    'school' => t('School'),
                    'grade' => t('Grade level'),
                    'teacher' => t('Teacher'),
                    'area' => t('Learning area'),
                    'dates' => t('Teaching dates and time'),
                    'quarter' => t('Quarter'),
                ],
                'parts' => $parts,
            ];
        });
    }
}
