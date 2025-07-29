<?php

namespace Simp\Core\modules\structures\taxonomy\traits;

use Simp\Core\modules\database\SPDO;
use Simp\Core\modules\structures\taxonomy\VocabularyManager;

class Helper
{
    protected string $table;
    protected string $vid;
    protected array $terms;

    protected SPDO $database;

    public function save(): bool
    {
        if (isset($this->table) && isset($this->vid)) {

            if ($this->table === 'term_data') {

                // save term data
                $query = "INSERT INTO term_data (vid, name, label) VALUES (:vid, :name, :label)";
                for ($i = 0; $i < count($this->terms); $i++) {
                    $term = $this->terms[$i];
                    $this->database->prepare($query)->execute($term);
                }
                return true;
            }

            elseif ($this->table === 'taxonomy_vocabulary') {
                VocabularyManager::factory()->addVocabulary($this->vid);
                return true;
            }

        }
        return false;
    }

    public function update(): bool
    {
        if (isset($this->table) && isset($this->vid)) {
            if ($this->table === 'term_data') {
                $query = "UPDATE term_data SET name = :name, label = :label WHERE id = :id";
                $this->database->prepare($query)->execute($this->terms);
                return true;
            }
            elseif ($this->table === 'taxonomy_vocabulary') {
                VocabularyManager::factory()->addVocabulary($this->vid);
                return true;
            }
        }
        return false;
    }

    public function delete(): bool
    {
        if (isset($this->table) && isset($this->vid)) {
            if ($this->table === 'term_data') {
                $query = "DELETE FROM term_data WHERE id = :id";
                $this->database->prepare($query)->execute($this->terms);
                return true;
            }
            elseif ($this->table === 'taxonomy_vocabulary') {
                return VocabularyManager::factory()->removeVocabulary($this->vid);
            }
        }
        return false;
    }
}