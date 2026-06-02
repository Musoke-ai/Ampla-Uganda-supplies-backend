<?php

namespace App\Controllers;

use App\Controllers\Traits\SecuresInput;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\NotificationModel;

/**
 * Notifications API Controller
 *
 * This controller provides a RESTful API for managing system-wide notifications.
 * It handles creating, reading, updating, and deleting notifications.
 */
class Notifications extends ResourceController
{
    use ResponseTrait;
    use SecuresInput;

    protected $model;

    public function __construct()
    {
        // Instantiate the NotificationModel
        $this->model = new NotificationModel();
    }

    /**
     * Get all notifications.
     * GET /notifications
     *
     * @return \CodeIgniter\HTTP\Response
     */
    public function index()
    {
        // Fetch all records from the model
        $data = $this->model->findAll();
        return $this->respond($data);
    }

    /**
     * Get a single notification by its ID.
     * GET /notifications/(:num)
     *
     * @param int|null $id The ID of the notification.
     * @return \CodeIgniter\HTTP\Response
     */
    public function show($id = null)
    {
        // Find the notification by its primary key
        $data = $this->model->find($id);
        if ($data) {
            return $this->respond($data);
        }

        // Return a not found error if the notification doesn't exist
        return $this->failNotFound('No notification found with id ' . $id);
    }

    /**
     * Create a new notification.
     * POST /notifications
     *
     * @return \CodeIgniter\HTTP\Response
     */
    public function create()
    {
        // Get the JSON data from the request body
        $data = $this->sanitizeNotificationPayload($this->request->getJSON(true) ?? []);

        // Attempt to insert the data using the model's validation
        if ($this->model->insert($data) === false) {
            // If validation fails, return the validation errors
            return $this->fail($this->model->errors());
        }

        // On successful creation, return a success response
        $response = [
            'status'   => 201,
            'error'    => null,
            'messages' => [
                'success' => 'Notification created successfully'
            ],
            'id' => $this->model->getInsertID()
        ];
        return $this->respondCreated($response);
    }

    /**
     * Update an existing notification by its ID.
     * PUT /notifications/(:num)
     *
     * @param int|null $id The ID of the notification.
     * @return \CodeIgniter\HTTP\Response
     */
    public function update($id = null)
    {
        // Get the JSON data from the request body
        $data = $this->sanitizeNotificationPayload($this->request->getJSON(true) ?? [], false);
        $id = $this->secureInt($id ?? ($data['id'] ?? null));
        unset($data['id']);

        if ($id === null) {
            return $this->failValidationErrors('A valid notification id is required.');
        }

        $existing = $this->model->find($id);
        if (! $existing) {
            return $this->failNotFound('No notification found with id ' . $id);
        }

        $data = array_merge($existing, $data);

        // Attempt to update the data using the model's validation
        if ($this->model->update($id, $data) === false) {
            // If validation fails, return the validation errors
            return $this->fail($this->model->errors());
        }
        
        // On successful update, return a success response
        $response = [
            'status'   => 200,
            'error'    => null,
            'messages' => [
                'success' => 'Notification updated successfully'
            ]
        ];
        return $this->respond($response);
    }

    /**
     * Delete a notification by its ID.
     * DELETE /notifications/(:num)
     *
     * @param int|null $id The ID of the notification.
     * @return \CodeIgniter\HTTP\Response
     */
    public function delete($id = null)
    {
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        $id = $this->secureInt($id ?? ($payload['id'] ?? null));

        if ($id === null) {
            return $this->failValidationErrors('A valid notification id is required.');
        }

        // Find the notification to ensure it exists before deletion
        $data = $this->model->find($id);
        if ($data) {
            // Delete the record
            $this->model->delete($id);
            $response = [
                'status'   => 200,
                'error'    => null,
                'messages' => [
                    'success' => 'Notification successfully deleted'
                ]
            ];
            return $this->respondDeleted($response);
        }

        // Return a not found error if the notification doesn't exist
        return $this->failNotFound('No notification found with id ' . $id);
    }

    private function sanitizeNotificationPayload(array $payload, bool $requireContent = true): array
    {
        $data = [];

        if (array_key_exists('id', $payload)) {
            $data['id'] = $this->secureInt($payload['id']);
        }

        if ($requireContent || array_key_exists('title', $payload)) {
            $data['title'] = $this->secureText($payload['title'] ?? '', 255);
        }

        if ($requireContent || array_key_exists('message', $payload)) {
            $data['message'] = $this->secureText($payload['message'] ?? '', 1000);
        }

        if ($requireContent || array_key_exists('notification_type', $payload)) {
            $data['notification_type'] = $this->secureText($payload['notification_type'] ?? 'system', 50);
        }

        if ($requireContent || array_key_exists('severity_level', $payload)) {
            $data['severity_level'] = $this->secureAllowed(
                $payload['severity_level'] ?? 'info',
                ['info', 'success', 'warning', 'error'],
                'info'
            );
        }

        if (array_key_exists('link_url', $payload)) {
            $data['link_url'] = $this->secureText($payload['link_url'], 2048, true);
        }

        if (array_key_exists('is_read', $payload)) {
            $data['is_read'] = $this->secureInt($payload['is_read'], 0) === 1 ? 1 : 0;
        }

        if (array_key_exists('read_at', $payload)) {
            $data['read_at'] = $this->secureText($payload['read_at'], 30, true);
        }

        return $data;
    }
}
