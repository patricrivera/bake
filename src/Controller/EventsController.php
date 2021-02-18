<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\Event;
use App\Model\Entity\EventOccurrence;
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

    // TODO <Patric> - Refactor the view method, this should use the new table event_occurrence for finding events
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
                $query->where(['EventOccurrence.endDateTime >=' => new \DateTime($data['end'])]);
            }

            // Set the logic for filtering attendees
            if (isset($data['invitees'])) {
                $invitees = explode(",", $data['invitees']);
                $query->leftJoinWith('EventAttendees', function (Query $q) use ($invitees) {
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
        $eventEntity = $this->Events->newEmptyEntity();
        try {
            if ($this->request->is('post')) {
                $data = $this->request->getData();
                $eventEntity = $this->Events->patchEntity($eventEntity, $data);
                // Before Saving, check first if the Attendees and Frequency is present
                $this->saveEventFrequency($data, $eventEntity);

                if (!$this->Events->save($eventEntity)) {
                    $errors = $eventEntity->getErrors();
                    throw new \Exception(json_encode($errors));
                }
                $events = $eventEntity->toArray();
                array_walk($events, function (&$detail) {
                    if ($detail instanceof FrozenTime) {
                        $detail = $detail->format('Y-m-d H:i');
                    }
                });
                $startDateTime = new FrozenTime($data['startDateTime']);
                $response = [
                    'id' => $events['id'],
                    'eventName' => $events['eventName'],
                    'frequency' => $data['frequency'],
                    'startDateTime' => $startDateTime->toDateTimeString(),
                    'endDateTime' => $startDateTime->addMinutes($data['duration'] ?? 0)->toDateTimeString(),
                    'duration' => $data['duration'],
                    'invitees' => $data['invitees'],
                ];
                if (isset($data['endDateTime'])) {
                    $response['endDateTime'] = $data['endDateTime'];
                }

                $this->set([
                    'event' => $response,
                ]);
            }
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            $this->set([
                'event' => ['status' => 'error', 'message' => $message],
            ]);
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

    private function saveEventFrequency($data, EntityInterface $eventEntity) {
        // Get the tables
        $attendeesTable = $this->getTableLocator()->get('Attendees');
        $frequencyTable = $this->getTableLocator()->get('Frequency');
        $eventFrequencyTable = $this->getTableLocator()->get('EventFrequency');
        $eventAttendeesTable = $this->getTableLocator()->get('EventAttendees');
        $eventOccurrenceTable = $this->getTableLocator()->get('EventOccurrence');
        if (!isset($data['frequency'])) {
            throw new \Exception("frequency field missing");
        }

        $frequencyEntity = $frequencyTable->find('all', [
            'conditions' => ['name' => $data['frequency']]
        ])->first();
        if (!$frequencyEntity) {
            throw new \Exception("Invalid Frequency");
        }
        $duration = $data['duration'] ?? 0;
        if ($duration < 0) {
            throw new \Exception("Duration should not be negative number");
        }

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
        $startDateTime = new FrozenTime($data['startDateTime'] ?? "");
        $endDateTime = new FrozenTime($data['endDateTime'] ?? $data['startDateTime'] ?? "");
        $eventOccurenceEntities = [];
        switch ($data['frequency']) {
            case "Once-Off":
                $this->validateConflictingSchedule($startDateTime, $startDateTime->addMinute($duration));
                $eventOccurenceEntities[] = $eventOccurrenceTable->newEmptyEntity()
                    ->set('event', $eventEntity)
                    ->set('duration', $duration)
                    ->set('startDateTime', $startDateTime)
                    ->set('endDateTime', $startDateTime->addMinute($duration));
                break;
            case "Weekly":
                $currentWeek = $startDateTime;
                for ($occurence = 0;
                     $occurence <= $startDateTime->diffInWeeks($endDateTime);
                     $occurence++) {
                    $this->validateConflictingSchedule($currentWeek, $currentWeek->addMinute($duration));
                    $eventOccurenceEntities[] = $eventOccurrenceTable->newEmptyEntity()
                        ->set('event', $eventEntity)
                        ->set('duration', $duration)
                        ->set('startDateTime', $currentWeek)
                        ->set('endDateTime', $currentWeek->addMinute($duration));
                    $currentWeek = $currentWeek->addWeek(1);
                }
                break;
            case "Monthly":
                $currentMonth = $startDateTime;
                for ($occurence = 0;
                     $occurence <= $startDateTime->diffInMonths($endDateTime);
                     $occurence++) {
                    $this->validateConflictingSchedule($currentMonth, $currentMonth->addMinute($duration));
                    $eventOccurenceEntities[] = $eventOccurrenceTable->newEmptyEntity()
                        ->set('event', $eventEntity)
                        ->set('duration', $duration)
                        ->set('startDateTime', $currentMonth)
                        ->set('endDateTime', $currentMonth->addMinute($duration));
                    $currentMonth = $currentMonth->addMonth(1);
                }
                break;
        }
        $eventOccurrenceTable->saveMany($eventOccurenceEntities);

        // Save Event Frequency Table
        $eventFrequencyEntity = $eventFrequencyTable->newEmptyEntity();
        $eventFrequencyEntity->set('event', $eventEntity);
        $eventFrequencyEntity->set('frequency', $frequencyEntity);
        $eventFrequencyTable->save($eventFrequencyEntity);

        // Save Event Attendees Table
        $eventAttendeesEntities = [];
        foreach ($attendeesEntities as $attendeeEntity) {
            $eventAttendeesEntities[] = $eventAttendeesTable->newEmptyEntity()
                ->set('attendee', $attendeeEntity)
                ->set('event', $eventEntity);
        }
        $eventAttendeesTable->saveMany($eventAttendeesEntities);
    }

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
