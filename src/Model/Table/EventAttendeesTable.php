<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * EventAttendees Model
 *
 * @property \App\Model\Table\EventsTable&\Cake\ORM\Association\BelongsTo $Events
 * @property \App\Model\Table\AttendeesTable&\Cake\ORM\Association\BelongsTo $Attendees
 *
 * @method \App\Model\Entity\EventAttendee newEmptyEntity()
 * @method \App\Model\Entity\EventAttendee newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\EventAttendee[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\EventAttendee get($primaryKey, $options = [])
 * @method \App\Model\Entity\EventAttendee findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\EventAttendee patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\EventAttendee[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\EventAttendee|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\EventAttendee saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\EventAttendee[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\EventAttendee[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\EventAttendee[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\EventAttendee[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class EventAttendeesTable extends Table
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('event_attendees');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Events', [
            'foreignKey' => 'event_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('Attendees', [
            'foreignKey' => 'attendee_id',
            'joinType' => 'INNER',
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
        $rules->add($rules->existsIn(['attendee_id'], 'Attendees'), ['errorField' => 'attendee_id']);

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
     * @param $data
     * @param $eventEntity
     * @throws \Exception
     */
    public function saveEvent($data, $eventEntity) {
        //Get the attendees entities
        $attendees = $this->Attendees->getMultipleByIds($data['invitees'] ?? []);

        $eventAttendeesEntities = [];
        foreach ($attendees as $attendeeEntity) {
            $eventAttendeesEntities[] = $this->newEmptyEntity()
                ->set('attendee', $attendeeEntity)
                ->set('event', $eventEntity);
        }
        $this->saveManyOrFail($eventAttendeesEntities);
    }
}
