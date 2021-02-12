<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateEventAttendees extends AbstractMigration
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
        $table = $this->table('event_attendees');
        $table->addColumn('event_id', 'integer')
            ->addForeignKey('event_id', 'events', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION']);

        $table->addColumn('attendee_id', 'integer')
            ->addForeignKey('attendee_id', 'attendees', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION']);

        $table->create();
    }
}
