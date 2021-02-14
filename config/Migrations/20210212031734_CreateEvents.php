<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateEvents extends AbstractMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function change()
    {
        $table = $this->table('events');

        $table->addColumn('eventName', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);

        $table->addColumn('duration', 'integer');

        $table->addColumn('startDateTime', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('endDateTime', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->create();
    }
}
