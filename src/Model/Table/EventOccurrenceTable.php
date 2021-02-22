<?php
declare(strict_types=1);

namespace App\Model\Table;

use App\Model\Entity\EventOccurrence;
use Cake\I18n\FrozenTime;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * EventOccurrence Model
 *
 * @property \App\Model\Table\EventsTable&\Cake\ORM\Association\BelongsTo $Events
 *
 * @method \App\Model\Entity\EventOccurrence newEmptyEntity()
 * @method \App\Model\Entity\EventOccurrence newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\EventOccurrence[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\EventOccurrence get($primaryKey, $options = [])
 * @method \App\Model\Entity\EventOccurrence findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\EventOccurrence patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\EventOccurrence[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\EventOccurrence|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\EventOccurrence saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\EventOccurrence[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\EventOccurrence[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\EventOccurrence[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\EventOccurrence[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class EventOccurrenceTable extends Table
{
    const FREQUENCY_OPERATION = [
        'add' => [
            'Once-Off' => 'addDays',
            'Weekly' => 'addWeeks',
            'Monthly' => 'addMonths',
        ],
        'diff' => [
            'Once-Off' => 'diffInDays',
            'Weekly' => 'diffInWeeks',
            'Monthly' => 'diffInMonths',
        ],
    ];

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('event_occurrence');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Events', [
            'foreignKey' => 'event_id',
            'joinType' => 'INNER',
        ]);

        $this->hasMany('EventAttendees', [
            'foreignKey' => 'event_id',
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->integer('duration')
            ->requirePresence('duration', 'create')
            ->notEmptyString('duration');

        $validator
            ->dateTime('startDateTime')
            ->requirePresence('startDateTime', 'create')
            ->notEmptyDateTime('startDateTime');

        $validator
            ->dateTime('endDateTime')
            ->allowEmptyDateTime('endDateTime');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['event_id'], 'Events'), ['errorField' => 'event_id']);

        return $rules;
    }

    /**
     * @param $id
     * @return int
     */
    public function purgeByEventId($id)
    {
        return $this->deleteAll(['event_id' => $id]);
    }

    /**
     * @param \DateTime|bool $startDate
     * @param \DateTime|bool $endDate
     * @param array $invitees
     * @return array
     */
    public function findAllBy($startDate, $endDate, $invitees = []) {
        $query = $this->find('all');
        $query->contain(['Events' => ['EventAttendees']]);

        if ($startDate) {
            $query->where(['EventOccurrence.startDateTime >=' => $startDate]);
        }

        if ($endDate) {
            $query->where(['EventOccurrence.endDateTime <=' => $endDate]);
        }

        // Set the logic for filtering attendees
        if (count($invitees)) {
            $query->innerJoinWith('Events.EventAttendees', function (Query $q) use ($invitees) {
                return $q->where(['EventAttendees.attendee_id IN' => $invitees]);
            });
        }

        // Sort the event by startDateTime
        $query->orderAsc('EventOccurrence.startDateTime');
        $query->distinct('EventOccurrence.id');

        $result = $query->all();
        $events = [];

        /* @var $event EventOccurrence */
        foreach ($result as $occurrence) {
            $inviteeIds = [];
            $attendees = $occurrence->event->event_attendees;
            if ($attendees) {
                foreach ($attendees as $attendee) {
                    $inviteeIds[] = $attendee->attendee_id;
                }
            }

            $events['items'][] = [
                'event_id' => $occurrence->event->id,
                'eventName' => $occurrence->event->eventName,
                'startDateTime' => $occurrence->startDateTime->toDateTimeString(),
                'endDateTime' => $occurrence->endDateTime->toDateTimeString(),
                'invitees' => $inviteeIds,
            ];
        }

        return $events;
    }

    /**
     * @param $data
     * @param $eventEntity
     * @throws \Exception
     */
    public function saveEvent($data, $eventEntity) {
        $duration = $data['duration'] ?? 0;
        $frequency = $data['frequency'];
        $startDateTime = $data['startDateTime'];
        $endDateTime = $data['endDateTime'];

        $eventOccurenceEntities = [];
        $occurrenceDiff = $startDateTime->{self::FREQUENCY_OPERATION['diff'][$frequency]}($endDateTime);
        for ($occurrence = 0; $occurrence <= $occurrenceDiff; $occurrence++) {
            $currentOccurrence = $startDateTime->{self::FREQUENCY_OPERATION['add'][$frequency]}($occurrence);
            $conflict = $this->hasConflictingSchedule($currentOccurrence, $currentOccurrence->addMinute($duration));
            if($conflict && $conflict->event->id !== $eventEntity->get('id')) {
                $eventName = $conflict->event->eventName;
                $timeSlot = "$conflict->startDateTime to $conflict->endDateTime";
                throw new \Exception("conflicting schedule with $eventName at $timeSlot");
            }
            $eventOccurenceEntities[] = $this->newEmptyEntity()
                ->set('event', $eventEntity)
                ->set('duration', $duration)
                ->set('startDateTime', $currentOccurrence)
                ->set('endDateTime', $currentOccurrence->addMinute($duration));
        }
        $this->saveManyOrFail($eventOccurenceEntities);
    }

    /**
     * @param FrozenTime $start
     * @param FrozenTime $end
     * @return array|\Cake\Datasource\EntityInterface|null
     */
    public function hasConflictingSchedule(FrozenTime $start, FrozenTime $end) {
        $query = $this->find('all');
        $query->contain(['Events']);
        $query->where(['(startDateTime BETWEEN :start AND :end) OR (endDateTime BETWEEN :start AND :end)'])
            ->bind(':start', $start->toDateTimeString(), 'date')
            ->bind(':end', $end->toDateTimeString(), 'date');

        return $query->first();
    }
}
