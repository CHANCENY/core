<?php

namespace Simp\Core\modules\structures\taxonomy;

use PDO;
use Simp\Core\modules\database\Database;
use Simp\Core\modules\structures\content_types\entity\Node;
use Simp\Environment\Definition\Helper;

class Term
{
    protected PDO $PDO;

    public function __construct(PDO $PDO) {
        $this->PDO = $PDO;
    }

    public static function search($value): array
    {
        $query = Database::database()->con()->prepare("SELECT * FROM term_data WHERE label LIKE :label");
        $query->bindValue(':label', "%$value%");
        $query->execute();
        return $query->fetchAll();
    }

    public function getTerms(): array
    {
        $query = $this->PDO->prepare("SELECT * FROM `term_data`");
        $query->execute();
        return $query->fetchAll();
    }

    public function getTerm(int $term_id): ?array
    {
        $query = $this->PDO->prepare("SELECT * FROM `term_data` WHERE `id` = :term_id");
        $query->execute(['term_id' => $term_id]);
        $term = $query->fetch();
        return !empty($term) ? $term : null;
    }

    public function getTermByVid(string $vid): array
    {
        $query = $this->PDO->prepare("SELECT * FROM `term_data` WHERE `vid` = :vid");
        $query->execute(['vid' => $vid]);
        return $query->fetchAll();
    }

    public function get(string $name): array
    {
        $query = $this->PDO->prepare("SELECT * FROM `term_data` WHERE `name` = :name");
        $query->execute(['name' => $name]);
        return $query->fetchAll();
    }

    protected function createName(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
        $name = preg_replace('/\s+/', '_', $name);
        return preg_replace('/_+/', '_', $name);
    }

    public function getReferenceEntities(string $name)
    {
        $list = $this->get($name);
        $tid_s = array_map(function ($item) { return $item['id']; },$list);

    }


    public function create(string $vid, string $name): bool
    {
        $term = [
            'vid' => $vid,
            'name' => strtolower($this->createName($name)),
            'label' => $name
        ];
        $query = $this->PDO->prepare("INSERT INTO `term_data` (`vid`, `name`, `label`) VALUES (:vid, :name, :label)");
        return $query->execute($term);

    }

    public function update(string $tid, string $label): bool
    {
        $name = strtolower($this->createName($label));
        $query = $this->PDO->prepare("UPDATE `term_data` SET `label` = :label, name = :name WHERE `id` = :tid");
        return $query->execute(['label' => $label, 'tid' => $tid, 'name' => $name]);
    }

    public function delete(string $tid): bool {
        return $this->PDO->prepare("DELETE FROM `term_data` WHERE `id` = :tid")->execute(['tid' => $tid]);
    }

    public static function factory(): Term
    {
        return new Term(Database::database()->con());
    }

    public static function load(int $tid): ?array
    {
        return Term::factory()->getTerm($tid);
    }
}