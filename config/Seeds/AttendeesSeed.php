<?php
declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * Attendees seed.
 */
class AttendeesSeed extends AbstractSeed
{
    /**
     * Run Method.
     *
     * Write your database seeder using this method.
     *
     * More information on writing seeds is available here:
     * https://book.cakephp.org/phinx/0/en/seeding.html
     *
     * @return void
     */
    public function run()
    {
        $faker = Faker\Factory::create();
        $data = [];
        for ($i = 0; $i < 100; $i++) {
            $data[] = [
                'firstName'    => $faker->firstName,
                'lastName'     => $faker->lastName,
            ];
        }

        $table = $this->table('attendees');
        $table->insert($data)->save();
    }
}
