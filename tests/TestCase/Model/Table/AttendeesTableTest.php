<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\AttendeesTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\AttendeesTable Test Case
 */
class AttendeesTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\AttendeesTable
     */
    protected $Attendees;

    /**
     * Fixtures
     *
     * @var array
     */
    protected $fixtures = [
        'app.Attendees',
        'app.EventAttendees',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Attendees') ? [] : ['className' => AttendeesTable::class];
        $this->Attendees = $this->getTableLocator()->get('Attendees', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->Attendees);

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
}
