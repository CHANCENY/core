<?php

namespace Simp\Core\extends\wiki\src\Entity;

use DI\DependencyException;
use DI\NotFoundException;
use InvalidArgumentException;
use PDO;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\extends\wiki\src\enum\WikiStatusEnum;
use Simp\Core\modules\services\Service;
use Simp\Core\modules\structures\taxonomy\Term;
use Simp\Core\modules\user\entity\User;
use Throwable;

class Wiki
{
    protected int $id;

    protected string $title = '';

    protected WikiContent $content;

    protected string $slug = '';

    protected WikiStatusEnum $status = WikiStatusEnum::DRAFT;

    protected \DateTime $created_at;

    protected \DateTime $updated_at;

    /**
     * @var User[]
     */
    protected array $author;

    protected array $tags;

    protected WikiRevisions $revision;

    protected bool $enforceNew = false;

    /**
     * @throws DependencyException
     * @throws NotFoundException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     * @throws \DateMalformedStringException
     */
    public function __construct(?int $id = null)
    {

        // Initialize properties
        $this->id = $id ?? 0;
        $this->content = new WikiContent("");
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
        $this->author = $id !== null && $id !== 0 ? $this->getAuthors($id) : [];
        $this->tags = $id !== null && $id !== 0 ? $this->getTags($id) : [];
        $this->revision = new WikiRevisions($this);

        // Load from a database if id is provided
        if ($id !== null && $id !== 0) {
            $query = "SELECT * FROM wiki_entities WHERE id = :id";
            $statement = Service::get('connection')->prepare($query);
            $statement->bindValue(':id', $id);
            $statement->execute();
            $wiki = $statement->fetch();
            if (!empty($wiki)) {
                $this->title = $wiki['title'];
                $this->content = new WikiContent($wiki['content']);
                $this->slug = $wiki['slug'];
                $this->status = WikiStatusEnum::from($wiki['status']);
                $this->created_at = new \DateTime($wiki['created_at'] ?? 'now');
                $this->updated_at = new \DateTime($wiki['updated_at'] ?? 'now');
            }
        }
    }

    /**
     * Load wikis by search params
     * @return Wiki[]
     * @throws DependencyException
     * @throws NotFoundException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     * @throws \DateMalformedStringException
     */
    public static function loadBySearch(array $search_params): array
    {
        $limit = $search_params['limit'] ?? 10;
        $offset = $search_params['offset'] ?? 0;
        $where = [];
        $joins = '';

        // Search query
        if (!empty($search_params['q'])) {
            $q = $search_params['q'];
            $where[] = "(wiki_entities.title LIKE :q OR wiki_entities.content LIKE :q1 OR wiki_entities_revision.content LIKE :q2)";
        }

        // Tag filter
        if (!empty($search_params['tid'])) {
            $joins .= " INNER JOIN wiki_entity_tags ON wiki_entities.id = wiki_entity_tags.wiki_id";
            $where[] = "wiki_entity_tags.tid = :tid";
        }

        $query = 'SELECT wiki_entities.id AS id
              FROM wiki_entities
              LEFT JOIN wiki_entities_revision ON wiki_entities.id = wiki_entities_revision.wiki_id
              ' . $joins;

        if ($where !== []) {
            $query .= " WHERE " . implode(" AND ", $where);
        }

        $query .= " GROUP BY wiki_entities.id
                ORDER BY wiki_entities.id DESC
                LIMIT :limit OFFSET :offset";

        $statement = Service::get('connection')->prepare($query);

        // Bind limit/offset
        $statement->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        // Bind search query
        if (!empty($search_params['q'])) {
            $statement->bindValue(':q', sprintf('%%%s%%', $q));
            $statement->bindValue(':q1', sprintf('%%%s%%', $q));
            $statement->bindValue(':q2', sprintf('%%%s%%', $q));
        }

        // Bind tag id
        if (!empty($search_params['tid'])) {
            $statement->bindValue(':tid', (int)$search_params['tid'], PDO::PARAM_INT);
        }

        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        $ids = array_column($result, 'id');

        $wiki = [];
        foreach ($ids as $id) {
            $wiki[] = Wiki::load($id);
        }

        return $wiki;
    }

    /**
     * @throws DependencyException
     * @throws \DateMalformedStringException
     * @throws PhpfastcacheCoreException
     * @throws NotFoundException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public static function loadBySlug(string $slug): false|Wiki
    {
        $query = "SELECT * FROM wiki_entities WHERE slug = :slug";
        $statement = Service::get('connection')->prepare($query);
        $statement->bindValue(':slug', $slug);
        $statement->execute();

        $wiki = $statement->fetch();
        if (!empty($wiki)) {
            return new Wiki($wiki['id']);
        }

        return false;
    }


    public function id(): int
    {
        return $this->id;
    }

    /**
     * Get the tags of the wiki
     * @throws DependencyException
     * @throws NotFoundException
     */
    private function getTags(int $id): array
    {
        $query = "SELECT tid FROM wiki_entity_tags WHERE wiki_id = :wiki_id";
        $statement = Service::get('connection')->prepare($query);
        $statement->bindValue(':wiki_id', $id);
        $statement->execute();

        $tags = $statement->fetchAll();
        $result = [];
        foreach ($tags as $tag) {
            $result[] = Term::load($tag['tid']);
        }

        return $result;
    }

    /**
     * Get the authors of the wiki
     * @return User[]
     * @throws DependencyException
     * @throws NotFoundException
     *
     */
    private function getAuthors(int $id): array
    {
        $query = "SELECT uid FROM wiki_authors WHERE wiki_id = :wiki_id";
        $statement = Service::get('connection')->prepare($query);
        $statement->bindValue(':wiki_id', $id);
        $statement->execute();

        $authors = $statement->fetchAll();
        $result = [];
        foreach ($authors as $author) {
            $result[] = User::load($author['uid']);
        }

        return $result;
    }

    /**
     * Generate a slug from a title
     */
    private function buildSlug(string $title): string
    {
        // Replace non-alphanumeric with hyphen
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $title);

        // Trim hyphens from start and end
        $slug = trim((string) $slug, '-');

        // Convert to lowercase
        $slug = strtolower($slug);

        return $slug;
    }


    /**
     * Enforces the creation of a new Wiki instance by setting the enforceNew property to true.
     */
    public function enforceNew(): Wiki
    {
        $this->enforceNew = true;
        return $this;
    }

    /**
     * Adds a new wiki instance with the provided data.
     *
     * @param array $wiki_data An associative array containing the wiki data.
     *                         Required keys: 'title' (string), 'content' (string).
     *                         Optional keys: 'authors' (array of user IDs), 'tags' (array of tag IDs or names).
     *
     * @return false|static Returns the created wiki instance on success or false on failure.
     *
     * @throws InvalidArgumentException If 'title' or 'content' is missing,
     *                                  or if no authors or tags are provided.
     */
    public function addWikiInstance(array $wiki_data): false|static
    {
        try{
            // Validate the data.
            if (empty($wiki_data['title']) || empty($wiki_data['content'])) {
                throw new \InvalidArgumentException('Title and content are required.');
            }

            $this->title = $wiki_data['title'];
            $this->content = new WikiContent($wiki_data['content']);
            $this->slug = $this->buildSlug($this->title);

            // Validate authors
            if (!empty($wiki_data['authors'])) {
                $this->author = [];
                foreach ($wiki_data['authors'] as $author) {
                    $this->author[] = User::load($author);
                }
            }

            if ($this->author === []) {
                throw new \InvalidArgumentException('At least one author is required.');
            }

            // Validate tags
            if (!empty($wiki_data['tags'])) {
                foreach ($wiki_data['tags'] as $tag) {

                    if (is_numeric($tag)) {
                        $term = Term::load($tag);
                        if ($term !== null && $term !== []) {
                            $this->tags[] = $term['id'];
                        }
                    }
                    elseif (is_string($tag)) {
                        $term = Term::search($tag);
                        if ($term !== []) {
                            $this->tags[] = $term[0]['id'];
                        }
                        else {
                            $term = Term::factory()->create('wiki', $tag);

                            if ($term) {
                                $this->tags[] = $term;
                            }
                        }
                    }

                }
            }

            if ($this->tags === []) {
                throw new \InvalidArgumentException('At least one tag is required.');
            }

            return $this;
        }catch (Throwable){
            return false;
        }
    }

    /**
     * Save the wiki instance to the database.
     * @throws DependencyException
     * @throws NotFoundException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     * @throws \DateMalformedStringException
     */
    public function save(): Wiki
    {
        // Save the wiki instance to the database.
        $query = "INSERT INTO wiki_entities (title, content, slug, status) VALUES (:title, :content, :slug, :status)";
        $statement = Service::get('connection')->prepare($query);
        $statement->bindValue('title', $this->title);
        $statement->bindValue('content', $this->content->__toString());
        $statement->bindValue('slug', $this->slug);
        $statement->bindValue('status', $this->status->value);
        $statement->execute();

        $this->id = (int) Service::get('connection')->lastInsertId();

        // Save the authors to the database.
        foreach ($this->author as $author) {
            $query = "INSERT INTO wiki_authors (wiki_id, uid) VALUES (:wiki_id, :uid)";
            $statement = Service::get('connection')->prepare($query);
            $statement->bindValue('wiki_id', $this->id);
            $statement->bindValue('uid', $author->getUid());
            $statement->execute();
        }

        // Save the tags to the database.
        foreach ($this->tags as $tag) {
            $query = "INSERT INTO wiki_entity_tags (wiki_id, tid) VALUES (:wiki_id, :tid)";
            $statement = Service::get('connection')->prepare($query);
            $statement->bindValue('wiki_id', $this->id);
            $statement->bindValue('tid', $tag);
            $statement->execute();
        }

        if ($this->enforceNew) {

            $revisions = new WikiRevisions($this->id);
            $this->revision = $revisions->addRevision($this->content->__toString(), $this->status, $this->author[0]->id());
        }

        // Load author and tag data from the database.
        $this->author = $this->getAuthors($this->id);
        $this->tags = $this->getTags($this->id);
        $this->revision = new WikiRevisions($this);
        return $this;
    }

    /**
     * Create a new wiki instance and save it to the database.
     * @return false|Wiki
     */
    public static function create(array $wiki_data): false|static
    {
        $wiki = new static();
        return $wiki->addWikiInstance($wiki_data);
    }

    /**
     * Load a wiki instance from the database by ID.
     * @throws DependencyException
     * @throws \DateMalformedStringException
     * @throws NotFoundException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public static function load(int $id): false|static
    {
        return new static($id);
    }

    /**
     * Add a new revision to the wiki with the given content and status.
     *
     * @param string $content The content of the new revision.
     * @param WikiStatusEnum $status The status of the new revision.
     * @return Wiki The created WikiRevisions instance containing the new revision data.
     * @throws DependencyException
     * @throws NotFoundException
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     * @throws \DateMalformedStringException
     */
    public function addRevision(string $content, WikiStatusEnum $status, ?int $author = null): Wiki
    {
        $author ??= $this->author[0]->id();
        $revision = $this->revision->addRevision($content, $status, $author);
        $this->revision = $revision;
        return $this;
    }

    /**
     * Get the title of the wiki.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Get the content of the wiki.
     */
    public function getContent(): WikiContent
    {
        if ($this->hasRevision() && $this->getLatestRevision()->getStatus() === WikiStatusEnum::PUBLISHED) {
            return $this->getLatestRevision()->getContent();
        }

        return $this->content;
    }


    /**
     * Get the slug of the wiki.
     */
    public function getSlug(): string
    {
        return $this->slug;
    }

    /**
     * Get the status of the wiki.
     */
    public function getStatus(): WikiStatusEnum
    {
        return $this->status;
    }

    /**
     * Get the authors of the wiki.
     * @return User[]
     */
    public function getAuthorsList(): array
    {
        return $this->author;
    }

    /**
     * Get the tags of the wiki.
     * @return Term[]
     */
    public function getTagsList(): array
    {
        return $this->tags;
    }

    /**
     * Check if the wiki is a draft.
     */
    public function isDraft(): bool
    {
        return $this->status === WikiStatusEnum::DRAFT;
    }

    /**
     * Check if the wiki is published.
     */
    public function isPublished(): bool
    {
        return $this->status === WikiStatusEnum::PUBLISHED;
    }

    /**
     * Check if the wiki is archived.
     */
    public function isArchived(): bool
    {
        return $this->status === WikiStatusEnum::ARCHIVED;
    }

    /**
     * Get a summary of the wiki content.
     */
    public function getSummary(int $length = 150): string
    {
        if ($this->hasRevision() && $this->getLatestRevision()->getStatus() === WikiStatusEnum::PUBLISHED) {
            return mb_strimwidth(strip_tags($this->getLatestRevision()->getContent()->__toString()), 0, $length, "...");
        }

        return mb_strimwidth(strip_tags($this->content->__toString()), 0, $length, "...");
    }

    /**
     * Get the word count of the wiki content.
     */
    public function getWordCount(): int
    {
        return str_word_count(strip_tags($this->content->__toString()));
    }

    /**
     * Calculate the reading time of the wiki based on word count.
     */
    public function getReadingTime(): string
    {
        $minutes = ceil($this->getWordCount() / 200);
        return $minutes . ' min read';
    }

    /**
     * Update the wiki entity with the provided data.
     */
    public function update(array $wiki_data): bool
    {
        try {
            if (!empty($wiki_data['title'])) {
                $this->title = $wiki_data['title'];
                $this->slug = $this->buildSlug($this->title);
            }

            if (!empty($wiki_data['content'])) {
                $this->content = new WikiContent($wiki_data['content']);
                $this->addRevision($this->content->__toString(), $this->status);
            }

            if (!empty($wiki_data['authors'])) {
                $this->author = [];
                foreach ($wiki_data['authors'] as $author) {
                    $this->author[] = User::load($author);
                }
            }

            if (!empty($wiki_data['tags'])) {
                $this->tags = [];
                foreach ($wiki_data['tags'] as $tag) {
                    $this->tags[] = is_numeric($tag) ? Term::load($tag) : Term::factory()->create('wiki', $tag);
                }
            }

            // Persist changes
            $query = "UPDATE wiki_entities SET title = :title, content = :content, slug = :slug, status = :status, updated_at = NOW() WHERE id = :id";
            $statement = Service::get('connection')->prepare($query);
            $statement->bindValue(':title', $this->title);
            $statement->bindValue(':content', $this->content->__toString());
            $statement->bindValue(':slug', $this->slug);
            $statement->bindValue(':status', $this->status->value);
            $statement->bindValue(':id', $this->id);
            $statement->execute();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Deletes the current wiki entity from the database.
     *
     * @return bool True if the deletion was successful, otherwise false.
     */
    public function delete(): bool
    {
        try {
            $query = "DELETE FROM wiki_entities WHERE id = :id";
            $statement = Service::get('connection')->prepare($query);
            $statement->bindValue(':id', $this->id);
            $statement->execute();
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Compare the latest revision with the previous one.
     *
     * @return string Diff output or empty string if not enough revisions.
     */
    public function compareLatestRevisions(): string
    {
        $revisions = $this->revision;

        // Need at least 2 revisions to compare
        if (count($revisions->getRevisions()) < 2) {
            return "Not enough revisions to compare.";
        }

        // Get the last two revisions
        $allRevisions = $revisions->getRevisions();
        $latestIndex = count($allRevisions) - 1;
        $previousIndex = $latestIndex - 1;

        return $revisions->compareRevisions($previousIndex, $latestIndex);
    }

    /**
     * Get the revisions object.
     */
    public function getRevisions(): WikiRevisions
    {
        return $this->revision;
    }

    /**
     * Get the latest revision of the wiki
     *
     * @return WikiRevision|null Returns the latest revision or null if none exist
     */
    public function getLatestRevision(): ?WikiRevision
    {
        return $this->revision->getLatestRevision();
    }

    /**
     * Load wiki entities associated with a specific tag
     *
     * @param int $tag_id The ID of the tag to filter wiki entities by
     * @param int $limit Maximum number of results to retrieve (default is 20)
     * @param int $offset Number of results to skip from the start (default is 0)
     * @return Wiki[] An array of Wiki objects matching the specified tag
     * @throws DependencyException
     * @throws NotFoundException
     *
     */
    public static function loadByTag(int $tag_id, int $limit = 20, int $offset = 0): array
    {
        // Use placeholders for limit and offset (PDO requires integers directly, not bindValue for LIMIT/OFFSET)
        $query = "SELECT * FROM wiki_entities WHERE id IN (SELECT wiki_id FROM wiki_entity_tags WHERE tid = :tag_id) LIMIT :limit OFFSET :offset";

        $connection = Service::get('connection');
        $statement = $connection->prepare($query);

        $statement->bindValue(':tag_id', $tag_id, PDO::PARAM_INT);
        $statement->bindValue(':limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':offset', $offset, PDO::PARAM_INT);

        $statement->execute();

        $wikis = $statement->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($wikis as $wiki) {
            $result[] = Wiki::load($wiki['id']);
        }

        return $result;
    }

    public function hasRevision(): bool
    {
        return $this->revision->getRevisions() !== [];
    }

    /**
     * @return Wiki[]
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function getRelatedWiki(int $limit): array
    {
        $list = [];

        foreach ($this->tags as $tag) {
            $list = array_merge($list, Wiki::loadByTag($tag['id'], $limit));
        }

        // Remove the current wiki from the list
        $list = array_filter($list, fn(\Simp\Core\extends\wiki\src\Entity\Wiki $wiki): bool => $wiki->id() !== $this->id);

        shuffle($list);
        return $list;
    }

    /**
     * @return mixed|User|null
     */
    public function getOriginalAuthor(): mixed
    {
        return $this->author[0] ?? null;
    }

}