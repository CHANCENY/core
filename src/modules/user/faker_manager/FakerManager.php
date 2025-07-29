<?php

namespace Simp\Core\modules\user\faker_manager;

use Faker\Generator;
use Faker\Provider\en_UG\Address;
use Faker\Provider\en_US\Company;
use Faker\Provider\en_US\Person;
use Faker\Provider\en_US\PhoneNumber;
use Faker\Provider\Internet;
use Faker\Provider\Lorem;
use Simp\Core\modules\structures\content_types\ContentDefinitionManager;
use Simp\Core\modules\structures\content_types\entity\Node;
use Simp\Core\modules\timezone\TimeZone;
use Simp\Core\modules\user\entity\User;

class FakerManager
{
    protected array $populated_data = [];
    public function __construct()
    {
        $this->fillable_fields = [
            'name',
            'email',
            'password',
            'status',
            'time_zone',
            'roles',
        ];
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

        $total = (int) ($total == 0 ? 1 : $total);

        for ($i = 0; $i < $total; $i++) {
            $this->populated_data[$i] = [
                'user' => [
                    'name' => $faker->userName(),
                    'mail' => $faker->email(),
                    'password' => $faker->password(),
                    'status' => $faker->randomElement([1, 0]),
                    'roles' => [$faker->randomElement(['administrator', 'authenticated', 'manager', 'content_creator'])],
                    'time_zone' => $faker->randomElement(array_keys((new TimeZone())->getSimplifiedTimezone())),
                ],
                'profile' => [
                    'first_name' => $faker->firstName(),
                    'last_name' => $faker->lastName(),
                    'description' => $faker->paragraphs(5, true),
                ]

            ];
            $this->populated_data[$i] = [...$this->populated_data[$i], ...$append_values];
        }
    }


    public function save(): void
    {
        foreach ($this->populated_data as $data) {
            $user = $data['user'];
            $user_new = User::create($user);
            if ($user_new instanceof User) {
                $profile = $user_new->getProfile();
                $profile->setFirstName($data['profile']['first_name']);
                $profile->setLastName($data['profile']['last_name']);
            }
        }
    }
}