<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

/**
 * KanbanTask model
 *
 * @property int $id
 * @property string $title
 * @property string $description
 * @property int $status_id
 * @property int $sort_order
 * @property string $created_at
 * @property string $updated_at
 *
 * @property KanbanStatus $status
 */
class KanbanTask extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%kanban_task}}';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'value' => new Expression('CURRENT_TIMESTAMP'),
            ],
        ];
    }

    public function rules()
    {
        return [
            [['title', 'status_id'], 'required'],
            [['title'], 'string', 'max' => 255],
            [['description'], 'string'],
            [['status_id', 'sort_order'], 'integer'],
            [['status_id'], 'exist', 'skipOnError' => true, 'targetClass' => KanbanStatus::class, 'targetAttribute' => ['status_id' => 'id']],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Title',
            'description' => 'Description',
            'status_id' => 'Status',
            'sort_order' => 'Sort Order',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
        ];
    }

    public function getStatus()
    {
        return $this->hasOne(KanbanStatus::class, ['id' => 'status_id']);
    }

    public function moveToStatus($statusId)
    {
        $this->status_id = $statusId;
        $this->updated_at = new Expression('CURRENT_TIMESTAMP');
        return $this->save(false, ['status_id', 'updated_at']);
    }
}
