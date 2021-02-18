<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\EventOccurrenceTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\EventOccurrenceTable Test Case
 */
class EventOccurrenceTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\EventOccurrenceTable
     */
    protected $EventOccurrence;

    /**
     * Fixtures
     *
     * @var array
     */
    protected $fixtures = [
        'app.EventOccurrence',
        'app.Events',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('EventOccurrence') ? [] : ['className' => EventOccurrenceTable::class];
        $this->EventOccurrence = $this->getTableLocator()->get('EventOccurrence', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->EventOccurrence);

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
