<?php
namespace Misuzu\DatabaseMigrations\NukeChatQuotes;

use PDO;

require_once '2018_10_09_181724_chat_quotes_table.php';

function migrate_up(PDO $conn): void
{
    \Misuzu\DatabaseMigrations\ChatQuotesTable\migrate_down($conn);
}

function migrate_down(PDO $conn): void
{
    \Misuzu\DatabaseMigrations\ChatQuotesTable\migrate_up($conn);
}
