<?php

namespace Simp\Core\modules\user\profiles;

use Simp\Core\modules\timezone\Zone;
use Simp\Core\modules\database\Database;
use Simp\Core\modules\timezone\TimeZone;
use Simp\Translate\lang\LanguageManager;
use Simp\Core\modules\files\helpers\FileFunction;

class Profile
{
    public function __construct(protected ?int $pid, protected ?string $first_name,
    protected ?string $last_name,
    protected ?int $profile_image,
    protected ?int $uid,
    protected ?string $time_zone,
    protected ?string $description,
    protected ?int $translation,
    protected ?string $translation_code)
    {
    }

    public function getPid(): ?int
    {
        return $this->pid;
    }

    public function getFirstName(): ?string
    {
        return $this->first_name;
    }

    public function getLastName(): ?string
    {
        return $this->last_name;
    }

    public function getProfileImage(): ?int
    {
        return $this->profile_image;
    }

    public function getImage(): ?string
    {
        return FileFunction::resolve_fid($this->profile_image);
    }

    public function getTranslation() {
        return $this->translation_code;
    }

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function getTimeZone(): ?string
    {
        return $this->time_zone;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getTranslationCode(): ?string {
        return $this->translation_code;
    }

    public function isTranslationEnabled(): bool {
        return !empty($this->translation);
    }

    public function setFirstName(?string $first_name): void
    {
        $this->first_name = $first_name;
    }

    public function setLastName(?string $last_name): void
    {
        $this->last_name = $last_name;
    }

    public function setTranslation(int $translation = 0): void {
        $this->translation = $translation;
    }

    public function setProfileImage(?int $profile_image): void
    {
        $this->profile_image = $profile_image;
    }

    public function setTranslationCode(string $code): void {
        $languageManager = LanguageManager::manager()->getByCode($code);
        if($languageManager) {
            $this->translation_code = $code;
        }
    }

    public function setTimeZone(?string $time_zone): void
    {
        $this->time_zone = $time_zone;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function update(): bool
    {
        $query = "UPDATE user_profile SET first_name = :first_name, last_name = :last_name, time_zone = :time_zone, description = :description, profile_image = :profile_image, translation = :translation, translation_code = :translation_code WHERE uid = :uid AND pid = :pid";
        $query = Database::database()->con()->prepare($query);

        $query->bindParam(":uid", $this->uid);
        $query->bindParam(":pid", $this->pid);
        $query->bindParam(":first_name", $this->first_name);
        $query->bindParam(":last_name", $this->last_name);
        $query->bindParam(":time_zone", $this->time_zone);
        $query->bindParam(":description", $this->description);
        $query->bindParam(":profile_image", $this->profile_image);
        $query->bindParam(":translation", $this->translation);
        $query->bindParam(":translation_code", $this->translation_code);
        return $query->execute();
    }

    public function timeZoneEntity(): ?Zone
    {
        $timezone = new TimeZone($this->time_zone);
        return $timezone->getZone();
    }

    public function __toString(): string
    {
        return json_encode([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'profile_image' => $this->profile_image,
            'time_zone' => $this->time_zone,
            'description' => $this->description,
            'translation' => $this->translation,
            'translation_code' => $this->translation_code
        ], JSON_PRETTY_PRINT);
    }

    public function toArray(): array {
        return [
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'profile_image' => $this->profile_image,
            'time_zone' => $this->time_zone,
            'description' => $this->description,
             'translation' => $this->translation,
             'translation_code' => $this->translation_code
        ];
    }

}
