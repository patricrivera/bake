<?php
declare(strict_types=1);

use Migrations\AbstractSeed;

/**
 * Frequency seed.
 */
class FrequencySeed extends AbstractSeed
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
        $data = [
            ['name' => "Once-Off"],
            ['name' => "Weekly"],
            ['name' => "Monthly"],
        ];

        $table = $this->table('frequency');
        $table->insert($data)->save();
    }
}
