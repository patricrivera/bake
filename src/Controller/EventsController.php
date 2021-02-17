<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\Event;
use Cake\Datasource\EntityInterface;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Datasource\ResultSetInterface;
use Cake\I18n\FrozenTime;
use Cake\ORM\Query;
use mysql_xdevapi\Exception;
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

    public function view() {
        try {
            $data = $this->request->getQuery();
            // Set the logic for filtering dates
            $query = $this->Events->find('all');

            if(isset($data['start'])) {
                $query->where(['startDateTime >=' => new \DateTime($data['start'] )]);
            }

            if(isset($data['end'])) {
                $query->where(['endDateTime <=' => new \DateTime($data['end'] )]);
            }

            // Set the logic for filtering attendees
            if(isset($data['invitees'])) {
                $invitees = explode(",", $data['invitees']);
                $query->contain(['EventAttendees']);
                $query->matching('EventAttendees', function (Query $q) use ($invitees) {
                    return $q->where(['EventAttendees.attendee_id IN' => $invitees]);
                });
                $query->group(['Events.id']);
            }
            $events = $query->all();

            $response = [
                'items' => [],
            ];
            /* @var $event Event */
            foreach ($events as $event) {
                $inviteeIds = [];
                $attendees = $event->event_attendees;
                if ($attendees) {
                    foreach ($attendees as $attendee) {
                        $inviteeIds[] = $attendee->attendee_id;
                    }
                }

                $response['items'][] = [
                  'event_id' => $event->id,
                  'eventName' => $event->eventName,
                  'startDateTime' => $event->startDateTime,
                  'endDateTime' => $event->endDateTime,
                  'invitees' => $inviteeIds,
                ];
            }

            return $this->response->withType('application/json')
                ->withStringBody(json_encode($response));

        } catch (\Throwable $exception) {
            die($exception->getMessage());
        }
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
                $frequencyEntity = $this->saveEventFrequency($data, $eventEntity);

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

                $this->set([
                    'event' => [
                        'id' => $events['id'],
                        'eventName' => $events['eventName'],
                        'frequency' => $frequencyEntity->get('name'),
                        'startDateTime' => $events['startDateTime'],
                        'endDateTime' => $events['endDateTime'],
                        'duration' => $events['duration'],
                        'invitees' => $data['invitees'],
                    ],
                ]);
            }
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            $this->set([
                'event' => ['status' => 'error', 'message' => $message],
            ]);
            /* TODO: <Patric> - Create a standard response for error messages */
        }
        $this->viewBuilder()->setOption('serialize', 'event');
    }

    public function edit($id = null) {
        // TODO: <Patric> - Update the logic of updating events
        /*
        Tasks:
        -Remove the attendee, if not included on the updated event payload
        -Add new attendee, for non existing attendee on the event details
        -Update the frequency, if the frequency of event changed
        -Update the following [starDateTime, endDateTime, duration, eventName], if changed
        */
    }

    private function saveEventFrequency($data, EntityInterface &$eventEntity) {
        // Get the tables
        $attendeesTable = $this->getTableLocator()->get('Attendees');
        $frequencyTable = $this->getTableLocator()->get('Frequency');
        $eventFrequencyTable = $this->getTableLocator()->get('EventFrequency');
        $eventAttendeesTable = $this->getTableLocator()->get('EventAttendees');
        if (!isset($data['frequency'])) {
            throw new \Exception("frequency field missing");
        }

        $frequencyEntity = $frequencyTable->find('all', [
            'conditions' => ['name' => $data['frequency']]
        ])->first();
        if (!$frequencyEntity) {
            throw new \Exception("Invalid Frequency");
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

        switch ($data['frequency']) {
            case "Once-Off":
                /* TODO: <Patric> - Check the frequency, non negative number must be accepted */
                /* If a once-off frequency we must calculate the endtime using the supplied duration (in minutes) */
                /** @var FrozenTime $startDateTime */
                $startDateTime = $eventEntity->get('startDateTime');
                $eventEntity->set('endDateTime', $startDateTime->addMinute($data['duration'] ?? 0));
                break;
            case "Weekly":
                break;
            case "Monthly":
                break;
        }

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

        return $frequencyEntity;
    }

}
