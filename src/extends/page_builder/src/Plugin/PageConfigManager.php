<?php

namespace Simp\Core\extends\page_builder\src\Plugin;



use Simp\Core\modules\database\Database;

class PageConfigManager
{
    /** @var int[] */
    protected array $pages = [];
    public function __construct(protected string $name = "")
    {
        $query = "SELECT * FROM page_builder_templates ORDER BY created_at DESC";
        $statement = Database::database()->con()->prepare($query);
        if (!empty($this->name)) {
            $query = "SELECT id FROM page_builder_templates WHERE name = :name";
            $statement = Database::database()->con()->prepare($query);
            $statement->bindValue(':name', $this->name);
        }


        $statement->execute();
        $results = $statement->fetchAll();

        if (!empty($results)) {
            $this->pages = array_column($results, 'id');
        }
    }

    /**
     * @return array<Page>
     */
    public function getPages(): array
    {
        return array_map(function ($id) {
            return new Page($id);
        }, $this->pages);
    }

    public function addPage(string $name, string $title, string $css, string $content)
    {
        $name = $this->makeSlug($name);

        // compress so that can be decompressed later
        $css_compress = base64_encode(gzcompress($css, 9));
        $content_compress = base64_encode(gzcompress($content, 9));

        $version = 1;
        $status = 1;

        if ($page = $this->getPage($name)) {
            $version = ($page['version'] ?? 0) + 1;
            $this->unpublishPage($page['id']);
            $title = $page['title'];
        }

        $query = "INSERT INTO page_builder_templates (name, title, css, content, version,status) 
              VALUES (:name, :title, :css, :content, :version,:status)";
        $statement = Database::database()->con()->prepare($query);
        $statement->bindValue(':name', $name);
        $statement->bindValue(':title', $title);
        $statement->bindValue(':css', $css_compress);
        $statement->bindValue(':content', $content_compress);
        $statement->bindValue(':version', $version);
        $statement->bindValue(':status', $status);

        if ($statement->execute()) {
            return $name;
        }
        return false;
    }

    public function unpublishPage(int $page_id)
    {
        $query = "UPDATE page_builder_templates SET status = 0 WHERE id = :id";
        $statement = Database::database()->con()->prepare($query);
        $statement->bindValue(':id', $page_id);
        return $statement->execute();
    }

    protected function getPage(string $name): array|false|null
    {
        $query = "SELECT * FROM page_builder_templates WHERE name = :name ORDER BY id DESC LIMIT 1";
        $statement = Database::database()->con()->prepare($query);
        $statement->bindValue(':name', $name);
        $statement->execute();
        return $statement->fetch();
    }

    protected function makeSlug(string $val): string
    {
        // convert to lowercase
        $slug = strtolower($val);

        // trim whitespace
        $slug = trim($slug);

        // replace non-alphanumeric characters with hyphens
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);

        // remove leading/trailing hyphens
        $slug = preg_replace('/^_+|_+$/', '', $slug);

        return $slug;
    }

    public static function factory(string $name = ''): PageConfigManager
    {
        return new PageConfigManager($name);
    }


}