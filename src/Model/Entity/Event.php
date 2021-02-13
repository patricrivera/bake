<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Event Entity
 *
 * @property int $id
 * @property string $eventName
 * @property int $duration
 * @property \Cake\I18n\FrozenTime $startDateTime
 * @property \Cake\I18n\FrozenTime|null $endDateTime
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime|null $modified
 *
 * @property \App\Model\Entity\EventAttendee[] $attendees
 * @property \App\Model\Entity\EventFrequency[] $frequency
 */
class Event extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array
     */
    protected $_accessible = [
        'eventName' => true,
        'duration' => true,
        'startDateTime' => true,
        'endDateTime' => true,
        'created' => true,
        'modified' => true,
        'attendees' => true,
        'frequency' => true,
    ];
}
