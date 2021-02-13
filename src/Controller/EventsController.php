<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\Event;
use Cake\Datasource\Exception\RecordNotFoundException;
use Cake\Datasource\ResultSetInterface;
use Cake\I18n\FrozenTime;
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

    /**
     * View method
     *
     * @param string|null $id Event id.
     * @return \Cake\Http\Response|null|void Renders view
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function view($id = null) {
        $events = $this->Events->get($id);
        $this->set('events', $events);
        $this->viewBuilder()->setOption('serialize', ['events']);
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
        }
        $this->viewBuilder()->setOption('serialize', 'event');
    }

    /**
     * Edit method
     *
     * @param string|null $id Event id.
     * @return \Cake\Http\Response|null|void Redirects on successful edit, renders view otherwise.
     * @throws \Cake\Datasource\Exception\RecordNotFoundException When record not found.
     */
    public function edit($id = null) {
        $event = $this->Events->get($id, [
            'contain' => [],
        ]);
        if ($this->request->is(['patch', 'post', 'put'])) {
            $event = $this->Events->patchEntity($event, $this->request->getData());
            if ($this->Events->save($event)) {
                $this->Flash->success(__('The event has been saved.'));

                return $this->redirect(['action' => 'index']);
            }
            $this->Flash->error(__('The event could not be saved. Please, try again.'));
        }
        $this->set(compact('event'));
    }

    private function saveEventFrequency($data, $eventEntity) {
        // Get the tables
        $attendeesTable = $this->getTableLocator()->get('Attendees');
        $frequencyTable = $this->getTableLocator()->get('Frequency');
        $eventFrequencyTable = $this->getTableLocator()->get('EventFrequency');
        $eventAttendeesTable = $this->getTableLocator()->get('EventAttendees');
        if(!isset($data['frequency'])) {
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
