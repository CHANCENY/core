<?php

namespace Simp\Core\extends\image\src\Loader;

use Simp\Core\modules\database\Database;
use Simp\Core\modules\files\entity\File;
use Simp\Core\modules\files\helpers\FileFunction;

class Gallery
{
    protected array $images = [];

    public function __construct()
    {
        $query = "SELECT * FROM image_gallery ORDER BY id DESC";
        $query = Database::database()->con()->prepare($query);
        $query->execute();
        $images = $query->fetchAll();

        foreach ($images as $image) {

            $file = File::load($image['fid']);
            if ($file instanceof File) {
                $array = $file->toArray();
                $array['uri'] = FileFunction::reserve_uri($file->getUri());
                $this->images[$file->getFid()] = $array;
            }

        }
    }

    public function getImages(): array
    {
        return $this->images;
    }

    public function getImagesCount(): int {
        return count($this->images);
    }

    public function getImagesByPage(int $page, int $per_page): array {
        $offset = ($page - 1) * $per_page;
        return array_slice($this->images, $offset, $per_page);
    }

    public static function factory(): Gallery {
        return new Gallery();
    }
}