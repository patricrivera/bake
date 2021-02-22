<?php
declare(strict_types=1);

namespace App\Controller;

use App\Model\Entity\Event;
use Cake\Datasource\ConnectionManager;
use Cake\Datasource\EntityInterface;
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

    const VALID_FREQUENCY = ['Once-Off', "Weekly", "Monthly"];


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

    /**
     * @return \Cake\Http\Response
     */
    public function view() {
        try {
            $data = $this->request->getQuery();
            /** @var Query $query */
            $eventOccurrenceTable = $this->getTableLocator()->get('EventOccurrence');
            $startDate = (isset($data['start'])) ? new \DateTime($data['start']) : false;
            $endDate = (isset($data['end'])) ? new \DateTime($data['end']) : false;
            $invitees = (isset($data['invitees'])) ? explode(",", $data['invitees']) : [];
            $events = $eventOccurrenceTable->findAllBy($startDate, $endDate, $invitees);
            $response = [
                'items' => $events,
            ];

        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            $response = ['status' => 'error', 'message' => $message];
        }
        return $this->response->withType('application/json')
            ->withStringBody(json_encode($response));
    }

    /**
     * Action for adding new events instances
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
                $response = $this->saveEvents($data, $eventEntity);

                $connection->commit();

                $this->set([
                    'event' => $response,
                ]);
            }
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            $this->set([
                'event' => ['status' => 'error', 'message' => $message],
            ]);
            $connection->rollback();
        }
        $this->viewBuilder()->setOption('serialize', 'event');
    }

    /**
     * Action for updating event instance details
     */
    public function edit() {
        $connection = ConnectionManager::get('default');
        $connection->begin();
        try {
            if ($this->request->is(['patch', 'put'])) {
                $data = $this->request->getData();
                $this->prepareData($data);
                $eventId = $data['id'];
                $eventEntity = $this->Events->get($eventId, [
                    'contain' => [],
                ]);
                $this->purgeEventsReferrence($eventId);
                $eventEntity = $this->Events->patchEntity($eventEntity, $data);
                $response = $this->saveEvents($data, $eventEntity);

                $connection->commit();

                $this->set([
                    'event' => $response,
                ]);

            }
        } catch (\Throwable $exception) {
            $message = $exception->getMessage();
            $this->set([
                'event' => ['status' => 'error', 'message' => $message],
            ]);
            $connection->rollback();
        }
        $this->viewBuilder()->setOption('serialize', 'event');

    }

    /**
     * @param $data
     * @throws \Exception
     */
    private function prepareData(&$data) {
        $frequencyName = $data['frequency'];
        // Validate if the frequency field is missing
        if (!isset($data['frequency'])) {
            throw new \Exception("frequency field missing.");
        }

        // Validate if existing frequency
        if (!in_array($data['frequency'], self::VALID_FREQUENCY)) {
            throw new \Exception("Invalid frequency.");
        }

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

        // Validate if the duration is non negative value
        $duration = $data['duration'] ?? 0;
        if ($duration < 0) {
            throw new \Exception("Duration should not be negative number.");
        }

        if ($this->request->is(['patch', 'put'])) {
            if (!isset($data['id']) || $data['id'] == "") {
                throw new \Exception("id field is missing.");
            }
            $data['modified'] = new \DateTime();
        }
    }

    /**
     * @param $data
     * @param $eventEntity
     * @throws \Exception
     */
    private function saveEvents($data, $eventEntity) {
        $this->saveEventAttendees($data, $eventEntity);
        $this->saveEventFrequency($data, $eventEntity);
        $this->saveEventOccurrence($data, $eventEntity);

        if (!$this->Events->save($eventEntity)) {
            $errors = $eventEntity->getErrors();
            throw new \Exception(json_encode($errors));
        }
        $events = $eventEntity->toArray();
        $startDateTime = new FrozenTime($data['startDateTime']);
        $endDateTime = new FrozenTime($data['endDateTime']);

        // TODO <Patric> - Should I change the response body when there are multiple event occurrence (monthly, weekly)
        $response = [
            'id' => $events['id'],
            'eventName' => $events['eventName'],
            'frequency' => $data['frequency'],
            'startDateTime' => $startDateTime->format('Y-m-d H:i'),
            'endDateTime' => $endDateTime->addMinutes($data['duration'] ?? 0)->format('Y-m-d H:i'),
            'duration' => $data['duration'],
            'invitees' => $data['invitees'],
        ];

        return $response;
    }

    /**
     * @param $data
     * @param EntityInterface $eventEntity
     * @throws \Exception
     */
    private function saveEventOccurrence($data, EntityInterface $eventEntity) {
        $eventOccurrenceTable = $this->getTableLocator()->get('EventOccurrence');
        $eventOccurrenceTable->saveEvent($data, $eventEntity);
    }

    /**
     * @param $data
     * @param EntityInterface $eventEntity
     * @throws \Exception
     */
    private function saveEventAttendees($data, EntityInterface $eventEntity) {
        $eventAttendeesTable = $this->getTableLocator()->get('EventAttendees');
        $eventAttendeesTable->saveEvent($data, $eventEntity);
    }

    /**
     * @param $data
     * @param EntityInterface $eventEntity
     * @throws \Exception
     */
    private function saveEventFrequency($data, EntityInterface $eventEntity) {
        $eventFrequencyTable = $this->getTableLocator()->get('EventFrequency');
        $eventFrequencyTable->saveEvent($data, $eventEntity);
    }

    /**
     * @param $eventId
     */
    private function purgeEventsReferrence($eventId) {
        $eventAttendeesTable = $this->getTableLocator()->get('EventAttendees');
        $eventFrequencyTable = $this->getTableLocator()->get('EventFrequency');
        $eventOccurrence = $this->getTableLocator()->get('EventOccurrence');
        $eventAttendeesTable->purgeByEventId($eventId);
        $eventFrequencyTable->purgeByEventId($eventId);
        $eventOccurrence->purgeByEventId($eventId);
    }

}
