<?php

namespace Simp\Core\modules\timezone;

use Simp\Core\lib\installation\SystemDirectory;
use Symfony\Component\Yaml\Yaml;

class TimeZone extends SystemDirectory
{
    protected array $timezones = [];

    protected Zone $zone;
    public function __construct(?string $timezone = null)
    {
        parent::__construct();
        $timezone_file = $this->webroot_dir .DIRECTORY_SEPARATOR .'core'. DIRECTORY_SEPARATOR .'defaults' . DIRECTORY_SEPARATOR . 'timezones'
        . DIRECTORY_SEPARATOR . 'timezone.yml';
        if (file_exists($timezone_file)) {
            $this->timezones = Yaml::parse(file_get_contents($timezone_file));
        }

        if (!empty($timezone)) {
           $found = array_filter($this->timezones, function ($item) use ($timezone) {
               return $item['tzCode'] === $timezone;
           });
           if (!empty($found) && $zone = reset($found)) {
               $this->zone =new Zone(...$zone);
           }
        }
    }

    public function getTimezones(): array
    {
        return $this->timezones;
    }

    public function getTimezone(string $name): array
    {
        $found = [];
        foreach ($this->timezones as $timezone) {
            if ($timezone['name'] === $name) {
                $found = $timezone;
                break;
            }
        }
        return $found;
    }

    public function getSimplifiedTimezone(): array
    {
        $timezone = [];
        foreach ($this->timezones as $timezone_info) {
            $timezone[$timezone_info['tzCode']] = $timezone_info['label'] ?? $timezone_info['name'];
        }
        return $timezone;
    }

    public function getZone(): ?Zone
    {
        return $this->zone ?? null;
    }
}