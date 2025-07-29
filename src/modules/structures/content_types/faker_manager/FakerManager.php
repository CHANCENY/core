<?php

namespace Simp\Core\modules\structures\content_types\faker_manager;

use Faker\Generator;
use Faker\Provider\en_UG\Address;
use Faker\Provider\en_US\Company;
use Faker\Provider\en_US\Person;
use Faker\Provider\en_US\PhoneNumber;
use Faker\Provider\Internet;
use Faker\Provider\Lorem;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;
use Simp\Core\modules\structures\content_types\entity\Node;

class FakerManager
{

    protected array $fillable_fields = [];
    protected array $populated_data = [];
    public function __construct(string $content_type)
    {
        $content_type_data = ContentDefinitionManager::contentDefinitionManager()->getContentType($content_type);
        if (is_array($content_type_data)) {
            $this->fillable_fields = [
                'title',
            ];
            foreach ($content_type_data['storage'] as $storage) {
                $this->fillable_fields[] = substr($storage,6,strlen($storage));
            }
        }
    }
    public function getFillableFields(): array
    {
        return $this->fillable_fields;
    }

    public function setFillableFields(array $fillable_fields): void
    {
        $this->fillable_fields = $fillable_fields;
    }

    public function populateData(array $append_values = [], int $total = 1): void
    {
        $faker = new Generator();
        $faker->addProvider(new Person($faker));
        $faker->addProvider(new Address($faker));
        $faker->addProvider(new PhoneNumber($faker));
        $faker->addProvider(new Company($faker));
        $faker->addProvider(new Lorem($faker));
        $faker->addProvider(new Internet($faker));

        if ($total > 1) {
            for ($i = 0; $i < $total; $i++) {
                foreach ($this->fillable_fields as $key=>$field) {
                    $this->populated_data[$i][$key] = $faker->$field();
                }
                $this->populated_data[$i] = [...$this->populated_data[$i], ...$append_values];
            }
        }
        else {
            foreach ($this->fillable_fields as $key=>$field) {
                $this->populated_data[$key] = $faker->$field();
            }
            $this->populated_data = [...$this->populated_data, ...$append_values];
        }
    }


    public function save(): void
    {
        if (isset($this->populated_data[0]) && is_array($this->populated_data[0])) {
            foreach ($this->populated_data as $value) {
                Node::create($value);
            }
        }else {
            Node::create($this->populated_data);
        }
    }
}