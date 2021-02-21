<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\Event;
use App\Model\Entity\EventOccurrence;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Datasource\ResultSetInterface;
use Cake\I18n\FrozenTime;
use Cake\ORM\Query;
use Psy\Util\Json;

/**
 * Events Controller
 *
 * @method Event[]|ResultSetInterface paginate($object = null, array $settings = [])
 */
class EventsController extends AppController {

    const VALID_FREQUENCY = ['Once-Off', "Weekly", "Monthly"];

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

    /** @var int Number of default occurrence if no endDateTime was provided,
     * note that it has additional one occurrence including the stardDateTime
     */
    const DEFAULT_OCCURRENCE = 9;

    /**
     * Index method
     *
     * @return \Cake\Http\Response|null|void Renders view
     */
    public function index() {
        $events = $this->Events->find('all');
        $this->set([
            'events' => $events,
            '_serialize' => ['events']
        ]);
        $this->viewBuilder()->setOption('serialize', 'events');
        $this->set(compact('events'));
    }

    public function view() {
        try {
            $data = $this->request->getQuery();
            // Set the logic for filtering dates
            /** @var Query $query */
            $eventOccurrenceTable = $this->getTableLocator()->get('EventOccurrence');
            $query = $eventOccurrenceTable->find('all');
            $query->contain(['Events', 'EventAttendees']);

            if (isset($data['start'])) {
                $query->where(['EventOccurrence.startDateTime >=' => new \DateTime($data['start'])]);
            }

            if (isset($data['end'])) {
                $query->where(['EventOccurrence.endDateTime <=' => new \DateTime($data['end'])]);
            }

            // Set the logic for filtering attendees
            if (isset($data['invitees'])) {
                $invitees = explode(",", $data['invitees']);
                $query->innerJoinWith('EventAttendees', function (Query $q) use ($invitees) {
                    return $q->where(['EventAttendees.attendee_id IN' => $invitees]);
                });
            }

            // Sort the event by startDateTime
            $query->orderAsc('EventOccurrence.startDateTime');
            $query->distinct('EventOccurrence.id');
            $events = $query->all();

            $response = [
                'items' => [],
            ];
            $inviteeIds = [];
            /* @var $event EventOccurrence */
            foreach ($events as $occurrence) {
                $attendees = $occurrence->event_attendees;
                if ($attendees && !isset($inviteeIds[$occurrence->event->id])) {
                    $inviteeIds[$occurrence->event->id] = [];
                    foreach ($attendees as $attendee) {
                        $inviteeIds[$occurrence->event->id][] = $attendee->attendee_id;
                    }
                }

                $response['items'][] = [
                    'event_id' => $occurrence->event->id,
                    'eventName' => $occurrence->event->eventName,
                    'startDateTime' => $occurrence->startDateTime->toDateTimeString(),
                    'endDateTime' => $occurrence->endDateTime->toDateTimeString(),
                    'invitees' => $inviteeIds[$occurrence->event->id],
                ];

            }

        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            $response = ['status' => 'error', 'message' => $message];
        }
        return $this->response->withType('application/json')
            ->withStringBody(json_encode($response));
    }

    /**
     * Add method
     *
     * @return \Cake\Http\Response|Json|null|void
     * @throws \Exception
     */
    public function add() {
        $connection = ConnectionManager::get('default');
        $connection->begin();
        $eventEntity = $this->Events->newEmptyEntity();
        try {
            if ($this->request->is('post')) {
                $data = $this->request->getData();
                $eventEntity = $this->Events->patchEntity($eventEntity, $data);
                $this->prepareData($data);
                $this->saveEventAttendees($data, $eventEntity);
                $this->saveEventFrequency($data, $eventEntity);
                $this->saveEventOccurrence($data, $eventEntity);
                if (!$this->Events->save($eventEntity)) {
                    $errors = $eventEntity->getErrors();
                    throw new \Exception(json_encode($errors));
                }
                $events = $eventEntity->toArray();
                $startDateTime = new FrozenTime($data['startDateTime']);

                // TODO <Patric> - Should I change the response body when there are multiple event occurrence (monthly, weekly)
                $response = [
                    'id' => $events['id'],
                    'eventName' => $events['eventName'],
                    'frequency' => $data['frequency'],
                    'startDateTime' => $startDateTime->format('Y-m-d H:i'),
                    'endDateTime' => $startDateTime->addMinutes($data['duration'] ?? 0)->format('Y-m-d H:i'),
                    'duration' => $data['duration'],
                    'invitees' => $data['invitees'],
                ];
                if (isset($data['endDateTime'])) {
                    $response['endDateTime'] = $data['endDateTime'];
                }
                $connection->commit();

                $this->set([
                    'event' => $response,
                ]);
            }
        }
        catch (\Throwable $exception) {
            $message = $exception->getMessage();
            $this->set([
                'event' => ['status' => 'error', 'message' => $message],
            ]);
            $connection->rollback();
        }
        $this->viewBuilder()->setOption('serialize', 'event');
    }

    public function edit($id = null) {
        // TODO: <Patric> - Update the logic of updating events
        /*
        Tasks:
        -Clear the following tables
        -EventAttendee, EventOcurrence, EventFrequency
        -Update the frequency, if the frequency of event changed
        -Update the following [starDateTime, endDateTime, duration, eventName], if changed
        */
    }

    /**
     * @param $data
     * @throws \Exception
     */
    private function prepareData(&$data) {
        $frequencyName = $data['frequency'];
        $data['startDateTime'] = new FrozenTime($data['startDateTime'] ?? "");
        if ($frequencyName == "Once-Off") {
            $data['endDateTime'] = $data['startDateTime'];
        } else {
            $data['endDateTime'] = (isset($data['endDateTime']))
                ? new FrozenTime($data['endDateTime'] ?? "")
                : $data['startDateTime']->{self::FREQUENCY_OPERATION['add'][$frequencyName]}(self::DEFAULT_OCCURRENCE);
        }

        // Validate if the endDateTime is less than the startDateTime
        if ($data['endDateTime'] < $data['startDateTime']) {
            throw new \Exception('endDateTime should be ahead of startDateTime.');
        }

        // Validate if the frequency field is missing
        if (!isset($data['frequency'])) {
            throw new \Exception("frequency field missing.");
        }

        // Validate if existing frequency
        if (!in_array($data['frequency'], self::VALID_FREQUENCY)) {
            throw new \Exception("Invalid frequency.");
        }

        // Validate if the duration is non negative value
        $duration = $data['duration'] ?? 0;
        if ($duration < 0) {
            throw new \Exception("Duration should not be negative number.");
        }
    }

    /**
     * @param $data
     * @param EntityInterface $eventEntity
     * @throws \Exception
     */
    private function saveEventOccurrence($data, EntityInterface $eventEntity) {
        $eventOccurrenceTable = $this->getTableLocator()->get('EventOccurrence');
        $duration = $data['duration'] ?? 0;
        $frequency = $data['frequency'];
        $startDateTime = $data['startDateTime'];
        $endDateTime = $data['endDateTime'];

        $eventOccurenceEntities = [];
        $occurrenceDiff = $startDateTime->{self::FREQUENCY_OPERATION['diff'][$frequency]}($endDateTime);
        for ($occurrence = 0; $occurrence <= $occurrenceDiff; $occurrence++) {
            $currentOccurrence = $startDateTime->{self::FREQUENCY_OPERATION['add'][$frequency]}($occurrence);
            $this->validateConflictingSchedule($currentOccurrence, $currentOccurrence->addMinute($duration));
            $eventOccurenceEntities[] = $eventOccurrenceTable->newEmptyEntity()
                ->set('event', $eventEntity)
                ->set('duration', $duration)
                ->set('startDateTime', $currentOccurrence)
                ->set('endDateTime', $currentOccurrence->addMinute($duration));
        }
        $eventOccurrenceTable->saveManyOrFail($eventOccurenceEntities);
    }

    /**
     * @param $data
     * @param EntityInterface $eventEntity
     * @throws \Exception
     */
    private function saveEventAttendees($data, EntityInterface $eventEntity) {
        $attendeesTable = $this->getTableLocator()->get('Attendees');
        $eventAttendeesTable = $this->getTableLocator()->get('EventAttendees');

        //Get the attendees entities
        $attendeesEntities = [];
        foreach ($data['invitees'] as $invitee) {
            try {
                $attendee = $attendeesTable->get($invitee);
                $attendeesEntities[] = $attendee;
                // Catch the record not found exception, and throw a generic message
            } catch (RecordNotFoundException $exception) {
                throw new \Exception("Invitee $invitee not found!");
            }
        }

        $eventAttendeesEntities = [];
        foreach ($attendeesEntities as $attendeeEntity) {
            $eventAttendeesEntities[] = $eventAttendeesTable->newEmptyEntity()
                ->set('attendee', $attendeeEntity)
                ->set('event', $eventEntity);
        }
        $eventAttendeesTable->saveManyOrFail($eventAttendeesEntities);
    }

    /**
     * @param $data
     * @param EntityInterface $eventEntity
     * @throws \Exception
     */
    private function saveEventFrequency($data, EntityInterface $eventEntity) {
        $frequencyTable = $this->getTableLocator()->get('Frequency');
        $eventFrequencyTable = $this->getTableLocator()->get('EventFrequency');

        $frequencyEntity = $frequencyTable->find('all', [
            'conditions' => ['name' => $data['frequency']]
        ])->first();
        if (!$frequencyEntity) {
            throw new \Exception("Invalid Frequency");
        }

        // Save Event Frequency Table
        $eventFrequencyEntity = $eventFrequencyTable->newEmptyEntity();
        $eventFrequencyEntity->set('event', $eventEntity);
        $eventFrequencyEntity->set('frequency', $frequencyEntity);
        $eventFrequencyTable->saveOrFail($eventFrequencyEntity);
    }

    /**
     * @param FrozenTime $start
     * @param FrozenTime $end
     * @throws \Exception
     */
    private function validateConflictingSchedule(FrozenTime $start, FrozenTime $end) {
        $eventOccurrenceTable = $this->getTableLocator()->get('EventOccurrence');
        $query = $eventOccurrenceTable->find('all');
        $query->contain(['Events']);
        $query->where(['(startDateTime BETWEEN :start AND :end) OR (endDateTime BETWEEN :start AND :end)'])
            ->bind(':start', $start->toDateTimeString(), 'date')
            ->bind(':end', $end->toDateTimeString(), 'date');

        $conflict = $query->first();
        if ($conflict) {
            $eventName = $conflict->event->eventName;
            $timeSlot = "$conflict->startDateTime to $conflict->endDateTime";
            throw new \Exception("conflicting schedule with $eventName at $timeSlot");
        }
    }

}
