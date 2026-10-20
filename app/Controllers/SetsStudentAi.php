<?php

declare(strict_types=1);

namespace Sibol\Controllers;

use Sibol\Core\Dates;
use Sibol\Core\Validator;
use Sibol\Services\AiAccess;

/**
 * The AI setting and guardian answers of one student, for administrators (People) and advisers (Advisory).
 * The caller has already checked that the person may manage this student.
 */
trait SetsStudentAi
{
    private function saveAiSetting(array $actor, array $student, string $back): void
    {
        $data = $this->validate(['setting' => 'required|in:school,on,off'], ['setting' => t('Setting for this student')], $back);
        $on = ['school' => null, 'on' => true, 'off' => false][$data['setting']];
        AiAccess::set($actor, $student, $on);
        $fresh = ['ai_enabled' => $on === null ? null : ($on ? 1 : 0)] + $student;
        $this->flash('success', AiAccess::studentOn($fresh)
            ? t('The companion and AI practice are on for :name.', ['name' => user_name($student)])
            : t('The companion and AI practice are off for :name.', ['name' => user_name($student)]));
    }

    private function saveGuardianAnswer(array $actor, array $student, string $back): void
    {
        $data = $this->validate([
            'answer' => 'required|in:yes,no',
            'answered_on' => 'required|date',
        ], ['answer' => t('Answer'), 'answered_on' => t('Date of the answer')], $back);
        $date = Validator::parseDate((string) $data['answered_on']);
        if ($date === null || $date->format('Y-m-d') > Dates::now()->format('Y-m-d') || $date->format('Y') < '2000') {
            $this->fail('answered_on', t('Enter the date the guardian answered, not a future date.'), $back);
        }
        AiAccess::recordGuardian($actor, $student, $data['answer'] === 'yes', (string) $data['answered_on']);
        $this->flash('success', $data['answer'] === 'yes'
            ? t('Recorded. The guardian agreed, so the companion is on for :name.', ['name' => user_name($student)])
            : t('Recorded. The guardian did not agree, so the companion is off for :name.', ['name' => user_name($student)]));
    }
}
