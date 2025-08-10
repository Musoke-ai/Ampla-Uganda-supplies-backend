<?php

namespace App\Controllers;

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
        $data = $this->request->getJSON(true);

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
        $data = $this->request->getJSON(true);

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
}