<?php

declare(strict_types=1);

namespace Sibol\Services;

use Sibol\Core\Db;

/**
 * School-level settings and policies. Stored as JSON per top-level key in
 * organization_settings and merged over these defaults, so new settings
 * appear with safe values without a migration.
 */
final class Settings
{
    /** @var array<int,array<string,mixed>> */
    private static array $cache = [];

    public const STUDENT_MODES = ['explain', 'socratic', 'practice', 'flashcards', 'summarize', 'study_plan', 'vocabulary', 'ask_materials'];
    public const TEACHER_MODES = ['lesson_ideas', 'explain_for_class', 'examples', 'differentiate', 'feedback_helper'];

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        return [
            'grade_levels' => ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'],
            'languages' => ['en', 'fil'],
            'grading' => [
                // 'points': percentage of all points earned. 'weighted': category weights below.
                'model' => 'points',
                'categories' => [],
                'passing_percent' => 75,
                'descriptors' => [],
            ],
            'class_codes' => [
                'enabled' => true,
                'ttl_days' => 14,
            ],
            'ai' => [
                'enabled' => true,
                'student_modes' => self::STUDENT_MODES,
                'teacher_tools' => true,
                'student_daily_messages' => 40,
                'teacher_daily_requests' => 80,
                'monthly_token_budget' => 2000000,
                'general_answers' => true,
                'max_question_chars' => 2000,
            ],
            'features' => [
                'document_answers' => true,
                'flashcards' => true,
                'practice_quizzes' => true,
                'study_plans' => true,
                'share_with_teacher' => true,
            ],
            'privacy' => [
                'ai_retention_days' => 365,
                'students_can_delete_chats' => true,
                'contact_name' => 'School Data Protection Officer',
                'contact_email' => '',
                'help_text' => 'If something you read or experience in Sibol worries you, talk to your class adviser or the guidance office. Sibol is not monitored in real time.',
            ],
            'notifications' => [
                'deadline_reminder_hours' => 24,
                'new_material' => true,
                'new_assignment' => true,
                'feedback_released' => true,
                'announcements' => true,
            ],
        ];
    }

    /** Optional starting points an administrator can load and then edit. */
    public static function gradingPresets(): array
    {
        return [
            'points' => [
                'label' => 'Points only',
                'value' => ['model' => 'points', 'categories' => [], 'passing_percent' => 75, 'descriptors' => []],
            ],
            'k12' => [
                'label' => 'K to 12 style categories (edit weights per your policy)',
                'value' => [
                    'model' => 'weighted',
                    'categories' => [
                        ['key' => 'written', 'label' => 'Written work', 'weight' => 40],
                        ['key' => 'performance', 'label' => 'Performance tasks', 'weight' => 40],
                        ['key' => 'periodic', 'label' => 'Periodic assessment', 'weight' => 20],
                    ],
                    'passing_percent' => 75,
                    'descriptors' => [
                        ['min' => 90, 'label' => 'Outstanding'],
                        ['min' => 85, 'label' => 'Very satisfactory'],
                        ['min' => 80, 'label' => 'Satisfactory'],
                        ['min' => 75, 'label' => 'Fairly satisfactory'],
                        ['min' => 0, 'label' => 'Did not meet expectations'],
                    ],
                ],
            ],
        ];
    }

    /** @return array<string,mixed> */
    public static function all(int $orgId): array
    {
        if (isset(self::$cache[$orgId])) {
            return self::$cache[$orgId];
        }
        $settings = self::defaults();
        foreach (Db::all('SELECT setting_key, setting_value FROM organization_settings WHERE organization_id = :o', ['o' => $orgId]) as $row) {
            $value = json_decode((string) $row['setting_value'], true);
            $key = (string) $row['setting_key'];
            if (isset($settings[$key]) && is_array($settings[$key]) && is_array($value) && !self::isList($settings[$key])) {
                $settings[$key] = array_replace($settings[$key], $value);
            } else {
                $settings[$key] = $value;
            }
        }
        return self::$cache[$orgId] = $settings;
    }

    /**
     * Dot-notation read, e.g. Settings::get($org, 'ai.student_daily_messages').
     * @return mixed
     */
    public static function get(int $orgId, string $key, $default = null)
    {
        $value = self::all($orgId);
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    /** @param mixed $value */
    public static function set(int $orgId, string $key, $value, ?int $actorId = null): void
    {
        Db::exec(
            'INSERT INTO organization_settings (organization_id, setting_key, setting_value, updated_by, updated_at)
             VALUES (:o, :k, :v, :u, :t)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)',
            ['o' => $orgId, 'k' => $key, 'v' => json_encode($value, JSON_UNESCAPED_UNICODE), 'u' => $actorId, 't' => Db::now()]
        );
        unset(self::$cache[$orgId]);
    }

    public static function flush(): void
    {
        self::$cache = [];
    }

    /** Human label for a companion mode key. */
    public static function modeLabel(string $mode): string
    {
        $labels = [
            'explain' => t('Explain a topic'),
            'socratic' => t('Guide me step by step'),
            'practice' => t('Practice quiz'),
            'flashcards' => t('Flashcards'),
            'summarize' => t('Summarize my material'),
            'study_plan' => t('Make a study plan'),
            'vocabulary' => t('Vocabulary and translation'),
            'ask_materials' => t('Ask my class materials'),
            'lesson_ideas' => t('Plan a lesson'),
            'explain_for_class' => t('Explain for my class'),
            'examples' => t('Create examples'),
            'differentiate' => t('Adapt an activity'),
            'feedback_helper' => t('Draft feedback wording'),
        ];
        return $labels[$mode] ?? $mode;
    }

    public static function modeDescription(string $mode): string
    {
        $descriptions = [
            'explain' => t('A clear explanation at the level you choose, with an example.'),
            'socratic' => t('One question at a time and hints first, so you work it out yourself.'),
            'practice' => t('A few questions on a topic, with explanations after you answer.'),
            'flashcards' => t('Quick recall cards from a class material or your own notes.'),
            'summarize' => t('The key points of one class material, with page references.'),
            'study_plan' => t('Break a test or topic into short study sessions you can add to your calendar.'),
            'vocabulary' => t('Meanings of terms in English and Filipino, keeping technical words accurate.'),
            'ask_materials' => t('Answers drawn only from your released class materials, with sources.'),
            'lesson_ideas' => t('Outline a lesson from your objectives and approved materials.'),
            'explain_for_class' => t('Ways to explain a concept at your students\' level.'),
            'examples' => t('Worked examples and practice items you can adapt.'),
            'differentiate' => t('Variations of an activity for different readiness levels.'),
            'feedback_helper' => t('Kind, specific wording for feedback you have already decided on.'),
        ];
        return $descriptions[$mode] ?? '';
    }

    private static function isList(array $value): bool
    {
        return $value === [] || array_keys($value) === range(0, count($value) - 1);
    }
}
