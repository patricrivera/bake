<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class CreateEventFrequency extends AbstractMigration {
    /**
     * @deprecated Change requirement, use the EventOccurence
     *
     * More information on this method is available here:
     * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
     * @return void
     */
    public function change() {
        $table = $this->table('event_frequency');
        $table->addColumn('event_id', 'integer')
            ->addForeignKey('event_id', 'events', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION']);

        $table->addColumn('frequency_id', 'integer')
            ->addForeignKey('frequency_id', 'frequency', 'id', ['delete' => 'CASCADE', 'update' => 'NO_ACTION']);

        $table->create();
    }
}
