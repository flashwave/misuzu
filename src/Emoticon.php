<?php
namespace Misuzu;

final class Emoticon {
    public const ALL = PHP_INT_MAX;

    public function __construct() {
    }

    public function getId(): int {
        return $this->emote_id ?? 0;
    }
    public function hasId(): bool {
        return isset($this->emote_id) && $this->emote_id > 0;
    }

    public function getOrder(): int {
        return $this->emote_order ?? 0;
    }
    public function setOrder(int $order): self {
        $this->emote_order = $order;
        return $this;
    }
    public function changeOrder(int $difference): self {
        if(!$this->hasId())
            return $this;

        DB::prepare('
            UPDATE  `msz_emoticons`
            SET     `emote_order` = `emote_order` + :diff
            WHERE   `emote_id` = :id
        ')->bind('id', $this->getId())
          ->bind('diff', $difference)
          ->execute();

        return $this;
    }

    public function getHierarchy(): int {
        return $this->emote_hierarchy ?? 0;
    }
    public function setHierarchy(int $hierarchy): self {
        $this->emote_hierarchy = $hierarchy;
        return $this;
    }

    public function getUrl(): string {
        return $this->emote_url ?? '';
    }
    public function setUrl(string $url): self {
        $this->emote_url = $url;
        return $this;
    }

    public function addString(string $string, ?int $order = null): bool {
        if(!$this->hasId())
            return false;

        if($order === null) {
            $order = DB::prepare('
                SELECT MAX(`emote_string_order`) + 1
                FROM `msz_emoticons_strings`
                WHERE `emote_id` = :id
            ')->bind('id', $this->getId())->fetchColumn();
        }

        return DB::prepare('
            REPLACE INTO `msz_emoticons_strings` (`emote_id`, `emote_string_order`, `emote_string`)
            VALUES (:id, :order, :string)
        ')->bind('id', $this->getId())
          ->bind('order', $order)
          ->bind('string', $string)
          ->execute();
    }
    public function removeString(string $string): bool {
        if(!$this->hasId())
            return false;

        return DB::prepare('
            DELETE FROM `msz_emoticons_strings`
            WHERE `emote_string` = :string
        ')->bind('string', $string)
          ->execute();
    }
    public function getStrings(): array {
        if(!$this->hasId())
            return [];

        return DB::prepare('
            SELECT   `emote_string_order`, `emote_string`
            FROM     `msz_emoticons_strings`
            WHERE    `emote_id` = :id
            ORDER BY `emote_string_order`
        ')->bind('id', $this->getId())->fetchObjects();
    }

    public function save(): bool {
        if($this->hasId()) {
            $save = DB::prepare('
                UPDATE `msz_emoticons`
                SET `emote_order` = :order,
                    `emote_hierarchy` = :hierarchy,
                    `emote_url` = :url
                WHERE `emote_id` = :id
            ')->bind('id', $this->getId());
        } else {
            $save = DB::prepare('
                INSERT INTO `msz_emoticons` (`emote_order`, `emote_hierarchy`, `emote_url`)
                VALUES (:order, :hierarchy, :url)
            ');
        }

        $saved = $save->bind('order', $this->getOrder())
            ->bind('hierarchy', $this->getHierarchy())
            ->bind('url', $this->getUrl())
            ->execute();

        if(!$this->hasId() && $saved)
            $this->emote_id = DB::lastId();

        return $saved;
    }

    public function delete(): void {
        if(!$this->hasId())
            return;

        DB::prepare('DELETE FROM `msz_emoticons` WHERE `emote_id` = :id')
            ->bind('id', $this->getId())
            ->execute();
    }

    public static function byId(int $emoteId): self {
        if($emoteId < 1)
            return [];

        $getEmote = DB::prepare('
            SELECT `emote_id`, `emote_order`, `emote_hierarchy`, `emote_url`
            FROM   `msz_emoticons`
            WHERE  `emote_id` = :id
        ');
        $getEmote->bind('id', $emoteId);
        return $getEmote->fetchObject(self::class);
    }

    public static function all(int $hierarchy = self::ALL, bool $unique = false, bool $order = true): array {
        $getEmotes = DB::prepare('
            SELECT    `emote_id`, `emote_order`, `emote_hierarchy`, `emote_url`
            FROM      `msz_emoticons`
            WHERE     `emote_hierarchy` <= :hierarchy
            ORDER BY  IF(:order, `emote_order`, `emote_id`)
        ');
        $getEmotes->bind('hierarchy', $hierarchy);
        $getEmotes->bind('order', $order);
        $emotes = $getEmotes->fetchObjects(self::class);

        // Removes aliases, emote with lowest ordering is considered the main
        if($unique) {
            $existing = [];

            for($i = 0; $i < count($emotes); $i++) {
                if(in_array($emotes[$i]->emote_url, $existing)) {
                    unset($emotes[$i]);
                } else {
                    $existing[] = $emotes[$i]->emote_url;
                }
            }
        }

        return $emotes;
    }
}
