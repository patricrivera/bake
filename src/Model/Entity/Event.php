<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Event Entity
 *
 * @property int $id
 * @property string $eventName
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime|null $modified
 *
 * @property \App\Model\Entity\EventAttendee[] $event_attendees
 * @property \App\Model\Entity\EventFrequency[] $event_frequency
 * @property \App\Model\Entity\EventOccurrence[] $event_occurrence
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
        'created' => true,
        'modified' => true,
        'event_attendees' => true,
        'event_frequency' => true,
        'event_occurrence' => true,
    ];
}
