<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\EventFrequencyTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\EventFrequencyTable Test Case
 */
class EventFrequencyTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\EventFrequencyTable
     */
    protected $EventFrequency;

    /**
     * Fixtures
     *
     * @var array
     */
    protected $fixtures = [
        'app.EventFrequency',
        'app.Events',
        'app.Frequency',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('EventFrequency') ? [] : ['className' => EventFrequencyTable::class];
        $this->EventFrequency = $this->getTableLocator()->get('EventFrequency', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->EventFrequency);

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
