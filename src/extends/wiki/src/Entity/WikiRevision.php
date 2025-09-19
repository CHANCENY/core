<?php

namespace Simp\Core\extends\wiki\src\Entity;

use DateMalformedStringException;
use DI\DependencyException;
use DI\NotFoundException;
use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\extends\wiki\src\enum\WikiStatusEnum;
use Simp\Core\modules\services\Service;
use Simp\Core\modules\user\entity\User;

class WikiRevision
{
    protected WikiContent $content;

    protected WikiStatusEnum $status = WikiStatusEnum::DRAFT;

    protected \DateTime $created_at;

    protected \DateTime $updated_at;

    protected int $wiki = 0;

    protected ?User $author = null;

    /**
     * @throws DependencyException
     * @throws PhpfastcacheCoreException
     * @throws NotFoundException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException|DateMalformedStringException
     */
    public function __construct(protected int $id)
    {
        $this->content = new WikiContent('');
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();

        $query = "SELECT * FROM wiki_entities_revision WHERE id = :id";
        $statement = Service::get('connection')->prepare($query);
        $statement->bindValue(':id', $this->id);
        $statement->execute();

        $revision = $statement->fetch();

        if (!empty($revision)) {
            $this->content = new WikiContent($revision['content']);
            $this->status = WikiStatusEnum::from($revision['status']);
            $this->created_at = new \DateTime($revision['created_at']);
            $this->updated_at = new \DateTime($revision['updated_at']);
            $this->wiki = $revision['wiki_id'];

            // Load author if uid column exists in the revision table
            if (!empty($revision['uid'])) {
                $this->author = User::load((int)$revision['uid']);
            }
        }
    }

    /** Getters */

    public function getId(): int
    {
        return $this->id;
    }

    public function getContent(): WikiContent
    {
        return $this->content;
    }

    public function getStatus(): WikiStatusEnum
    {
        return $this->status;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updated_at;
    }

    public function getWikiId(): int
    {
        return $this->wiki;
    }

    public function getAuthor(): ?User
    {
        return $this->author;
    }

    /**
     * Load the associated Wiki object
     * @throws DependencyException
     * @throws DateMalformedStringException
     * @throws \Exception
     */
    public function getWiki(): Wiki
    {
        return Wiki::load($this->wiki);
    }

    /**
     * Get a short summary of this revision content
     */
    public function getSummary(int $length = 150): string
    {
        return mb_strimwidth(strip_tags($this->content->__toString()), 0, $length, "...");
    }
}
