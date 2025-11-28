<?php

use yii\db\Migration;

class m241128_100001_create_kanban_task_table extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%kanban_task}}', [
            'id' => $this->primaryKey(),
            'title' => $this->string(255)->notNull(),
            'description' => $this->text(),
            'status_id' => $this->integer()->notNull(),
            'sort_order' => $this->integer()->defaultValue(0),
            'created_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
            'updated_at' => $this->timestamp()->defaultExpression('CURRENT_TIMESTAMP'),
        ]);

        $this->addForeignKey(
            'fk-kanban_task-status_id',
            '{{%kanban_task}}',
            'status_id',
            '{{%kanban_status}}',
            'id',
            'RESTRICT',
            'CASCADE'
        );

        $this->createIndex('idx-kanban_task-status_id', '{{%kanban_task}}', 'status_id');

        // Insert sample tasks
        $this->batchInsert('{{%kanban_task}}',
            ['title', 'description', 'status_id', 'sort_order'],
            [
                ['Research competitors', 'Analyze top 5 competitors', 1, 1],
                ['Define user personas', 'Create 3 main user personas', 1, 2],
                ['Write project brief', 'Document project requirements', 1, 3],
                ['Design homepage mockup', 'Create wireframes and mockups', 2, 1],
                ['Set up database schema', 'Design and implement DB structure', 2, 2],
                ['Implement user auth', 'Login, registration, password reset', 3, 1],
                ['Create API endpoints', 'RESTful API for mobile app', 3, 2],
                ['Project setup', 'Initialize repository and CI/CD', 4, 1],
                ['Team onboarding', 'Onboard new team members', 4, 2],
            ]
        );
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk-kanban_task-status_id', '{{%kanban_task}}');
        $this->dropTable('{{%kanban_task}}');
    }
}
