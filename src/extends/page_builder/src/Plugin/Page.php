<?php

namespace Simp\Core\extends\page_builder\src\Plugin;

use Simp\Core\modules\database\Database;

class Page
{
    protected string $name;
    protected string $content;
    protected string $css;
    protected string $title;
    protected int $version;
    protected int $status;


    public function __construct(protected int $pid)
    {
        $query = "SELECT * FROM page_builder_templates WHERE id = :pid";
        $statement = Database::database()->con()->prepare($query);
        $statement->bindValue(':pid', $this->pid);
        $statement->execute();
        $page = $statement->fetch();
        $this->name = $page['name'] ?? "";
        $this->content = $page['content'] ?? "";
        $this->css = $page['css'] ?? "";
        $this->title = $page['title'] ?? "";
        $this->version = $page['version'] ?? 0;
        $this->status = $page['status'] ?? 0;
    }

    public static function search(mixed $search)
    {
        $query = "SELECT id, title, name, version, status, created_at FROM page_builder_templates WHERE title LIKE :search ORDER BY id DESC";
        $statement = Database::database()->con()->prepare($query);
        $statement->bindValue(':search', "%$search%");
        $statement->execute();
        return $statement->fetchAll();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContent(): string
    {
        return gzuncompress(base64_decode($this->content));
    }

    public function getCss(): string
    {
        return gzuncompress(base64_decode($this->css));
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public static function load(int $pid): Page
    {
        return new Page($pid);
    }

    public function id()
    {
        return $this->pid;
    }
    public function getPid()
    {
        return $this->pid;
    }


}