<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\FrequencyTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\FrequencyTable Test Case
 */
class FrequencyTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\FrequencyTable
     */
    protected $Frequency;

    /**
     * Fixtures
     *
     * @var array
     */
    protected $fixtures = [
        'app.Frequency',
        'app.EventFrequency',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Frequency') ? [] : ['className' => FrequencyTable::class];
        $this->Frequency = $this->getTableLocator()->get('Frequency', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown(): void
    {
        unset($this->Frequency);

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
