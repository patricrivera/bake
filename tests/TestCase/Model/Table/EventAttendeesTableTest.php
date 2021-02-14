<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\EventAttendeesTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\EventAttendeesTable Test Case
 */
class EventAttendeesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\EventAttendeesTable
     */
    protected $EventAttendees;

    /**
     * Fixtures
     *
     * @var array
     */
    protected $fixtures = [
        'app.EventAttendees',
        'app.Events',
        'app.Attendees',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('EventAttendees') ? [] : ['className' => EventAttendeesTable::class];
        $this->EventAttendees = $this->getTableLocator()->get('EventAttendees', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->EventAttendees);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
