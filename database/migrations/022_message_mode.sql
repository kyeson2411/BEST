-- The companion mode each answer was written in. A conversation can change mode ("Turn into a lesson" moves an
-- "Explain for my class" briefing to "Plan a lesson"), and only answers written as lessons can be saved as lesson
-- drafts. Answers already saved take their conversation's mode now, before any conversation changes mode again.
ALTER TABLE ai_messages ADD COLUMN mode VARCHAR(30) NULL AFTER role;

UPDATE ai_messages m JOIN ai_conversations c ON c.id = m.conversation_id
SET m.mode = c.mode
WHERE m.role = 'assistant' AND m.mode IS NULL;
