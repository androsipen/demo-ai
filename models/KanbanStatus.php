<?php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * KanbanStatus model
 *
 * @property int $id
 * @property string $key
 * @property string $label
 * @property string $color
 * @property int $sort_order
 * @property string $created_at
 *
 * @property KanbanTask[] $tasks
 */
class KanbanStatus extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%kanban_status}}';
    }

    public function rules()
    {
        return [
            [['key', 'label', 'color'], 'required'],
            [['key'], 'string', 'max' => 50],
            [['label'], 'string', 'max' => 100],
            [['color'], 'string', 'max' => 7],
            [['sort_order'], 'integer'],
            [['key'], 'unique'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'key' => 'Key',
            'label' => 'Label',
            'color' => 'Color',
            'sort_order' => 'Sort Order',
            'created_at' => 'Created At',
        ];
    }

    public function getTasks()
    {
        return $this->hasMany(KanbanTask::class, ['status_id' => 'id'])
            ->orderBy(['sort_order' => SORT_ASC]);
    }

    public static function getAllOrdered()
    {
        return self::find()->orderBy(['sort_order' => SORT_ASC])->all();
    }
}
