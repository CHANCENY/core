<?php

namespace Simp\Core\extends\announcement\src\plugin;

use Phpfastcache\Exceptions\PhpfastcacheCoreException;
use Phpfastcache\Exceptions\PhpfastcacheDriverException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Simp\Core\modules\database\Database;
use Simp\Core\modules\user\current_user\CurrentUser;

class Announcement
{
    protected int $owner_uid;
    protected string $title;
    protected string $content;
    protected int $to_uid;

    /**
     * @throws PhpfastcacheCoreException
     * @throws PhpfastcacheLogicException
     * @throws PhpfastcacheDriverException
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function __construct()
    {
        $this->owner_uid = CurrentUser::currentUser()->getUser()->getUid();
        $this->title = 'Hi';
        $this->content = 'Hello there!';
        $this->to_uid = CurrentUser::currentUser()->getUser()->getUid();
    }

    public function setOwnerUid(int $owner_uid): void
    {
        $this->owner_uid = $owner_uid;
    }
    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function setToUid(int $to_uid): void
    {
        $this->to_uid = $to_uid;
    }

    public function send(): bool {
        $query = "INSERT INTO announcement (owner_uid, title, content, to_uid) VALUES (:owner_uid, :title, :content, :to_uid)";
        $query = Database::database()->con()->prepare($query);
        $query->execute([
            ':owner_uid'=>$this->owner_uid,
            ':title'=>$this->title,
            ':content'=>$this->content,
            ':to_uid'=>$this->to_uid
        ]);
        return true;
    }

    public function getAnnouncements(): array
    {
        $query = "SELECT * FROM announcement WHERE to_uid = :to_uid AND status = 0 ORDER BY created_at DESC";
        $query = Database::database()->con()->prepare($query);
        $query->execute([
            ':to_uid'=>$this->to_uid
        ]);
        $announcements = $query->fetchAll();
        $id = array_column($announcements, 'id');
        if (empty($announcements)) {
            return $announcements;
        }
        $query = "UPDATE announcement SET status = 1 WHERE id IN (".implode(',', $id).")";
        $query = Database::database()->con()->prepare($query);
        $query->execute();
        return $announcements;
    }

    public function getUnreadAnnouncementsCount(): int
    {
        $query = "SELECT COUNT(*) FROM announcement WHERE to_uid = :to_uid AND status = 0";
        $query = Database::database()->con()->prepare($query);
        $query->execute([
            ':to_uid'=>$this->to_uid
        ]);
        return $query->fetchColumn();
    }

    /**
     * Creates and sets up a new announcement with the given title, content, and recipient user ID.
     *
     * @param string $title The title of the announcement.
     * @param string $content The content of the announcement.
     * @param int $to_uid The user ID of the announcement recipient.
     * @return Announcement The newly created announcement object.
     */
    public static function setAnnouncement(string $title, string $content, int $to_uid): Announcement
    {
        $announcement = new Announcement();
        $announcement->setTitle($title);
        $announcement->setContent($content);
        $announcement->setToUid($to_uid);
        return $announcement;
    }



    public static function factory(): Announcement
    {
        return new Announcement();
    }
}