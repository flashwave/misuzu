<?php
namespace Misuzu\Console\Commands;

use Misuzu\DB;
use Misuzu\Console\CommandArgs;
use Misuzu\Console\CommandInterface;

class CronCommand implements CommandInterface {
    public function getName(): string {
        return 'cron';
    }
    public function getSummary(): string {
        return 'Runs scheduled tasks and cleanups.';
    }

    public function dispatch(CommandArgs $args): void {
        $runSlow = $args->hasFlag('slow');

        foreach(self::TASKS as $task) {
            if($runSlow || empty($task['slow'])) {
                echo $task['name'] . PHP_EOL;

                switch($task['type']) {
                    case 'sql':
                        DB::exec($task['command']);
                        break;

                    case 'func':
                        call_user_func($task['command']);
                        break;

                    case 'selffunc':
                        call_user_func(self::class . '::' . $task['command']);
                        break;
                }
            }
        }
    }

    private static function syncForum(): void {
        \Misuzu\Forum\ForumCategory::root()->synchronise(true);
    }

    private const TASKS = [
        [
            'name' => 'Ensures main role exists.',
            'type' => 'sql',
            'slow' => true,
            'command' => "
                INSERT IGNORE INTO `msz_roles`
                    (`role_id`, `role_name`, `role_hierarchy`, `role_colour`, `role_description`, `role_created`)
                VALUES
                    (1, 'Member', 1, 1073741824, NULL, NOW())
            ",
        ],
        [
            'name' => 'Ensures all users are in the main role.',
            'type' => 'sql',
            'slow' => true,
            'command' => "
                INSERT INTO `msz_user_roles`
                    (`user_id`, `role_id`)
                SELECT `user_id`, 1 FROM `msz_users` as u
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM `msz_user_roles` as ur
                    WHERE `role_id` = 1
                    AND u.`user_id` = ur.`user_id`
                )
            ",
        ],
        [
            'name' => 'Ensures all display_role values are correct with `msz_user_roles`.',
            'type' => 'sql',
            'slow' => true,
            'command' => "
                UPDATE `msz_users` as u
                SET `display_role` = (
                     SELECT ur.`role_id`
                     FROM `msz_user_roles` as ur
                     LEFT JOIN `msz_roles` as r
                     ON r.`role_id` = ur.`role_id`
                     WHERE ur.`user_id` = u.`user_id`
                     ORDER BY `role_hierarchy` DESC
                     LIMIT 1
                )
                WHERE NOT EXISTS (
                    SELECT 1
                    FROM `msz_user_roles` as ur
                    WHERE ur.`role_id` = u.`display_role`
                    AND `ur`.`user_id` = u.`user_id`
                )
            ",
        ],
        [
            'name' => 'Remove expired sessions.',
            'type' => 'sql',
            'command' => "
                DELETE FROM `msz_sessions`
                WHERE `session_expires` < NOW()
            ",
        ],
        [
            'name' => 'Remove old password reset records.',
            'type' => 'sql',
            'command' => "
                DELETE FROM `msz_users_password_resets`
                WHERE `reset_requested` < NOW() - INTERVAL 1 WEEK
            ",
        ],
        [
            'name' => 'Remove old chat login tokens.',
            'type' => 'sql',
            'command' => "
                DELETE FROM `msz_user_chat_tokens`
                WHERE `token_created` < NOW() - INTERVAL 1 WEEK
            ",
        ],
        [
            'name' => 'Clean up login history.',
            'type' => 'sql',
            'command' => "
                DELETE FROM `msz_login_attempts`
                WHERE `attempt_created` < NOW() - INTERVAL 1 MONTH
            ",
        ],
        [
            'name' => 'Clean up audit log.',
            'type' => 'sql',
            'command' => "
                DELETE FROM `msz_audit_log`
                WHERE `log_created` < NOW() - INTERVAL 3 MONTH
            ",
        ],
        [
            'name' => 'Remove stale forum tracking entries.',
            'type' => 'sql',
            'command' => "
                DELETE tt FROM `msz_forum_topics_track` as tt
                LEFT JOIN `msz_forum_topics` as t
                ON t.`topic_id` = tt.`topic_id`
                WHERE t.`topic_bumped` < NOW() - INTERVAL 1 MONTH
            ",
        ],
        [
            'name' => 'Synchronise forum_id.',
            'type' => 'sql',
            'slow' => true,
            'command' => "
                UPDATE `msz_forum_posts` AS p
                INNER JOIN `msz_forum_topics` AS t
                ON t.`topic_id` = p.`topic_id`
                SET p.`forum_id` = t.`forum_id`
            ",
        ],
        [
            'name' => 'Recount forum topics and posts.',
            'type' => 'selffunc',
            'slow' => true,
            'command' => 'syncForum',
        ],
        [
            'name' => 'Clean up expired tfa tokens.',
            'type' => 'sql',
            'command' => "
                DELETE FROM `msz_auth_tfa`
                WHERE `tfa_created` < NOW() - INTERVAL 15 MINUTE
            ",
        ],
    ];
}
