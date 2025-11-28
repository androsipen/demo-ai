<?php

namespace app\models;

use yii\db\ActiveRecord;

class ActivityLog extends ActiveRecord
{
    const ACTION_TASK_CREATED = 'task_created';
    const ACTION_TASK_MOVED = 'task_moved';
    const ACTION_TASK_DELETED = 'task_deleted';
    const ACTION_TASK_UPDATED = 'task_updated';

    public static function tableName()
    {
        return 'activity_log';
    }

    public function rules()
    {
        return [
            [['action'], 'required'],
            [['action', 'from_status', 'to_status'], 'string', 'max' => 50],
            [['task_title'], 'string', 'max' => 255],
            [['task_id'], 'integer'],
            [['details'], 'string'],
            [['created_at'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'action' => 'Action',
            'task_id' => 'Task ID',
            'task_title' => 'Task Title',
            'from_status' => 'From Status',
            'to_status' => 'To Status',
            'details' => 'Details',
            'created_at' => 'Created At',
        ];
    }

    /**
     * Get human-readable action description
     */
    public function getDescription()
    {
        switch ($this->action) {
            case self::ACTION_TASK_CREATED:
                return "Task \"{$this->task_title}\" was created in {$this->formatStatus($this->to_status)}";
            case self::ACTION_TASK_MOVED:
                return "Task \"{$this->task_title}\" was moved from {$this->formatStatus($this->from_status)} to {$this->formatStatus($this->to_status)}";
            case self::ACTION_TASK_DELETED:
                return "Task \"{$this->task_title}\" was deleted";
            case self::ACTION_TASK_UPDATED:
                return "Task \"{$this->task_title}\" was updated";
            default:
                return $this->action;
        }
    }

    /**
     * Format status key to human-readable label
     */
    protected function formatStatus($statusKey)
    {
        $labels = [
            'backlog' => 'Backlog',
            'todo' => 'To Do',
            'in_progress' => 'In Progress',
            'done' => 'Done',
        ];
        return $labels[$statusKey] ?? $statusKey;
    }

    /**
     * Get recent activity logs
     */
    public static function getRecent($limit = 20)
    {
        return self::find()
            ->orderBy(['created_at' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(array $fields = [], array $expand = [], $recursive = true)
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'task_id' => $this->task_id,
            'task_title' => $this->task_title,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'description' => $this->getDescription(),
            'created_at' => $this->created_at,
        ];
    }
}
