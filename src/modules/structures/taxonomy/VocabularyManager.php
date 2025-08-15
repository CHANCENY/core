<?php

namespace Simp\Core\modules\structures\taxonomy;

use Simp\Core\lib\installation\SystemDirectory;
use Symfony\Component\Yaml\Yaml;

class VocabularyManager
{
    protected array $vocabularies = [];
    protected string $location;

    public function __construct()
    {
        $system = new SystemDirectory;
        $this->location = $system->setting_dir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'taxonomy' . DIRECTORY_SEPARATOR . 'vocabulary.yml';
        if (!is_dir($system->setting_dir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'taxonomy')) {
            @mkdir($system->setting_dir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'taxonomy');
        }
        if (!file_exists($this->location)) {
            @touch($this->location);
        }
        $this->vocabularies = Yaml::parseFile($this->location) ?? [];
    }

    public function addVocabulary(string $name): bool
    {
        $name_neural = strtolower($this->createName($name));
        if (!isset($this->vocabularies[$name_neural])) {
            $this->vocabularies[$name_neural] = [
                'name' => $name_neural,
                'label' => $name
            ];
            $d = Yaml::dump($this->vocabularies, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
            return !empty(file_put_contents($this->location, $d));
        }

        $this->vocabularies[$name_neural]['label'] = $name;
        $d = Yaml::dump($this->vocabularies, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        return !empty(file_put_contents($this->location, $d));
    }

    protected function createName(string $name): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $name);
        $name = preg_replace('/\s+/', '_', $name);
        return preg_replace('/_+/', '_', $name);
    }

    public static function factory(): VocabularyManager
    {
        return new self();
    }

    public function removeVocabulary(string $vid): bool
    {
        if (isset($this->vocabularies[$vid])) {
            unset($this->vocabularies[$vid]);
            $d = Yaml::dump($this->vocabularies, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
            return !file_put_contents($this->location, $d);
        }
        return false;
    }

    public function getVocabularies(): array
    {
        return $this->vocabularies;
    }

    public function getVocabulary(mixed $name)
    {
        return $this->vocabularies[$name] ?? [];
    }

    public function updateVocabulary(mixed $name, mixed $label): bool
    {
        $name_neural = strtolower($this->createName($name));
        if (!isset($this->vocabularies[$name_neural])) {
            $this->vocabularies[$name_neural]['label'] = $label;
        }
        $d = Yaml::dump($this->vocabularies);
        return !empty(file_put_contents($this->location, $d));
    }

}