<?php

declare(strict_types=1);

namespace Sibol\Controllers\Teacher;

use Sibol\Core\Db;
use Sibol\Services\SectionCopies;

/** "Copy to other sections" for the material, assignment and lesson controllers. */
trait CopiesToSections
{
    /**
     * Copies a record into each section ticked on the form, all or nothing. SectionCopies refuses
     * (404) any class that is not an active class the teacher teaches.
     * @param string $type material|assignment|lesson
     */
    private function copyToSections(string $type, int $id, string $back): void
    {
        $user = $this->user();
        $classIds = array_values(array_unique(array_filter(array_map('intval', $this->request->arr('class_ids')))));
        if (!$classIds) {
            $this->fail('class_ids', t('Choose at least one section to copy to.'), $back . '#copy-title');
        }
        $names = [];
        $also = [];
        Db::transaction(static function () use ($type, $user, $id, $classIds, &$names, &$also): void {
            foreach ($classIds as $classId) {
                $result = SectionCopies::$type($user, $id, $classId);
                $names[] = $result['class_name'];
                $also = array_merge($also, $result['also_copied']);
            }
        });
        $this->flash('success', tp('Copied to :classes as a draft. Publish it there when that section is ready.', 'Copied to :classes as drafts. Publish each copy when its section is ready.', count($names), ['classes' => implode(', ', $names)]));
        if ($also) {
            $this->flash('info', t('Linked materials were copied too, as drafts: :titles. Publish them so students can open them.', ['titles' => implode(', ', array_unique($also))]));
        }
    }
}
