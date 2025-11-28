<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use app\models\KanbanStatus;
use app\models\KanbanTask;

class KanbanController extends Controller
{
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'move-task' => ['POST'],
                    'create-task' => ['POST'],
                    'delete-task' => ['POST'],
                ],
            ],
        ];
    }

    public function beforeAction($action)
    {
        // Enable CSRF validation for AJAX requests
        if (in_array($action->id, ['move-task', 'create-task', 'delete-task'])) {
            $this->enableCsrfValidation = true;
        }
        return parent::beforeAction($action);
    }

    public function actionIndex()
    {
        $statuses = KanbanStatus::getAllOrdered();

        return $this->render('index', [
            'statuses' => $statuses,
        ]);
    }

    public function actionMoveTask()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $taskId = Yii::$app->request->post('taskId');
        $newStatusKey = Yii::$app->request->post('newStatus');

        $task = KanbanTask::findOne($taskId);
        if (!$task) {
            return [
                'success' => false,
                'error' => 'Task not found',
            ];
        }

        $newStatus = KanbanStatus::findOne(['key' => $newStatusKey]);
        if (!$newStatus) {
            return [
                'success' => false,
                'error' => 'Status not found',
            ];
        }

        if ($task->moveToStatus($newStatus->id)) {
            return [
                'success' => true,
                'taskId' => $taskId,
                'newStatus' => $newStatusKey,
            ];
        }

        return [
            'success' => false,
            'error' => 'Failed to update task',
        ];
    }

    public function actionCreateTask()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $title = Yii::$app->request->post('title');
        $description = Yii::$app->request->post('description', '');
        $statusKey = Yii::$app->request->post('status', 'backlog');

        $status = KanbanStatus::findOne(['key' => $statusKey]);
        if (!$status) {
            return [
                'success' => false,
                'error' => 'Status not found',
            ];
        }

        $task = new KanbanTask();
        $task->title = $title;
        $task->description = $description;
        $task->status_id = $status->id;
        $task->sort_order = KanbanTask::find()
            ->where(['status_id' => $status->id])
            ->max('sort_order') + 1;

        if ($task->save()) {
            return [
                'success' => true,
                'task' => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => $statusKey,
                ],
            ];
        }

        return [
            'success' => false,
            'error' => 'Failed to create task',
            'errors' => $task->errors,
        ];
    }

    public function actionDeleteTask()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $taskId = Yii::$app->request->post('taskId');

        $task = KanbanTask::findOne($taskId);
        if (!$task) {
            return [
                'success' => false,
                'error' => 'Task not found',
            ];
        }

        if ($task->delete()) {
            return [
                'success' => true,
                'taskId' => $taskId,
            ];
        }

        return [
            'success' => false,
            'error' => 'Failed to delete task',
        ];
    }
}
