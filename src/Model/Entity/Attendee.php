<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Attendee Entity
 *
 * @property int $id
 * @property string $firstName
 * @property string $lastName
 *
 * @property \App\Model\Entity\EventAttendee[] $event_attendees
 */
class Attendee extends Entity
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
        'firstName' => true,
        'lastName' => true,
        'event_attendees' => true,
    ];
}
