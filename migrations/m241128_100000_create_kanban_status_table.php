<?php

use yii\db\Migration;

class m241128_100000_create_kanban_status_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%kanban_status}}', [
            'id' => $this->primaryKey(),
            'key' => $this->string(50)->notNull()->unique(),
            'label' => $this->string(100)->notNull(),
            'color' => $this->string(7)->notNull(),
            'sort_order' => $this->integer()->defaultValue(0),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->batchInsert('{{%kanban_status}}',
            ['key', 'label', 'color', 'sort_order'],
            [
                ['backlog', 'Backlog', '#6c757d', 1],
                ['todo', 'To Do', '#0d6efd', 2],
                ['in_progress', 'In Progress', '#ffc107', 3],
                ['done', 'Done', '#198754', 4],
            ]
        );
    }

    public function safeDown()
    {
        $this->dropTable('{{%kanban_status}}');
    }
}
