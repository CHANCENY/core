<?php

namespace Simp\Core\extends\form_builder\src\Plugin;

use Exception;
use Simp\Core\modules\database\Database;

class FormSettings
{
    protected string $title;
    protected string $confirmation;
    protected int $limit;
    protected string $embedded;
    protected string $slug;
    protected string $status;
    protected string $notify;
    protected string $require_login;

    /**
     * @throws Exception
     */
    public function __construct(private readonly string $form_name)
    {
        $query = "SELECT * FROM form_settings WHERE form_name = :form_name";
        $statement = Database::database()->con()->prepare($query);
        $statement->bindValue(':form_name', $this->form_name);
        $statement->execute();
        $settings = $statement->fetch();
        if ($settings) {
            $this->title = $settings['title'];
            $this->confirmation = $settings['confirmation'];
            $this->limit = $settings['submit_limit'];
            $this->embedded = $settings['embedded'];
            $this->slug = $settings['slug'];
            $this->status = $settings['status'];
            $this->notify = $settings['notify'];
            $this->require_login = $settings['require_login'];
        }
        else {
            $this->title = '';
            $this->confirmation = '';
            $this->limit = 0;
            $this->embedded = '';
            $this->slug = '';
            $this->status = '';
            $this->notify = '';
            $this->require_login = '';
        }
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getConfirmation(): string
    {
        return $this->confirmation;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getEmbedded(): string
    {
        return $this->embedded;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getNotify(): string
    {
        return $this->notify;
    }

    public function create(string $title, string $confirmation, int $limit, string $embedded, string $slug, string $status, string $notify, string $require_login): bool
    {
        $query = "INSERT INTO form_settings (form_name, title, confirmation, submit_limit, embedded, slug, status, notify, require_login) VALUES (:form_name, :title, :confirmation, :limit, :embedded, :slug, :status, :notify,:require_login)";
        $statement = $this->getF($query, $title, $confirmation, $limit, $embedded, $slug, $status, $notify,$require_login);
        return $statement->execute();
    }

    public function update(string $title, string $confirmation, int $limit, string $embedded, string $slug, string $status, string $notify, string $require_login): bool
    {
        $query = "UPDATE form_settings SET title = :title, confirmation = :confirmation, submit_limit = :limit, embedded = :embedded, slug = :slug, status = :status, notify = :notify, require_login = :require_login WHERE form_name = :form_name";
        $statement = $this->getF($query, $title, $confirmation, $limit, $embedded, $slug, $status, $notify,$require_login);
        return $statement->execute();
    }

    public function delete(): bool
    {
        $query = "DELETE FROM form_settings WHERE form_name = :form_name";
        $statement = Database::database()->con()->prepare($query);
        $statement->bindValue(':form_name', $this->form_name);
        return $statement->execute();
    }

    public function getFormName(): string
    {
        return $this->form_name;
    }

    public static function factory(string $form_name): FormSettings
    {
        return new FormSettings($form_name);
    }

    /**
     * @param string $query
     * @param string $title
     * @param string $confirmation
     * @param int $limit
     * @param string $embedded
     * @param string $slug
     * @param string $status
     * @param string $notify
     * @param string $require_login
     * @return false|\PDOStatement
     */
    public function getF(string $query, string $title, string $confirmation, int $limit, string $embedded, string $slug, string $status, string $notify, string $require_login): \PDOStatement|false
    {
        $statement = Database::database()->con()->prepare($query);
        $statement->bindValue(':form_name', $this->form_name);
        $statement->bindValue(':title', $title);
        $statement->bindValue(':confirmation', $confirmation);
        $statement->bindValue(':limit', $limit);
        $statement->bindValue(':embedded', $embedded);
        $statement->bindValue(':slug', $slug);
        $statement->bindValue(':status', $status);
        $statement->bindValue(':notify', $notify);
        $statement->bindValue(':require_login', $require_login);
        return $statement;
    }

    public function __get(string $name)
    {
        return match ($name) {
            'title' => $this->title,
            'confirmation' => $this->confirmation,
            'limit' => $this->limit,
            'embedded' => $this->embedded,
            'slug' => $this->slug,
            'status' => $this->status,
            'notify' => $this->notify,
            default => null,
        };
    }

    public function isFormActive(): bool
    {
        return  !empty($this->slug) && $this->status == 'published';

    }

    public function getRequireLogin(): string
    {
        return $this->require_login;
    }


}