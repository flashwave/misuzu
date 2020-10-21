<?php
namespace Misuzu\Forum;

use Misuzu\Colour;
use Misuzu\DB;
use Misuzu\Memoizer;
use Misuzu\Pagination;
use Misuzu\Users\User;

class ForumCategoryException extends ForumException {}
class ForumCategoryNotFoundException extends ForumCategoryException {}

class ForumCategory {
    public const TYPE_DISCUSSION = 0;
    public const TYPE_CATEGORY = 1;
    public const TYPE_LINK = 2;
    public const TYPE_FEATURE = 3;

    public const TYPES = [
        self::TYPE_DISCUSSION, self::TYPE_CATEGORY, self::TYPE_LINK, self::TYPE_FEATURE,
    ];
    public const HAS_CHILDREN = [
        self::TYPE_DISCUSSION, self::TYPE_CATEGORY, self::TYPE_FEATURE,
    ];
    public const HAS_TOPICS = [
        self::TYPE_DISCUSSION, self::TYPE_FEATURE,
    ];
    public const HAS_PRIORITY_VOTES = [
        self::TYPE_FEATURE,
    ];

    public const ROOT_ID = 0;

    // Database fields
    private $forum_id = -1;
    private $forum_order = 0;
    private $forum_parent = 0;
    private $forum_name = '';
    private $forum_type = self::TYPE_DISCUSSION;
    private $forum_description = null;
    private $forum_icon = null;
    private $forum_colour = null;
    private $forum_link = null;
    private $forum_link_clicks = null;
    private $forum_created = null;
    private $forum_archived = 0;
    private $forum_hidden = 0;
    private $forum_count_topics = 0;
    private $forum_count_posts = 0;

    public const TABLE = 'forum_categories';
    private const QUERY_SELECT = 'SELECT %1$s FROM `' . DB::PREFIX . self::TABLE . '` AS '. self::TABLE;
    private const SELECT = '%1$s.`forum_id`, %1$s.`forum_order`, %1$s.`forum_parent`, %1$s.`forum_name`, %1$s.`forum_type`, %1$s.`forum_description`'
                         . ', %1$s.`forum_icon`, %1$s.`forum_colour`, %1$s.`forum_link`, %1$s.`forum_link_clicks`'
                         . ', %1$s.`forum_archived`, %1$s.`forum_hidden`, %1$s.`forum_count_topics`, %1$s.`forum_count_posts`'
                         . ', UNIX_TIMESTAMP(%1$s.`forum_created`) AS `forum_created`';

    private $categoryColour = null;
    private $realColour = null;
    private $parentCategory = null;
    private $children = null;

    public function getId(): int {
        return $this->forum_id < 0 ? -1 : $this->forum_id;
    }
    public function isRoot(): bool {
        return $this->forum_id === self::ROOT_ID;
    }

    public function getOrder(): int {
        return $this->forum_order;
    }
    public function setOrder(int $order): self {
        $this->forum_order = $order;
        return $this;
    }
    public function moveBelow(self $other): self {
        $this->setOrder($other->getOrder() + 1);
        return $this;
    }
    public function moveAbove(self $other): self {
        $this->setOrder($other->getOrder() - 1);
        return $this;
    }

    public function getParentId(): int {
        return $this->forum_parent < 0 ? -1 : $this->forum_parent;
    }
    public function setParentId(int $otherId): self {
        $this->forum_parent = $otherId;
        $this->parentCategory = null;
        return $this;
    }
    public function hasParent(): bool {
        return $this->getParentId() > 0;
    }
    public function getParent(): self {
        if($this->parentCategory === null)
            $this->parentCategory = $this->hasParent() ? self::byId($this->getParentId()) : self::root();
        return $this->parentCategory;
    }
    public function setParent(?self $other): self {
        $this->forum_parent = $other === null ? 0 : $other->getId();
        $this->parentCategory = $other;
        return $this;
    }
    public function getParentTree(): array {
        $current = $this;
        $parents = [];
        while(!$current->isRoot())
            $parents[] = $current = $current->getParent();
        return array_reverse($parents);
    }

    public function getUrl(): string {
        if($this->isRoot())
            return url('forum-index');
        return url('forum-category', ['forum' => $this->getId()]);
    }

    public function getName(): string {
        return $this->forum_name;
    }
    public function setName(string $name): self {
        $this->forum_name = $name;
        return $this;
    }

    public function getType(): int {
        return $this->forum_type;
    }
    public function setType(int $type): self {
        $this->forum_type = $type;
        return $this;
    }
    public function isDiscussionForum(): bool { return $this->getType() === self::TYPE_DISCUSSION; }
    public function isCategoryForum(): bool   { return $this->getType() === self::TYPE_CATEGORY; }
    public function isFeatureForum(): bool    { return $this->getType() === self::TYPE_FEATURE; }
    public function isLink(): bool            { return $this->getType() === self::TYPE_LINK; }
    public function canHaveChildren(): bool {
        return in_array($this->getType(), self::HAS_CHILDREN);
    }
    public function canHaveTopics(): bool {
        return in_array($this->getType(), self::HAS_TOPICS);
    }
    public function canHavePriorityVotes(): bool {
        return in_array($this->getType(), self::HAS_PRIORITY_VOTES);
    }

    public function getDescription(): string {
        return $this->forum_description ?? '';
    }
    public function hasDescription(): bool {
        return !empty($this->forum_description);
    }
    public function setDescription(string $description): self {
        $this->forum_description = empty($description) ? null : $description;
        return $this;
    }
    public function getParsedDescription(): string {
        return nl2br($this->getDescription());
    }

    public function getIcon(): string {
        $icon = $this->getRealIcon();
        if(!empty($icon))
            return $icon;

        if($this->isArchived())
            return 'fas fa-archive fa-fw';

        switch($this->getType()) {
            case self::TYPE_FEATURE:
                return 'fas fa-star fa-fw';
            case self::TYPE_LINK:
                return 'fas fa-link fa-fw';
            case self::TYPE_CATEGORY:
                return 'fas fa-folder fa-fw';
        }

        return 'fas fa-comments fa-fw';
    }
    public function getRealIcon(): string {
        return $this->forum_icon ?? '';
    }
    public function hasIcon(): bool {
        return !empty($this->forum_icon);
    }
    public function setIcon(string $icon): self {
        $this->forum_icon = empty($icon) ? null : $icon;
        return $this;
    }

    public function getColourRaw(): int {
        return $this->forum_colour ?? 0x40000000;
    }
    public function setColourRaw(?int $raw): self {
        $this->forum_colour = $raw;
        $this->realColour = null;
        $this->categoryColour = null;
        return $this;
    }
    public function getColour(): Colour { // Swaps parent colour in if no category colour is present
        if($this->realColour === null) {
            $this->realColour = $this->getCategoryColour();
            if($this->realColour->getInherit() && $this->hasParent())
                $this->realColour = $this->getParent()->getColour();
        }
        return $this->realColour;
    }
    public function getCategoryColour(): Colour {
        if($this->categoryColour === null)
            $this->categoryColour = new Colour($this->getColourRaw());
        return $this->categoryColour;
    }
    public function setColour(Colour $colour): self {
        return $this->setColourRaw($colour === null ? null : $colour->getRaw());
    }

    public function getLink(): string {
        return $this->forum_link ?? '';
    }
    public function hasLink(): bool {
        return !empty($this->forum_link);
    }
    public function setLink(string $link): self {
        $this->forum_link = empty($link) ? null : $link;
    }

    public function getLinkClicks(): int {
        return $this->forum_link_clicks ?? -1;
    }
    public function shouldCountLinkClicks(): bool {
        return $this->isLink() && $this->getLinkClicks() >= 0;
    }
    public function setCountLinkClicks(bool $state): self {
        if($this->isLink() && $this->shouldCountLinkClicks() !== $state) {
            $this->forum_link_clicks = $state ? 0 : null;

            // forum_link_clicks is not affected by the save method so we must save
            DB::prepare(
                'UPDATE `' . DB::PREFIX . self::TABLE . '`'
                . ' SET `forum_link_clicks` = :clicks'
                . ' WHERE `forum_id` = :category'
            )   ->bind('category', $this->getId())
                ->bind('clicks', $this->forum_link_clicks)
                ->execute();
        }
        return $this;
    }
    public function increaseLinkClicks(): void {
        if($this->shouldCountLinkClicks()) {
            $this->forum_link_clicks = $this->getLinkClicks() + 1;
            DB::prepare(
                'UPDATE `' . DB::PREFIX . self::TABLE . '`'
                . ' SET `forum_link_clicks` = `forum_link_clicks` + 1'
                . ' WHERE `forum_id` = :category AND `forum_type` = ' . self::TYPE_LINK
            )->bind('category', $this->getId())->execute();
        }
    }

    public function getCreatedTime(): int {
        return $this->forum_created === null ? -1 : $this->forum_created;
    }

    public function isArchived(): bool {
        return boolval($this->forum_archived);
    }
    public function setArchived(bool $archived): self {
        $this->forum_archived = $archived ? 1 : 0;
        return $this;
    }

    public function isHidden(): bool {
        return boolval($this->forum_hidden);
    }
    public function setHidden(bool $hidden): self {
        $this->forum_hidden = $hidden ? 1 : 0;
        return $this;
    }

    public function getTopicCount(): int {
        return $this->forum_count_topics ?? 0;
    }
    public function getPostCount(): int {
        return $this->forum_count_posts ?? 0;
    }
    public function increaseTopicPostCount(bool $hasTopic): void {
        if($this->isLink() || $this->isRoot())
            return;
        if($this->hasParent())
            $this->getParent()->increaseTopicPostCount($hasTopic);

        if($hasTopic)
            $this->forum_count_topics = $this->getTopicCount() + 1;
        $this->forum_count_posts = $this->getPostCount() + 1;

        DB::prepare(
            'UPDATE `' . DB::PREFIX . self::TABLE . '`'
            . ' SET `forum_count_posts` = `forum_count_posts` + 1'
            . ($hasTopic ? ', `forum_count_topics` = `forum_count_topics` + 1' : '')
            . ' WHERE `forum_id` = :category'
        )->bind('category', $this->getId())->execute();
    }

    // Param is fucking hackjob
    // -1 = no check
    // null = guest
    // User = user
    public function getChildren(/* ?User */ $viewer = -1): array {
        if(!$this->canHaveChildren())
            return [];
        if($this->children === null)
            $this->children = self::all($this);
        if($viewer === null || $viewer instanceof User) {
            $children = [];
            foreach($this->children as $child)
                if($child->canView($viewer))
                    $children[] = $child;
            return $children;
        }
        return $this->children;
    }

    public function getActualTopicCount(bool $includeDeleted = false): int {
        if(!$this->canHaveTopics())
            return 0;
        return ForumTopic::countByCategory($this, $includeDeleted);
    }
    public function getActualPostCount(bool $includeDeleted = false): int {
        if(!$this->canHaveTopics())
            return 0;
        return ForumPost::countByCategory($this, $includeDeleted);
    }

    public function getTopics(bool $includeDeleted = false, ?Pagination $pagination = null): array {
        if(!$this->canHaveTopics())
            return [];
        return ForumTopic::byCategory($this, $includeDeleted, $pagination);
    }

    public function checkLegacyPermission(?User $user, int $perm, bool $strict = false): bool {
        return forum_perms_check_user(
            MSZ_FORUM_PERMS_GENERAL,
            $this->isRoot() ? null : $this->getId(),
            $user === null ? 0 : $user->getId(),
            $perm, $strict
        );
    }
    public function canView(?User $user): bool {
        return $this->checkLegacyPermission($user, MSZ_FORUM_PERM_SET_READ);
    }

    public function hasRead(User $user): bool {
        static $cache = [];

        $cacheId = $user->getId() . ':' . $this->getId();
        if(isset($cache[$cacheId]))
            return $cache[$cacheId];

        if(!$this->canView($user))
            return $cache[$cacheId] = true;

        $countUnread = (int)DB::prepare(
            'SELECT COUNT(*) FROM `' . DB::PREFIX . ForumTopic::TABLE . '` AS ti'
            . ' LEFT JOIN `' . DB::PREFIX . ForumTopicTrack::TABLE . '` AS tt'
            . ' ON tt.`topic_id` = ti.`topic_id` AND tt.`user_id` = :user'
            . ' WHERE ti.`forum_id` = :forum AND ti.`topic_deleted` IS NULL'
            . ' AND ti.`topic_bumped` >= NOW() - INTERVAL :limit SECOND'
            . ' AND (tt.`track_last_read` IS NULL OR tt.`track_last_read` < ti.`topic_bumped`)'
        )->bind('forum', $this->getId())
         ->bind('user', $user->getId())
         ->bind('limit', ForumTopic::UNREAD_TIME_LIMIT)
         ->fetchColumn();

        if($countUnread > 0)
            return $cache[$cacheId] = false;

        foreach($this->getChildren() as $child)
            if(!$child->hasRead($user))
                return $cache[$cacheId] = false;

        return $cache[$cacheId] = true;
    }

    public function markAsRead(User $user, bool $recursive = true): void {
        if($this->isRoot()) {
            if(!$recursive)
                return;
            $recursive = false;
        }

        if($recursive) {
            $children = $this->getChildren($user);
            foreach($children as $child)
                $child->markAsRead($user, true);
        }

        $mark = DB::prepare(
            'INSERT INTO `' . DB::PREFIX . ForumTopicTrack::TABLE . '`'
            . ' (`user_id`, `topic_id`, `forum_id`, `track_last_read`)'
            . ' SELECT u.`user_id`, t.`topic_id`, t.`forum_id`, NOW()'
            . ' FROM `msz_forum_topics` AS t'
            . ' LEFT JOIN `msz_users` AS u ON u.`user_id` = :user'
            . ' WHERE t.`topic_deleted` IS NULL'
            . ' AND t.`topic_bumped` >= NOW() - INTERVAL :limit SECOND'
            . ($this->isRoot() ? '' : ' AND t.`forum_id` = :forum')
            . ' GROUP BY t.`topic_id`'
            . ' ON DUPLICATE KEY UPDATE `track_last_read` = NOW()'
        )->bind('user', $user->getId())
         ->bind('limit', ForumTopic::UNREAD_TIME_LIMIT);

        if(!$this->isRoot())
            $mark->bind('forum', $this->getId());

        $mark->execute();
    }

    public function checkCooldown(User $user): int {
        return (int)DB::prepare(
            'SELECT TIMESTAMPDIFF(SECOND, COALESCE(MAX(`post_created`), NOW() - INTERVAL 1 YEAR), NOW())'
            . ' FROM `' . DB::PREFIX . ForumPost::TABLE . '`'
            . ' WHERE `forum_id` = :forum AND `user_id` = :user'
        )->bind('forum', $this->getId())->bind('user', $user->getId())->fetchColumn();
    }

    public function getLatestTopic(?User $viewer = null): ?ForumTopic {
        $lastTopic = ForumTopic::byCategoryLast($this);
        $children = $this->getChildren($viewer);

        foreach($children as $child) {
            $topic = $child->getLatestTopic($viewer);
            if($topic !== null && ($lastTopic === null || $topic->getBumpedTime() > $lastTopic->getBumpedTime()))
                $lastTopic = $topic;
        }

        return $lastTopic;
    }

    // This function is really fucking expensive and should only be called by cron
    // Optimise this as much as possible at some point
    public function synchronise(bool $save = true): array {
        $topics = 0; $posts = 0; $topicStats = [];

        $children = $this->getChildren();
        foreach($children as $child) {
            $stats = $child->synchronise($save);
            $topics += $stats['topics'];
            $posts += $stats['posts'];
            if(empty($topicStats) || (!empty($stats['topic_stats']) && $stats['topic_stats']['last_post_time'] > $topicStats['last_post_time']))
                $topicStats = $stats['topic_stats'];
        }

        $getCounts = DB::prepare(
            'SELECT :forum as `target_forum_id`, ('
            .  ' SELECT COUNT(`topic_id`)'
            .  ' FROM `msz_forum_topics`'
            .  ' WHERE `forum_id` = `target_forum_id`'
            .  ' AND `topic_deleted` IS NULL'
            . ') AS `topics`, ('
            .  ' SELECT COUNT(`post_id`)'
            .  ' FROM `msz_forum_posts`'
            .  ' WHERE `forum_id` = `target_forum_id`'
            .  ' AND `post_deleted` IS NULL'
            . ') AS `posts`'
        );
        $getCounts->bind('forum', $this->getId());
        $counts = $getCounts->fetch();
        $topics += $counts['topics'];
        $posts += $counts['posts'];

        foreach($this->getTopics() as $topic) {
            $stats = $topic->synchronise($save);
            if(empty($topicStats) || $stats['last_post_time'] > $topicStats['last_post_time'])
                $topicStats = $stats;
        }

        if($save && !$this->isRoot()) {
            $setCounts = DB::prepare(
                'UPDATE `msz_forum_categories`'
                . ' SET `forum_count_topics` = :topics, `forum_count_posts` = :posts'
                . ' WHERE `forum_id` = :forum'
            );
            $setCounts->bind('forum', $this->getId());
            $setCounts->bind('topics', $topics);
            $setCounts->bind('posts', $posts);
            $setCounts->execute();
        }

        return [
            'topics' => $topics,
            'posts' => $posts,
            'topic_stats' => $topicStats,
        ];
    }

    public static function root(): self {
        static $root = null;
        if($root === null) {
            $root = new static;
            $root->forum_id = self::ROOT_ID;
            $root->forum_name = 'Forums';
            $root->forum_type = self::TYPE_CATEGORY;
            $root->forum_created = 1359324884;
        }
        return $root;
    }

    public function save(): void {
        if($this->isRoot())
            return;
        $isInsert = $this->getId() < 0;

        if($isInsert) {
            $save = DB::prepare(
                'INSERT INTO `' . DB::PREFIX . self::TABLE . '` ('
                . '`forum_order`, `forum_parent`, `forum_name`, `forum_type`, `forum_description`, `forum_icon`'
                . ', `forum_colour`, `forum_link`, `forum_archived`, `forum_hidden`'
                . ') VALUES (:order, :parent, :name, :type, :desc, :icon, :colour, :link, :archived, :hidden)'
            );
        } else {
            $save = DB::prepare(
                'UPDATE `' . DB::PREFIX . self::TABLE . '`'
                . ' SET `forum_order` = :order, `forum_parent` = :parent, `forum_name` = :name, `forum_type` = :type'
                . ', `forum_description` = :desc, `forum_icon` = :icon, `forum_colour` = :colour, `forum_link` = :link'
                . ', `forum_archived` = :archived, `forum_hidden` = :hidden'
                . ' WHERE `forum_id` = :category'
            )->bind('category', $this->getId());
        }

        $save->bind('order', $this->forum_order)
            ->bind('parent', $this->forum_parent)
            ->bind('name', $this->forum_name)
            ->bind('type', $this->forum_type)
            ->bind('desc', $this->forum_description)
            ->bind('icon', $this->forum_icon)
            ->bind('colour', $this->forum_colour)
            ->bind('link', $this->forum_link)
            ->bind('archived', $this->forum_archived)
            ->bind('hidden', $this->forum_hidden);

        if($isInsert) {
            $this->forum_id = $save->executeGetId();
            $this->forum_created = time();
        } else $save->execute();
    }

    private static function memoizer() {
        static $memoizer = null;
        if($memoizer === null)
            $memoizer = new Memoizer;
        return $memoizer;
    }

    private static function byQueryBase(): string {
        return sprintf(self::QUERY_SELECT, sprintf(self::SELECT, self::TABLE));
    }
    public static function byId(int $categoryId): self {
        if($categoryId === self::ROOT_ID)
            return self::root();

        return self::memoizer()->find($categoryId, function() use ($categoryId) {
            $object = DB::prepare(self::byQueryBase() . ' WHERE `forum_id` = :category')
                ->bind('category', $categoryId)
                ->fetchObject(self::class);
            if(!$object)
                throw new ForumCategoryNotFoundException;
            return $object;
        });
    }
    public static function all(?self $parent = null): array {
        $getObjects = DB::prepare(
            self::byQueryBase()
                . ($parent === null ? '' : ' WHERE `forum_parent` = :parent')
                . ' ORDER BY `forum_order`'
        );

        if($parent !== null)
            $getObjects->bind('parent', $parent->getId());

        $objects = [];
        $memoizer = self::memoizer();
        while($object = $getObjects->fetchObject(self::class))
            $memoizer->insert($objects[] = $object);
        return $objects;
    }
}
