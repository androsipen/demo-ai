<?php

use yii\db\Migration;

class m241128_100002_create_activity_log_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('activity_log', [
            'id' => $this->primaryKey(),
            'action' => $this->string(50)->notNull(),
            'task_id' => $this->integer(),
            'task_title' => $this->string(255),
            'from_status' => $this->string(50),
            'to_status' => $this->string(50),
            'details' => $this->text(),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->createIndex('idx-activity_log-action', 'activity_log', 'action');
        $this->createIndex('idx-activity_log-task_id', 'activity_log', 'task_id');
        $this->createIndex('idx-activity_log-created_at', 'activity_log', 'created_at');
    }

    public function safeDown()
    {
        $this->dropTable('activity_log');
    }
}
