-- Whether anti-cheating was on for a quiz attempt, set when the attempt starts. Attempts from before anti-cheating
-- existed stay NULL, so their teacher is never told "nothing was recorded" for an attempt nobody watched; switching
-- it on or off later does not change an attempt already under way.
ALTER TABLE submissions ADD COLUMN anti_cheating TINYINT(1) NULL AFTER paste_blocked_count;
