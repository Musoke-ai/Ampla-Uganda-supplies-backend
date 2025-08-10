<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * NotificationModel
 *
 * This model is responsible for interacting with the 'notifications' database table.
 * It provides a structured way to query, insert, update, and delete notification records.
 * This model is designed for the system-wide notification schema, where notifications
 * are not tied to a specific user.
 */
class NotificationModel extends Model
{
    /**
     * The table associated with this model.
     *
     * @var string
     */
    protected $table = 'notifications';

    /**
     * The primary key of the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Whether to use an auto-incrementing primary key.
     *
     * @var bool
     */
    protected $useAutoIncrement = true;

    /**
     * The type of data that is returned by find* methods.
     *
     * @var string
     */
    protected $returnType = 'array'; // Can be 'object' or a custom entity class

    /**
     * Whether to "soft delete" records instead of truly deleting them.
     *
     * @var bool
     */
    protected $useSoftDeletes = false;

    /**
     * An array of field names that are allowed to be set during
     * insert and update methods.
     *
     * @var array
     */
    protected $allowedFields = [
        'title',
        'message',
        'is_read',
        'read_at',
        'notification_type',
        'severity_level',
        'link_url'
    ];

    // --- Timestamps ---

    /**
     * Whether to automatically populate the created_at and updated_at fields.
     *
     * @var bool
     */
    protected $useTimestamps = true;

    /**
     * The name of the database column that contains the creation timestamp.
     *
     * @var string
     */
    protected $createdField = 'created_at';

    /**
     * The name of the database column that contains the update timestamp.
     * Set to null because the table schema does not have an 'updated_at' column.
     *
     * @var string
     */
    protected $updatedField = ''; // No updated_at field in the schema

    // --- Validation ---

    /**
     * Validation rules for the data.
     *
     * @var array
     */
    protected $validationRules = [
        'title'             => 'required|max_length[255]',
        'message'           => 'required',
        'notification_type' => 'required|max_length[50]',
        'severity_level'    => 'required|max_length[20]',
        'link_url'          => 'permit_empty|valid_url_strict|max_length[2048]',
        'is_read'           => 'in_list[0,1]',
        'read_at'           => 'permit_empty|valid_date',
    ];

    /**
     * Custom validation messages.
     *
     * @var array
     */
    protected $validationMessages = [
        'title' => [
            'required'   => 'A title is required for the notification.',
            'max_length' => 'The title cannot exceed 255 characters.',
        ],
        'link_url' => [
            'valid_url_strict' => 'Please provide a valid URL.',
        ],
    ];

    /**
     * Whether to skip validation.
     *
     * @var bool
     */
    protected $skipValidation = false;
}