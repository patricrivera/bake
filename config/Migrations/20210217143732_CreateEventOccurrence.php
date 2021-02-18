<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateEventOccurrence extends AbstractMigration
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
        $table = $this->table('event_occurrence');
        $table->addColumn('event_id', 'integer')
            ->addForeignKey('event_id', 'events', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION']);

        $table->addColumn('duration', 'integer');

        $table->addColumn('startDateTime', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('endDateTime', 'datetime', [
            'default' => null,
            'null' => true,
        ]);
        $table->create();
    }
}
